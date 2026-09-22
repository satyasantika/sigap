<?php

namespace App\Providers;

use App\Listeners\CatatWaktuMasuk;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Login::class, CatatWaktuMasuk::class);

        // Pesan validasi bawaan Laravel berbahasa Inggris. Seluruh antarmuka
        // SIGAP berbahasa Indonesia (AGENTS.md bagian 3), termasuk pesan galat.
        Validator::replacer('required', fn ($pesan, $atribut) => "Kolom {$atribut} wajib diisi.");
    }
}
