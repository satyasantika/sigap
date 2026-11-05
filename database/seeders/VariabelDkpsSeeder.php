<?php

namespace Database\Seeders;

use App\Models\VariabelDkps;
use Database\Seeders\Concerns\MembacaBerkasData;
use Illuminate\Database\Seeder;

/**
 * Katalog variabel DKPS dari data/variabel-dkps.json. `urutan` mengikuti
 * urutan berkas supaya layar menampilkannya seperti lembar "5. DKPS".
 */
class VariabelDkpsSeeder extends Seeder
{
    use MembacaBerkasData;

    /** Jumlah variabel di katalog; berubah hanya bersama berkasnya. */
    private const JUMLAH = 136;

    public function run(): void
    {
        foreach ($this->bacaJson('variabel-dkps.json', self::JUMLAH) as $urutan => $v) {
            VariabelDkps::updateOrCreate(
                ['kode' => $v['kode']],
                [
                    'label' => $v['label'],
                    'lembar' => $v['lembar'],
                    'cara' => $v['cara'],
                    'kolom' => $v['kolom'] ?? null,
                    'parameter' => $v['parameter'] ?? null,
                    'rumus' => $v['rumus'],
                    'urutan' => $urutan,
                ],
            );
        }

        $this->command?->info('Variabel DKPS: '.self::JUMLAH.' baris');
    }
}
