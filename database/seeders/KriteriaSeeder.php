<?php

namespace Database\Seeders;

use App\Models\Kriteria;
use Database\Seeders\Concerns\MembacaBerkasData;
use Illuminate\Database\Seeder;
use RuntimeException;

class KriteriaSeeder extends Seeder
{
    use MembacaBerkasData;

    public function run(): void
    {
        $daftar = $this->bacaJson('kriteria.json', 9);

        foreach ($daftar as $k) {
            Kriteria::updateOrCreate(
                ['kode' => $k['kode']],
                ['nama' => $k['nama'], 'urutan' => $k['urutan'], 'bobot' => $k['bobot']],
            );
        }

        $total = (float) Kriteria::sum('bobot');

        if (abs($total - 100.00) > 0.001) {
            throw new RuntimeException("Total bobot kriteria {$total}, seharusnya 100,00.");
        }

        $this->command?->info('Kriteria: 9 baris, total bobot 100,00');
    }
}
