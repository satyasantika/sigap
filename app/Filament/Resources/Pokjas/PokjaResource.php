<?php

namespace App\Filament\Resources\Pokjas;

use App\Filament\Resources\Pokjas\Pages\CreatePokja;
use App\Filament\Resources\Pokjas\Pages\EditPokja;
use App\Filament\Resources\Pokjas\Pages\ListPokjas;
use App\Filament\Resources\Pokjas\RelationManagers\AnggotaRelationManager;
use App\Filament\Resources\Pokjas\Schemas\PokjaForm;
use App\Filament\Resources\Pokjas\Tables\PokjasTable;
use App\Models\Pokja;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Pengguna yang hanya boleh mengelola anggota pokjanya sendiri tidak
     * melihat pokja lain. Policy menjaga barisnya; kueri ini menjaga daftarnya.
     */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $u = auth()->user();

        if ($u === null) {
            return $q->whereRaw('1 = 0');
        }

        if (in_array('ya', Izin::lingkup($u, 'pokja.anggota'), true)
            || in_array('ya', Izin::lingkup($u, 'pokja.kelola'), true)) {
            return $q;
        }

        return $q->whereIn($q->getModel()->getQualifiedKeyName(), $u->idPokjanya());
    }

    public static function getRelations(): array
    {
        return [AnggotaRelationManager::class];
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
