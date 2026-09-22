<?php

namespace App\Filament\Resources\Kriterias;

use App\Filament\Resources\Kriterias\Pages\ListKriterias;
use App\Filament\Resources\Kriterias\Pages\ViewKriteria;
use App\Filament\Resources\Kriterias\Schemas\KriteriaInfolist;
use App\Filament\Resources\Kriterias\Tables\KriteriasTable;
use App\Models\Kriteria;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Hanya-baca. Lihat App\Policies\ReferensiPolicy. */
class KriteriaResource extends Resource
{
    protected static ?string $model = Kriteria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Referensi';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Kriteria';

    protected static ?string $modelLabel = 'kriteria';

    protected static ?string $pluralModelLabel = 'kriteria';

    protected static ?string $recordTitleAttribute = 'nama';

    public static function infolist(Schema $schema): Schema
    {
        return KriteriaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KriteriasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKriterias::route('/'),
            'view' => ViewKriteria::route('/{record}'),
        ];
    }
}
