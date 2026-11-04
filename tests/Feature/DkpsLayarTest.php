<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Filament\Pages\DkpsLayar;
use App\Filament\Pages\TabelDkpsLayar;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PembangkitTagihan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Layar daftar Tabel DKPS (`/panel/dkps`) dikelompokkan per pokja; TS 2027. */
class DkpsLayarTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::aktif()->firstOrFail();
        $this->periode->forceFill(['ts_tahun' => 2027])->save();
        $this->prodi = Prodi::firstOrFail();
    }

    private function pengguna(PeranPengguna $peran, ?string $kodePokja = null): User
    {
        $u = User::create([
            'name' => 'u', 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@dkps-layar.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    #[Test]
    public function ketua_melihat_seluruh_pokja_pemilik_tabel(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get('/panel/dkps')
            ->assertOk()
            ->assertSee('POKJA-SDM')
            ->assertSee('POKJA-DIK')
            ->assertSee('Tabel 7')
            ->assertSee('Tabel 18')
            ->assertSee('0 / 13 lembar terverifikasi')
            ->assertSee(TabelDkpsLayar::urlUntuk('1', '1.2'), false);
    }

    #[Test]
    public function anggota_hanya_melihat_tabel_pokjanya(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'))
            ->get('/panel/dkps')
            ->assertOk()
            ->assertSee('Tabel 7')
            ->assertDontSee('Tabel 18')
            ->assertDontSee('POKJA-DIK');
    }

    #[Test]
    public function pimpinan_melihat_tanpa_tombol_tambah(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Pimpinan))
            ->get('/panel/dkps')
            ->assertOk()
            ->assertSee('Tabel 18')
            ->assertDontSee('Tambah');
    }

    #[Test]
    public function status_lembar_dan_tagihan_tampil_per_baris(): void
    {
        app(PembangkitTagihan::class)->untuk($this->periode);

        DkpsBaris::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'dkps_butir_id' => DkpsButir::dariKode('28')->id, 'lembar_kode' => '28',
            'tahun_acuan' => 'TS', 'sumber' => SumberData::Manual,
            'data' => ['nama_dtps' => 'Dr. A', 'judul_artikel' => 'Artikel', 'jumlah_sitasi' => 4],
        ]);

        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $this->actingAs($ketua);

        $halaman = new DkpsLayar;
        $sdm = collect($halaman->progres())->firstWhere('kode', 'POKJA-SDM');
        $t28 = collect($sdm['tabel'])->first(fn (array $t) => $t['butir']->kode === '28');

        $this->assertSame('lengkap', $t28['lembar']['28']['status']);
        $this->assertNotNull($halaman->tagihanTabel($t28['butir']));

        $this->get('/panel/dkps')->assertOk()->assertSee('Lengkap');
    }

    #[Test]
    public function menu_tabel_dkps_tampil_bagi_ketua_tetapi_tidak_bagi_admin(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Ketua));
        $this->assertTrue(DkpsLayar::shouldRegisterNavigation());

        $this->actingAs($this->pengguna(PeranPengguna::Admin));
        $this->assertFalse(DkpsLayar::shouldRegisterNavigation());
    }
}
