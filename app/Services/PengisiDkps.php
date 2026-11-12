<?php

namespace App\Services;

use App\Enums\SumberData;
use App\Exceptions\IsianDkpsTidakSah;
use App\Exceptions\TahunDiLuarJendela;
use App\Models\Bukti;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\User;
use App\Support\Izin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Satu-satunya jalan tulis baris DKPS dari formulir.
 *
 * Urutannya disengaja: isian diperiksa skema lebih dulu, baru wewenang.
 * Pengisi yang salah ketik perlu tahu kolom mana yang salah; menolak
 * wewenang lebih dulu hanya menunda kabar itu. Penulisan baru terjadi
 * setelah semuanya lolos, supaya tidak ada baris setengah jadi.
 */
class PengisiDkps
{
    public function __construct(
        private readonly VerifikatorDkps $verifikator,
        private readonly PengelolaBukti $pengelolaBukti,
    ) {}

    /**
     * Menyimpan satu baris lembar — baru, atau menyunting `$baris`.
     *
     * `$data` dianggap BARIS UTUH: kolom yang tidak dikirim menjadi kosong,
     * bukan mewarisi nilai lama. Pemanggil wajib mengirim seluruh kolom;
     * menggabungkan diam-diam dengan data lama akan membuat validasi menilai
     * baris yang tidak pernah dilihat pengisi.
     *
     * Pada lembar berbaris tetap `$kunciBaris` wajib (atau `_baris` di
     * `$data`), dan bila `$baris` null baris yang sudah ada untuk kunci itu
     * diperbarui — satu kunci baris tetap hanya boleh punya satu baris.
     *
     * Menyunting baris terverifikasi hanya boleh oleh pemegang
     * `dkps.verifikasi`, dan verifikasinya dicabut: cap verifikasi berlaku
     * untuk angka yang diperiksa, bukan untuk angka penggantinya.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws IsianDkpsTidakSah
     * @throws TahunDiLuarJendela
     * @throws AuthorizationException
     */
    public function simpan(
        DkpsButir $butir,
        string $kodeLembar,
        Periode $periode,
        array $data,
        SumberData $sumber,
        User $oleh,
        ?DkpsBaris $baris = null,
        ?string $kunciBaris = null,
    ): DkpsBaris {
        // Baris yang disunting harus milik lembar yang disebut pemanggil.
        // Kalau tidak, fill() memindahkannya ke tabel/periode lain sementara
        // pokja_id (diisi sekali saat dibuat) tetap menunjuk pemilik lama.
        if ($baris !== null && (
            $baris->periode_id !== $periode->id
            || $baris->dkps_butir_id !== $butir->id
            || $baris->lembar_kode !== $kodeLembar
        )) {
            throw new IsianDkpsTidakSah(['_baris' => 'Baris tidak termasuk lembar ini.']);
        }

        $skema = $butir->skema($kodeLembar);

        // Tanpa kunci, SkemaLembar melempar InvalidArgumentException; di sini
        // diubah menjadi galat isian agar formulir bisa menampilkannya.
        if ($skema->adaBarisTetap() && ($kunciBaris ?? $data['_baris'] ?? null) === null) {
            throw new IsianDkpsTidakSah(['_baris' => 'Kunci baris wajib untuk lembar berbaris tetap.']);
        }

        // Kunci baris tetap yang asing (dan tahun yang tak terbaca) dilempar
        // SkemaLembar sebagai InvalidArgumentException; dijadikan galat isian
        // supaya pemanggil — grid dan impor — hanya menangani tiga jenis
        // penolakan. TahunDiLuarJendela juga turunan InvalidArgumentException,
        // tetapi dibiarkan lewat karena pemanggil menampilkannya tersendiri.
        try {
            $normal = $skema->normalkan($data, $kunciBaris);
            $galat = $skema->validasi($normal, $kunciBaris);

            if ($galat !== []) {
                throw new IsianDkpsTidakSah($galat);
            }

            $tahunAcuan = $skema->tahunAcuan($periode, $normal, $kunciBaris);
            $kunciNormal = $skema->kunciBaris($normal, $kunciBaris);
        } catch (TahunDiLuarJendela $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            throw new IsianDkpsTidakSah(['_baris' => $e->getMessage()]);
        }

        if ($baris === null && $skema->adaBarisTetap()) {
            $baris = DkpsBaris::query()
                ->where('periode_id', $periode->id)
                ->where('dkps_butir_id', $butir->id)
                ->where('lembar_kode', $kodeLembar)
                ->where('kunci_normal', $kunciNormal)
                ->first();
        }

        // periode_id ikut diisi supaya Izin memeriksa penguncian periode;
        // tanpanya baris baru lolos ke periode yang sudah dikunci.
        // dkps_butir_id ikut diisi supaya Izin mengenali penanggung jawab
        // tabel yang bukan anggota pokja pemiliknya.
        $calon = $baris ?? new DkpsBaris([
            'dkps_butir_id' => $butir->id,
            'pokja_id' => $this->pokjaId($butir, $periode),
            'periode_id' => $periode->id,
        ]);

        if (! Izin::boleh($oleh, 'dkps.isi', $calon)) {
            throw new AuthorizationException('Anda tidak berwenang mengisi tabel DKPS ini.');
        }

        $cabutVerifikasi = $baris?->terverifikasi() ?? false;

        if ($cabutVerifikasi && ! Izin::boleh($oleh, 'dkps.verifikasi', $baris)) {
            throw new AuthorizationException('Baris sudah diverifikasi; hanya verifikator yang boleh mengubahnya.');
        }

        return DB::transaction(function () use ($baris, $periode, $butir, $kodeLembar, $tahunAcuan, $normal, $sumber, $kunciNormal, $cabutVerifikasi) {
            $baris ??= new DkpsBaris;
            $baris->fill([
                'prodi_id' => $periode->prodi_id,
                'periode_id' => $periode->id,
                'dkps_butir_id' => $butir->id,
                'lembar_kode' => $kodeLembar,
                'tahun_acuan' => $tahunAcuan,
                'data' => $normal,
                'sumber' => $sumber,
                'kunci_normal' => $kunciNormal,
            ])->save();

            return $cabutVerifikasi
                ? $this->verifikator->batalkan($baris)
                : $baris->refresh();
        });
    }

    /**
     * Soft delete satu baris — termasuk baris tetap, yang dengan begitu
     * "dikosongkan" dan akan terisi baru bila disimpan lagi.
     *
     * Baris terverifikasi ditolak: menghapusnya akan menghilangkan jejak
     * angka yang sudah dinyatakan benar tanpa ada yang mencabutnya.
     *
     * @throws AuthorizationException
     */
    public function hapus(DkpsBaris $baris, User $oleh): void
    {
        if (! Izin::boleh($oleh, 'dkps.isi', $baris)) {
            throw new AuthorizationException('Anda tidak berwenang menghapus baris DKPS ini.');
        }

        if ($baris->terverifikasi()) {
            throw new RuntimeException('Baris terverifikasi tidak bisa dihapus; cabut verifikasinya lebih dulu.');
        }

        DB::transaction(fn () => $baris->delete());
    }

    /** @return Collection<int, DkpsBaris> */
    public function barisLembar(DkpsButir $butir, string $kodeLembar, Periode $periode): Collection
    {
        return DkpsBaris::query()
            ->where('periode_id', $periode->id)
            ->where('dkps_butir_id', $butir->id)
            ->where('lembar_kode', $kodeLembar)
            ->with(['bukti', 'verifikator'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Membuat bukti tautan lalu menautkannya ke baris.
     *
     * Prodi dan periode bukti SELALU diambil dari baris, menimpa isian
     * pemanggil: bukti yang menopang sebuah baris tidak boleh tersasar ke
     * periode lain dari baris itu.
     *
     * @param  array<string, mixed>  $atribut  judul, tanggal_kejadian, sumber, dst.
     *
     * @throws AuthorizationException
     */
    public function tautkanBuktiTautan(DkpsBaris $baris, string $url, array $atribut, User $oleh): Bukti
    {
        // Baris ikut dikirim agar penguncian periodenya ikut diperiksa.
        if (! Izin::boleh($oleh, 'bukti.unggah', $baris)) {
            throw new AuthorizationException('Anda tidak berwenang mengunggah bukti.');
        }

        return DB::transaction(function () use ($baris, $url, $atribut, $oleh) {
            $bukti = $this->pengelolaBukti->simpanTautan($url, [
                'prodi_id' => $baris->prodi_id,
                'periode_id' => $baris->periode_id,
            ] + $atribut, $oleh);

            $baris->bukti()->syncWithoutDetaching([$bukti->id]);

            return $bukti;
        });
    }

    /**
     * Pokja pemilik tabel pada periode itu — untuk memeriksa wewenang
     * sebelum barisnya ada. Tanpa lingkup periode, sama dengan kait
     * `creating` di DkpsBaris: yang menentukan periode baris, bukan periode
     * yang sedang dilihat pengguna.
     */
    private function pokjaId(DkpsButir $butir, Periode $periode): ?string
    {
        if ($butir->pokja_kode === null) {
            return null;
        }

        return Pokja::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $periode->id)
            ->where('kode', $butir->pokja_kode)
            ->value('id');
    }
}
