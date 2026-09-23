<?php

namespace App\Filament\Resources\Buktis;

use App\Filament\Resources\Buktis\Pages\CreateBukti;
use App\Filament\Resources\Buktis\Pages\EditBukti;
use App\Filament\Resources\Buktis\Pages\ListBuktis;
use App\Filament\Resources\Buktis\Pages\ViewBukti;
use App\Filament\Resources\Buktis\Schemas\BuktiForm;
use App\Filament\Resources\Buktis\Schemas\BuktiInfolist;
use App\Filament\Resources\Buktis\Tables\BuktisTable;
use App\Models\Bukti;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BuktiResource extends Resource
{
    protected static ?string $model = Bukti::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    protected static string|UnitEnum|null $navigationGroup = 'Pengumpulan';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Bukti';

    protected static ?string $modelLabel = 'bukti';

    protected static ?string $pluralModelLabel = 'bukti';

    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return BuktiForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BuktiInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BuktisTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'bukti');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuktis::route('/'),
            'create' => CreateBukti::route('/create'),
            'view' => ViewBukti::route('/{record}'),
            'edit' => EditBukti::route('/{record}/edit'),
        ];
    }

    /** Bukti yang tidak bisa dibuka asesor: angka yang harus terlihat. */
    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::query()->bermasalah()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
