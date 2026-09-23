<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Hal-hal yang bergantung pada DI MANA SIGAP dipasang, bukan pada isinya.
 *
 * SIGAP berjalan di dua tempat dengan bentuk alamat yang berbeda:
 * `http://localhost:8021` di akar domain, dan
 * `https://supportfkip.unsil.ac.id/sigap` di bawah subfolder. Perbedaan itu
 * menyentuh setiap tautan yang dibangun aplikasi, jadi ia diputuskan di satu
 * tempat — di sini — bukan ditebak ulang di provider, di perintah pemeriksa,
 * dan di Blade.
 */
class Pemasangan
{
    /**
     * Subfolder pemasangan, misalnya `/sigap`. Kosong bila di akar domain.
     *
     * Diturunkan dari jalur pada APP_URL, bukan dari header permintaan. Server
     * balik yang memangkas awalan sebelum meneruskan ke PHP tidak meninggalkan
     * jejak yang bisa diandalkan, jadi satu-satunya sumber yang pasti adalah
     * APP_URL.
     */
    public static function subfolder(): string
    {
        $jalur = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        return $jalur === '' ? '' : '/'.$jalur;
    }

    public static function diSubfolder(): bool
    {
        return self::subfolder() !== '';
    }

    /**
     * Membuat seluruh tautan benar ketika SIGAP dipasang di bawah subfolder.
     *
     * Tanpa ini setiap `url()`, `route()`, dan `asset()` menghasilkan jalur
     * dari akar domain — `/panel/login`, bukan `/sigap/panel/login` — dan
     * seluruh tautan menunjuk ke luar aplikasi. Yang membuatnya mahal:
     * halaman mukanya tetap tampil, jadi pemasangannya kelihatan berhasil
     * sampai ada yang menekan tombol.
     *
     * Pemasangan di akar domain tidak tersentuh sama sekali.
     */
    public static function terapkan(): void
    {
        if (! self::diSubfolder()) {
            return;
        }

        $url = rtrim((string) config('app.url'), '/');

        URL::forceRootUrl($url);

        if (str_starts_with($url, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
