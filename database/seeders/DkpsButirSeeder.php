<?php

namespace Database\Seeders;

use App\Models\DkpsButir;
use Database\Seeders\Concerns\MembacaBerkasData;
use Illuminate\Database\Seeder;
use RuntimeException;

class DkpsButirSeeder extends Seeder
{
    use MembacaBerkasData;

    public function run(): void
    {
        $daftar = $this->bacaJson('dkps-tabel.json', 28);

        foreach ($daftar as $b) {
            DkpsButir::updateOrCreate(
                ['no' => $b['no']],
                [
                    'nama' => $b['nama'],
                    // Label Buku 3 melompati "Tabel 13"; jangan diturunkan dari `no`.
                    'label_tabel' => $b['label_tabel'] ?: null,
                    'jendela_data' => $b['jendela_data'],
                    'keterangan' => $b['keterangan'],
                ],
            );
        }

        $nomor = DkpsButir::orderBy('no')->pluck('no')->all();

        if ($nomor !== range(1, 28)) {
            throw new RuntimeException('Butir DKPS harus bernomor 1..28 tanpa lompatan.');
        }

        $this->command?->info('Butir DKPS: 28 baris bernomor 1..28');
    }
}
