<?php

namespace App\Services;

use App\Models\Periode;
use InvalidArgumentException;

/**
 * SATU-SATUNYA tempat aritmetika tahun boleh terjadi di seluruh aplikasi.
 *
 * aturan proyek 3: TS adalah parameter, bukan tahun yang ditulis mati.
 * Tidak boleh ada `2026` atau `2027` di dalam kueri, migrasi, atau logika —
 * semuanya diturunkan dari `periode.ts_tahun` lewat kelas ini. Ada uji yang
 * menelusuri seluruh app/ dan database/migrations/ untuk memastikannya.
 *
 * Alasannya bukan kerapian: akreditasi berikutnya memakai TS yang berbeda, dan
 * satu angka tahun yang tertinggal di sebuah kueri akan menghasilkan laporan
 * yang tampak wajar tetapi menghitung jendela data yang salah.
 */
class JendelaTs
{
    public const SAAT_TS = 'saat TS';

    public const TS2_SD_TS = 'TS-2 s.d. TS';

    public const TS4_SD_TS2 = 'TS-4 s.d. TS-2';

    public const LIMA_TAHUN = '5 tahun terakhir';

    /**
     * @return array{int, int} [tahun awal, tahun akhir] — keduanya inklusif
     */
    public static function rentang(Periode $periode, string $jendela): array
    {
        $ts = (int) $periode->ts_tahun;

        return match ($jendela) {
            self::SAAT_TS => [$ts, $ts],
            self::TS2_SD_TS => [$ts - 2, $ts],
            self::TS4_SD_TS2 => [$ts - 4, $ts - 2],
            self::LIMA_TAHUN => [$ts - 4, $ts],
            default => throw new InvalidArgumentException(
                "Jendela data `{$jendela}` tidak dikenal. Yang sah: "
                .implode(', ', self::semua()).'.'
            ),
        };
    }

    /** @return array<int, string> */
    public static function semua(): array
    {
        return [self::SAAT_TS, self::TS2_SD_TS, self::TS4_SD_TS2, self::LIMA_TAHUN];
    }

    /** Daftar tahun di dalam jendela, untuk pilihan di formulir. */
    public static function tahun(Periode $periode, string $jendela): array
    {
        [$awal, $akhir] = self::rentang($periode, $jendela);

        return range($awal, $akhir);
    }

    /**
     * Tahun sesungguhnya dari label relatif seperti "TS-2".
     *
     * Dipakai layar isian DKPS: pengisi melihat "TS-2 (2025)" dan tahu tahun
     * mana yang diminta tanpa menghitung sendiri.
     */
    public static function tahunDariLabel(Periode $periode, string $label): int
    {
        if ($label === 'TS') {
            return (int) $periode->ts_tahun;
        }

        if (preg_match('/^TS-(\d+)$/', $label, $cocok) !== 1) {
            throw new InvalidArgumentException("Label tahun `{$label}` tidak dikenal.");
        }

        return (int) $periode->ts_tahun - (int) $cocok[1];
    }

    /** Label relatif yang sah untuk satu jendela, terurut dari yang terlama. */
    public static function labelDalamJendela(string $jendela): array
    {
        return match ($jendela) {
            self::SAAT_TS => ['TS'],
            self::TS2_SD_TS => ['TS-2', 'TS-1', 'TS'],
            self::TS4_SD_TS2 => ['TS-4', 'TS-3', 'TS-2'],
            self::LIMA_TAHUN => ['TS-4', 'TS-3', 'TS-2', 'TS-1', 'TS'],
            default => throw new InvalidArgumentException("Jendela data `{$jendela}` tidak dikenal."),
        };
    }

    /**
     * Keterangan yang dibaca pengisi, misalnya "TS-2 s.d. TS (2025–2027)".
     */
    public static function keterangan(Periode $periode, string $jendela): string
    {
        [$awal, $akhir] = self::rentang($periode, $jendela);

        return $awal === $akhir
            ? "{$jendela} ({$awal})"
            : "{$jendela} ({$awal}–{$akhir})";
    }
}
