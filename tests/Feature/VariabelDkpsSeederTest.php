<?php

namespace Tests\Feature;

use App\Enums\CaraBacaVariabel;
use App\Models\DkpsButir;
use App\Models\Rumus;
use App\Models\VariabelDkps;
use App\Support\Dkps\SkemaLembar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Katalog variabel DKPS adalah jembatan antara isian tabel dan rumus. Setiap
 * lembar, kolom, baris tetap, dan pilihan yang ia sebut harus benar-benar ada
 * di data/dkps-tabel.json — rujukan yang salah ketik akan terbaca sebagai nol
 * diam-diam, dan itu lebih berbahaya daripada galat.
 */
class VariabelDkpsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function skema(string $kodeLembar): SkemaLembar
    {
        // Kode tabel = bagian sebelum titik: lembar "24.1" milik Tabel 24.
        return DkpsButir::dariKode(explode('.', $kodeLembar)[0])->skema($kodeLembar);
    }

    /** @return array<int, string> */
    private function kunciKolom(SkemaLembar $skema): array
    {
        return array_column($skema->kolom(), 'kunci');
    }

    #[Test]
    public function katalog_tersemai_dan_setiap_lembar_dikenal(): void
    {
        $this->assertGreaterThanOrEqual(90, VariabelDkps::count());

        foreach (VariabelDkps::orderBy('urutan')->get() as $v) {
            $this->assertNotEmpty($v->lembar, "{$v->kode} tidak menyebut lembar.");

            foreach ($v->lembar as $kodeLembar) {
                $tabel = explode('.', $kodeLembar)[0];
                $this->assertContains($kodeLembar, DkpsButir::dariKode($tabel)->kodeLembar(), "{$v->kode}: lembar {$kodeLembar} tidak dikenal.");

                if ($v->cara === CaraBacaVariabel::Khusus) {
                    continue;
                }

                $skema = $this->skema($kodeLembar);
                $kolom = $this->kunciKolom($skema);
                $baris = array_column($skema->barisTetap(), 'kunci');
                $parameter = $v->parameter ?? [];

                if ($v->kolom !== null) {
                    $this->assertContains($v->kolom, $kolom, "{$v->kode}: kolom {$v->kolom} tidak ada di lembar {$kodeLembar}.");
                }

                if (isset($parameter['filter'])) {
                    $this->assertContains($parameter['filter']['kolom'], $kolom, "{$v->kode}: kolom saringan tidak ada di lembar {$kodeLembar}.");
                }

                if ($v->cara === CaraBacaVariabel::NilaiSel) {
                    $this->assertContains($parameter['baris'], $baris, "{$v->kode}: baris {$parameter['baris']} tidak ada di lembar {$kodeLembar}.");
                    $this->assertContains($parameter['kolom'], $kolom, "{$v->kode}: kolom {$parameter['kolom']} tidak ada di lembar {$kodeLembar}.");
                }

                if ($v->cara === CaraBacaVariabel::JumlahSel) {
                    $this->assertNotEmpty($parameter['sel'], "{$v->kode} tidak menyebut sel.");

                    foreach ($parameter['sel'] as [$kunciBaris, $kunciKolom]) {
                        $this->assertContains($kunciBaris, $baris, "{$v->kode}: baris {$kunciBaris} tidak ada di lembar {$kodeLembar}.");
                        $this->assertContains($kunciKolom, $kolom, "{$v->kode}: kolom {$kunciKolom} tidak ada di lembar {$kodeLembar}.");
                    }
                }
            }
        }
    }

    #[Test]
    public function cara_pilihan_merujuk_pilihan_yang_ada(): void
    {
        $diperiksa = 0;

        foreach (VariabelDkps::all() as $v) {
            foreach ($v->lembar as $kodeLembar) {
                $skema = $this->skema($kodeLembar);

                if ($v->cara === CaraBacaVariabel::CacahPilihan) {
                    $this->assertNotEmpty($v->parameter['nilai'], "{$v->kode} tidak menyebut nilai pilihan.");

                    foreach ($v->parameter['nilai'] as $nilai) {
                        $this->assertContains($nilai, $skema->pilihan($v->kolom), "{$v->kode}: pilihan `{$nilai}` tidak ada di kolom {$v->kolom}.");
                        $diperiksa++;
                    }
                }

                // Saringan berdasarkan nilai pilihan tunduk pada aturan yang sama.
                foreach ($v->parameter['filter']['nilai'] ?? [] as $nilai) {
                    $kolomSaring = $v->parameter['filter']['kolom'];
                    $this->assertContains($nilai, $skema->pilihan($kolomSaring), "{$v->kode}: pilihan saringan `{$nilai}` tidak ada di kolom {$kolomSaring}.");
                    $diperiksa++;
                }
            }
        }

        $this->assertGreaterThan(0, $diperiksa, 'Katalog seharusnya memuat variabel bercara cacah_pilihan.');
    }

    #[Test]
    public function setiap_rumus_di_katalog_ada_di_rumus_json(): void
    {
        $kodeRumus = Rumus::pluck('kode')->all();

        foreach (VariabelDkps::all() as $v) {
            foreach ($v->rumus as $kode) {
                $this->assertContains($kode, $kodeRumus, "{$v->kode} menyebut rumus {$kode} yang tidak ada.");
            }
        }
    }
}
