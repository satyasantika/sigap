<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembangkitTagihan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pagar tagihan lewat HTTP — assertion 403 yang ditunda dari tahap 1 karena
 * obyeknya belum ada waktu itu (lihat CLAUDE.md bagian 7).
 */
class PanelTagihanTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->prodi = Prodi::firstOrFail();

        app(PembangkitTagihan::class)->untuk($this->periode);
    }

    private function pengguna(PeranPengguna $peran, ?string $kodePokja = null): User
    {
        $u = User::create([
            'name' => $peran->value, 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@tgh.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    // ---- Butir kriteria terima tahap 1 yang ditunda ---------------------

    #[Test]
    public function pimpinan_yang_mencoba_membuat_tagihan_mendapat_403(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Pimpinan))
            ->get('/panel/tagihans/'.Tagihan::first()->id.'/edit')
            ->assertForbidden();
    }

    #[Test]
    public function anggota_tidak_melihat_tagihan_milik_anggota_lain(): void
    {
        $saya = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');
        $orangLain = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');

        $punyaSaya = Tagihan::first();
        $punyaOrangLain = Tagihan::skip(1)->first();

        $punyaSaya->update(['penanggung_jawab_id' => $saya->id]);
        $punyaOrangLain->update(['penanggung_jawab_id' => $orangLain->id]);

        // Daftarnya disaring di tingkat kueri, bukan hanya barisnya dipagari
        // Policy: judul tagihan orang lain pun tidak boleh bocor.
        $this->actingAs($saya)
            ->get('/panel/tagihans')
            ->assertSuccessful()
            ->assertSee($punyaSaya->judul)
            ->assertDontSee($punyaOrangLain->judul);
    }

    // ---- Lingkup per peran ----------------------------------------------

    #[Test]
    public function koordinator_hanya_melihat_tagihan_pokjanya(): void
    {
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, 'POKJA-DIK');

        $dik = Tagihan::whereHas('pokja', fn ($q) => $q->where('kode', 'POKJA-DIK'))->first();
        $sdm = Tagihan::whereHas('pokja', fn ($q) => $q->where('kode', 'POKJA-SDM'))->first();

        $this->actingAs($koordinator)
            ->get('/panel/tagihans')
            ->assertSuccessful()
            ->assertSee($dik->judul)
            ->assertDontSee($sdm->judul);
    }

    /** @return array<string, array{string}> */
    public static function peranPembaca(): array
    {
        return [
            'ketua' => ['ketua'],
            'pimpinan' => ['pimpinan'],
            'auditor' => ['auditor'],
        ];
    }

    #[Test]
    #[DataProvider('peranPembaca')]
    public function ketua_pimpinan_dan_auditor_membaca_seluruh_tagihan(string $peran): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::from($peran)))
            ->get('/panel/tagihans')
            ->assertSuccessful()
            ->assertSee(Tagihan::first()->judul);
    }

    #[Test]
    public function auditor_tidak_bisa_menyunting_tagihan(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Auditor))
            ->get('/panel/tagihans/'.Tagihan::first()->id.'/edit')
            ->assertForbidden();
    }

    #[Test]
    public function ketua_bisa_menyunting_penugasan(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get('/panel/tagihans/'.Tagihan::first()->id.'/edit')
            ->assertSuccessful();
    }

    // ---- Tagihan Saya ---------------------------------------------------

    #[Test]
    public function tagihan_saya_hanya_menampilkan_milik_sendiri_yang_belum_selesai(): void
    {
        $saya = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');

        $aktif = Tagihan::first();
        $selesai = Tagihan::skip(1)->first();
        $orangLain = Tagihan::skip(2)->first();

        $aktif->update(['penanggung_jawab_id' => $saya->id]);
        $selesai->update(['penanggung_jawab_id' => $saya->id]);
        $selesai->forceFill(['status' => StatusTagihan::Disetujui])->save();

        $this->actingAs($saya)
            ->get('/panel/tagihan-saya')
            ->assertSuccessful()
            ->assertSee($aktif->judul)
            ->assertDontSee($selesai->judul)
            ->assertDontSee($orangLain->judul);
    }

    #[Test]
    public function halaman_detail_menampilkan_riwayat_dan_bobot(): void
    {
        $t = Tagihan::whereNotNull('elemen_id')->first();

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful()
            ->assertSee($t->judul)
            ->assertSee('Riwayat status');
    }

    #[Test]
    public function detail_tagihan_elemen_syarat_perlu_menandainya(): void
    {
        $t = Tagihan::whereHas('elemen', fn ($q) => $q->where('no', 58))->first();

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful()
            ->assertSee('SYARAT PERLU');
    }
}
