<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Filament\Pages\TabelDkpsLayar;
use App\Filament\Pages\VariabelDkpsLayar;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengisiDkps;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman Variabel DKPS: katalog variabel beserta nilainya pada periode aktif.
 */
class VariabelDkpsLayarTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->periode->forceFill(['ts_tahun' => 2027])->save();
        $this->prodi = Prodi::firstOrFail();
    }

    private function pengguna(PeranPengguna $peran, ?string $kodePokja = null): User
    {
        $u = User::factory()->peran($peran)->create([
            'prodi_id' => $this->prodi->id, 'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    /** Tiga kerja sama bidang pendidikan di lembar 1.1. */
    private function kerjaSamaPendidikan(): void
    {
        $butir = DkpsButir::dariKode('1');
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, $butir->pokja_kode);

        foreach (['UniSZA', 'UPI', 'UNY'] as $mitra) {
            app(PengisiDkps::class)->simpan($butir, '1.1', $this->periode, [
                'lembaga_mitra' => $mitra, 'nasional' => 'V', 'judul_kegiatan' => 'Kegiatan '.$mitra,
                'tanggal_awal' => '01/02/2026', 'tanggal_akhir' => '01/02/2028',
            ], SumberData::Manual, $koordinator);
        }
    }

    /** Sel tabel yang berisi teks persis itu, tanpa memedulikan spasi di sekitarnya. */
    private function sel(string $teks): string
    {
        return '#<td[^>]*>\s*'.preg_quote($teks, '#').'\s*</td>#';
    }

    #[Test]
    public function koordinator_pokja_data_melihat_halaman_dan_nilai(): void
    {
        $this->kerjaSamaPendidikan();

        $html = $this->actingAs($this->pengguna(PeranPengguna::Koordinator, 'POKJA-DATA'))
            ->get(VariabelDkpsLayar::getUrl())
            ->assertOk()
            ->assertSee('Variabel DKPS')
            ->assertSee('N_KERJASAMA_PENDIDIKAN')
            ->getContent();

        // Baris N_KERJASAMA_PENDIDIKAN: nilai 3 (bulat, tanpa desimal) dan 3 baris.
        preg_match('#<tr[^>]*>(?:(?!</tr>).)*N_KERJASAMA_PENDIDIKAN(?:(?!</tr>).)*</tr>#s', $html, $baris);
        $this->assertNotEmpty($baris, 'Baris N_KERJASAMA_PENDIDIKAN tidak ditemukan.');
        $this->assertMatchesRegularExpression($this->sel('3'), $baris[0]);
    }

    #[Test]
    public function variabel_kosong_tampil_strip_dengan_catatan(): void
    {
        $html = $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get(VariabelDkpsLayar::getUrl())
            ->assertOk()
            ->getContent();

        preg_match('#<tr[^>]*>(?:(?!</tr>).)*N_KERJASAMA_PENDIDIKAN(?:(?!</tr>).)*</tr>#s', $html, $baris);
        $this->assertNotEmpty($baris);
        $this->assertStringContainsString('—', $baris[0]);
        $this->assertStringContainsString('text-gray-500', $baris[0]);
    }

    #[Test]
    public function auditor_ditolak(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Auditor))
            ->get(VariabelDkpsLayar::getUrl())
            ->assertForbidden();
    }

    #[Test]
    public function anggota_di_luar_pokja_data_ditolak(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK'))
            ->get(VariabelDkpsLayar::getUrl())
            ->assertForbidden();
    }

    #[Test]
    public function tautan_lembar_mengarah_ke_tabel_dkps(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get(VariabelDkpsLayar::getUrl())
            ->assertOk()
            ->assertSee(TabelDkpsLayar::urlUntuk('1', '1.1'), false);
    }
}
