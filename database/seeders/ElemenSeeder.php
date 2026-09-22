<?php

namespace Database\Seeders;

use App\Models\Elemen;
use App\Models\Kriteria;
use Database\Seeders\Concerns\MembacaBerkasData;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Kunci alaminya `no` (1..59), bukan id — id boleh berbeda tiap bangun ulang,
 * nomor elemen tidak.
 *
 * Pemetaan nama kolom sengaja ditulis terang-terangan di sini: kunci JSON
 * `kriteria` berisi kode "K1", sementara kolomnya `kriteria_id`; kunci `pokja`
 * menjadi kolom `pokja_kode`. Lihat CLAUDE.md bagian 7 butir 5.
 */
class ElemenSeeder extends Seeder
{
    use MembacaBerkasData;

    public function run(): void
    {
        $daftar = $this->bacaJson('elemen.json', 59);

        $idKriteria = Kriteria::pluck('id', 'kode');

        if ($idKriteria->isEmpty()) {
            throw new RuntimeException('KriteriaSeeder harus berjalan lebih dulu.');
        }

        foreach ($daftar as $e) {
            $kriteriaId = $idKriteria[$e['kriteria']] ?? null;

            if ($kriteriaId === null) {
                throw new RuntimeException("Elemen {$e['no']} menunjuk kriteria tak dikenal: {$e['kriteria']}.");
            }

            Elemen::updateOrCreate(
                ['no' => $e['no']],
                [
                    'kriteria_id' => $kriteriaId,
                    'nama' => $e['nama'],
                    'bobot' => $e['bobot'],
                    'jenis' => $e['jenis'],
                    'syarat_perlu' => $e['syarat_perlu'],
                    'pokja_kode' => $e['pokja'],
                    'panduan' => $e['panduan'],
                    // Teks kosong disimpan sebagai null supaya layar pengerjaan
                    // bisa menyembunyikan kotaknya, bukan menampilkan kotak hampa.
                    'pertanyaan_pemandu' => $e['pertanyaan_pemandu'] ?: null,
                    'parameter' => $e['parameter'] ?: null,
                    'bukti_pendukung' => $e['bukti_pendukung'] ?: null,
                    'evaluasi_refleksi' => $e['evaluasi_refleksi'] ?: null,
                    'tindak_lanjut' => $e['tindak_lanjut'] ?: null,
                ],
            );
        }

        // Dijaga di seeder, bukan hanya di uji: seeder yang menghasilkan angka
        // lain harus berhenti sebelum basis datanya dipakai.
        $total = (float) Elemen::sum('bobot');

        if (abs($total - 100.00) > 0.001) {
            throw new RuntimeException("Total bobot elemen {$total}, seharusnya persis 100,00.");
        }

        $this->command?->info('Elemen: 59 baris, total bobot 100,00');
    }
}
