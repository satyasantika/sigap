<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Pages\SyaratPerluLayar;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use App\Services\KalkulatorRumus;
use App\Support\Rumus\HasilRumus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kartu E17 harus menampilkan PDS3 (terkait syarat perlu), bukan PGBLKL yang
 * kebetulan dihitung belakangan. TS 2027.
 */
class SyaratPerluLayarTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->periode->forceFill(['ts_tahun' => 2027])->save();
        $this->ketua = User::factory()->peran(PeranPengguna::Ketua)->create([
            'prodi_id' => Prodi::firstOrFail()->id, 'wajib_ganti_sandi' => false,
        ]);
    }

    private function simpan(HasilRumus $hasil): void
    {
        app(KalkulatorRumus::class)->simpan($hasil, $this->periode, $this->ketua);
    }

    private function pgblkl(): HasilRumus
    {
        return app(KalkulatorRumus::class)->pgblkl(ndgb: 1, ndlk: 3, ndl: 4, ndtps: 12);
    }

    #[Test]
    public function kartu_e17_menampilkan_pds3_bukan_pgblkl(): void
    {
        // Urutan simpan meniru PenghitungRumusDkps::hitungSemua().
        $this->simpan(app(KalkulatorRumus::class)->pds3(nds3: 5, ndtps: 12, ndlk: 3, ndgb: 1));
        $this->simpan($this->pgblkl());
        $this->actingAs($this->ketua);

        Livewire::test(SyaratPerluLayar::class)
            ->assertSee('PDS3')
            ->assertSee('41,67');
    }

    #[Test]
    public function tanpa_hasil_bersyarat_memakai_yang_terbaru(): void
    {
        $this->simpan($this->pgblkl());
        $this->actingAs($this->ketua);

        Livewire::test(SyaratPerluLayar::class)
            ->assertSee('PGBLKL');
    }
}
