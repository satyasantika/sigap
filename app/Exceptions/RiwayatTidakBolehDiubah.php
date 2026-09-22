<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar bila ada yang mencoba menyunting atau menghapus baris
 * tagihan_riwayat atau narasi_versi.
 *
 * Keduanya append only karena ia jejak audit: siapa menyetujui apa dan kapan.
 * Begitu bisa disunting, ia berhenti menjadi bukti.
 */
class RiwayatTidakBolehDiubah extends RuntimeException
{
    public static function untuk(string $tabel, string $aksi): self
    {
        return new self(
            "Tabel {$tabel} bersifat append only: {$aksi} tidak diizinkan. ".
            'Riwayat adalah jejak audit; untuk membatalkan sebuah perpindahan, '.
            'catat perpindahan baru, jangan hapus yang lama.'
        );
    }
}
