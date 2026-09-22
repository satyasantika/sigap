<?php

namespace App\Console\Commands;

use App\Enums\AksesTautan;
use App\Enums\JenisBukti;
use App\Models\Bukti;
use App\Services\PemeriksaTautan;
use Illuminate\Console\Command;

/**
 * Dijadwalkan harian. Tautan Drive yang tadinya terbuka bisa tertutup
 * kapan saja — seseorang memindahkan berkasnya, atau menyetel ulang berbagi
 * folder induknya — dan tidak ada yang memberi tahu.
 */
class PeriksaTautanBukti extends Command
{
    protected $signature = 'bukti:periksa-tautan
        {--periode= : Batasi pada satu periode}
        {--paksa : Periksa ulang termasuk yang sudah terbuka}';

    protected $description = 'Periksa keterbacaan tautan bukti tanpa kredensial, seperti asesor';

    public function handle(PemeriksaTautan $pemeriksa): int
    {
        $q = Bukti::query()
            ->where('jenis', JenisBukti::Tautan)
            ->whereNotNull('url_kanonik');

        if ($periode = $this->option('periode')) {
            $q->where('periode_id', $periode);
        }

        if (! $this->option('paksa')) {
            // Yang sudah gagal tiga kali tidak dicoba lagi otomatis: ia sudah
            // menjadi urusan manusia, dan mengulanginya tiap hari hanya
            // menambah beban tanpa menambah informasi.
            $q->where(fn ($q) => $q
                ->where('akses_status', '!=', AksesTautan::GagalPeriksa)
                ->orWhere('akses_percobaan', '<', PemeriksaTautan::BATAS_PERCOBAAN));
        }

        $jumlah = (clone $q)->count();

        if ($jumlah === 0) {
            $this->info('Tidak ada tautan yang perlu diperiksa.');

            return self::SUCCESS;
        }

        $this->info("Memeriksa {$jumlah} tautan tanpa kredensial...");

        $cacah = [];

        $q->chunkById(50, function ($kumpulan) use ($pemeriksa, &$cacah) {
            foreach ($kumpulan as $bukti) {
                $status = $pemeriksa->periksa($bukti);
                $cacah[$status->value] = ($cacah[$status->value] ?? 0) + 1;

                // Jeda supaya tidak dianggap penyalahgunaan oleh pihak sana.
                usleep(250_000);
            }
        });

        $this->table(
            ['Status', 'Jumlah'],
            collect($cacah)->map(fn ($n, $s) => [AksesTautan::from($s)->label(), $n])->values()->all(),
        );

        $perluManusia = Bukti::where('akses_status', AksesTautan::GagalPeriksa)
            ->where('akses_percobaan', '>=', PemeriksaTautan::BATAS_PERCOBAAN)->count();

        if ($perluManusia > 0) {
            $this->warn("{$perluManusia} tautan gagal diperiksa ".PemeriksaTautan::BATAS_PERCOBAAN.' kali — perlu diperiksa manusia.');
        }

        return self::SUCCESS;
    }
}
