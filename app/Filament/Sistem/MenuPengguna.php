<?php

namespace App\Filament\Sistem;

use App\Services\Impersonasi;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Isi menu pengguna di pojok kanan atas.
 *
 * Dua hal yang diurus di sini, keduanya diminta manusia:
 *
 * 1. Keluar SELALU bertanya dulu. Tombol keluar bertetangga dengan tombol
 *    profil dan penukar tema; salah klik di tengah menyusun narasi berarti
 *    kehilangan isian yang belum tersimpan. Modal menahan sampai ada jawaban.
 * 2. Saat sedang menyamar, tersedia jalan keluar yang TIDAK mengakhiri sesi:
 *    "Kembali ke akun saya". Tanpa itu, satu-satunya cara berhenti menyamar
 *    adalah keluar lalu masuk lagi.
 */
class MenuPengguna
{
    /** @return array<string, Action> */
    public static function item(): array
    {
        return [
            'kembali_admin' => self::kembaliKeAdmin(),
            'logout' => self::keluar(),
        ];
    }

    private static function kembaliKeAdmin(): Action
    {
        $impersonasi = app(Impersonasi::class);

        return Action::make('kembali_admin')
            ->label(fn () => 'Kembali ke '.($impersonasi->adminAsli()?->nama_lengkap ?? 'akun saya'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn () => $impersonasi->sedangBerlangsung())
            ->sort(PHP_INT_MAX - 1)
            ->action(function () use ($impersonasi) {
                $impersonasi->akhiri();

                return redirect(Filament::getUrl());
            });
    }

    private static function keluar(): Action
    {
        $impersonasi = app(Impersonasi::class);

        return Action::make('logout')
            ->label('Keluar')
            ->icon(Heroicon::OutlinedArrowLeftEndOnRectangle)
            ->color('danger')
            ->sort(PHP_INT_MAX)
            ->requiresConfirmation()
            ->modalHeading('Keluar dari SIGAP?')
            ->modalDescription(fn () => $impersonasi->sedangBerlangsung()
                ? 'Anda sedang menyamar sebagai '.(Auth::user()?->nama_lengkap ?? 'pengguna lain')
                    .'. Keluar akan mengakhiri penyamaran sekaligus sesi Anda sebagai '
                    .($impersonasi->adminAsli()?->nama_lengkap ?? 'admin')
                    .'. Bila yang Anda maksud hanya berhenti menyamar, batalkan lalu pilih '
                    .'"Kembali ke akun saya".'
                : 'Isian yang belum disimpan akan hilang. Lanjutkan keluar?')
            ->modalSubmitActionLabel('Ya, keluar')
            ->modalCancelActionLabel('Batal, saya masih bekerja')
            ->modalIcon(Heroicon::OutlinedArrowLeftEndOnRectangle)
            ->action(function () use ($impersonasi) {
                // Penyamaran ditutup lebih dulu supaya baris "impersonasi.akhiri"
                // tercatat selagi sesinya masih ada. Sesudah session()->invalidate()
                // id admin aslinya sudah hilang dan jejaknya menggantung.
                if ($impersonasi->sedangBerlangsung()) {
                    $impersonasi->akhiri();
                }

                Filament::auth()->logout();

                session()->invalidate();
                session()->regenerateToken();

                return redirect(Filament::getLoginUrl());
            });
    }
}
