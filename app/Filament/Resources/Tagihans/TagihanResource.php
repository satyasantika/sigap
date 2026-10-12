<?php

namespace App\Filament\Resources\Tagihans;

use App\Filament\Resources\Tagihans\Pages\EditTagihan;
use App\Filament\Resources\Tagihans\Pages\ListTagihans;
use App\Filament\Resources\Tagihans\Pages\ViewTagihan;
use App\Filament\Resources\Tagihans\Schemas\TagihanForm;
use App\Filament\Resources\Tagihans\Schemas\TagihanInfolist;
use App\Filament\Resources\Tagihans\Tables\TagihansTable;
use App\Models\Tagihan;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Semua Tagihan — layar kerja ketua.
 *
 * Kueri disaring menurut lingkup izin, bukan hanya tombolnya disembunyikan:
 * `Izin::boleh` mengembalikan true untuk lingkup `pokjanya` dan `miliknya`
 * ketika obyeknya null, sehingga penyaringan daftar menjadi tanggung jawab
 * Resource ini.
 */
class TagihanResource extends Resource
{
    protected static ?string $model = Tagihan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pengumpulan';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Semua Tagihan';

    protected static ?string $modelLabel = 'tagihan';

    protected static ?string $pluralModelLabel = 'tagihan';

    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return TagihanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TagihanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagihansTable::configure($table);
    }

    /**
     * Penyaringan lingkup di tingkat kueri.
     *
     * Tanpa ini, seorang anggota yang membuka daftar akan melihat judul
     * tagihan milik orang lain — Policy hanya menjaga barisnya, bukan
     * daftarnya.
     */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $u = auth()->user();

        if ($u === null) {
            return $q->whereRaw('1 = 0');
        }

        // Pengguna berperan ganda melihat gabungan lingkup tiap perannya.
        $lingkup = Izin::lingkup($u, 'tagihan.lihat');

        if (in_array('ya', $lingkup, true)) {
            return $q;
        }

        $pokjanya = in_array('pokjanya', $lingkup, true);
        $miliknya = in_array('miliknya', $lingkup, true);

        if (! $pokjanya && ! $miliknya) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(fn (Builder $w) => $w
            ->when($pokjanya, fn ($x) => $x->orWhereIn('pokja_id', $u->idPokjanya()))
            ->when($miliknya, fn ($x) => $x->orWhere('penanggung_jawab_id', $u->getKey())));
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'tagihan');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTagihans::route('/'),
            'view' => ViewTagihan::route('/{record}'),
            'edit' => EditTagihan::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->belumSelesai()->count();
    }
}
