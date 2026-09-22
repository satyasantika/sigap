<?php

namespace App\Enums;

/**
 * Tiga jenis elemen, dan pembagian ini menentukan berapa tinggi nilai
 * akreditasi bisa naik.
 *
 *   data     20 elemen, bobot 36,75 — skornya lahir dari rumus atas angka
 *            DKPS. Tidak bisa dinaikkan dengan menulis narasi lebih bagus.
 *   rubrik   30 elemen, bobot 49,75 — dinilai dengan mencocokkan keadaan
 *            terhadap deskriptor.
 *   refleksi  9 elemen, bobot 13,50 — satu di tiap kriteria, berisi evaluasi
 *            dan tindak lanjut, bukan parameter.
 *
 * Kalau seluruh rubrik dan refleksi berskor 4 sementara data berskor 3,
 * NA-nya 363,25 — hanya 2,25 di atas ambang Unggul 361. Itu sebabnya elemen
 * berjenis `data` tidak bisa diabaikan.
 */
enum JenisElemen: string
{
    case Data = 'data';
    case Rubrik = 'rubrik';
    case Refleksi = 'refleksi';

    public function label(): string
    {
        return match ($this) {
            self::Data => 'Data',
            self::Rubrik => 'Rubrik',
            self::Refleksi => 'Refleksi',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Data => 'info',
            self::Rubrik => 'primary',
            self::Refleksi => 'warning',
        };
    }

    /** Keterangan singkat untuk layar referensi. */
    public function ringkas(): string
    {
        return match ($this) {
            self::Data => 'Skor dihitung dari rumus atas angka DKPS.',
            self::Rubrik => 'Skor dinilai dengan mencocokkan keadaan terhadap deskriptor.',
            self::Refleksi => 'Berisi evaluasi dan tindak lanjut, bukan parameter.',
        };
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $j) => [$j->value => $j->label()])
            ->all();
    }
}
