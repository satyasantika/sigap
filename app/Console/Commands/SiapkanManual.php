<?php

namespace App\Console\Commands;

use App\Enums\LevelSyaratPerlu;
use App\Enums\PeranPengguna;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\Simulasi;
use App\Models\User;
use App\Services\Simulator;
use App\Support\Na\Skenario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Menyiapkan basis data demo agar siap ditangkap layarnya untuk manual.
 *
 * Tiga hal yang dikerjakan, semuanya hanya masuk akal di lingkungan demo:
 *
 * 1. Mematikan `wajib_ganti_sandi` pada enam pengguna contoh. Tanpa itu,
 *    setiap login berujung di layar ganti sandi dan seluruh tangkapan layar
 *    menjadi layar yang sama.
 * 2. Menyamakan kata sandi keenamnya ke `password`, supaya skrip tangkapan
 *    layar tidak perlu menyimpan rahasia apa pun.
 * 3. Membuat satu simulasi contoh, supaya layar simulasi tidak tertangkap
 *    dalam keadaan kosong.
 *
 * MENOLAK BERJALAN DI PRODUKSI. Menyeragamkan kata sandi enam akun adalah
 * tindakan yang pantas di basis data demo dan tidak pantas di mana pun
 * selain itu.
 */
class SiapkanManual extends Command
{
    protected $signature = 'sigap:siapkan-manual';

    protected $description = 'Menyiapkan basis data demo untuk penangkapan layar manual pengguna';

    public function handle(Simulator $simulator): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini menyeragamkan kata sandi pengguna contoh. Tidak untuk produksi.');

            return self::FAILURE;
        }

        $surel = [
            'admin@sigap.test', 'ketua@sigap.test', 'pimpinan@sigap.test',
            'koordinator@sigap.test', 'anggota@sigap.test', 'auditor@sigap.test',
        ];

        $jumlah = 0;

        foreach ($surel as $s) {
            $u = User::where('email', $s)->first();

            if ($u === null) {
                $this->warn("Pengguna contoh {$s} tidak ada. Jalankan seeder lebih dulu.");

                continue;
            }

            $u->forceFill([
                'wajib_ganti_sandi' => false,
                'password' => Hash::make('password'),
            ])->save();

            $jumlah++;
        }

        $this->info("{$jumlah} pengguna contoh disiapkan (sandi: password).");

        $admin = User::where('peran', PeranPengguna::Admin)->first();
        $periode = Periode::aktif()->first();

        if ($admin !== null && $periode !== null && Simulasi::count() === 0) {
            $elemen = Elemen::orderByDesc('bobot')->first();
            $syarat = Elemen::bersyaratPerlu()->orderBy('no')->first();

            $simulator->buatSimulasiSkor(
                $admin,
                $periode,
                'Andai elemen terberat penuh',
                new Skenario(
                    skor: [$elemen->id => 4],
                    syaratPerlu: [$syarat->id => LevelSyaratPerlu::Lima],
                ),
                'Contoh untuk manual pengguna.',
            );

            $this->info('Satu simulasi contoh dibuat.');
        }

        return self::SUCCESS;
    }
}
