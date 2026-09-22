<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Urutannya mengikat: elemen butuh kriteria, syarat perlu dan rumus butuh
 * elemen. Seeder referensi tidak bergantung pada periode, jadi ia boleh
 * berjalan sebelum organisasi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IzinSeeder::class,
            KriteriaSeeder::class,
            ElemenSeeder::class,
            SyaratPerluSeeder::class,
            RumusSeeder::class,
            DkpsButirSeeder::class,
            OrganisasiSeeder::class,
        ]);
    }
}
