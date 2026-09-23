<?php

namespace App\Filament\Pages;

use App\Enums\LevelSyaratPerlu;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\Simulasi;
use App\Services\Simulator;
use App\Support\Izin;
use App\Support\Na\Skenario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use UnitEnum;

/**
 * Layar simulasi — dua bentuk, keduanya bisa dibuat dan dihapus.
 *
 * Seluruh baris di layar ini diberi lencana "SIMULASI". Itu bukan hiasan:
 * angka NA di sini bercampur pengandaian, dan satu tangkapan layar yang
 * terlepas dari konteksnya akan dibaca sebagai nilai akreditasi sungguhan.
 *
 * Wewenangnya `simulasi.kelola` (membuat dan menghapus) dan `simulasi.lihat`
 * (membaca), keduanya dari `data/izin-sistem.json`.
 */
class SimulasiLayar extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Simulasi';

    protected static ?string $title = 'Simulasi';

    protected static ?string $slug = 'simulasi';

    protected string $view = 'filament.pages.simulasi';

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::bolehSistem(auth()->user(), 'simulasi.lihat');
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: canAccess() di atas yang menolak.
     * Lihat App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && Izin::bolehMenu(auth()->user(), 'simulasi');
    }

    private function bolehKelola(): bool
    {
        return Auth::check() && Izin::bolehSistem(Auth::user(), 'simulasi.kelola');
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->aksiBuatSkor(),
            $this->aksiBuatSandbox(),
        ];
    }

    private function aksiBuatSkor(): Action
    {
        return Action::make('buat_skor')
            ->label('Simulasi skor')
            ->icon(Heroicon::OutlinedCalculator)
            ->visible(fn () => $this->bolehKelola())
            ->modalHeading('Bagaimana jika skornya berbeda?')
            ->modalDescription('Pengandaian di bawah tidak menyentuh penilaian sungguhan. '
                .'Yang dihitung ulang hanya NA-nya, dan hasilnya hanya hidup di layar ini.')
            ->modalSubmitActionLabel('Hitung')
            ->schema([
                Select::make('periode_id')
                    ->label('Periode acuan')
                    ->options(fn () => Periode::sungguhan()->orderByDesc('ts_tahun')->pluck('nama', 'id'))
                    ->default(fn () => Periode::aktif()->value('id'))
                    ->required()
                    ->native(false),
                TextInput::make('nama')
                    ->label('Nama simulasi')
                    ->placeholder('Andai E58 dan E12 naik')
                    ->required()
                    ->maxLength(120),
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('Apa yang ingin dijawab simulasi ini?')
                    ->rows(2)
                    ->maxLength(500),
                Repeater::make('skor')
                    ->label('Skor yang diandaikan')
                    ->schema([
                        Select::make('elemen_id')
                            ->label('Elemen')
                            ->options(fn () => Elemen::orderBy('no')
                                ->get(['id', 'no', 'nama'])
                                ->mapWithKeys(fn (Elemen $e) => [$e->id => "E{$e->no} — {$e->nama}"]))
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->distinct(),
                        Select::make('skor')
                            ->label('Skor')
                            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4'])
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2)
                    ->addActionLabel('Tambah elemen')
                    ->default([]),
                Repeater::make('syarat_perlu')
                    ->label('Syarat perlu yang diandaikan')
                    ->helperText('NA tinggi tanpa syarat perlu tetap berujung Terakreditasi, bukan Unggul. '
                        .'Di sinilah pengaruhnya terlihat.')
                    ->schema([
                        Select::make('elemen_id')
                            ->label('Elemen syarat perlu')
                            ->options(fn () => Elemen::bersyaratPerlu()
                                ->orderBy('no')
                                ->get(['id', 'no', 'nama'])
                                ->mapWithKeys(fn (Elemen $e) => [$e->id => "E{$e->no} — {$e->nama}"]))
                            ->required()
                            ->native(false)
                            ->distinct(),
                        Select::make('level')
                            ->label('Level')
                            ->options(collect(LevelSyaratPerlu::cases())
                                ->mapWithKeys(fn (LevelSyaratPerlu $l) => [$l->value => $l->label()]))
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2)
                    ->addActionLabel('Tambah syarat perlu')
                    ->default([]),
            ])
            ->action(function (array $data, Simulator $simulator) {
                $skenario = new Skenario(
                    skor: collect($data['skor'] ?? [])
                        ->mapWithKeys(fn (array $b) => [$b['elemen_id'] => (int) $b['skor']])
                        ->all(),
                    syaratPerlu: collect($data['syarat_perlu'] ?? [])
                        ->mapWithKeys(fn (array $b) => [$b['elemen_id'] => LevelSyaratPerlu::from($b['level'])])
                        ->all(),
                );

                $this->jalankan(fn () => $simulator->buatSimulasiSkor(
                    Auth::user(),
                    Periode::findOrFail($data['periode_id']),
                    $data['nama'],
                    $skenario,
                    $data['keterangan'] ?? null,
                ), 'Simulasi skor dibuat.');
            });
    }

    private function aksiBuatSandbox(): Action
    {
        return Action::make('buat_sandbox')
            ->label('Periode latihan')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->color('gray')
            ->visible(fn () => $this->bolehKelola())
            ->modalHeading('Buat periode latihan')
            ->modalDescription('Menyalin kerangka periode acuan — pokja dan seluruh tagihannya — '
                .'ke periode baru yang bertanda SIMULASI. Narasi, bukti, dan penilaian TIDAK ikut '
                .'disalin; periode latihan dimulai kosong. Bisa dibuang utuh kapan saja.')
            ->modalSubmitActionLabel('Buat')
            ->schema([
                Select::make('periode_id')
                    ->label('Periode acuan')
                    ->options(fn () => Periode::sungguhan()->orderByDesc('ts_tahun')->pluck('nama', 'id'))
                    ->default(fn () => Periode::aktif()->value('id'))
                    ->required()
                    ->native(false),
                TextInput::make('nama')
                    ->label('Nama periode latihan')
                    ->placeholder('Latihan anggota baru')
                    ->helperText('Akan diberi awalan "[SIMULASI]" secara otomatis.')
                    ->required()
                    ->maxLength(60),
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (array $data, Simulator $simulator) {
                $this->jalankan(fn () => $simulator->buatSandbox(
                    Auth::user(),
                    Periode::findOrFail($data['periode_id']),
                    $data['nama'],
                    $data['keterangan'] ?? null,
                ), 'Periode latihan dibuat.');
            });
    }

    /** Membungkus penolakan wewenang menjadi pemberitahuan, bukan galat 500. */
    private function jalankan(callable $kerja, string $pesan): void
    {
        try {
            $kerja();
        } catch (RuntimeException $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($pesan)->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Simulasi::query()->with(['periode', 'periodeSandbox', 'pembuat']))
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->description(fn (Simulasi $record) => str($record->keterangan ?? '')->limit(90))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'skor' ? 'Pengandaian skor' : 'Periode latihan')
                    ->color(fn (string $state) => $state === 'skor' ? 'info' : 'gray'),
                TextColumn::make('periode.nama')->label('Acuan')->toggleable(),
                TextColumn::make('hasil.na')
                    ->label('NA simulasi')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 2, ',', '.')),
                TextColumn::make('hasil.status')
                    ->label('Status simulasi')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('periodeSandbox.nama')
                    ->label('Periode latihan')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('pembuat.nama_lengkap')->label('Dibuat oleh')->toggleable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d F Y, H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options(['skor' => 'Pengandaian skor', 'periode' => 'Periode latihan']),
            ])
            ->recordActions([
                Action::make('hitung_ulang')
                    ->label('Hitung ulang')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Simulasi $record) => $this->bolehKelola() && $record->jenis === 'skor')
                    ->action(function (Simulasi $record, Simulator $simulator) {
                        $banding = $simulator->bandingkan($record);
                        $simulator->perbaruiHasil($record);

                        Notification::make()
                            ->title('NA simulasi '.number_format($banding['simulasi']->na, 2, ',', '.'))
                            ->body('Sungguhan '.number_format($banding['nyata']->na, 2, ',', '.')
                                .' — selisih '.number_format($banding['selisih'], 2, ',', '.')
                                .($banding['statusBerubah'] ? '. Statusnya berubah.' : '. Statusnya tetap.'))
                            ->info()
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Hapus')
                    ->visible(fn () => $this->bolehKelola())
                    ->modalHeading('Hapus simulasi?')
                    ->modalDescription(fn (Simulasi $record) => $record->jenis === 'periode'
                        ? 'Periode latihan "'.($record->periodeSandbox?->nama ?? '—').'" ikut dibuang '
                            .'BESERTA seluruh tagihan, narasi, dan bukti latihan di dalamnya. '
                            .'Tidak bisa dikembalikan. Data periode sungguhan tidak tersentuh.'
                        : 'Pengandaian ini dibuang. Tidak ada data penilaian yang tersentuh — '
                            .'simulasi skor memang tidak pernah menulis ke sana.')
                    ->using(fn (Simulasi $record, Simulator $simulator) => $simulator->hapus(Auth::user(), $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada simulasi')
            ->emptyStateDescription('Simulasi dibuat dan dihapus seperlunya. Tidak ada yang permanen di sini.');
    }
}
