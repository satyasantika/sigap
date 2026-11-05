<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Baris DKPS yang tahunnya (dari kolom tanggal/tahun) jatuh di luar jendela
 * data tabelnya. Ditolak, bukan diam-diam disimpan: baris semacam itu akan
 * ikut dihitung di laporan padahal asesor tidak memintanya.
 */
class TahunDiLuarJendela extends InvalidArgumentException
{
    public static function untuk(int $tahun, string $keteranganJendela): self
    {
        return new self("Tahun {$tahun} di luar jendela data {$keteranganJendela}.");
    }
}
