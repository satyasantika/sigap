<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Services\Demo;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pintu masuk demo, bergerbang kode akses.
 *
 * Alurnya tiga langkah dan tidak lebih: masukkan kode, pilih peran, masuk.
 * Kode disimpan di sesi setelah diterima, supaya memilih peran tidak menuntut
 * mengetik ulang — tetapi masa berlakunya diperiksa LAGI saat masuk, karena
 * demo bisa ditutup admin di antara dua langkah itu.
 */
class DemoController extends Controller
{
    public function __construct(private readonly Demo $demo) {}

    public function index(): View
    {
        $simulasi = $this->demo->simulasiSesiIni();

        return view('demo.masuk', [
            'simulasi' => $simulasi?->demoTerbuka() ? $simulasi : null,
            'peran' => Demo::peranYangBisaDicoba(),
        ]);
    }

    public function periksaKode(Request $permintaan): RedirectResponse
    {
        $permintaan->validate(['kode' => ['required', 'string', 'max:24']]);

        $simulasi = $this->demo->dariKode($permintaan->string('kode')->toString());

        if ($simulasi === null) {
            // Satu pesan untuk kode salah DAN kode kedaluwarsa. Membedakannya
            // memberi tahu penebak bahwa kodenya pernah benar.
            throw ValidationException::withMessages([
                'kode' => 'Kode demo tidak dikenali atau masa berlakunya sudah lewat. '
                    .'Mintalah kode baru kepada administrator sistem.',
            ]);
        }

        session()->put(Demo::KUNCI_SESI, $simulasi->getKey());

        return redirect()->route('demo');
    }

    public function masuk(Request $permintaan): RedirectResponse
    {
        $permintaan->validate(['peran' => ['required', 'string']]);

        $simulasi = $this->demo->simulasiSesiIni();

        // Diperiksa ulang: demo bisa ditutup admin setelah kodenya diterima.
        if ($simulasi === null || ! $simulasi->demoTerbuka()) {
            session()->forget(Demo::KUNCI_SESI);

            return redirect()->route('demo')
                ->withErrors(['kode' => 'Demo ini sudah ditutup. Mintalah kode baru.']);
        }

        $dicoba = PeranPengguna::tryFrom($permintaan->string('peran')->toString());

        if (! $dicoba instanceof PeranPengguna) {
            return redirect()->route('demo')->withErrors(['peran' => 'Peran tidak dikenali.']);
        }

        try {
            $this->demo->masuk($simulasi, $dicoba);
        } catch (RuntimeException $e) {
            return redirect()->route('demo')->withErrors(['peran' => $e->getMessage()]);
        }

        return redirect(Filament::getUrl());
    }

    public function keluar(): RedirectResponse
    {
        $this->demo->keluar();

        return redirect()->route('demo')
            ->with('sigap.pesan', 'Sesi demo diakhiri. Tidak ada data sungguhan yang tersentuh.');
    }
}
