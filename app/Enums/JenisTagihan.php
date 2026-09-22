<?php

namespace App\Enums;

/**
 * Empat jenis pekerjaan pengumpulan.
 *
 *   narasi             satu per elemen (59) — naskah LED 200-600 kata
 *   bukti              satu per elemen yang meminta bukti pendukung
 *   data_dkps          satu per butir DKPS (28), dipegang POKJA-DATA
 *   perbaikan_praktik  dibuat manual oleh ketua; perubahan nyata di dunia
 *                      nyata, bukan dokumen
 *
 * Hanya `narasi` dan `bukti` yang memikul bobot. `data_dkps` dan
 * `perbaikan_praktik` berbobot 0,000 — pekerjaannya nyata, tetapi bobotnya
 * sudah terhitung lewat elemen yang dilayaninya. Menghitungnya dua kali akan
 * membuat jumlah bobot periode melebihi 100.
 */
enum JenisTagihan: string
{
    case Narasi = 'narasi';
    case Bukti = 'bukti';
    case DataDkps = 'data_dkps';
    case PerbaikanPraktik = 'perbaikan_praktik';

    public function label(): string
    {
        return match ($this) {
            self::Narasi => 'Naskah LED',
            self::Bukti => 'Pengumpulan bukti',
            self::DataDkps => 'Isian DKPS',
            self::PerbaikanPraktik => 'Perbaikan praktik',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Narasi => 'primary',
            self::Bukti => 'info',
            self::DataDkps => 'warning',
            self::PerbaikanPraktik => 'danger',
        };
    }

    /** Apakah jenis ini ikut memikul bobot elemen. */
    public function berbobot(): bool
    {
        return in_array($this, [self::Narasi, self::Bukti], true);
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $j) => [$j->value => $j->label()])
            ->all();
    }
}
