<?php

namespace App\Support\Impor;

/**
 * Kunci duplikasi dinormalkan lebih dulu, kalau tidak "SK Dekan  No. 2401"
 * dan "sk dekan no 2401" dianggap dua baris berbeda — dan itulah yang
 * sebenarnya terjadi saat data ditempel dari beberapa sumber.
 */
class NormalisasiKunci
{
    public static function dari(string ...$bagian): string
    {
        return collect($bagian)
            ->map(fn (string $b) => self::satu($b))
            ->filter()
            ->join('|');
    }

    private static function satu(string $teks): string
    {
        $teks = trim($teks);
        $teks = mb_strtolower($teks);
        // Spasi ganda menjadi satu.
        $teks = preg_replace('/\s+/u', ' ', $teks) ?? $teks;
        // Tanda baca di ujung dibuang: "2401." dan "2401" sama saja.
        $teks = trim($teks, " \t\n\r\0\x0B.,;:!?-–—");

        return $teks;
    }
}
