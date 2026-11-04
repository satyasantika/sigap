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
        $skema = $butir->skema($kodeLembar);

        // Tanpa kunci, SkemaLembar melempar InvalidArgumentException; di sini
        // diubah menjadi galat isian agar formulir bisa menampilkannya.
        if ($skema->adaBarisTetap() && ($kunciBaris ?? $data['_baris'] ?? null) === null) {
            throw new IsianDkpsTidakSah(['_baris' => 'Kunci baris wajib untuk lembar berbaris tetap.']);
        }

        $normal = $skema->normalkan($data, $kunciBaris);
        $galat = $skema->validasi($normal, $kunciBaris);

        if ($galat !== []) {
            throw new IsianDkpsTidakSah($galat);
        }

        $tahunAcuan = $skema->tahunAcuan($periode, $normal, $kunciBaris);
        $kunciNormal = $skema->kunciBaris($normal, $kunciBaris);

        if ($baris === null && $skema->adaBarisTetap()) {
            $baris = DkpsBaris::query()
                ->where('periode_id', $periode->id)
                ->where('dkps_butir_id', $butir->id)
                ->where('lembar_kode', $kodeLembar)
                ->where('kunci_normal', $kunciNormal)
                ->first();
        }

        $calon = $baris ?? new DkpsBaris(['pokja_id' => $this->pokjaId($butir, $periode)]);

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
     * Prodi dan periode bukti dilengkapi dari baris, supaya pemanggil
     * formulir tidak perlu mengulang data yang sudah melekat pada baris.
     *
     * @param  array<string, mixed>  $atribut  judul, tanggal_kejadian, sumber, dst.
     *
     * @throws AuthorizationException
     */
    public function tautkanBuktiTautan(DkpsBaris $baris, string $url, array $atribut, User $oleh): Bukti
    {
        if (! Izin::boleh($oleh, 'bukti.unggah')) {
            throw new AuthorizationException('Anda tidak berwenang mengunggah bukti.');
        }

        return DB::transaction(function () use ($baris, $url, $atribut, $oleh) {
            $bukti = $this->pengelolaBukti->simpanTautan($url, $atribut + [
                'prodi_id' => $baris->prodi_id,
                'periode_id' => $baris->periode_id,
            ], $oleh);

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
