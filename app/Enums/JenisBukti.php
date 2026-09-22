<?php

namespace App\Enums;

enum JenisBukti: string
{
    case Berkas = 'berkas';
    case Tautan = 'tautan';

    public function label(): string
    {
        return match ($this) {
            self::Berkas => 'Berkas terunggah',
            self::Tautan => 'Tautan',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Berkas => 'primary',
            self::Tautan => 'info',
        };
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $j) => [$j->value => $j->label()])->all();
    }
}
