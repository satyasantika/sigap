<?php

namespace App\Enums;

/**
 * Cara membaca satu variabel katalog dari baris DKPS (data/variabel-dkps.json).
 *
 * Sengaja enum kecil, bukan ekspresi bebas: pembaca variabel menulis satu
 * metode per cara, sehingga tidak ada pengurai teks yang bisa salah diam-diam.
 * Yang tidak muat di salah satu cara ditandai `khusus` dan dihitung di kode.
 */
enum CaraBacaVariabel: string
{
    case CacahBaris = 'cacah_baris';
    case CacahCentang = 'cacah_centang';
    case CacahPilihan = 'cacah_pilihan';
    case CacahTerisi = 'cacah_terisi';
    case CacahUnik = 'cacah_unik';
    case JumlahKolom = 'jumlah_kolom';
    case RataKolom = 'rata_kolom';
    case NilaiSel = 'nilai_sel';
    case JumlahSel = 'jumlah_sel';
    case Khusus = 'khusus';

    public function label(): string
    {
        return match ($this) {
            self::CacahBaris => 'Cacah baris',
            self::CacahCentang => 'Cacah baris tercentang',
            self::CacahPilihan => 'Cacah baris berpilihan tertentu',
            self::CacahTerisi => 'Cacah baris berkolom terisi',
            self::CacahUnik => 'Cacah nilai unik',
            self::JumlahKolom => 'Jumlah kolom',
            self::RataKolom => 'Rata-rata kolom',
            self::NilaiSel => 'Nilai satu sel',
            self::JumlahSel => 'Jumlah beberapa sel',
            self::Khusus => 'Dihitung khusus',
        };
    }
}
