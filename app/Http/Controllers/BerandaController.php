<?php

namespace App\Http\Controllers;

use App\Support\Izin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * Halaman muka publik.
 *
 * Sampai sekarang akar situs langsung mengalihkan ke `/panel`; halaman ini
 * menggantikannya atas permintaan manusia. Karena ia terbuka tanpa masuk, ada
 * satu aturan yang mengikat: **tidak ada satu pun angka akreditasi di sini.**
 * Tidak ada NA, tidak ada progres, tidak ada nama dosen, tidak ada bukti.
 *
 * Yang ditampilkan hanya bentuk instrumennya — 59 elemen, bobot 100, sembilan
 * kriteria, lima syarat perlu — dan itu bukan rahasia: seluruhnya tertulis di
 * Peraturan LAMDIK Nomor 5 Tahun 2025 yang terbit untuk umum. Angkanya dibaca
 * dari `data/*.json`, sumber yang sama dengan seeder, supaya halaman muka tidak
 * bisa menyimpang dari isi sistemnya.
 *
 * Basis data sengaja tidak disentuh sama sekali. Halaman muka yang tetap
 * tampil ketika basis data mati adalah halaman yang masih bisa memberi tahu
 * orang apa yang sedang terjadi.
 */
class BerandaController extends Controller
{
    public function __invoke(): View
    {
        return view('beranda', [
            'angka' => $this->angka(),
            'peran' => $this->peran(),
        ]);
    }

    /**
     * Angka instrumen, dibaca dari berkas yang sama dengan seeder.
     *
     * @return array<string, array{nilai: string, label: string, catatan: string}>
     */
    private function angka(): array
    {
        return Cache::store('array')->rememberForever('sigap.beranda.angka', function () {
            $elemen = $this->baca('elemen.json');
            $bobot = round(array_sum(array_column($elemen, 'bobot')), 2);

            return [
                'elemen' => [
                    'nilai' => (string) count($elemen),
                    'label' => 'elemen penilaian',
                    'catatan' => 'Dibagi habis ke '.count($this->baca('pokja.json')).' kelompok kerja.',
                ],
                'bobot' => [
                    'nilai' => number_format($bobot, 2, ',', '.'),
                    'label' => 'total bobot',
                    'catatan' => 'Progres dihitung dari bobot, tidak pernah dari cacah tagihan.',
                ],
                'kriteria' => [
                    'nilai' => (string) count($this->baca('kriteria.json')),
                    'label' => 'kriteria',
                    'catatan' => 'K1 sampai K9, seluruhnya tampil di dasbor.',
                ],
                'syarat' => [
                    'nilai' => (string) count($this->baca('syarat-perlu.json')),
                    'label' => 'syarat perlu',
                    'catatan' => 'Kelimanya harus terpenuhi. Empat dari lima tetap berarti tidak.',
                ],
                'dkps' => [
                    'nilai' => (string) count($this->baca('dkps-tabel.json')),
                    'label' => 'butir DKPS',
                    'catatan' => 'Tabel kuantitatif yang menyuapi 15 rumus penilaian.',
                ],
                'izin' => [
                    'nilai' => (string) (count($this->baca('izin.json')['aksi']) * count($this->baca('peran.json'))),
                    'label' => 'sel matriks izin',
                    'catatan' => '24 aksi × 6 peran, semuanya bisa diperiksa dari dalam sistem.',
                ],
            ];
        });
    }

    /**
     * Enam peran apa adanya dari data/peran.json.
     *
     * Termasuk kolom `tidak_boleh`, dan itu disengaja: yang paling sering
     * disalahpahami tentang SIGAP bukan apa yang bisa dilakukan sebuah peran,
     * melainkan apa yang justru tidak — admin tidak menyetujui tagihan, ketua
     * tidak mengelola pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    private function peran(): array
    {
        return Cache::store('array')->rememberForever('sigap.beranda.peran', function () {
            return array_map(
                fn (array $p) => $p + ['menu' => $this->menuPeran($p['kode'])],
                $this->baca('peran.json'),
            );
        });
    }

    /** Menu yang dilihat peran ini, diambil dari matriks yang sama dengan panel. */
    private function menuPeran(string $kode): array
    {
        return collect(Izin::daftarMenu())
            ->filter(fn (array $m, string $k) => (Izin::matriksMenu()[$k][$kode] ?? 'tidak') !== 'tidak')
            ->pluck('label')
            ->values()
            ->all();
    }

    private function baca(string $berkas): array
    {
        return json_decode(
            file_get_contents(base_path("data/{$berkas}")),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
