<?php

namespace App\Filament\Resources\DkpsBaris;

use App\Filament\Resources\DkpsBaris\Pages\CreateDkpsBaris;
use App\Filament\Resources\DkpsBaris\Pages\EditDkpsBaris;
use App\Filament\Resources\DkpsBaris\Pages\ListDkpsBaris;
use App\Filament\Resources\DkpsBaris\Pages\ViewDkpsBaris;
use App\Filament\Resources\DkpsBaris\Schemas\DkpsBarisForm;
use App\Filament\Resources\DkpsBaris\Tables\DkpsBarisTable;
use App\Models\DkpsBaris as ModelDkpsBaris;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DkpsBarisResource extends Resource
{
    protected static ?string $model = ModelDkpsBaris::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Isian DKPS';

    protected static ?string $modelLabel = 'baris DKPS';

    protected static ?string $pluralModelLabel = 'isian DKPS';

    public static function form(Schema $schema): Schema
    {
        return DkpsBarisForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DkpsBarisTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'dkps_baris');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDkpsBaris::route('/'),
            'create' => CreateDkpsBaris::route('/create'),
            'view' => ViewDkpsBaris::route('/{record}'),
            'edit' => EditDkpsBaris::route('/{record}/edit'),
        ];
    }

    /** Baris yang belum diverifikasi: angka yang harus terlihat. */
    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::whereNull('diverifikasi_pada')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
