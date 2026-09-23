<?php

namespace App\Filament\Resources\Penilaians;

use App\Filament\Resources\Penilaians\Pages\ListPenilaians;
use App\Filament\Resources\Penilaians\Schemas\PenilaianForm;
use App\Filament\Resources\Penilaians\Tables\PenilaiansTable;
use App\Models\Penilaian;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Asesmen mandiri: 59 elemen dengan pemilih skor 1-4.
 *
 * Dua penilai boleh menskor terpisah — kunci uniknya (periode, elemen,
 * penilai) — meniru mekanisme dua asesor pada asesmen kecukupan.
 */
class PenilaianResource extends Resource
{
    protected static ?string $model = Penilaian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Asesmen Mandiri';

    protected static ?string $modelLabel = 'penilaian';

    protected static ?string $pluralModelLabel = 'asesmen mandiri';

    public static function form(Schema $schema): Schema
    {
        return PenilaianForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenilaiansTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'penilaian');
    }

    public static function getPages(): array
    {
        return ['index' => ListPenilaians::route('/')];
    }
}
