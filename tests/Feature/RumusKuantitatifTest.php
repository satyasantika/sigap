<?php

namespace Tests\Feature;

use App\Services\KalkulatorRumus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Patokan rumus kuantitatif AK 3.0 yang baru, dihitung tangan dari contoh
 * kertas kerja AK IAPSK 3.0.
 *
 * Semua metode yang diuji di sini murni (tanpa basis data). `skorTepat`
 * selalu DIPOTONG dua desimal, bukan dibulatkan; patokan yang berbeda dari
 * hitungan bulat ditandai di komentarnya.
 */
class RumusKuantitatifTest extends TestCase
{
    private function kalkulator(): KalkulatorRumus
    {
        return app(KalkulatorRumus::class);
    }

    // ---- PRESMA — elemen 12 ---------------------------------------------

    #[Test]
    public function presma_contoh_kertas_kerja(): void
    {
        $hasil = $this->kalkulator()->presma(13, 6, 15, 7521);

        // 2 + RI/a + RN/b − RI·RN/(a·b) = 2,3561…, DIPOTONG menjadi 2,35
        // (brief menulis 2,36 hasil pembulatan).
        $this->assertEqualsWithDelta(2.35, $hasil->skorTepat, 0.001);
        $this->assertSame(2, $hasil->skor);
    }

    #[Test]
    public function presma_internasional_cukup_memberi_empat(): void
    {
        $hasil = $this->kalkulator()->presma(1, 10, 5, 200);

        $this->assertEqualsWithDelta(4.0, $hasil->skorTepat, 0.01);
        $this->assertSame(4, $hasil->skor);
    }

    #[Test]
    public function presma_tanpa_prestasi_memberi_satu(): void
    {
        $hasil = $this->kalkulator()->presma(0, 0, 0, 200);

        $this->assertEqualsWithDelta(1.0, $hasil->skorTepat, 0.01);
        $this->assertEqualsWithDelta(0.0, $hasil->nilai, 0.0001);
    }

    // ---- PKIM — elemen 13 -----------------------------------------------

    #[Test]
    public function pkim_ambang(): void
    {
        $k = $this->kalkulator();

        $this->assertSame(1, $k->pkim(5, 43, 21, 31, 7521)->skor);
        $this->assertSame(4, $k->pkim(10, 10, 0, 0, 100)->skor);
        $this->assertSame(3, $k->pkim(15, 0, 0, 0, 100)->skor);
        $this->assertSame(2, $k->pkim(10, 0, 0, 0, 100)->skor);
    }

    #[Test]
    public function pkim_ambang_dibandingkan_pada_rasio_mentah(): void
    {
        // 1504/7521 = 0,19997…: tampil 0,2 setelah dibulatkan, tetapi rasio
        // mentahnya di bawah 0,2, jadi skornya 3, bukan 4.
        $hasil = $this->kalkulator()->pkim(1504, 0, 0, 0, 7521);

        $this->assertEqualsWithDelta(0.2, $hasil->nilai, 0.0001);
        $this->assertSame(3, $hasil->skor);
    }

    // ---- BKD — elemen 18 ------------------------------------------------

    #[Test]
    public function bkd_pita(): void
    {
        $k = $this->kalkulator();

        foreach ([[14, 4.0], [9, 2.0], [17, 2.0], [6.5, 1.0], [18, 1.0], [5, 1.0], [19, 1.0]] as [$x, $harapan]) {
            $this->assertEqualsWithDelta($harapan, $k->bkd($x)->skorTepat, 0.01, "BKD {$x}");
        }
    }

    // ---- RGP — elemen 22 ------------------------------------------------

    #[Test]
    public function rgp_contoh_kertas_kerja(): void
    {
        $k = $this->kalkulator();

        $this->assertEqualsWithDelta(4.0, $k->rgp(27, 10, 19, 20, 27)->skorTepat, 0.01);
        // Rasio 2,5 / 0,48 / 0,28 / 0,4 → 3 + rata(0,6; 0,7; 0,8).
        $this->assertEqualsWithDelta(3.70, $k->rgp(25, 10, 12, 7, 10)->skorTepat, 0.01);
        // Rasio 2 / 0,2 / 0,1 / 0,2 → 2 + 0,2/0,3 = 2,666…, DIPOTONG menjadi
        // 2,66 (brief menulis 2,67 hasil pembulatan).
        $this->assertEqualsWithDelta(2.66, $k->rgp(20, 10, 4, 2, 4)->skorTepat, 0.001);
    }

    #[Test]
    public function rgp_dua_per_bidang_studi_dengan_rasio_lebih_baik_tidak_turun_ke_dua(): void
    {
        // BS 2, MD 0,5, MU 0,5, GP 0,6: kertas kerja E348 jatuh ke 2 karena
        // MU ≥ b dan GP ≥ c; keputusan SIGAP memberi 3 (rasio lebih baik
        // tidak boleh menurunkan skor).
        $hasil = $this->kalkulator()->rgp(20, 10, 10, 10, 12);

        $this->assertEqualsWithDelta(3.0, $hasil->skorTepat, 0.001);
        $this->assertSame(3, $hasil->skor);
    }

    // ---- DANA — elemen 25 -----------------------------------------------

    #[Test]
    public function dana_contoh_kertas_kerja(): void
    {
        $hasil = $this->kalkulator()->dana(437281818, 4761381820, 1145616667, 7521, 28);

        $this->assertEqualsWithDelta(3.0, $hasil->skorTepat, 0.01);
        $this->assertSame(1, $hasil->komponen['skor_op']);
        $this->assertSame(4, $hasil->komponen['skor_pd']);
        $this->assertSame(4, $hasil->komponen['skor_pkm']);
    }

    #[Test]
    public function dana_ambang_tepat_di_batas(): void
    {
        $k = $this->kalkulator();

        $this->assertEqualsWithDelta(4.0, $k->dana(18e6, 10e6, 5e6, 1, 1)->skorTepat, 0.01);
        $this->assertEqualsWithDelta(3.0, $k->dana(10e6, 7e6, 3e6, 1, 1)->skorTepat, 0.01);
    }

    // ---- PDIPPKM, PMKI — elemen 32 -------------------------------------

    #[Test]
    public function pdippkm_ambang(): void
    {
        $k = $this->kalkulator();

        $penuh = $k->pdippkm(28, 28);
        $this->assertEqualsWithDelta(100.0, $penuh->nilai, 0.001);
        $this->assertSame(4, $penuh->skor);

        $tiga = $k->pdippkm(10, 28);
        $this->assertEqualsWithDelta(35.71, $tiga->nilai, 0.001);
        $this->assertSame(3, $tiga->skor);

        $dua = $k->pdippkm(3, 28);
        $this->assertEqualsWithDelta(10.71, $dua->nilai, 0.001);
        $this->assertSame(2, $dua->skor);

        $this->assertSame(1, $k->pdippkm(2, 28)->skor);
    }

    #[Test]
    public function pmki_tangga_kertas_kerja(): void
    {
        $k = $this->kalkulator();

        $penuh = $k->pmki(14, 13);
        $this->assertEqualsWithDelta(1.0, $penuh->nilai, 0.0001);
        $this->assertSame(4, $penuh->skor);
        $this->assertNotNull($penuh->catatan);

        $this->assertSame(3, $k->pmki(2, 13)->skor);
        $this->assertSame(2, $k->pmki(1, 13)->skor);
        $this->assertSame(2, $k->pmki(0, 13)->skor);
    }

    // ---- RPPL — elemen 36 -----------------------------------------------

    #[Test]
    public function rppl_ambang(): void
    {
        $k = $this->kalkulator();

        foreach ([[5, 4], [3.5, 3], [2, 2], [1.9, 1]] as [$x, $harapan]) {
            $hasil = $k->rppl($x);
            $this->assertSame($harapan, $hasil->skor, "RPPL {$x}");
            $this->assertEqualsWithDelta((float) $harapan, $hasil->skorTepat, 0.001, "RPPL {$x}");
        }
    }

    // ---- PMTK — elemen 40 -----------------------------------------------

    #[Test]
    public function pmtk_contoh_kertas_kerja(): void
    {
        $k = $this->kalkulator();

        $contoh = $k->pmtk(104, 173, 102, 169);
        $this->assertEqualsWithDelta(0.9783, $contoh->nilai, 0.0001);
        $this->assertSame(4, $contoh->skor);

        // PMTK 0,3 → 1 + 6 × 0,3 = 2,8.
        $rendah = $k->pmtk(100, 100, 30, 30);
        $this->assertEqualsWithDelta(2.8, $rendah->skorTepat, 0.001);
        $this->assertSame(2, $rendah->skor);
    }

    // ---- RMKCPL — elemen 45 ---------------------------------------------

    #[Test]
    public function rmkcpl_ambang(): void
    {
        $k = $this->kalkulator();

        foreach ([[13, 4], [2, 3], [1, 2], [0, 1]] as [$mk, $harapan]) {
            $this->assertSame($harapan, $k->rmkcpl($mk, 13)->skor, "RMKCPL {$mk}/13");
        }
    }

    // ---- RPEN, RPKM — elemen 49, 54 -------------------------------------

    #[Test]
    public function rpen_contoh_kertas_kerja(): void
    {
        $hasil = $this->kalkulator()->rpen(42, 14, 28, 28);

        $this->assertSame('RPEN', $hasil->kode);
        $this->assertEqualsWithDelta(4.0, $hasil->skorTepat, 0.001);
        $this->assertEqualsWithDelta(0.5, $hasil->nilai, 0.0001);
    }

    #[Test]
    public function rpen_hanya_dana_pt(): void
    {
        $k = $this->kalkulator();

        // RL = 60/3/28 = 0,7142…; 2·RL/c = 1,4285…, DIPOTONG menjadi 1,42
        // (brief menulis 1,43 hasil pembulatan).
        $this->assertEqualsWithDelta(1.42, $k->rpen(0, 0, 60, 28)->skorTepat, 0.001);
        $this->assertEqualsWithDelta(1.0, $k->rpen(0, 0, 28, 28)->skorTepat, 0.001);
    }

    #[Test]
    public function rpen_campuran(): void
    {
        // RI = 0,0238…, RN = 0,1190… → 2,6841…, dipotong 2,68.
        $this->assertEqualsWithDelta(2.68, $this->kalkulator()->rpen(2, 10, 28, 28)->skorTepat, 0.001);
    }

    #[Test]
    public function rpkm_memakai_kode_sendiri(): void
    {
        $hasil = $this->kalkulator()->rpkm(17, 3, 64, 28);

        $this->assertSame('RPKM', $hasil->kode);
        $this->assertEqualsWithDelta(4.0, $hasil->skorTepat, 0.001);
    }

    // ---- RPUB — elemen 50 -----------------------------------------------

    private function publikasiNol(): array
    {
        return array_fill_keys(['na1', 'na2', 'na3', 'na4', 'nb1', 'nb2', 'nb3', 'nc1', 'nc2', 'nc3', 'nd1', 'nd2'], 0);
    }

    #[Test]
    public function rpub_contoh_kertas_kerja(): void
    {
        $hasil = $this->kalkulator()->rpub([
            'na1' => 11, 'na2' => 70, 'na3' => 16, 'na4' => 35,
            'nb1' => 3, 'nb2' => 15, 'nb3' => 12,
            'nc1' => 15, 'nc2' => 3, 'nc3' => 4,
            'nd1' => 74, 'nd2' => 4,
        ], 28);

        $this->assertSame('RPUB', $hasil->kode);
        $this->assertEqualsWithDelta(4.0, $hasil->skorTepat, 0.001);
    }

    #[Test]
    public function rpub_nd_ikut_dihitung(): void
    {
        // Hanya ND1: RN = 28/28 = 1 = b → 3 + RI/a = 3. Tanpa ND, skornya 1.
        $hasil = $this->kalkulator()->rpub(['nd1' => 28] + $this->publikasiNol(), 28);

        $this->assertEqualsWithDelta(1.0, $hasil->komponen['rn'], 0.0001);
        $this->assertEqualsWithDelta(3.0, $hasil->skorTepat, 0.001);
        $this->assertSame(3, $hasil->skor);
    }

    #[Test]
    public function rpub_kunci_hilang_ditolak(): void
    {
        $publikasi = $this->publikasiNol();
        unset($publikasi['nc2']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('nc2');

        $this->kalkulator()->rpub($publikasi, 28);
    }

    // ---- Pembagi nol ----------------------------------------------------

    #[Test]
    public function pembagi_nol_ditolak(): void
    {
        $k = $this->kalkulator();

        $pemanggil = [
            fn () => $k->presma(1, 1, 1, 0),
            fn () => $k->rgp(1, 0, 0, 0, 0),
            fn () => $k->pdippkm(1, 0),
            fn () => $k->pmki(1, 0),
            fn () => $k->pmtk(0, 0, 0, 0),
            fn () => $k->rmkcpl(1, 0),
            fn () => $k->rpen(1, 1, 1, 0),
            fn () => $k->rpkm(1, 1, 1, 0),
            fn () => $k->rpub($this->publikasiNol(), 0),
        ];

        foreach ($pemanggil as $panggil) {
            try {
                $panggil();
                $this->fail('Pembagi nol seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
