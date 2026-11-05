<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Filament\Resources\Penilaians\Pages\ListPenilaians;
use App\Filament\Resources\Penilaians\Schemas\PenilaianForm;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengisiDkps;
use Database\Seeders\DatabaseSeeder;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Formulir asesmen mandiri menampilkan Skor A dari rumus di samping pemilih
 * skor, supaya penilai melihat angka hitungan sebelum menetapkan skornya.
 */
class PenilaianSkorATest extends TestCase
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

    private function isi(string $kodeLembar, array $data): void
    {
        $butir = DkpsButir::dariKode(explode('.', $kodeLembar)[0]);

        app(PengisiDkps::class)->simpan($butir, $kodeLembar, $this->periode, $data,
            SumberData::Manual, $this->pengguna(PeranPengguna::Koordinator, $butir->pokja_kode));
    }

    /** Tabel 1 dan dua DTPS dari Tabel 6 — cukup untuk RK (elemen 7). */
    private function fixture(): void
    {
        foreach ([['1.1', 'UniSZA', 'internasional'], ['1.2', 'BRIN', 'nasional'],
            ['1.3', 'SMAN 1 Tasikmalaya', 'wilayah_lokal']] as [$lembar, $mitra, $tingkat]) {
            $this->isi($lembar, [
                'lembaga_mitra' => $mitra, $tingkat => 'V', 'judul_kegiatan' => 'Kegiatan '.$mitra,
                'tanggal_awal' => '01/02/2026', 'tanggal_akhir' => '01/02/2028',
            ]);
        }

        $this->isi('6', ['nama_dtps' => 'Dr. Ani', 'nidn_nidk' => '0401010101', 'jabatan_akademik' => 'Lektor',
            'doktor' => 'Universitas Pendidikan Indonesia']);
        $this->isi('6', ['nama_dtps' => 'Dr. Budi', 'nidn_nidk' => '0402020202', 'jabatan_akademik' => 'Lektor Kepala',
            'doktor' => 'Universitas Pendidikan Indonesia']);
    }

    /** Teks entri "Skor A dari rumus" pada formulir buat-penilaian untuk elemen itu. */
    private function skorAUntuk(Elemen $elemen): string
    {
        $this->actingAs($this->pengguna(PeranPengguna::Ketua));
        $teks = null;

        // Modal aksi dirender sebagai partial Livewire terpisah, jadi isinya
        // dibaca dari komponen skemanya, bukan dari HTML halaman.
        Livewire::test(ListPenilaians::class)
            ->mountAction('create')
            ->fillForm(['elemen_id' => $elemen->id])
            ->assertSchemaComponentExists('skor_a', checkComponentUsing: function (TextEntry $entri) use (&$teks) {
                $teks = (string) (($s = $entri->getState()) instanceof Htmlable ? $s->toHtml() : $s);

                return true;
            });

        return $teks;
    }

    #[Test]
    public function elemen_berumus_menampilkan_skor_a_hasil_hitungan(): void
    {
        $this->fixture();
        $this->artisan('rumus:hitung')->assertSuccessful();

        $rk = NilaiRumus::where('rumus_kode', 'RK')->firstOrFail();
        $this->assertNotNull($rk->skor_tepat);

        $teks = $this->skorAUntuk(Elemen::where('no', 7)->firstOrFail());

        $this->assertStringContainsString(sprintf('RK: %s → Skor A %s (dihitung %s)',
            number_format((float) $rk->nilai, 2, ',', '.'),
            number_format((float) $rk->skor_tepat, 2, ',', '.'),
            $rk->dihitung_pada->translatedFormat('d F Y')), $teks);
    }

    #[Test]
    public function elemen_berumus_yang_belum_dihitung(): void
    {
        $this->assertStringContainsString('RK: Belum dihitung dari DKPS',
            $this->skorAUntuk(Elemen::where('no', 7)->firstOrFail()));
    }

    #[Test]
    public function elemen_tanpa_rumus(): void
    {
        $this->assertSame('Elemen ini tidak punya rumus kuantitatif.',
            $this->skorAUntuk(Elemen::doesntHave('rumus')->orderBy('no')->firstOrFail()));
    }

    #[Test]
    public function tanpa_elemen_terpilih(): void
    {
        $this->assertSame('Pilih elemen dulu.', PenilaianForm::skorA(null));
    }
}
