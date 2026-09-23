<?php

namespace App\Http\Controllers;

use App\Services\Impersonasi;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Satu jalan keluar dari penyamaran yang tidak bergantung pada Livewire.
 *
 * Tombol di spanduk memakai formulir POST biasa, bukan aksi Livewire, supaya
 * ia tetap berfungsi pada halaman mana pun — termasuk halaman yang gagal
 * memuat JavaScript. Terjebak di dalam penyamaran tanpa tombol keluar adalah
 * kegagalan yang mahal: satu-satunya jalan lain adalah menghapus cookie.
 */
class ImpersonasiController extends Controller
{
    public function akhiri(Impersonasi $impersonasi): RedirectResponse
    {
        // Tamu diarahkan ke halaman masuk, bukan ditolak middleware `auth`.
        // Middleware itu memakai rute bernama `login`, yang tidak ada di SIGAP:
        // halaman masuknya milik panel Filament. Memeriksanya di sini membuat
        // rute ini tidak bergantung pada konteks panel.
        if (! Auth::check()) {
            return redirect(Filament::getLoginUrl());
        }

        $admin = $impersonasi->akhiri();

        if ($admin === null) {
            return redirect(Filament::getUrl());
        }

        return redirect(Filament::getUrl())
            ->with('sigap.pesan', 'Penyamaran diakhiri. Anda kembali sebagai '.$admin->nama_lengkap.'.');
    }
}
