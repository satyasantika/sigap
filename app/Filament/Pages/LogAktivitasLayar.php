<?php

namespace App\Filament\Pages;

use App\Models\LogAktivitas;
use App\Support\Izin;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Log aktivitas — tempat jejak penyamaran bisa dibaca.
 *
 * Halaman ini adalah syarat yang membuat impersonasi boleh ada. Wewenang untuk
 * menjadi orang lain tanpa tempat membaca apa yang dilakukan selama menjadi
 * orang lain bukan fitur pengelolaan, melainkan pintu belakang. Karena itu
 * `auditor` juga membacanya, bukan admin saja: yang diperiksa tidak boleh
 * menjadi satu-satunya yang memegang catatan pemeriksaan.
 *
 * Hanya-baca dan memang tidak bisa dibuat bisa-tulis — modelnya menolak
 * `update` dan `delete` di tingkat peristiwa Eloquent.
 */
class LogAktivitasLayar extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static ?string $title = 'Log Aktivitas';

    protected static ?string $slug = 'log-aktivitas';

    protected string $view = 'filament.pages.log-aktivitas';

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::bolehSistem(auth()->user(), 'impersonasi.log');
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: canAccess() di atas yang menolak.
     * Lihat App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && Izin::bolehMenu(auth()->user(), 'log_aktivitas');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LogAktivitas::query()->with(['user', 'admin']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d F Y, H:i:s')
                    ->sortable(),
                TextColumn::make('user.nama_lengkap')
                    ->label('Bertindak sebagai')
                    ->placeholder('pengguna terhapus')
                    ->searchable(),
                TextColumn::make('admin.nama_lengkap')
                    ->label('Sebenarnya')
                    ->placeholder('— dirinya sendiri')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('aksi')
                    ->label('Aksi')
                    ->badge()
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('lewat_impersonasi')
                    ->label('Hanya yang lewat penyamaran')
                    // Argumen ditangkap Filament berdasarkan NAMA parameter,
                    // bukan tipenya. Menamainya $q membuat saringan ini diam-diam
                    // tidak berfungsi. Lihat CATATAN-SERAH-TERIMA.md bagian 4.1.
                    ->query(fn (Builder $query) => $query->whereNotNull('impersonasi_oleh')),
                SelectFilter::make('aksi')
                    ->label('Aksi')
                    ->options(fn () => LogAktivitas::query()
                        ->distinct()
                        ->orderBy('aksi')
                        ->pluck('aksi', 'aksi')
                        ->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Belum ada aktivitas tercatat')
            ->emptyStateDescription('Log terisi sendiri begitu ada penyamaran atau tindakan yang dicatat.');
    }
}
