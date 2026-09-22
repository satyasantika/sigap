<?php

namespace App\Services;

use App\Enums\JenisBukti;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use App\Models\Komentar;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Penyimpanan, pemversian, dan validasi bukti.
 *
 * Nama berkas di disk diacak; nama asli disimpan di kolom terpisah. Kalau nama
 * pengguna dipakai apa adanya sebagai jalur, isi folder bukti bisa ditebak
 * dari luar — dan isinya nama dosen serta nomor serdik.
 */
class PengelolaBukti
{
    public const DISK = 'bukti';

    public function __construct(private readonly PemeriksaTautan $pemeriksa = new PemeriksaTautan) {}

    /**
     * Menyimpan bukti berupa berkas.
     *
     * @param  array<string, mixed>  $atribut
     */
    public function simpanBerkas(UploadedFile $berkas, array $atribut, User $oleh): Bukti
    {
        $sha = hash_file('sha256', $berkas->getRealPath());

        $nama = Str::uuid()->toString().'.'.$berkas->getClientOriginalExtension();
        $path = $berkas->storeAs('', $nama, self::DISK);

        return Bukti::create($atribut + [
            'jenis' => JenisBukti::Berkas,
            'path' => $path,
            'nama_asli' => $berkas->getClientOriginalName(),
            'mime' => $berkas->getClientMimeType(),
            'ukuran' => $berkas->getSize(),
            'sha256' => $sha,
            'kunci_normal' => $sha,
            'diunggah_oleh' => $oleh->getKey(),
        ]);
    }

    /**
     * Menyimpan bukti berupa tautan, lalu memeriksanya SEKETIKA.
     *
     * Diperiksa di sini, bukan menunggu penjadwalan harian, supaya pengunggah
     * tahu sebelum meninggalkan halaman. Mengetahui besok berarti tautan
     * rusak itu sudah menempel di beberapa elemen lebih dulu.
     *
     * @param  array<string, mixed>  $atribut
     */
    public function simpanTautan(string $url, array $atribut, User $oleh): Bukti
    {
        $urai = NormalisasiTautan::urai($url);

        if ($urai === null) {
            throw new RuntimeException("URL tidak sah: {$url}");
        }

        $bukti = Bukti::create($atribut + [
            'jenis' => JenisBukti::Tautan,
            'url' => $url,
            'penyedia' => $urai['penyedia'],
            'url_kanonik' => $urai['url_kanonik'],
            'tautan_id' => $urai['tautan_id'],
            'tautan_bentuk' => $urai['tautan_bentuk'],
            'kunci_normal' => $urai['url_kanonik'],
            'diunggah_oleh' => $oleh->getKey(),
        ]);

        $this->pemeriksa->periksa($bukti);

        return $bukti->refresh();
    }

    /**
     * Bukti lain di periode yang sama dengan isi identik.
     *
     * Dikembalikan agar pemanggil bisa menawarkan menautkan yang lama alih-alih
     * mengunggah ulang — bukan ditolak, karena kadang memang perlu dua salinan.
     */
    public function kembaranDi(string $periodeId, ?string $sha256 = null, ?string $urlKanonik = null): ?Bukti
    {
        $kunci = $sha256 ?? $urlKanonik;

        if ($kunci === null) {
            return null;
        }

        return Bukti::where('periode_id', $periodeId)
            ->where('kunci_normal', $kunci)
            ->first();
    }

    /**
     * Mengunggah pengganti: baris BARU dengan bukti_induk_id, yang lama tetap ada.
     *
     * Bukti lama tidak dihapus karena tagihan yang sudah disetujui mungkin
     * menautkannya, dan menghapusnya akan membuat persetujuan itu menunjuk
     * ke ruang kosong.
     */
    public function gantiVersi(Bukti $lama, UploadedFile $berkas, User $oleh): Bukti
    {
        return DB::transaction(function () use ($lama, $berkas, $oleh) {
            $baru = $this->simpanBerkas($berkas, [
                'prodi_id' => $lama->prodi_id,
                'periode_id' => $lama->periode_id,
                'judul' => $lama->judul,
                'deskripsi' => $lama->deskripsi,
                'keterangan' => $lama->keterangan,
                'tanggal_kejadian' => $lama->tanggal_kejadian,
                'sumber' => $lama->sumber,
                'versi' => $lama->versi + 1,
                'bukti_induk_id' => $lama->getKey(),
            ], $oleh);

            // Elemen yang ditopang ikut pindah beserta keterangannya.
            foreach ($lama->elemen as $e) {
                $baru->elemen()->attach($e->id, ['keterangan' => $e->pivot->keterangan]);
            }

            return $baru;
        });
    }

    /**
     * Menyunting bukti MENGEMBALIKAN status validasinya.
     *
     * Kalau tidak, seseorang bisa mendapat cap `sah` untuk satu dokumen lalu
     * menukar isinya diam-diam. Validasi berlaku untuk apa yang dilihat
     * validator, bukan untuk judulnya.
     *
     * @param  array<string, mixed>  $atribut
     */
    public function sunting(Bukti $bukti, array $atribut, User $oleh): Bukti
    {
        $bukti->update($atribut);

        if ($bukti->validasi_status !== ValidasiBukti::BelumDivalidasi) {
            $this->kembalikanKeBelumDivalidasi($bukti, $oleh);
        }

        return $bukti->refresh();
    }

    /** Menilai keabsahan — sumbu kedua, keputusan manusia. */
    public function validasi(Bukti $bukti, ValidasiBukti $status, User $oleh, ?string $catatan = null): Bukti
    {
        if ($status->butuhCatatan() && blank($catatan)) {
            throw new RuntimeException(
                'Menandai bukti sebagai '.$status->label().' wajib disertai catatan. '.
                'Tanpa alasan, pengunggah akan mengulang kesalahan yang sama.'
            );
        }

        DB::transaction(function () use ($bukti, $status, $oleh, $catatan) {
            $bukti->forceFill([
                'validasi_status' => $status,
                'divalidasi_oleh' => $oleh->getKey(),
                'divalidasi_pada' => now(),
                'catatan_validasi' => $catatan,
            ])->save();

            // Komentar otomatis: keputusan validasi ikut terbaca di lini masa
            // bukti, bukan hanya sebagai satu kolom yang mudah terlewat.
            Komentar::create([
                'commentable_type' => $bukti->getMorphClass(),
                'commentable_id' => $bukti->getKey(),
                'user_id' => $oleh->getKey(),
                'isi' => "Status keabsahan diubah menjadi \"{$status->label()}\"."
                    .(filled($catatan) ? " Catatan: {$catatan}" : ''),
            ]);
        });

        return $bukti->refresh();
    }

    private function kembalikanKeBelumDivalidasi(Bukti $bukti, User $oleh): void
    {
        $sebelum = $bukti->validasi_status;

        $bukti->forceFill([
            'validasi_status' => ValidasiBukti::BelumDivalidasi,
            'divalidasi_oleh' => null,
            'divalidasi_pada' => null,
            'catatan_validasi' => null,
        ])->save();

        Komentar::create([
            'commentable_type' => $bukti->getMorphClass(),
            'commentable_id' => $bukti->getKey(),
            'user_id' => $oleh->getKey(),
            'isi' => "Bukti disunting, status keabsahan dikembalikan dari \"{$sebelum->label()}\" ke \"Belum divalidasi\".",
        ]);
    }

    public function hapusBerkas(Bukti $bukti): void
    {
        if ($bukti->path !== null && Storage::disk(self::DISK)->exists($bukti->path)) {
            Storage::disk(self::DISK)->delete($bukti->path);
        }
    }
}
