<?php

namespace Database\Seeders;

use App\Models\Izin as ModelIzin;
use App\Support\Izin;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Menyemai 144 sel izin dari data/izin.json.
 *
 * AGENTS.md aturan 1: tidak boleh menyalin isinya ke larik PHP. Bila berkasnya
 * hilang atau rusak, seeder GAGAL DENGAN KERAS — diam-diam menyemai matriks
 * kosong berarti seluruh aplikasi menolak semua orang tanpa alasan yang jelas.
 */
class IzinSeeder extends Seeder
{
    public function run(): void
    {
        $berkas = base_path('data/izin.json');

        if (! is_file($berkas)) {
            throw new RuntimeException("data/izin.json tidak ditemukan di {$berkas}.");
        }

        $isi = json_decode(file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);

        $peran = $isi['peran'] ?? [];
        $lingkup = array_keys($isi['lingkup'] ?? []);
        $daftarAksi = $isi['aksi'] ?? [];

        if (count($peran) !== 6 || $daftarAksi === []) {
            throw new RuntimeException('Struktur data/izin.json tidak sesuai harapan.');
        }

        $jumlah = 0;

        foreach ($daftarAksi as $aksi) {
            foreach ($peran as $p) {
                $nilai = $aksi[$p] ?? null;

                if (! in_array($nilai, $lingkup, true)) {
                    throw new RuntimeException(
                        "Nilai izin tidak sah pada {$aksi['kode']}.{$p}: ".var_export($nilai, true)
                    );
                }

                // Kunci alami (aksi, peran) — id boleh berbeda tiap bangun ulang.
                ModelIzin::updateOrCreate(
                    ['aksi' => $aksi['kode'], 'peran' => $p],
                    ['nilai' => $nilai],
                );
                $jumlah++;
            }
        }

        if ($jumlah !== 144) {
            throw new RuntimeException("Seharusnya 144 sel izin, yang tersemai {$jumlah}.");
        }

        Izin::lupakan();

        $this->command?->info("Izin: {$jumlah} sel (".count($daftarAksi).' aksi x '.count($peran).' peran)');
    }
}
