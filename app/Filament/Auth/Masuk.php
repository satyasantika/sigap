<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Halaman masuk SIGAP.
 *
 * Filament sengaja menyamakan pesan "sandi salah" dengan "tidak boleh masuk
 * panel" supaya keberadaan sebuah akun tidak bisa ditebak dari luar. Perilaku
 * itu DIPERTAHANKAN.
 *
 * Yang dibedakan hanya satu hal: pengguna yang sandinya SUDAH terbukti benar
 * tetapi akunnya nonaktif. Pada titik itu penyerang sudah memegang sandi yang
 * sah, jadi pesan yang jelas tidak membocorkan apa pun — sementara bagi dosen
 * yang akunnya baru dinonaktifkan, ia berhenti menebak-nebak sandinya sendiri.
 *
 * Lihat vibecoding/docs/08-auth-dan-izin.md bagian 1 butir 6.
 */
class Masuk extends Login
{
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if ($user instanceof User && ! $user->aktif) {
            throw ValidationException::withMessages([
                'data.email' => 'Akun ini dinonaktifkan. Hubungi administrator sistem untuk mengaktifkannya kembali.',
            ]);
        }

        return parent::isUserAllowedToAccessPanel($user);
    }
}
