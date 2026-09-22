<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kata sandi awal dibuat admin, bukan dikirim lewat surel. Karena itu pengguna
 * wajib menggantinya pada kesempatan pertama — bila tidak dipaksa, kata sandi
 * awal itu akan bertahan berbulan-bulan.
 *
 * Lihat vibecoding/docs/08-auth-dan-izin.md bagian "Pengguna awal".
 */
class PaksaGantiSandi
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna?->wajib_ganti_sandi && ! $this->dikecualikan($request)) {
            return redirect()
                ->to(filament()->getProfileUrl())
                ->with('peringatan', 'Ganti kata sandi awal Anda sebelum melanjutkan.');
        }

        return $next($request);
    }

    /** Halaman profil dan keluar harus tetap terbuka, kalau tidak jadi jerat. */
    private function dikecualikan(Request $request): bool
    {
        $profil = filament()->getProfileUrl();

        return ($profil && $request->fullUrlIs($profil.'*'))
            || $request->routeIs('filament.*.auth.logout')
            || $request->routeIs('livewire.*');
    }
}
