<?php

namespace App\Providers;

use App\Listeners\CatatWaktuMasuk;
use App\Support\Pemasangan;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tautan harus benar sebelum apa pun dirender. Lihat App\Support\Pemasangan.
        Pemasangan::terapkan();

        $this->percayaiProksi();

        Event::listen(Login::class, CatatWaktuMasuk::class);

        // Terjemahan pesan validasi ada di lang/id/validation.php, bukan
        // ditambal satu per satu di sini. Laravel 11 ke atas tidak menyertakan
        // berkas terjemahan apa pun, jadi tanpa berkas itu pesannya muncul
        // sebagai kunci mentah ("validation.required").
    }

    /**
     * Mempercayai server balik agar Laravel membaca skema, host, dan awalan
     * jalur (`X-Forwarded-*`) yang sesungguhnya, bukan yang dilihat PHP dari
     * sambungan lokalnya ke proksi.
     *
     * Ini SENGAJA tidak dilakukan lewat `bootstrap/app.php` (parameter
     * `trustProxies` pada `withMiddleware()`). Closure di sana dijalankan
     * Laravel saat `HttpKernel` pertama kali dibuat — titik yang terjadi
     * SEBELUM bootstrapper `LoadEnvironmentVariables` memuat `.env` untuk
     * permintaan itu. Akibatnya `env('TRUSTED_PROXIES')` di titik itu kembali
     * NULL pada sebagian permintaan (tergantung proses PHP-FPM mana yang
     * menanganinya), proksi kadang dipercaya kadang tidak, dan setiap tautan
     * yang bergantung pada header proksi — termasuk endpoint update Livewire
     * — kadang benar kadang rusak tanpa pola yang kelihatan dari luar.
     *
     * `boot()` provider berjalan setelah config penuh dimuat, jadi
     * pembacaan env di sini selalu konsisten.
     */
    private function percayaiProksi(): void
    {
        $proksi = env('TRUSTED_PROXIES');

        if (blank($proksi)) {
            return;
        }

        $alamat = $proksi === '*' ? '*' : explode(',', $proksi);

        TrustProxies::at($alamat);
    }
}
