<?php

namespace App\Filament\Resources\Elemens;

use App\Filament\Resources\Elemens\Pages\ListElemens;
use App\Filament\Resources\Elemens\Pages\ViewElemen;
use App\Filament\Resources\Elemens\Schemas\ElemenInfolist;
use App\Filament\Resources\Elemens\Tables\ElemensTable;
use App\Models\Elemen;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hanya-baca. Instrumen diubah lewat data/*.json, bukan lewat layar —
 * lihat App\Policies\ReferensiPolicy.
 */
class ElemenResource extends Resource
{
    protected static ?string $model = Elemen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Referensi';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Elemen';

    protected static ?string $modelLabel = 'elemen';

    protected static ?string $pluralModelLabel = 'elemen';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function infolist(Schema $schema): Schema
    {
        return ElemenInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ElemensTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListElemens::route('/'),
            'view' => ViewElemen::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}
