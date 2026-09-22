<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * vibecoding/docs/08-auth-dan-izin.md bagian 1 butir 7:
 * `terakhir_masuk_pada` diperbarui setiap kali pengguna masuk.
 *
 * Dipakai admin untuk melihat siapa yang sebenarnya memakai sistem sebelum
 * menonaktifkan akun.
 */
class CatatWaktuMasuk
{
    public function handle(Login $event): void
    {
        $event->user->forceFill(['terakhir_masuk_pada' => now()])->saveQuietly();
    }
}
