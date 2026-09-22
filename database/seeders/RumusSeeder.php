<?php

namespace Database\Seeders;

use App\Models\Elemen;
use App\Models\Rumus;
use Database\Seeders\Concerns\MembacaBerkasData;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Pemetaan kunci JSON ke kolom yang perlu diperhatikan:
 *   `skor`   -> kolom `aturan_skor`
 *   `elemen` -> kolom `elemen_id` (nomor polos di JSON, null untuk NA)
 * Lihat CLAUDE.md bagian 7 butir 5.
 */
class RumusSeeder extends Seeder
{
    use MembacaBerkasData;

    /** Rumus inti yang wajib ada; ketiadaannya berarti data terpotong. */
    private const INTI = ['PDS3', 'PGBLKL', 'PPDTPS', 'RSA', 'RK', 'NA'];

    public function run(): void
    {
        $daftar = $this->bacaJson('rumus.json', 15);

        $idElemen = Elemen::pluck('id', 'no');

        foreach ($daftar as $r) {
            $elemenId = null;

            if ($r['elemen'] !== null) {
                $elemenId = $idElemen[$r['elemen']] ?? null;

                if ($elemenId === null) {
                    throw new RuntimeException("Rumus {$r['kode']} menunjuk elemen tak dikenal: {$r['elemen']}.");
                }
            }

            Rumus::updateOrCreate(
                ['kode' => $r['kode']],
                [
                    'nama' => $r['nama'],
                    'elemen_id' => $elemenId,
                    'ekspresi' => $r['ekspresi'],
                    'variabel' => $r['variabel'],
                    'aturan_skor' => $r['skor'],
                    'jendela' => $r['jendela'],
                    'catatan' => $r['catatan'] ?? null,
                ],
            );
        }

        $hilang = array_diff(self::INTI, Rumus::pluck('kode')->all());

        if ($hilang !== []) {
            throw new RuntimeException('Rumus inti hilang: '.implode(', ', $hilang).'.');
        }

        $this->command?->info('Rumus: 15 baris, rumus inti lengkap');
    }
}
