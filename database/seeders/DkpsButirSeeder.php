<?php

namespace Database\Seeders;

use App\Models\DkpsButir;
use Database\Seeders\Concerns\MembacaBerkasData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DkpsButirSeeder extends Seeder
{
    use MembacaBerkasData;

    public function run(): void
    {
        $daftar = $this->bacaJson('dkps-tabel.json', 29);

        // Dua tahap: penomoran DKPS 3.0 bergeser dari basis data lama, jadi
        // `no` baru bisa bentrok dengan `no` lama pada indeks unik. Baris
        // dicocokkan lewat `kode`, bukan `no`.
        // Dari `no` terbesar ke terkecil: tiap baris bergeser ke nilai yang
        // lebih besar dari semua baris yang belum bergeser, jadi tidak pernah
        // bentrok sekalipun `no` sebagian sudah di atas 100.
        DB::table('dkps_butir')->orderByDesc('no')->update(['no' => DB::raw('no + 100')]);

        foreach ($daftar as $b) {
            DkpsButir::updateOrCreate(
                ['kode' => $b['kode']],
                [
                    'no' => $b['no'],
                    'nama' => $b['nama'],
                    'label_tabel' => $b['label_tabel'],
                    'jendela_data' => $b['jendela_data'],
                    'keterangan' => $b['keterangan'],
                    'pokja_kode' => $b['pokja'],
                    'elemen' => $b['elemen'],
                    'lembar' => $b['lembar'],
                ],
            );
        }

        if (DkpsButir::orderBy('no')->pluck('no')->all() !== range(1, 29)) {
            throw new RuntimeException('Tabel DKPS harus bernomor 1..29 tanpa lompatan.');
        }

        $jumlahLembar = DkpsButir::all()->sum(fn (DkpsButir $b) => count($b->lembar));

        $this->command?->info("Butir DKPS: 29 tabel, {$jumlahLembar} lembar");
    }
}
