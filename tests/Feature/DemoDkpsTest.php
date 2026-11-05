<?php

namespace Tests\Feature;

use App\Enums\LevelSyaratPerlu;
use App\Enums\PeranPengguna;
use App\Models\DkpsBaris;
use App\Models\Elemen;
use App\Models\NilaiRumus;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\StatusSyaratPerlu;
use App\Models\User;
use App\Services\KalkulatorNa;
use App\Services\PembacaVariabelDkps;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Isi DKPS demonstrasi: DemoSeeder menulis `data/demo-dkps.json` lewat
 * PengisiDkps dan menghitung rumus lewat PenghitungRumusDkps — jalur yang
 * sama dengan produksi. Yang dijaga di sini adalah ISI demonya (cerita
 * "hampir Unggul, tersandung satu syarat perlu"), bukan fitur sesi demo;
 * yang terakhir itu milik DemoTest.
 *
 * Seeder dijalankan ulang per uji (RefreshDatabase); mahal, tetapi proyek
 * ini belum punya pola seeding sekali per kelas.
 */
class DemoDkpsTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->periode = Periode::aktif()->firstOrFail();
    }

    /** @return array<string, array{baris: array<int, array<string, mixed>>}> */
    private function demo(): array
    {
        return json_decode(file_get_contents(base_path('data/demo-dkps.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return Collection<string, NilaiRumus> hasil terakhir per kode rumus */
    private function hasilTerakhir(): Collection
    {
        return NilaiRumus::withoutGlobalScope('lingkup_periode')
            ->terakhir($this->periode->id)->get()->keyBy('rumus_kode');
    }

    #[Test]
    public function setiap_lembar_demo_tertulis(): void
    {
        $demo = $this->demo();
        $this->assertCount(35, $demo);

        foreach ($demo as $kode => $lembar) {
            $this->assertSame(
                count($lembar['baris']),
                DkpsBaris::withoutGlobalScope('lingkup_periode')
                    ->where('periode_id', $this->periode->id)
                    ->where('lembar_kode', (string) $kode)->count(),
                "Lembar {$kode}",
            );
        }
    }

    #[Test]
    public function tidak_ada_token_tahun_tersisa(): void
    {
        $this->assertGreaterThan(0, DB::table('dkps_baris')->count());
        $this->assertSame(0, DB::table('dkps_baris')->where('data', 'like', '%{TS%')->count());
    }

    #[Test]
    public function tidak_ada_variabel_demo_yang_kosong(): void
    {
        $variabel = collect(app(PembacaVariabelDkps::class)->semua($this->periode));
        $this->assertNotEmpty($variabel, 'Katalog variabel DKPS kosong — VariabelDkpsSeeder tidak dipanggil.');

        $kosong = $variabel
            ->filter(fn ($nv) => $nv->nilai === null)
            ->map(fn ($nv) => $nv->catatan)
            ->all();

        $this->assertSame([], $kosong);
    }

    #[Test]
    public function semua_rumus_demo_terhitung(): void
    {
        $hasil = $this->hasilTerakhir();

        $this->assertCount(27, $hasil);
        $this->assertFalse($hasil->has('NA'));
    }

    #[Test]
    public function pds3_demo_memberi_skor_empat_tetapi_syarat_lima_tahun_belum(): void
    {
        $pds3 = $this->hasilTerakhir()->get('PDS3');

        $this->assertNotNull($pds3);
        $this->assertEqualsWithDelta(41.67, (float) $pds3->nilai, 0.01);
        $this->assertSame(4, $pds3->skor);
        $this->assertTrue($pds3->memenuhi_syarat_3_tahun);
        $this->assertFalse($pds3->memenuhi_syarat_5_tahun);
    }

    #[Test]
    public function skor_a_demo_campuran(): void
    {
        $hasil = $this->hasilTerakhir();
        $skor = $hasil->pluck('skor')->filter(fn ($s) => $s !== null)->unique()->values()->all();

        foreach ([2, 3, 4] as $s) {
            $this->assertContains($s, $skor, "Tidak ada rumus berskor {$s}");
        }

        $this->assertNull($hasil->get('RMS')?->skor);
        $this->assertNotNull($hasil->get('RMS'));
    }

    #[Test]
    public function syarat_perlu_demo_konsisten_dengan_rumus(): void
    {
        $level = fn (int $no) => StatusSyaratPerlu::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $this->periode->id)
            ->where('elemen_id', Elemen::where('no', $no)->value('id'))
            ->value('level');

        $this->assertSame(LevelSyaratPerlu::Tiga, $level(17));
        $this->assertSame(LevelSyaratPerlu::Belum, $level(58));

        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();
        $this->assertSame(2, (int) Penilaian::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $this->periode->id)
            ->where('elemen_id', Elemen::where('no', 58)->value('id'))
            ->where('penilai_id', $ketua->id)
            ->value('skor'));
    }

    #[Test]
    public function na_demo_tetap_terakreditasi_lima_tahun_dengan_satu_syarat_belum(): void
    {
        $na = app(KalkulatorNa::class)->hitung($this->periode);

        $this->assertGreaterThanOrEqual(321, $na->na);
        $this->assertFalse($na->syarat3);
        $this->assertStringContainsString('5 tahun', $na->kalimatStatus());
        $this->assertStringNotContainsString('Unggul', $na->kalimatStatus());
    }
}
