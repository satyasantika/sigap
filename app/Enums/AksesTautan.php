<?php

namespace App\Enums;

/**
 * Keterbacaan bukti bagi orang yang TIDAK punya akses apa-apa — yaitu asesor.
 *
 * Diperiksa mesin tanpa kredensial apa pun. Memakai kredensial membuat
 * pemeriksaan selalu lulus dan karena itu tidak berguna.
 *
 * `gagal_periksa` adalah kegagalan MESIN, bukan kesalahan pengunggah: jaringan
 * putus, server lambat, atau 5xx di pihak sana. Ia tetap menghalangi
 * persetujuan — "tidak terbukti terbuka" sama berbahayanya dengan "terbukti
 * tertutup" — tetapi diwarnai berbeda supaya orang tidak mengira dirinya salah.
 */
enum AksesTautan: string
{
    case BelumDiperiksa = 'belum_diperiksa';
    case Terbuka = 'terbuka';
    case PerluIzin = 'perlu_izin';
    case TidakDitemukan = 'tidak_ditemukan';
    case GagalPeriksa = 'gagal_periksa';

    public function label(): string
    {
        return match ($this) {
            self::BelumDiperiksa => 'Belum diperiksa',
            self::Terbuka => 'Terbuka',
            self::PerluIzin => 'Perlu izin',
            self::TidakDitemukan => 'Tidak ditemukan',
            self::GagalPeriksa => 'Gagal diperiksa',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::BelumDiperiksa => 'gray',
            self::Terbuka => 'success',
            self::PerluIzin => 'danger',
            self::TidakDitemukan => 'danger',
            self::GagalPeriksa => 'warning',
        };
    }

    /** Hanya `terbuka` yang membolehkan tagihan disetujui. */
    public function bolehDisetujui(): bool
    {
        return $this === self::Terbuka;
    }

    public function alasanPenolakan(): string
    {
        return match ($this) {
            self::Terbuka => '',
            self::BelumDiperiksa => 'keterbacaannya belum diperiksa',
            self::PerluIzin => 'tidak bisa dibuka tanpa izin — asesor akan melihat halaman masuk',
            self::TidakDitemukan => 'tautannya tidak ditemukan',
            self::GagalPeriksa => 'pemeriksaan keterbacaannya gagal, belum terbukti bisa dibuka',
        };
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $a) => [$a->value => $a->label()])->all();
    }
}
