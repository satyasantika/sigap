<?php

namespace Tests\Feature;

use App\Enums\JenisTagihan;
use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Filament\Pages\DkpsLayar;
use App\Filament\Pages\TabelDkpsLayar;
use App\Livewire\LembarDkps;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembangkitTagihan;
use App\Services\PengisiDkps;
use App\Support\Izin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    // ---- Delegasi dari layar DKPS ---------------------------------------

    private function tagihanTabel(string $kode): Tagihan
    {
        return Tagihan::where('periode_id', $this->periode->id)
            ->where('jenis', JenisTagihan::DataDkps)
            ->where('dkps_butir_id', DkpsButir::dariKode($kode)->id)
            ->firstOrFail();
    }

    #[Test]
    public function ketua_menugaskan_penanggung_jawab_tabel_dari_layar_dkps(): void
    {
        app(PembangkitTagihan::class)->untuk($this->periode);
        $anggota = $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM');
        $this->actingAs($this->pengguna(PeranPengguna::Ketua));

        Livewire::test(DkpsLayar::class)
            ->callAction('tugaskan', ['penanggung_jawab_id' => $anggota->id], ['butir' => DkpsButir::dariKode('28')->id])
            ->assertHasNoActionErrors();

        $this->assertSame($anggota->id, $this->tagihanTabel('28')->penanggung_jawab_id);
        $this->assertSame(1, $anggota->notifications()->count());

        // Dikosongkan = dikembalikan ke seluruh pokja.
        Livewire::test(DkpsLayar::class)
            ->callAction('tugaskan', ['penanggung_jawab_id' => null], ['butir' => DkpsButir::dariKode('28')->id]);

        $this->assertNull($this->tagihanTabel('28')->penanggung_jawab_id);
    }

    #[Test]
    public function penanggung_jawab_dari_pokja_lain_boleh_mengisi_tabel_itu_saja(): void
    {
        app(PembangkitTagihan::class)->untuk($this->periode);

        // Tabel 28 milik POKJA-SDM; orangnya anggota POKJA-DIK.
        $luar = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');
        $t28 = LembarDkps::calonBaris(DkpsButir::dariKode('28'), '28', $this->periode);
        $t6 = LembarDkps::calonBaris(DkpsButir::dariKode('6'), '6', $this->periode);

        $this->assertFalse(Izin::boleh($luar, 'dkps.isi', $t28));

        $this->tagihanTabel('28')->update(['penanggung_jawab_id' => $luar->id]);

        $this->assertTrue(Izin::boleh($luar, 'dkps.isi', $t28));
        $this->assertFalse(Izin::boleh($luar, 'dkps.isi', $t6), 'Tabel lain di pokja itu tetap tertutup.');
        $this->assertFalse(Izin::boleh($luar, 'dkps.verifikasi', $t28), 'Verifikasi tetap milik pokja pemilik.');

        // Jalur tulis yang sebenarnya, bukan hanya tombolnya.
        $baris = app(PengisiDkps::class)->simpan(
            butir: DkpsButir::dariKode('28'), kodeLembar: '28', periode: $this->periode,
            data: ['nama_dtps' => 'Dr. A', 'judul_artikel' => 'Artikel', 'jumlah_sitasi' => 4],
            sumber: SumberData::Manual, oleh: $luar,
        );
        $this->assertTrue($baris->exists);

        $this->actingAs($luar)->get('/panel/dkps')->assertOk()
            ->assertSee('Tabel 28')
            ->assertSee('Ditugaskan kepada Anda')
            ->assertDontSee('Tabel 6 ');
    }

    #[Test]
    public function ketua_mengatur_anggota_pokja_dari_layar_dkps(): void
    {
        $lama = $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM');
        $baru = $this->pengguna(PeranPengguna::Anggota);
        $this->actingAs($this->pengguna(PeranPengguna::Ketua));

        $sdm = Pokja::where('kode', 'POKJA-SDM')->firstOrFail();
        $tetap = $sdm->anggota()->pluck('users.id')->reject(fn ($id) => $id === $lama->id)->values()->all();

        Livewire::test(DkpsLayar::class)
            ->callAction('anggota', ['anggota' => [...$tetap, $baru->id]], ['pokja' => 'POKJA-SDM'])
            ->assertHasNoActionErrors();

        $kini = $sdm->anggota()->pluck('users.id')->all();
        $this->assertContains($baru->id, $kini);
        $this->assertNotContains($lama->id, $kini);
        $this->assertTrue(Izin::boleh($baru, 'dkps.isi', LembarDkps::calonBaris(DkpsButir::dariKode('28'), '28', $this->periode)));
    }

    #[Test]
    public function anggota_tidak_mendapat_tombol_delegasi(): void
    {
        app(PembangkitTagihan::class)->untuk($this->periode);
        $this->actingAs($this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'));

        Livewire::test(DkpsLayar::class)
            ->assertActionHidden('tugaskan', ['butir' => DkpsButir::dariKode('28')->id])
            ->assertActionHidden('anggota', ['pokja' => 'POKJA-SDM']);
    }
}
