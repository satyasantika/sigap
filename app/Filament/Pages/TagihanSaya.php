<?php

namespace App\Filament\Pages;

use App\Enums\JenisTagihan;
use App\Enums\StatusTagihan;
use App\Filament\Resources\Tagihans\TagihanResource;
use App\Models\Tagihan;
use App\Support\Izin;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Layar harian anggota pokja: hanya tagihan miliknya, hanya yang belum
 * selesai, terurut tenggat terdekat.
 *
 * Sengaja halaman tersendiri, bukan saringan di Semua Tagihan. Orang yang
 * membuka panel pagi hari ingin melihat apa yang harus dikerjakan hari ini —
 * bukan 137 baris yang harus disaring dulu setiap kali.
 */
class TagihanSaya extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Pengumpulan';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Tagihan Saya';

    protected static ?string $title = 'Tagihan Saya';

    protected string $view = 'filament.pages.tagihan-saya';

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::boleh(auth()->user(), 'tagihan.lihat');
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: canAccess() di atas yang menolak.
     * Lihat App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && Izin::bolehMenu(auth()->user(), 'tagihan_saya');
    }

    public static function getNavigationBadge(): ?string
    {
        $jumlah = static::kueriDasar()->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::kueriDasar()->terlambat()->exists() ? 'danger' : 'primary';
    }

    private static function kueriDasar(): Builder
    {
        $u = auth()->user();

        if ($u === null) {
            return Tagihan::query()->whereRaw('1 = 0');
        }

        return Tagihan::query()->milik($u)->belumSelesai();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => static::kueriDasar())
            ->columns([
                TextColumn::make('judul')->label('Tagihan')->searchable()->wrap()
                    ->description(fn (Tagihan $r) => $r->elemen
                        ? 'E'.$r->elemen->no.($r->elemen->syarat_perlu ? ' · ⚠ SYARAT PERLU' : '')
                        : $r->dkpsButir?->label_tabel)
                    ->color(fn (Tagihan $r) => $r->elemen?->syarat_perlu ? 'danger' : null),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (JenisTagihan $state) => $state->label())
                    ->color(fn (JenisTagihan $state) => $state->warna()),
                TextColumn::make('tenggat')->label('Tenggat')->date('d M Y')->sortable()
                    ->placeholder('belum ditetapkan')
                    ->color(fn (Tagihan $r) => $r->terlambat() ? 'danger' : null)
                    ->weight(fn (Tagihan $r) => $r->terlambat() ? 'bold' : null)
                    ->description(fn (Tagihan $r) => $r->terlambat() ? 'TERLAMBAT' : null),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusTagihan $state) => $state->label())
                    ->color(fn (StatusTagihan $state) => $state->warna()),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka')
                    ->url(fn (Tagihan $r) => TagihanResource::getUrl('view', ['record' => $r])),
            ])
            // Tenggat terdekat lebih dulu; yang belum bertenggat di belakang,
            // bukan di depan seperti yang dilakukan NULL secara bawaan.
            ->defaultSort('tenggat')
            ->emptyStateHeading('Tidak ada tagihan untuk Anda')
            ->emptyStateDescription('Semua tagihan Anda sudah disetujui, atau belum ada yang ditugaskan.');
    }
}
