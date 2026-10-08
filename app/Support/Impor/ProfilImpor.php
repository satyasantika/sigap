<?php

namespace App\Support\Impor;

use App\Models\ImporBatch;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu profil impor per sasaran. Komponen ImporTempel dipakai ulang apa
 * adanya; yang berbeda hanya profilnya.
 *
 * Profil lain (ImporBarisDkps, ImporPublikasiDtps, dan seterusnya) menyusul di
 * tahap 5 tanpa menyentuh komponennya.
 */
interface ProfilImpor
{
    public function nama(): string;

    /**
     * Kolom yang bisa dipetakan.
     *
     * @return array<string, array{label: string, wajib: bool, tipe: string, contoh: string}>
     */
    public function medan(): array;

    /**
     * Kunci duplikasi satu baris, SETELAH dinormalkan.
     * null berarti baris ini tidak bisa diperiksa duplikasinya.
     *
     * @param  array<string, mixed>  $baris
     */
    public function kunciDuplikat(array $baris): ?string;

    /**
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    public function normalkan(array $baris): array;

    /**
     * Daftar galat pada satu baris; larik kosong berarti sah.
     *
     * @param  array<string, mixed>  $baris
     * @return array<int, string>
     */
    public function validasi(array $baris): array;

    /** @param  array<string, mixed>  $baris */
    public function simpan(array $baris, ImporBatch $batch): Model;

    /** @param  array<string, mixed>  $baris */
    public function perbarui(Model $lama, array $baris, ImporBatch $batch): Model;

    /**
     * Baris yang sudah ada di basis data dengan kunci ini.
     *
     * $periodeId null untuk profil yang tidak terikat periode (mis.
     * pengguna) -- lihat ImporPengguna.
     */
    public function cariYangAda(string $kunci, ?string $periodeId): ?Model;
}
