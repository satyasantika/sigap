<?php

namespace App\Providers;

use App\Listeners\CatatWaktuMasuk;
use App\Support\Pemasangan;
use Illuminate\Auth\Events\Login;
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

        Event::listen(Login::class, CatatWaktuMasuk::class);

        // Terjemahan pesan validasi ada di lang/id/validation.php, bukan
        // ditambal satu per satu di sini. Laravel 11 ke atas tidak menyertakan
        // berkas terjemahan apa pun, jadi tanpa berkas itu pesannya muncul
        // sebagai kunci mentah ("validation.required").
    }
}
