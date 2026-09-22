<?php

namespace App\Enums;

/**
 * Keabsahan bukti — sumbu kedua, dinilai MANUSIA.
 *
 * Mesin bisa memastikan sebuah tautan terbuka; ia tidak bisa memastikan SK
 * yang dibuka itu benar menopang klaim elemennya. Karena itu ketua atau
 * koordinator pokja yang memutuskan.
 *
 * `meragukan` dan `tidak_sah` MENUNTUT catatan: menolak tanpa alasan membuat
 * pengunggah mengulang kesalahan yang sama.
 */
enum ValidasiBukti: string
{
    case BelumDivalidasi = 'belum_divalidasi';
    case Sah = 'sah';
    case Meragukan = 'meragukan';
    case TidakSah = 'tidak_sah';

    public function label(): string
    {
        return match ($this) {
            self::BelumDivalidasi => 'Belum divalidasi',
            self::Sah => 'Sah',
            self::Meragukan => 'Meragukan',
            self::TidakSah => 'Tidak sah',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::BelumDivalidasi => 'gray',
            self::Sah => 'success',
            self::Meragukan => 'warning',
            self::TidakSah => 'danger',
        };
    }

    public function butuhCatatan(): bool
    {
        return in_array($this, [self::Meragukan, self::TidakSah], true);
    }

    public function bolehDisetujui(): bool
    {
        return $this === self::Sah;
    }

    public function alasanPenolakan(): string
    {
        return match ($this) {
            self::Sah => '',
            self::BelumDivalidasi => 'keabsahannya belum divalidasi',
            self::Meragukan => 'keabsahannya diragukan',
            self::TidakSah => 'dinyatakan tidak sah',
        };
    }

    /** Antrean validasi berisi yang belum diputuskan dan yang diragukan. */
    public static function perluDitinjau(): array
    {
        return [self::BelumDivalidasi, self::Meragukan];
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $v) => [$v->value => $v->label()])->all();
    }
}
