<?php

namespace App\Support;

/**
 * Jati diri aplikasi: nama, pemilik, tahun hak cipta.
 *
 * Satu tempat supaya footer, halaman masuk, dan manual pengguna tidak saling
 * menyimpang. Nilainya dibaca dari `config/sigap.php` — lihat berkas itu untuk
 * alasan mengapa tahun hak cipta tidak boleh berada di dalam app/.
 */
class Jati
{
    public static function nama(): string
    {
        return config('sigap.nama');
    }

    public static function namaPanjang(): string
    {
        return config('sigap.nama_panjang');
    }

    public static function pemilik(): string
    {
        return config('sigap.pemilik');
    }

    public static function tahunHakCipta(): string
    {
        return (string) config('sigap.tahun_hak_cipta');
    }

    /** Baris yang muncul di footer setiap halaman. */
    public static function hakCipta(): string
    {
        return '© '.self::tahunHakCipta().' '.self::pemilik();
    }
}
