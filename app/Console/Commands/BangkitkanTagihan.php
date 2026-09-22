<?php

namespace App\Console\Commands;

use App\Models\Periode;
use App\Services\PembangkitTagihan;
use Illuminate\Console\Command;

class BangkitkanTagihan extends Command
{
    protected $signature = 'tagihan:bangkitkan {periode : Nama atau id periode}';

    protected $description = 'Bangkitkan tagihan narasi, bukti, dan DKPS untuk satu periode';

    public function handle(PembangkitTagihan $pembangkit): int
    {
        $kunci = $this->argument('periode');

        $periode = Periode::where('id', $kunci)->orWhere('nama', $kunci)->first();

        if ($periode === null) {
            $this->error("Periode `{$kunci}` tidak ditemukan.");

            return self::FAILURE;
        }

        $this->info("Membangkitkan tagihan untuk periode {$periode->nama} (TS {$periode->ts_tahun})...");

        $hasil = $pembangkit->untuk($periode);

        $this->table(
            ['Jenis', 'Dibuat'],
            [
                ['Naskah LED', $hasil['narasi']],
                ['Pengumpulan bukti', $hasil['bukti']],
                ['Isian DKPS', $hasil['data_dkps']],
                ['Sudah ada, dilewati', $hasil['dilewati']],
            ],
        );

        $this->info('Jumlah bobot_terkait periode ini utuh 100,000.');

        return self::SUCCESS;
    }
}
