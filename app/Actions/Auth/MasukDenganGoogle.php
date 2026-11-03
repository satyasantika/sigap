<?php

namespace App\Actions\Auth;

use App\Enums\PeranPengguna;
use App\Exceptions\MasukGoogleDitolak;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as PenggunaGoogle;

/**
 * Semua akun Google boleh masuk. Dicocokkan lewat google_id lalu surel; bila
 * belum ada, dibuat akun baru dengan peran paling sempit (Anggota).
 */
class MasukDenganGoogle
{
    public function jalankan(PenggunaGoogle $google): User
    {
        $email = strtolower(trim((string) $google->getEmail()));
        $raw = $google instanceof AbstractUser ? $google->getRaw() : [];
        $terverifikasi = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL);

        if ($email === '' || ! $terverifikasi) {
            throw new MasukGoogleDitolak('Surel akun Google Anda belum terverifikasi.');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $nama = $google->getName() ?: Str::before($email, '@');

            // `peran` wajib diisi (kolom enum tanpa bawaan; MySQL non-strict
            // akan diam-diam memakai nilai pertama, "admin"). Akun baru
            // mendapat peran paling sempit, sama seperti UserFactory.
            $user = User::create([
                'name' => $nama,
                'nama_lengkap' => $nama,
                'email' => $email,
                'password' => Str::random(40),
                'peran' => PeranPengguna::Anggota,
                'aktif' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($user->google_id !== null && $user->google_id !== $google->getId()) {
            throw new MasukGoogleDitolak('Akun ini sudah ditautkan ke akun Google lain.');
        }

        if (! $user->aktif) {
            throw new MasukGoogleDitolak('Akun Anda dinonaktifkan. Hubungi admin.');
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $google->getId()])->save();
        }

        return $user;
    }
}
