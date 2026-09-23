<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar bila ada yang mencoba menghapus baris yang harus tetap ada.
 *
 * Berbeda dari RiwayatTidakBolehDiubah: baris-baris ini BOLEH disunting —
 * level syarat perlu berubah sepanjang periode, impor batch ditandai
 * dibatalkan, sesi impersonasi ditutup saat penyamaran berakhir. Yang tidak
 * boleh hanyalah hilangnya.
 *
 * Penolakan dipilih daripada soft delete karena lebih keras dan lebih jujur:
 * tidak ada tombol yang bisa menyembunyikan barisnya, dan tidak ada kolom
 * `deleted_at` yang menyiratkan ada sesuatu untuk dikembalikan padahal
 * memang tidak pernah ada yang terhapus.
 */
class BarisTidakBolehDihapus extends RuntimeException
{
    public static function untuk(string $tabel, string $alasan): self
    {
        return new self(
            "Baris pada tabel {$tabel} tidak boleh dihapus. {$alasan} ".
            'Bila isinya keliru, perbaiki nilainya — jangan hapus barisnya.'
        );
    }
}
