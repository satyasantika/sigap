<?php

namespace App\Filament\Pages;

use App\Filament\Resources\NilaiRumuses\Tables\NilaiRumusesTable;
use App\Models\Periode;
use App\Models\VariabelDkps;
use App\Services\PembacaVariabelDkps;
use App\Support\Izin;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Katalog variabel DKPS beserta nilainya pada periode aktif.
 *
 * Hanya-baca. Gunanya menjawab "angka RK ini datang dari mana?": setiap
 * variabel menunjuk lembar sumbernya, jumlah barisnya, dan berapa baris yang
 * belum diverifikasi — sebelum seseorang menekan Hitung dari DKPS.
 */
class VariabelDkpsLayar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVariable;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Variabel DKPS';

    protected static ?string $title = 'Variabel DKPS';

    protected static ?string $slug = 'dkps/variabel';

    protected string $view = 'filament.pages.variabel-dkps';

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'variabel_dkps');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function periode(): ?Periode
    {
        return Periode::aktif()->first();
    }

    /**
     * Satu baris per variabel katalog, urut `urutan`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function baris(): array
    {
        $periode = $this->periode();

        if ($periode === null) {
            return [];
        }

        $nilai = app(PembacaVariabelDkps::class)->semua($periode);

        return VariabelDkps::orderBy('urutan')->get()
            ->map(function (VariabelDkps $v) use ($nilai) {
                $n = $nilai[$v->kode] ?? null;

                return [
                    'kode' => $v->kode,
                    'label' => $v->label,
                    'nilai' => $n?->nilai === null ? null : NilaiRumusesTable::angka($n->nilai),
                    'catatan' => $n?->catatan,
                    'lembar' => collect($v->lembar ?? [])
                        ->map(fn (string $kode) => [
                            'kode' => $kode,
                            'url' => TabelDkpsLayar::urlUntuk(explode('.', $kode)[0], $kode),
                        ])->all(),
                    'jumlah_baris' => $n?->jumlahBaris ?? 0,
                    'belum_terverifikasi' => $n?->belumTerverifikasi ?? 0,
                    'rumus' => implode(', ', $v->rumus ?? []),
                ];
            })->all();
    }
}
