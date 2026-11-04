<?php

namespace App\Console\Commands;

use App\Models\DkpsBaris;
use App\Models\Pokja;
use Illuminate\Console\Command;

/**
 * Menyelaraskan baris DKPS dari sebelum DKPS 3.0 dengan skema lembar dan pokja.
 *
 * Baris baru mendapat `lembar_kode` dan `pokja_id` dari kait `creating` di
 * DkpsBaris. Baris yang lebih tua dari kait itu tidak punya keduanya, sehingga
 * tidak tampil di layar Tabel DKPS milik pokja mana pun. Perintah ini mengisi
 * yang kosong saja — yang sudah terisi tidak disentuh — jadi aman diulang.
 *
 * Baris tidak dihapus dan isi `data`-nya tidak diubah: baris yang kuncinya tak
 * cocok skema tetap muncul sebagai "Baris lama yang perlu dipindahkan".
 */
class SelaraskanDkps extends Command
{
    protected $signature = 'dkps:selaraskan';

    protected $description = 'Mengisi lembar_kode dan pokja_id baris DKPS lama (sebelum DKPS 3.0)';

    public function handle(): int
    {
        $diselaraskan = 0;
        $tanpaPokja = 0;

        DkpsBaris::withoutGlobalScopes()
            ->withTrashed()
            ->with('butir')
            ->where(fn ($q) => $q->whereNull('pokja_id')->orWhereNull('lembar_kode'))
            ->chunkById(200, function ($kumpulan) use (&$diselaraskan, &$tanpaPokja): void {
                foreach ($kumpulan as $baris) {
                    $butir = $baris->butir;

                    if ($butir === null) {
                        $tanpaPokja++;

                        continue;
                    }

                    if (blank($baris->lembar_kode)) {
                        $baris->lembar_kode = $butir->kodeLembar()[0] ?? null;
                    }

                    if (blank($baris->pokja_id) && $butir->pokja_kode !== null) {
                        $baris->pokja_id = Pokja::withoutGlobalScopes()
                            ->where('periode_id', $baris->periode_id)
                            ->where('kode', $butir->pokja_kode)
                            ->value('id');
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

        if ($tanpaPokja > 0) {
            $this->warn("Pokja tidak ditemukan: {$tanpaPokja} baris (periode barisnya belum punya pokja "
                .'berkode yang diminta butirnya). Buat pokjanya lewat layar Pokja, lalu jalankan ulang.');
        }

        return self::SUCCESS;
    }
}
