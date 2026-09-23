<?php

namespace App\Filament\Resources\Pokjas;

use App\Filament\Resources\Pokjas\Pages\CreatePokja;
use App\Filament\Resources\Pokjas\Pages\EditPokja;
use App\Filament\Resources\Pokjas\Pages\ListPokjas;
use App\Filament\Resources\Pokjas\Schemas\PokjaForm;
use App\Filament\Resources\Pokjas\Tables\PokjasTable;
use App\Models\Pokja;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PokjaResource extends Resource
{
    protected static ?string $model = Pokja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Pokja';

    protected static ?string $modelLabel = 'pokja';

    protected static ?string $pluralModelLabel = 'pokja';

    public static function form(Schema $schema): Schema
    {
        return PokjaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PokjasTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'pokja');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPokjas::route('/'),
            'create' => CreatePokja::route('/create'),
            'edit' => EditPokja::route('/{record}/edit'),
        ];
    }
}
