<?php

namespace App\Console\Commands;

use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Services\PenghitungRumusDkps;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Menghitung seluruh rumus kuantitatif dari isian Tabel DKPS.
 *
 * Perintah artisan tidak punya sesi, jadi lingkup periode dilepas dan periode
 * dicari langsung. Periode terkunci dilaporkan sebagai galat per periode lalu
 * dilewati; kode keluar 1 hanya untuk `--periode` yang tidak dikenal.
 */
class HitungRumus extends Command
{
    protected $signature = 'rumus:hitung {--periode= : ID periode; tanpa ini, semua periode aktif}';

    protected $description = 'Menghitung seluruh rumus kuantitatif dari isian Tabel DKPS dan menyimpan hasil barunya';

    public function handle(PenghitungRumusDkps $penghitung): int
    {
        $id = $this->option('periode');

        if ($id !== null && $id !== '') {
            $periode = Periode::withoutGlobalScope('lingkup_periode')->find($id);

            if ($periode === null) {
                $this->error("Periode {$id} tidak ditemukan");

                return self::FAILURE;
            }

            $daftar = collect([$periode]);
        } else {
            $daftar = Periode::withoutGlobalScope('lingkup_periode')->aktif()->orderBy('created_at')->get();

            if ($daftar->isEmpty()) {
                $this->info('Tidak ada periode aktif.');

                return self::SUCCESS;
            }
        }

        foreach ($daftar as $periode) {
            $this->line("Periode {$periode->nama} (TS {$periode->ts_tahun})");

            try {
                $laporan = $penghitung->hitungSemua($periode);
            } catch (RuntimeException $e) {
                $this->error("Periode {$periode->nama}: {$e->getMessage()}");

                continue;
            }

            $this->line('Disimpan');
            $this->tabel(['Kode', 'Nilai', 'Skor A'], array_map(fn (NilaiRumus $n, string $kode): array => [
                $kode, $this->angka($n->nilai), $this->angka($n->skor_tepat),
            ], $laporan->disimpan, array_keys($laporan->disimpan)));

            $this->line('Tidak berubah');
            $this->tabel(['Kode'], array_map(fn (string $kode): array => [$kode], array_keys($laporan->tidakBerubah)));

            $this->line('Dilewati');
            $this->tabel(['Kode', 'Alasan'], array_map(fn (string $alasan, string $kode): array => [
                $kode, $alasan,
            ], $laporan->dilewati, array_keys($laporan->dilewati)));

            $this->info($laporan->ringkasan());
        }

        return self::SUCCESS;
    }

    /** @param  list<string>  $kepala */
    private function tabel(array $kepala, array $baris): void
    {
        if ($baris === []) {
            $this->line('  (tidak ada)');

            return;
        }

        $this->table($kepala, $baris);
    }

    private function angka(mixed $nilai): string
    {
        return $nilai === null ? '—' : number_format((float) $nilai, 2, ',', '.');
    }
}
