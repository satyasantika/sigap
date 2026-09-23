<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tur terpandu: alur kerja tiap peran, langkah demi langkah.
 *
 * Melengkapi manual, bukan menggantikannya. Manual menjawab "layar ini apa";
 * tur menjawab "saya harus mulai dari mana". Orang baru hampir selalu
 * menanyakan yang kedua lebih dulu.
 *
 * Naskah dan gambarnya dibaca dari `docs/manual/tur.json` — berkas yang sama
 * yang menyusun manual, dibangkitkan `tools/susun-manual.py`. Disengaja:
 * manual dan tur berubah bersama, atau tidak berubah sama sekali. Keduanya
 * yang menceritakan dua versi sistem yang berbeda lebih buruk daripada salah
 * satunya tidak ada.
 *
 * Seperti halaman muka, tur TIDAK menyentuh basis data. Ia terbuka untuk siapa
 * saja, dan satu-satunya yang ditampilkannya adalah tangkapan layar dengan
 * data contoh.
 */
class TurController extends Controller
{
    public function index(): View
    {
        return view('tur.index', ['peran' => $this->data()['peran']]);
    }

    public function peran(Request $permintaan, string $peran): View
    {
        $semua = $this->data()['peran'];

        if (! array_key_exists($peran, $semua)) {
            throw new NotFoundHttpException("Tur untuk peran {$peran} tidak ada.");
        }

        $isi = $semua[$peran];
        $jumlah = count($isi['langkah']);

        // Nomor langkah ada di alamat, bukan di keadaan peramban. Satu langkah
        // jadi bisa ditautkan — "lihat langkah 4" — dan turnya tetap berjalan
        // tanpa JavaScript sama sekali.
        $ke = max(1, min($jumlah, (int) $permintaan->query('langkah', 1)));

        return view('tur.peran', [
            'kode' => $peran,
            'peran' => $isi,
            'ke' => $ke,
            'jumlah' => $jumlah,
            'langkah' => $isi['langkah'][$ke - 1] ?? null,
            'peranLain' => collect($semua)->except($peran)->all(),
        ]);
    }

    /** @return array{peran: array<string, array<string, mixed>>} */
    private function data(): array
    {
        return Cache::store('array')->rememberForever('sigap.tur', function () {
            $berkas = base_path('docs/manual/tur.json');

            if (! is_file($berkas)) {
                throw new NotFoundHttpException(
                    'docs/manual/tur.json belum dibangkitkan. Jalankan: python3 tools/susun-manual.py'
                );
            }

            return json_decode(file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);
        });
    }
}
