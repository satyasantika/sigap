<?php

namespace Tests\Feature;

use App\Services\KalkulatorRumus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pola interpolasi RI/RN/RW yang dipakai lima elemen (E7 bagian A.2, E12,
 * E49, E50, E54), dengan ambang contoh E12 (a = 0,005; b = 0,05; c = 0,1).
 *
 * Uji ini mengunci kelima pita dan kedua perbedaan Buku 4 dengan kertas
 * kerja yang dicatat di spec §2.1: koefisien pita 2–3 adalah 1 (bukan 2),
 * dan pita bawah diinterpolasi max(1, 2·RW/c) (bukan tetap 1).
 *
 * Metodenya privat, jadi dipanggil lewat refleksi supaya uji ini berdiri
 * sendiri tanpa bergantung pada salah satu rumus pemakainya.
 */
class RumusTigaTingkatTest extends TestCase
{
    private function skor(float $ri, float $rn, float $rw): float
    {
        return (new ReflectionMethod(KalkulatorRumus::class, 'skorTigaTingkat'))
            ->invoke(app(KalkulatorRumus::class), $ri, $rn, $rw, 0.005, 0.05, 0.1);
    }

    public static function pita(): array
    {
        return [
            'RI ≥ a → 4' => [0.01, 0, 0, 4.0],
            'RI < a, RN ≥ b → 3 + RI/a' => [0.001, 0.06, 0, 3.2],
            'contoh kertas kerja E147–E162' => [13 / 7521, 6 / 7521, 15 / 7521, 2.3561],
            'RI = 0, 0 < RN < b → pita 2–3' => [0, 0.025, 0.05, 2.5],
            'RI = 0, RN = 0, RW ≥ c → 2' => [0, 0, 0.1, 2.0],
            'pita bawah: RW = c/2 → 1' => [0, 0, 0.05, 1.0],
            'pita bawah diinterpolasi: RW = 0,08 → 1,6' => [0, 0, 0.08, 1.6],
        ];
    }

    #[Test]
    #[DataProvider('pita')]
    public function skor_mengikuti_lima_pita(float $ri, float $rn, float $rw, float $harapan): void
    {
        $this->assertEqualsWithDelta($harapan, $this->skor($ri, $rn, $rw), 0.0001);
    }

    #[Test]
    public function koefisien_pita_dua_tiga_adalah_satu(): void
    {
        // Buku 4 menulis koefisien 2 untuk RI/a; dengan itu RI = 0,9a dan
        // RN = 0,9b memberi 3,89, keluar dari pita "2–3". Kertas kerja
        // memakai 1, dan nilainya selalu di dalam [2, 3].
        $this->assertLessThanOrEqual(3.0, $this->skor(0.0045, 0.045, 0));
    }
}
