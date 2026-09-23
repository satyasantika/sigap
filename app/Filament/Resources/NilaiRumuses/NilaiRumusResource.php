<?php

namespace App\Filament\Resources\NilaiRumuses;

use App\Filament\Resources\NilaiRumuses\Pages\ListNilaiRumuses;
use App\Filament\Resources\NilaiRumuses\Tables\NilaiRumusesTable;
use App\Models\NilaiRumus;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Hanya-baca: angka di sini lahir dari KalkulatorRumus, bukan diketik orang. */
class NilaiRumusResource extends Resource
{
    protected static ?string $model = NilaiRumus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Nilai Rumus';

    protected static ?string $modelLabel = 'nilai rumus';

    protected static ?string $pluralModelLabel = 'nilai rumus';

    public static function table(Table $table): Table
    {
        return NilaiRumusesTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'nilai_rumus');
    }

    public static function getPages(): array
    {
        return ['index' => ListNilaiRumuses::route('/')];
    }
}
