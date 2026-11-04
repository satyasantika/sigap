<?php

namespace App\Filament\Resources\NilaiRumuses\Pages;

use App\Filament\Resources\NilaiRumuses\NilaiRumusResource;
use App\Models\NilaiRumus;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListNilaiRumuses extends ListRecords
{
    protected static string $resource = NilaiRumusResource::class;

    /**
     * Catatan TKM ditampilkan di layar, bukan hanya di komentar kode.
     *
     * Dulu kotak peringatan: tafsir "dibagi 4" belum dikonfirmasi. Kertas
     * kerja AK IAPSK 3.0 menuliskan rumus yang sama di sel E207, jadi kini ia
     * catatan informatif — orang yang membaca angka TKM tetap perlu tahu
     * mengapa skalanya persentase, bukan 100–400.
     */
    public function getSubheading(): ?HtmlString
    {
        if (! NilaiRumus::where('rumus_kode', 'TKM')->exists()) {
            return null;
        }

        return new HtmlString(
            '<div class="rounded-lg border border-info-300 bg-info-50 p-4 text-sm dark:border-info-500/30 dark:bg-info-500/10">'
            .'<p class="font-medium">Normalisasi TKM sesuai kertas kerja AK IAPSK 3.0.</p>'
            .'<p class="mt-1">Rumus <code>TKMi = 4a+3b+2c+d</code> menghasilkan skala 100–400; sistem membaginya '
            .'dengan 4 agar sebanding dengan ambang persentase. Tafsir ini dikonfirmasi oleh kertas kerja '
            .'AK IAPSK 3.0 (sel E207: <code>/4</code>, ambang 0,25 / 0,5 / 0,75).</p>'
            .'</div>'
        );
    }
}
