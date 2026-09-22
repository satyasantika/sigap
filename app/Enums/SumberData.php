<?php

namespace App\Enums;

/**
 * Asal data sebuah bukti.
 *
 * `manual` bukan aib — banyak hal memang hanya ada di kertas. Tetapi ia
 * ditandai supaya dasbor bisa menunjukkan mana yang sebenarnya bisa ditarik
 * dari SIAKAD dan masih diketik tangan; itu yang pertama diragukan asesor.
 */
enum SumberData: string
{
    case Siakad = 'siakad';
    case Manual = 'manual';
    case Pddikti = 'pddikti';
    case Eksternal = 'eksternal';

    public function label(): string
    {
        return match ($this) {
            self::Siakad => 'SIAKAD',
            self::Manual => 'Diisi manual',
            self::Pddikti => 'PDDikti',
            self::Eksternal => 'Sumber eksternal',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Siakad => 'success',
            self::Manual => 'warning',
            self::Pddikti => 'info',
            self::Eksternal => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
