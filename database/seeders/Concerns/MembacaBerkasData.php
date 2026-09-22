<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Pembaca data/*.json bersama untuk seluruh seeder referensi.
 *
 * Alasannya satu: kegagalan harus seragam dan keras. Seeder yang melanjutkan
 * dengan larik kosong akan menghasilkan basis data yang terlihat sehat tetapi
 * menghitung Nilai Akreditasi dari nol elemen — dan itu baru ketahuan berbulan
 * kemudian, saat angka di dasbor dibandingkan dengan hitungan tangan asesor.
 */
trait MembacaBerkasData
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function bacaJson(string $namaBerkas, int $jumlahDiharapkan): array
    {
        $jalur = base_path("data/{$namaBerkas}");

        if (! is_file($jalur)) {
            throw new RuntimeException(
                "Berkas data tidak ditemukan: {$jalur}. ".
                'Berkas di data/ adalah dependensi, bukan bahan kerja — ia wajib terlacak git.'
            );
        }

        try {
            $isi = json_decode(file_get_contents($jalur), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Gagal menguraikan {$namaBerkas}: {$e->getMessage()}", previous: $e);
        }

        if (! is_array($isi) || $isi === []) {
            throw new RuntimeException("{$namaBerkas} kosong atau bukan larik.");
        }

        if (count($isi) !== $jumlahDiharapkan) {
            throw new RuntimeException(
                "{$namaBerkas} berisi ".count($isi)." baris, seharusnya {$jumlahDiharapkan}. ".
                'Jalankan `python3 data/verifikasi.py` sebelum melanjutkan.'
            );
        }

        return $isi;
    }
}
