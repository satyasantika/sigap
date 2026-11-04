<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
use App\Enums\SumberData;
use App\Livewire\LembarDkps;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengisiDkps;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lembar DKPS bergaya Excel (komponen LembarDkps) dan halaman satu tabel
 * (TabelDkpsLayar); TS 2027.
 */
class LembarDkpsTest extends TestCase
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
            'email' => $peran->value.'-'.uniqid().'@lembar.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    private function anggota(string $kodePokja): User
    {
        return $this->pengguna(PeranPengguna::Anggota, $kodePokja);
    }

    private function koordinator(string $kodePokja): User
    {
        return $this->pengguna(PeranPengguna::Koordinator, $kodePokja);
    }

    /** @return array{butirId: string, kodeLembar: string, periodeId: string} */
    private function lembar(string $kodeTabel, ?string $kodeLembar = null): array
    {
        return [
            'butirId' => DkpsButir::dariKode($kodeTabel)->id,
            'kodeLembar' => $kodeLembar ?? $kodeTabel,
            'periodeId' => $this->periode->id,
        ];
    }

    #[Test]
    public function lembar_menampilkan_kepala_bertingkat_dan_nomor_kolom_excel(): void
    {
        $this->actingAs($this->anggota('POKJA-SDM'));

        Livewire::test(LembarDkps::class, $this->lembar('8'))
            ->assertSeeHtml('Pendidikan: Pembelajaran dan Pembimbingan')
            ->assertSee('PS yang Diakreditasi')
            ->assertSeeHtml('class="sigap-sel-isian')
            ->assertSeeHtml('class="sigap-sel-hitung')
            ->assertSeeHtml('colspan="6"')
            ->assertSee('Tambah baris');
    }

    #[Test]
    public function menyimpan_baris_baru_lewat_komponen(): void
    {
        $this->actingAs($this->anggota('POKJA-SDM'));

        Livewire::test(LembarDkps::class, $this->lembar('28'))
            ->call('tambahBaris')
            ->set('baru.0.nama_dtps', 'Dr. Ani')
            ->set('baru.0.judul_artikel', 'Judul')
            ->set('baru.0.jumlah_sitasi', '12')
            ->call('simpan', 'baru:0')
            ->assertHasNoErrors()
            ->assertSet('galat', []);

        $baris = DkpsBaris::where('lembar_kode', '28')->sole();
        $this->assertSame(12, $baris->nilai('jumlah_sitasi'));
        $this->assertSame(SumberData::Manual, $baris->sumber);
    }

    #[Test]
    public function menyunting_baris_tersimpan_memperbarui_baris_yang_sama(): void
    {
        $this->actingAs($u = $this->anggota('POKJA-SDM'));
        $baris = app(PengisiDkps::class)->simpan(DkpsButir::dariKode('28'), '28', $this->periode,
            ['nama_dtps' => 'Dr. Ani', 'judul_artikel' => 'Judul', 'jumlah_sitasi' => 12], SumberData::Manual, $u);

        Livewire::test(LembarDkps::class, $this->lembar('28'))
            ->set("baris.{$baris->id}.jumlah_sitasi", '15')
            ->call('simpan', $baris->id)
            ->assertSet('disimpanTerakhir', $baris->id);

        $this->assertSame(15, $baris->refresh()->nilai('jumlah_sitasi'));
        $this->assertSame(1, DkpsBaris::where('lembar_kode', '28')->count());
    }

    #[Test]
    public function galat_validasi_tampil_di_bawah_sel(): void
    {
        $this->actingAs($this->anggota('POKJA-SDM'));

        Livewire::test(LembarDkps::class, $this->lembar('28'))
            ->call('tambahBaris')
            ->set('baru.0.nama_dtps', 'Dr. Ani')
            ->set('baru.0.judul_artikel', 'Judul')
            ->set('baru.0.jumlah_sitasi', '-3')
            ->call('simpan', 'baru:0')
            ->assertSee('Jumlah Sitasi harus bilangan bulat ≥ 0.')
            ->assertSeeHtml('sigap-sel-galat');

        $this->assertSame(0, DkpsBaris::where('lembar_kode', '28')->count());
    }

    #[Test]
    public function lembar_baris_tetap_tidak_punya_tombol_tambah_dan_menampilkan_semua_baris(): void
    {
        $this->actingAs($this->anggota('POKJA-MAWA'));

        Livewire::test(LembarDkps::class, $this->lembar('2'))
            ->assertDontSee('Tambah baris')
            ->assertSee('Tahun Akademik')
            ->assertSee('TS-2')
            ->assertSee('TS-1')
            ->assertSeeHtml('>Jumlah<');
    }

    #[Test]
    public function menyimpan_baris_tetap_lalu_jumlah_di_bawah_ikut_terhitung(): void
    {
        $this->actingAs($this->anggota('POKJA-MAWA'));

        $lembar = Livewire::test(LembarDkps::class, $this->lembar('2'))
            ->set('baris.tetap:TS.kuota', '40')
            ->call('simpan', 'tetap:TS')
            ->set('baris.tetap:TS-1.kuota', '35')
            ->call('simpan', 'tetap:TS-1');

        $this->assertSame(2, DkpsBaris::where('lembar_kode', '2')->count());
        $baris = DkpsBaris::where('lembar_kode', '2')->where('tahun_acuan', 'TS')->sole();
        $this->assertSame(40, $baris->nilai('kuota'));
        $lembar->assertSet("baris.{$baris->id}.kuota", 40)->assertSeeHtml('>75<');
    }

    #[Test]
    public function koordinator_pokja_lain_tidak_bisa_menyimpan(): void
    {
        $this->actingAs($this->koordinator('POKJA-DIK'));

        Livewire::test(LembarDkps::class, $this->lembar('7'))
            ->assertDontSee('Tambah baris')
            ->assertDontSeeHtml('class="sigap-sel-isian')
            ->call('tambahBaris')
            ->assertForbidden();
    }

    #[Test]
    public function periode_terkunci_membuat_lembar_hanya_baca(): void
    {
        $this->actingAs($this->anggota('POKJA-SDM'));
        $this->periode->forceFill(['status' => StatusPeriode::Dikunci])->save();

        Livewire::test(LembarDkps::class, $this->lembar('28'))
            ->assertDontSee('Tambah baris')
            ->call('tambahBaris')
            ->assertForbidden();
    }

    #[Test]
    public function baris_lama_yang_tidak_cocok_skema_ditampilkan_terpisah(): void
    {
        $this->actingAs($this->anggota('POKJA-SDM'));
        $lama = DkpsBaris::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'dkps_butir_id' => DkpsButir::dariKode('28')->id, 'lembar_kode' => '28',
            'tahun_acuan' => 'TS', 'data' => ['penulis' => 'Dr. Lama', 'sitasi' => '7'],
            'sumber' => SumberData::Manual,
        ]);

        Livewire::test(LembarDkps::class, $this->lembar('28'))
            ->assertSee('Baris lama yang perlu dipindahkan')
            ->assertSee('Dr. Lama')
            ->assertNotSet("baris.{$lama->id}", ['penulis' => 'Dr. Lama', 'sitasi' => '7'])
            ->call('hapusBaris', $lama->id)
            ->assertDontSee('Baris lama yang perlu dipindahkan');

        $this->assertSoftDeleted($lama);
    }

    #[Test]
    public function halaman_tabel_terbuka_dengan_tab_lembar_dan_jendela(): void
    {
        $this->actingAs($this->anggota('POKJA-MUTU'));

        $this->get('/panel/dkps/tabel/1?lembar=1.2')
            ->assertOk()
            ->assertSee('Tabel 1 — Kerjasama Tridharma Perguruan Tinggi')
            ->assertSee('Bagian 1 - Kerjasama Bidang Pendidikan')
            ->assertSee('Bagian 2 - Kerjasama Bidang Penelitian')
            ->assertSee('TS-2 s.d. TS (')
            ->assertSee('POKJA-MUTU')
            ->assertSee('Impor tempel');

        $this->get('/panel/dkps/tabel/99')->assertNotFound();
        $this->get('/panel/dkps/tabel/1?lembar=9.9')->assertNotFound();
    }

    #[Test]
    public function tautkan_bukti_dari_baris(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->actingAs($u = $this->anggota('POKJA-SDM'));
        $baris = app(PengisiDkps::class)->simpan(DkpsButir::dariKode('28'), '28', $this->periode,
            ['nama_dtps' => 'Dr. Ani', 'judul_artikel' => 'Judul', 'jumlah_sitasi' => 12], SumberData::Manual, $u);

        Livewire::test(LembarDkps::class, $this->lembar('28'))
            ->call('bukaBukti', $baris->id)
            ->assertSet('buktiUntuk', $baris->id)
            ->set('buktiUrl', 'https://drive.google.com/file/d/XYZ/view')
            ->set('buktiJudul', 'Tangkapan Google Scholar')
            ->set('buktiTanggal', now()->subMonth()->toDateString())
            ->call('tautkanBukti')
            ->assertHasNoErrors()
            ->assertSet('buktiUntuk', null);

        $this->assertSame(1, $baris->bukti()->count());
    }
}
