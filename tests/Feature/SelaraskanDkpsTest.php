<?php

namespace Tests\Feature;

use App\Enums\JenisTagihan;
use App\Enums\SumberData;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembangkitTagihan;
use App\Services\PengisiDkps;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `dkps:selaraskan` membereskan peninggalan sebelum DKPS 3.0: baris yang
 * lembarnya tak dikenal tabelnya, dan tagihan DKPS yang masih di POKJA-DATA.
 */
class SelaraskanDkpsTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
    }

    private function pokja(string $kode): string
    {
        return Pokja::where('periode_id', $this->periode->id)->where('kode', $kode)->value('id');
    }

    private function tagihanDkps(string $kodeTabel): ?Tagihan
    {
        return Tagihan::where('periode_id', $this->periode->id)
            ->where('jenis', JenisTagihan::DataDkps)
            ->where('dkps_butir_id', DkpsButir::dariKode($kodeTabel)->id)
            ->first();
    }

    #[Test]
    public function baris_lama_tabel_24_dipindah_ke_lembar_pertama_dan_tampil(): void
    {
        // Sandi contoh seeder diganti supaya cek-kesiapan hanya gagal karena
        // baris yatim.
        User::query()->get()->each(
            fn (User $u) => $u->forceFill(['password' => Hash::make('sandi-yang-panjang-dan-acak')])->save()
        );

        $butir = DkpsButir::dariKode('24');
        $baris = DkpsBaris::create([
            'prodi_id' => Prodi::firstOrFail()->id, 'periode_id' => $this->periode->id,
            'dkps_butir_id' => $butir->id, 'tahun_acuan' => 'TS',
            'data' => ['sangat_baik' => 40], 'sumber' => SumberData::Manual,
        ]);

        // Hasil migrasi lama: lembar_kode = kode tabel, padahal Tabel 24 kini
        // hanya punya lembar 24.1 dan 24.2.
        DkpsBaris::withoutGlobalScopes()->whereKey($baris->id)->update(['lembar_kode' => '24']);
        $this->assertCount(0, app(PengisiDkps::class)->barisLembar($butir, '24.1', $this->periode));

        $this->artisan('sigap:cek-kesiapan')
            ->expectsOutputToContain('Tidak ada baris DKPS dengan lembar_kode asing')
            ->assertFailed();

        $this->artisan('dkps:selaraskan')
            ->expectsOutputToContain('Diselaraskan: 1')
            ->expectsOutputToContain('Lembar asing dibetulkan: 1')
            ->assertSuccessful();

        $this->assertSame('24.1', $baris->fresh()->lembar_kode);
        $this->assertSame([$baris->id],
            app(PengisiDkps::class)->barisLembar($butir, '24.1', $this->periode)->modelKeys());

        $this->artisan('dkps:selaraskan')
            ->expectsOutputToContain('Lembar asing dibetulkan: 0')
            ->assertSuccessful();

        $this->artisan('sigap:cek-kesiapan')->assertSuccessful();
    }

    #[Test]
    public function tagihan_dkps_dipindah_ke_pokja_pemilik_dan_yang_kurang_dibuat(): void
    {
        app(PembangkitTagihan::class)->untuk($this->periode);

        // Keadaan periode yang dibangkitkan sebelum DKPS 3.0: tagihan Tabel 7
        // masih milik POKJA-DATA dengan judul lama, dan Tabel 4 (baru di 3.0)
        // belum punya tagihan.
        $this->tagihanDkps('7')->forceFill(['pokja_id' => $this->pokja('POKJA-DATA'), 'judul' => 'Isi Tabel 6 — lama'])->save();
        $this->tagihanDkps('4')->forceDelete();
        $jumlahAwal = Tagihan::count();

        $this->artisan('dkps:selaraskan')
            ->expectsOutputToContain('Tagihan DKPS diselaraskan: 1')
            ->expectsOutputToContain('Tagihan DKPS ditambahkan: 1')
            ->assertSuccessful();

        $t7 = $this->tagihanDkps('7');
        $this->assertSame($this->pokja('POKJA-SDM'), $t7->pokja_id);
        $this->assertSame('Isi Tabel 7 — '.DkpsButir::dariKode('7')->nama, $t7->judul);

        $t4 = $this->tagihanDkps('4');
        $this->assertNotNull($t4);
        $this->assertSame($this->pokja(DkpsButir::dariKode('4')->pokja_kode), $t4->pokja_id);
        $this->assertSame($jumlahAwal + 1, Tagihan::count());

        // Idempoten.
        $this->artisan('dkps:selaraskan')
            ->expectsOutputToContain('Tagihan DKPS diselaraskan: 0')
            ->expectsOutputToContain('Tagihan DKPS ditambahkan: 0')
            ->assertSuccessful();
    }

    #[Test]
    public function periode_tanpa_tagihan_tidak_dibangkitkan_diam_diam(): void
    {
        $this->assertSame(0, Tagihan::count());

        $this->artisan('dkps:selaraskan')
            ->expectsOutputToContain('Tagihan DKPS ditambahkan: 0')
            ->assertSuccessful();

        $this->assertSame(0, Tagihan::count());
    }
}
