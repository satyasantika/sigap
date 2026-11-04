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

    // ---- Pembagi nol ----------------------------------------------------

    #[Test]
    public function pembagi_nol_ditolak(): void
    {
        $k = $this->kalkulator();

        foreach ([fn () => $k->presma(1, 1, 1, 0), fn () => $k->rgp(1, 0, 0, 0, 0)] as $panggil) {
            try {
                $panggil();
                $this->fail('Pembagi nol seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
