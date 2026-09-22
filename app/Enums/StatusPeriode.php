<?php

namespace App\Enums;

/**
 * Status periode akreditasi.
 *
 * `dikunci` dan `selesai` menutup seluruh aksi penulisan untuk SEMUA peran —
 * lihat App\Policies\BasePolicy. Satu-satunya jalan keluar adalah admin lewat
 * `periode.kelola`, supaya periode yang keliru dikunci masih bisa dibuka.
 */
enum StatusPeriode: string
{
    case Persiapan = 'persiapan';
    case Berjalan = 'berjalan';
    case Dikunci = 'dikunci';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Persiapan => 'Persiapan',
            self::Berjalan => 'Berjalan',
            self::Dikunci => 'Dikunci',
            self::Selesai => 'Selesai',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Persiapan => 'gray',
            self::Berjalan => 'success',
            self::Dikunci => 'warning',
            self::Selesai => 'info',
        };
    }

    /** Periode terkunci menolak seluruh penulisan isi akreditasi. */
    public function terkunci(): bool
    {
        return in_array($this, [self::Dikunci, self::Selesai], true);
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
