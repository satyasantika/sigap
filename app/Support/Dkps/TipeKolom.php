<?php

namespace App\Support\Dkps;

/**
 * Tipe kolom lembar DKPS, sesuai kosakata `tipe` di data/dkps-tabel.json.
 */
enum TipeKolom: string
{
    case Teks = 'teks';
    case Angka = 'angka';
    case Desimal = 'desimal';
    case Tanggal = 'tanggal';
    case Tahun = 'tahun';
    case Pilihan = 'pilihan';
    case Centang = 'centang';
    case Tautan = 'tautan';
    case Hitung = 'hitung';

    public function label(): string
    {
        return match ($this) {
            self::Teks => 'Teks',
            self::Angka => 'Bilangan bulat',
            self::Desimal => 'Angka desimal',
            self::Tanggal => 'Tanggal',
            self::Tahun => 'Tahun',
            self::Pilihan => 'Pilihan',
            self::Centang => 'Centang',
            self::Tautan => 'Tautan',
            self::Hitung => 'Dihitung otomatis',
        };
    }

    /** Kolom hitung tidak diisi dan tidak disimpan; nilainya diturunkan saat tampil. */
    public function isian(): bool
    {
        return $this !== self::Hitung;
    }
}
