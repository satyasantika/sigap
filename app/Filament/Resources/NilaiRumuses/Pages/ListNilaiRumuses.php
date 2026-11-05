<?php

namespace App\Filament\Resources\NilaiRumuses\Pages;

use App\Filament\Resources\NilaiRumuses\NilaiRumusResource;
use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Services\PenghitungRumusDkps;
use App\Support\Izin;
use App\Support\Rumus\LaporanPerhitungan;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use RuntimeException;

class ListNilaiRumuses extends ListRecords
{
    protected static string $resource = NilaiRumusResource::class;

    /** Alasan dilewati yang ditampilkan di notifikasi; sisanya diringkas. */
    private const MAKS_ALASAN = 5;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hitung')
                ->label('Hitung dari DKPS')
                ->icon(Heroicon::OutlinedCalculator)
                ->visible(fn () => Izin::boleh(auth()->user(), 'nilai_rumus.hitung'))
                ->requiresConfirmation()
                ->modalHeading('Hitung rumus dari isian DKPS?')
                ->modalDescription('Setiap rumus yang hasilnya berubah menambah baris baru; hasil lama tetap tersimpan.')
                ->action(fn () => $this->hitung()),
        ];
    }

    private function hitung(): void
    {
        $periode = Periode::aktif()->first();

        if ($periode === null) {
            Notification::make()->title('Tidak ada periode aktif.')->danger()->send();

            return;
        }

        try {
            $laporan = app(PenghitungRumusDkps::class)->hitungSemua($periode, auth()->user());
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        if ($laporan->dilewati === []) {
            Notification::make()->title($laporan->ringkasan())->success()->send();

            return;
        }

        Notification::make()
            ->title($laporan->ringkasan())
            ->body(self::daftarDilewati($laporan))
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * "KODE: alasan" per baris, paling banyak MAKS_ALASAN, sisanya dihitung.
     * Isi notifikasi dirender sebagai HTML, jadi barisnya dipisah `<br>` dan
     * tiap alasan di-escape lebih dulu.
     */
    private static function daftarDilewati(LaporanPerhitungan $laporan): string
    {
        $baris = collect($laporan->dilewati)
            ->map(fn (string $alasan, string $kode) => e("{$kode}: {$alasan}"))
            ->values();

        $teks = $baris->take(self::MAKS_ALASAN)->implode('<br>');
        $sisa = $baris->count() - self::MAKS_ALASAN;

        return $sisa > 0 ? $teks."<br>… dan {$sisa} lagi" : $teks;
    }

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
