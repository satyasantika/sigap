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
     * Peringatan TKM ditampilkan di layar, bukan hanya di komentar kode.
     *
     * Angka TKM memakai tafsir yang BELUM dikonfirmasi ke LAMDIK — nilai
     * mentah berskala 100–400 dibagi 4 agar sebanding dengan ambang persentase.
     * Angkanya boleh dipakai, tetapi tidak boleh dipakai diam-diam: orang yang
     * membacanya harus tahu ia berdiri di atas asumsi.
     */
    public function getSubheading(): ?HtmlString
    {
        if (! NilaiRumus::where('rumus_kode', 'TKM')->exists()) {
            return null;
        }

        return new HtmlString(
            '<div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm dark:border-warning-500/30 dark:bg-warning-500/10">'
            .'<p class="font-medium">Angka TKM memakai asumsi yang belum dikonfirmasi ke LAMDIK.</p>'
            .'<p class="mt-1">Rumus <code>TKMi = 4a+3b+2c+d</code> menghasilkan skala 100–400, sementara '
            .'ambangnya dinyatakan sebagai persentase (TKM ≥ 75%). Sistem membaginya dengan 4 agar sebanding. '
            .'Konfirmasikan tafsir ini sebelum angkanya dipakai dalam laporan resmi.</p>'
            .'</div>'
        );
    }
}
