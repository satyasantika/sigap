<?php

namespace App\Filament\Resources\Prodis;

use App\Filament\Resources\Prodis\Pages\CreateProdi;
use App\Filament\Resources\Prodis\Pages\EditProdi;
use App\Filament\Resources\Prodis\Pages\ListProdis;
use App\Filament\Resources\Prodis\Schemas\ProdiForm;
use App\Filament\Resources\Prodis\Tables\ProdisTable;
use App\Models\Prodi;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProdiResource extends Resource
{
    protected static ?string $model = Prodi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Prodi';

    protected static ?string $modelLabel = 'prodi';

    protected static ?string $pluralModelLabel = 'prodi';

    public static function form(Schema $schema): Schema
    {
        return ProdiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProdisTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'prodi');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProdis::route('/'),
            'create' => CreateProdi::route('/create'),
            'edit' => EditProdi::route('/{record}/edit'),
        ];
    }
}
