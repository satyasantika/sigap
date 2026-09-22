<?php

namespace App\Filament\Resources\DkpsButirs;

use App\Filament\Resources\DkpsButirs\Pages\ListDkpsButirs;
use App\Filament\Resources\DkpsButirs\Pages\ViewDkpsButir;
use App\Filament\Resources\DkpsButirs\Schemas\DkpsButirInfolist;
use App\Filament\Resources\DkpsButirs\Tables\DkpsButirsTable;
use App\Models\DkpsButir;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Hanya-baca. Lihat App\Policies\ReferensiPolicy. */
class DkpsButirResource extends Resource
{
    protected static ?string $model = DkpsButir::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Referensi';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Butir DKPS';

    protected static ?string $modelLabel = 'butir DKPS';

    protected static ?string $pluralModelLabel = 'butir DKPS';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function infolist(Schema $schema): Schema
    {
        return DkpsButirInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DkpsButirsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDkpsButirs::route('/'),
            'view' => ViewDkpsButir::route('/{record}'),
        ];
    }
}
