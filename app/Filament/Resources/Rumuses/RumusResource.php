<?php

namespace App\Filament\Resources\Rumuses;

use App\Filament\Resources\Rumuses\Pages\ListRumuses;
use App\Filament\Resources\Rumuses\Pages\ViewRumus;
use App\Filament\Resources\Rumuses\Schemas\RumusInfolist;
use App\Filament\Resources\Rumuses\Tables\RumusesTable;
use App\Models\Rumus;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Hanya-baca. Lihat App\Policies\ReferensiPolicy. */
class RumusResource extends Resource
{
    protected static ?string $model = Rumus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVariable;

    protected static string|UnitEnum|null $navigationGroup = 'Referensi';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Rumus';

    protected static ?string $modelLabel = 'rumus';

    protected static ?string $pluralModelLabel = 'rumus';

    protected static ?string $recordTitleAttribute = 'kode';

    public static function infolist(Schema $schema): Schema
    {
        return RumusInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RumusesTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'rumus');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRumuses::route('/'),
            'view' => ViewRumus::route('/{record}'),
        ];
    }
}
