<?php

namespace App\Filament\Pages;

use App\Enums\LevelSyaratPerlu;
use App\Models\Elemen;
use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\StatusSyaratPerlu;
use App\Support\Izin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Lima kartu syarat perlu.
 *
 * Ambang tiga dan lima tahun BERDAMPINGAN dengan nilai terukurnya, karena
 * pada E17 dan E51 ambang skor 4 justru lebih rendah daripada ambang lima
 * tahun — menampilkan salah satunya saja mengundang kesimpulan yang salah.
 *
 * Levelnya ditetapkan manusia, bukan diturunkan otomatis dari nilai_rumus:
 * sebagian ambangnya majemuk dan berbunyi kalimat, dan tiga dari lima berupa
 * ambang skor yang lahir dari penilaian rubrik.
 */
class SyaratPerluLayar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Syarat Perlu';

    protected static ?string $title = 'Syarat Perlu';

    protected static ?string $slug = 'syarat-perlu';

    protected string $view = 'filament.pages.syarat-perlu';

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::boleh(auth()->user(), 'dasbor.lihat');
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: canAccess() di atas yang menolak.
     * Lihat App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && Izin::bolehMenu(auth()->user(), 'syarat_perlu');
    }

    public function periode(): ?Periode
    {
        return Periode::aktif()->first();
    }

    /**
     * Lima kartu beserta ambang, nilai terukur, dan levelnya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function kartu(): array
    {
        $periode = $this->periode();

        if ($periode === null) {
            return [];
        }

        $status = StatusSyaratPerlu::where('periode_id', $periode->id)->get()->keyBy('elemen_id');

        return Elemen::bersyaratPerlu()->with('syaratPerlu')->orderBy('no')->get()
            ->map(function (Elemen $e) use ($periode, $status) {
                $baris = $status[$e->id] ?? null;

                return [
                    'elemen' => $e,
                    'syarat' => $e->syaratPerlu,
                    'level' => $baris?->level ?? LevelSyaratPerlu::Belum,
                    'nilai_terukur' => $baris?->nilai_terukur,
                    'catatan' => $baris?->catatan,
                    // Nilai dari rumus, bila ada — supaya keputusan manusia
                    // punya angka pendukung, bukan sekadar perasaan.
                    'dari_rumus' => $this->nilaiDariRumus($periode, $e),
                ];
            })->all();
    }

    /** @return array<string, mixed>|null */
    private function nilaiDariRumus(Periode $periode, Elemen $elemen): ?array
    {
        $rumus = $elemen->rumus()->pluck('kode');

        if ($rumus->isEmpty()) {
            return null;
        }

        $nilai = NilaiRumus::where('periode_id', $periode->id)
            ->whereIn('rumus_kode', $rumus)
            ->orderByDesc('id')->first();

        if ($nilai === null) {
            return null;
        }

        return [
            'kode' => $nilai->rumus_kode,
            'nilai' => $nilai->nilai,
            'syarat3' => $nilai->memenuhi_syarat_3_tahun,
            'syarat5' => $nilai->memenuhi_syarat_5_tahun,
            'dihitung' => $nilai->dihitung_pada,
        ];
    }

    public function sudahDiLima(): int
    {
        return collect($this->kartu())->filter(fn (array $k) => $k['level']->memenuhiLima())->count();
    }

    public function aksiUbahLevel(): Action
    {
        return Action::make('ubah_level')
            ->label('Tetapkan level')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn () => auth()->check() && Izin::boleh(auth()->user(), 'syarat_perlu.ubah'))
            ->schema([
                Select::make('elemen_id')
                    ->label('Elemen')
                    ->options(fn () => Elemen::bersyaratPerlu()->orderBy('no')->get()
                        ->mapWithKeys(fn (Elemen $e) => [$e->id => "E{$e->no} — {$e->nama}"])->all())
                    ->required()->native(false),
                Select::make('level')
                    ->label('Level pemenuhan')
                    ->options(LevelSyaratPerlu::pilihan())
                    ->required()->native(false),
                TextInput::make('nilai_terukur')
                    ->label('Nilai terukur')
                    ->helperText('Angka pendukung keputusan ini, misalnya "PDS3 = 41,67".')
                    ->maxLength(100),
                Textarea::make('catatan')->label('Catatan')->rows(3),
            ])
            ->action(function (array $data): void {
                $periode = $this->periode();

                StatusSyaratPerlu::updateOrCreate(
                    ['periode_id' => $periode->id, 'elemen_id' => $data['elemen_id']],
                    [
                        'prodi_id' => $periode->prodi_id,
                        'level' => $data['level'],
                        'nilai_terukur' => $data['nilai_terukur'] ?? null,
                        'catatan' => $data['catatan'] ?? null,
                        'diperbarui_oleh' => auth()->id(),
                    ],
                );

                Notification::make()->title('Level syarat perlu diperbarui')->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [$this->aksiUbahLevel()];
    }
}
