<?php

namespace App\Console\Commands;

use App\Enums\JenisTagihan;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Tagihan;
use App\Services\PembangkitTagihan;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Menyelaraskan data dari sebelum DKPS 3.0 dengan skema lembar dan pokja.
 *
 * Baris baru mendapat `lembar_kode` dan `pokja_id` dari kait `creating` di
 * DkpsBaris. Baris yang lebih tua dari kait itu tidak punya keduanya, sehingga
 * tidak tampil di layar Tabel DKPS milik pokja mana pun. Baris yang
 * `lembar_kode`-nya tidak dikenal tabelnya (mis. `24` padahal Tabel 24 kini
 * berlembar 24.1 dan 24.2) sama yatimnya: semua layar menyaring lembar persis.
 * Keduanya dibetulkan ke lembar pertama tabelnya; `pokja_id` yang sudah terisi
 * tidak disentuh — jadi aman diulang.
 *
 * Baris tidak dihapus dan isi `data`-nya tidak diubah: baris yang kuncinya tak
 * cocok skema tetap muncul sebagai "Baris lama yang perlu dipindahkan".
 *
 * Tagihan DKPS ikut diselaraskan, karena PembangkitTagihan idempoten per
 * (periode, jenis, butir) dan tidak pernah menyentuh tagihan yang sudah ada:
 * tagihan lama tetap di POKJA-DATA dengan judul lama, dan tabel baru tidak
 * punya tagihan. Hanya periode yang SUDAH punya tagihan yang disentuh;
 * periode tanpa tagihan dibiarkan untuk `tagihan:bangkitkan`.
 */
class SelaraskanDkps extends Command
{
    protected $signature = 'dkps:selaraskan';

    protected $description = 'Menyelaraskan baris dan tagihan DKPS lama (sebelum DKPS 3.0) dengan lembar dan pokja pemilik tabel';

    public function handle(PembangkitTagihan $pembangkit): int
    {
        $this->selaraskanBaris();

        return $this->selaraskanTagihan($pembangkit) ? self::SUCCESS : self::FAILURE;
    }

    private function selaraskanBaris(): void
    {
        $diselaraskan = 0;
        $lembarAsing = 0;
        $tanpaPokja = 0;

        DkpsBaris::withoutGlobalScopes()
            ->withTrashed()
            ->with('butir')
            ->chunkById(200, function ($kumpulan) use (&$diselaraskan, &$lembarAsing, &$tanpaPokja): void {
                foreach ($kumpulan as $baris) {
                    $butir = $baris->butir;

                    if ($butir === null) {
                        $tanpaPokja++;

                        continue;
                    }

                    $kodeLembar = $butir->kodeLembar();

                    if (blank($baris->lembar_kode)) {
                        $baris->lembar_kode = $kodeLembar[0] ?? null;
                    } elseif ($kodeLembar !== [] && ! in_array($baris->lembar_kode, $kodeLembar, true)) {
                        $baris->lembar_kode = $kodeLembar[0];
                        $lembarAsing++;
                    }

                    if (blank($baris->pokja_id)) {
                        $baris->pokja_id = $this->pokjaId($butir, $baris->periode_id);
                    }

                    if ($baris->pokja_id === null) {
                        $tanpaPokja++;
                    }

                    if ($baris->isDirty()) {
                        $baris->saveQuietly();
                        $diselaraskan++;
                    }
                }
            });

        $this->info("Diselaraskan: {$diselaraskan}");
        $this->info("Lembar asing dibetulkan: {$lembarAsing}");

        if ($tanpaPokja > 0) {
            $this->warn("Pokja tidak ditemukan: {$tanpaPokja} baris (periode barisnya belum punya pokja "
                .'berkode yang diminta butirnya). Buat pokjanya lewat layar Pokja, lalu jalankan ulang.');
        }
    }

    private function selaraskanTagihan(PembangkitTagihan $pembangkit): bool
    {
        $dipindahkan = 0;
        $ditambahkan = 0;
        $gagal = false;

        $periodeBertagihan = Tagihan::withoutGlobalScope('lingkup_periode')
            ->distinct()->pluck('periode_id');
        $butir = DkpsButir::all()->keyBy('id');

        foreach (Periode::whereIn('id', $periodeBertagihan)->orderBy('created_at')->get() as $periode) {
            $tagihan = Tagihan::withoutGlobalScope('lingkup_periode')
                ->where('periode_id', $periode->id)
                ->where('jenis', JenisTagihan::DataDkps)
                ->whereNotNull('dkps_butir_id')
                ->get();

            foreach ($tagihan as $t) {
                $b = $butir[$t->dkps_butir_id] ?? null;

                if ($b === null) {
                    continue;
                }

                // Sama dengan PembangkitTagihan: tanpa pokja pemilik, tagihan
                // tetap di pokjanya sekarang daripada tak berpemilik.
                $pokjaId = $this->pokjaId($b, $periode->id) ?? $t->pokja_id;

                $t->pokja_id = $pokjaId;
                $t->judul = "Isi {$b->label_tabel} — {$b->nama}";

                if ($t->isDirty()) {
                    $t->save();
                    $dipindahkan++;
                }
            }

            try {
                $ditambahkan += $pembangkit->untuk($periode)['data_dkps'];
            } catch (RuntimeException $e) {
                $gagal = true;
                $this->error("Periode {$periode->nama}: tagihan yang kurang tidak bisa dibangkitkan — {$e->getMessage()}");
            }
        }

        $this->info("Tagihan DKPS diselaraskan: {$dipindahkan}");
        $this->info("Tagihan DKPS ditambahkan: {$ditambahkan}");

        return ! $gagal;
    }

    /**
     * Pokja pemilik tabel pada periode itu. Lingkup periode dilepas (perintah
     * ini lintas periode), tetapi SoftDeletes tidak: pokja yang sudah dihapus
     * bukan pemilik.
     */
    private function pokjaId(DkpsButir $butir, ?string $periodeId): ?string
    {
        if ($butir->pokja_kode === null || $periodeId === null) {
            return null;
        }

        return Pokja::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $periodeId)
            ->where('kode', $butir->pokja_kode)
            ->value('id');
    }
}
