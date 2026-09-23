<?php

namespace App\Filament\Resources\Narasis;

use App\Filament\Resources\Narasis\Pages\ListNarasis;
use App\Filament\Resources\Narasis\Tables\NarasisTable;
use App\Models\Narasi;
use App\Support\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Naskah LED: 59 elemen dalam satu daftar.
 *
 * Penulisannya dilakukan di layar pengerjaan tagihan, bukan di sini —
 * di sana panduan, parameter, dan bukti tertautnya terlihat sekaligus.
 * Daftar ini gunanya melihat kemajuan menyeluruh: mana yang masih kosong,
 * mana yang kurang kata, mana yang belum punya bukti.
 */
class NarasiResource extends Resource
{
    protected static ?string $model = Narasi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Pengumpulan';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Naskah LED';

    protected static ?string $modelLabel = 'naskah';

    protected static ?string $pluralModelLabel = 'naskah LED';

    public static function table(Table $table): Table
    {
        return NarasisTable::configure($table);
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: penolakannya tetap milik Policy. Lihat
     * App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && Izin::bolehMenu(auth()->user(), 'narasi');
    }

    public static function getPages(): array
    {
        return ['index' => ListNarasis::route('/')];
    }
}
