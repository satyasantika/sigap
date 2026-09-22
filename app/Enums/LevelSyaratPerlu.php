<?php

namespace App\Enums;

/**
 * Level pemenuhan satu syarat perlu.
 *
 * Urutannya bermakna: `lima` mencakup `tiga`. Syarat yang terpenuhi di level
 * lima tahun otomatis terpenuhi di level tiga tahun, tetapi tidak sebaliknya.
 */
enum LevelSyaratPerlu: string
{
    case Belum = 'belum';
    case Tiga = 'tiga';
    case Lima = 'lima';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum terpenuhi',
            self::Tiga => 'Terpenuhi untuk masa 3 tahun',
            self::Lima => 'Terpenuhi untuk masa 5 tahun',
        };
    }

    public function ringkas(): string
    {
        return match ($this) {
            self::Belum => 'Belum',
            self::Tiga => '3 tahun',
            self::Lima => '5 tahun',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Belum => 'danger',
            self::Tiga => 'warning',
            self::Lima => 'success',
        };
    }

    /** Memenuhi ambang tiga tahun — `lima` juga memenuhinya. */
    public function memenuhiTiga(): bool
    {
        return $this !== self::Belum;
    }

    public function memenuhiLima(): bool
    {
        return $this === self::Lima;
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $l) => [$l->value => $l->label()])->all();
    }
}
