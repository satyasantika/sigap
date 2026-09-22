<?php

namespace Tests\Feature;

use App\Models\Periode;
use App\Models\Prodi;
use App\Services\JendelaTs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Empat jendela data, diuji pada TS = 2027 sesuai prompt.
 *
 * Tahun 2027 hanya muncul DI SINI, di dalam uji — bukan di app/ maupun
 * migrasi. Ada uji terpisah yang menjaga itu.
 */
class JendelaTsTest extends TestCase
{
    use RefreshDatabase;

    private function periode(int $ts = 2027): Periode
    {
        $prodi = Prodi::create([
            'kode' => 'X', 'nama' => 'X', 'jenjang' => 'ppg',
            'upps' => 'X', 'perguruan_tinggi' => 'X', 'aktif' => true,
        ]);

        return Periode::create([
            'prodi_id' => $prodi->id, 'nama' => 'Uji', 'ts_tahun' => $ts,
            'versi_instrumen' => 'IAPSK 3.0', 'status' => 'berjalan',
        ]);
    }

    /** @return array<string, array{string, int, int}> */
    public static function jendela(): array
    {
        return [
            'saat TS' => ['saat TS', 2027, 2027],
            'TS-2 s.d. TS' => ['TS-2 s.d. TS', 2025, 2027],
            'TS-4 s.d. TS-2' => ['TS-4 s.d. TS-2', 2023, 2025],
            '5 tahun terakhir' => ['5 tahun terakhir', 2023, 2027],
        ];
    }

    #[Test]
    #[DataProvider('jendela')]
    public function rentang_benar_untuk_ts_2027(string $jendela, int $awal, int $akhir): void
    {
        $this->assertSame([$awal, $akhir], JendelaTs::rentang($this->periode(), $jendela));
    }

    #[Test]
    public function jendela_bergeser_mengikuti_ts(): void
    {
        // Inilah gunanya: akreditasi berikutnya memakai TS berbeda, dan tidak
        // ada satu pun angka yang perlu diubah di kode.
        $this->assertSame([2028, 2030], JendelaTs::rentang($this->periode(2030), 'TS-2 s.d. TS'));
    }

    #[Test]
    public function jendela_tak_dikenal_ditolak_dengan_daftar_yang_sah(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tidak dikenal/');

        JendelaTs::rentang($this->periode(), '3 tahun terakhir');
    }

    #[Test]
    public function label_relatif_diterjemahkan_ke_tahun_sesungguhnya(): void
    {
        $p = $this->periode();

        $this->assertSame(2027, JendelaTs::tahunDariLabel($p, 'TS'));
        $this->assertSame(2026, JendelaTs::tahunDariLabel($p, 'TS-1'));
        $this->assertSame(2023, JendelaTs::tahunDariLabel($p, 'TS-4'));
    }

    #[Test]
    public function label_dalam_jendela_terurut_dari_yang_terlama(): void
    {
        $this->assertSame(['TS-2', 'TS-1', 'TS'], JendelaTs::labelDalamJendela('TS-2 s.d. TS'));
        $this->assertSame(['TS-4', 'TS-3', 'TS-2'], JendelaTs::labelDalamJendela('TS-4 s.d. TS-2'));
        $this->assertSame(['TS'], JendelaTs::labelDalamJendela('saat TS'));
    }

    #[Test]
    public function keterangan_menyebut_tahun_sesungguhnya(): void
    {
        $p = $this->periode();

        // Supaya pengisi tahu tahun mana yang diminta tanpa membuka Buku 3.
        $this->assertSame('TS-2 s.d. TS (2025–2027)', JendelaTs::keterangan($p, 'TS-2 s.d. TS'));
        $this->assertSame('saat TS (2027)', JendelaTs::keterangan($p, 'saat TS'));
    }

    #[Test]
    public function seluruh_jendela_di_data_dkps_dikenali(): void
    {
        $p = $this->periode();

        // Kalau data/dkps-tabel.json memuat jendela yang tidak dikenal kelas
        // ini, layar isiannya akan pecah saat butir itu dibuka.
        $jendela = collect(json_decode(
            file_get_contents(base_path('data/dkps-tabel.json')), true, flags: JSON_THROW_ON_ERROR,
        ))->pluck('jendela_data')->unique();

        foreach ($jendela as $j) {
            $this->assertIsArray(JendelaTs::rentang($p, $j), "Jendela `{$j}` dari DKPS tidak dikenali.");
        }
    }

    #[Test]
    public function seluruh_jendela_di_data_rumus_dikenali(): void
    {
        $p = $this->periode();

        $jendela = collect(json_decode(
            file_get_contents(base_path('data/rumus.json')), true, flags: JSON_THROW_ON_ERROR,
        ))->pluck('jendela')->unique()
            // NA tidak terikat jendela; nilainya "-".
            ->reject(fn (string $j) => $j === '-' || str_contains($j, 'semester'));

        foreach ($jendela as $j) {
            $this->assertIsArray(JendelaTs::rentang($p, $j), "Jendela `{$j}` dari rumus tidak dikenali.");
        }
    }
}
