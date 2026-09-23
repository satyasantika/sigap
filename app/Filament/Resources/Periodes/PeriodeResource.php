<?php

namespace App\Filament\Resources\Periodes;

use App\Filament\Resources\Periodes\Pages\CreatePeriode;
use App\Filament\Resources\Periodes\Pages\EditPeriode;
use App\Filament\Resources\Periodes\Pages\ListPeriodes;
use App\Filament\Resources\Periodes\Schemas\PeriodeForm;
use App\Filament\Resources\Periodes\Tables\PeriodesTable;
use App\Models\Periode;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PeriodeResource extends Resource
{
    protected static ?string $model = Periode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Periode';

    protected static ?string $modelLabel = 'periode';

    protected static ?string $pluralModelLabel = 'periode';

    public static function form(Schema $schema): Schema
    {
        return PeriodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PeriodesTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'periode');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeriodes::route('/'),
            'create' => CreatePeriode::route('/create'),
            'edit' => EditPeriode::route('/{record}/edit'),
        ];
    }
}
