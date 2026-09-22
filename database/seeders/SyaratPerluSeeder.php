<?php

namespace Database\Seeders;

use App\Models\Elemen;
use App\Models\SyaratPerlu;
use Database\Seeders\Concerns\MembacaBerkasData;
use Illuminate\Database\Seeder;
use RuntimeException;

class SyaratPerluSeeder extends Seeder
{
    use MembacaBerkasData;

    public function run(): void
    {
        $daftar = $this->bacaJson('syarat-perlu.json', 5);

        $idElemen = Elemen::pluck('id', 'no');

        foreach ($daftar as $s) {
            $elemenId = $idElemen[$s['elemen_no']] ?? null;

            if ($elemenId === null) {
                throw new RuntimeException("Syarat perlu menunjuk elemen tak dikenal: {$s['elemen_no']}.");
            }

            SyaratPerlu::updateOrCreate(
                ['elemen_id' => $elemenId],
                [
                    'jenis_ambang' => $s['jenis_ambang'],
                    'ambang_3_tahun' => $s['ambang_3_tahun'],
                    'ambang_5_tahun' => $s['ambang_5_tahun'],
                    'catatan' => $s['catatan'] ?: null,
                    // Nomor versi Tabel 1.3 Buku 4 — berbeda dari nomor elemen
                    // untuk tiga dari lima butir. Selisihnya memang ada di
                    // dokumen aslinya; jangan "diperbaiki".
                    'nomor_di_tabel_1_3' => $s['nomor_di_tabel_1_3_buku_4'],
                ],
            );
        }

        $ditandai = Elemen::bersyaratPerlu()->pluck('no')->sort()->values()->all();

        if ($ditandai !== [17, 34, 45, 51, 58]) {
            throw new RuntimeException(
                'Elemen bertanda syarat perlu: '.implode(', ', $ditandai).' — seharusnya 17, 34, 45, 51, 58.'
            );
        }

        $this->command?->info('Syarat perlu: 5 baris pada elemen 17, 34, 45, 51, 58');
    }
}
