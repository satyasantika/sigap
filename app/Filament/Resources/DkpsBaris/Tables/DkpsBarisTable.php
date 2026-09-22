<?php

namespace App\Filament\Resources\DkpsBaris\Tables;

use App\Enums\SumberData;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Services\VerifikatorDkps;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DkpsBarisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('butir.label_tabel')->label('Butir')->badge()->sortable(),
                TextColumn::make('butir.nama')->label('Nama butir')->wrap()->searchable(),
                TextColumn::make('tahun_acuan')->label('Tahun')->badge()->sortable(),
                TextColumn::make('sumber')->label('Sumber')->badge()
                    ->formatStateUsing(fn (SumberData $state) => $state->label())
                    ->color(fn (SumberData $state) => $state->warna())
                    // Baris manual yang sebenarnya ada di SIAKAD ditandai:
                    // asesor membandingkannya dengan SIAKAD, dan angka yang
                    // diketik tangan hampir selalu berbeda dari angka sistem.
                    ->description(fn (DkpsBaris $record) => $record->calonSelisih()
                        ? '⚠ seharusnya dari SIAKAD'
                        : null),
                TextColumn::make('diverifikasi_pada')->label('Verifikasi')
                    ->dateTime('d M Y')
                    ->placeholder('belum diverifikasi')
                    ->color(fn (DkpsBaris $record) => $record->terverifikasi() ? 'success' : 'warning')
                    ->description(fn (DkpsBaris $record) => $record->verifikator?->nama_lengkap),
                TextColumn::make('bukti_count')->label('Bukti')->counts('bukti')
                    ->color(fn ($state) => $state === 0 ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('dkps_butir_id')->label('Butir')
                    ->options(fn () => DkpsButir::orderBy('no')
                        ->get()->mapWithKeys(fn (DkpsButir $b) => [$b->id => "{$b->label_tabel} — {$b->nama}"])->all())
                    ->searchable(),
                SelectFilter::make('tahun_acuan')->label('Tahun acuan')
                    ->options(['TS' => 'TS', 'TS-1' => 'TS-1', 'TS-2' => 'TS-2', 'TS-3' => 'TS-3', 'TS-4' => 'TS-4']),
                SelectFilter::make('sumber')->label('Sumber')->options(SumberData::pilihan()),
                Filter::make('belum_diverifikasi')->label('Belum diverifikasi')
                    ->query(fn (Builder $query) => $query->whereNull('diverifikasi_pada')),
                Filter::make('calon_selisih')->label('Manual, seharusnya dari SIAKAD')
                    ->query(fn (Builder $query) => $query->where('sumber', SumberData::Manual)
                        ->whereHas('butir', fn (Builder $b) => $b->whereIn('no', DkpsBaris::BUTIR_DARI_SIAKAD))),
                Filter::make('tanpa_bukti')->label('Belum punya bukti')
                    ->query(fn (Builder $query) => $query->whereDoesntHave('bukti')),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Sunting'),
                Action::make('verifikasi')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Verifikasi menyatakan angka baris ini sudah dicocokkan dengan sumbernya.')
                    ->visible(fn (DkpsBaris $record) => ! $record->terverifikasi()
                        && auth()->user()->can('verifikasi', $record))
                    ->action(function (DkpsBaris $record): void {
                        try {
                            app(VerifikatorDkps::class)->verifikasi($record, auth()->user());

                            Notification::make()->title('Baris diverifikasi')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Belum bisa diverifikasi')
                                ->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('verifikasi_massal')
                        ->label('Verifikasi terpilih')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $oleh = auth()->user();
                            $verifikator = app(VerifikatorDkps::class);
                            $berhasil = 0;
                            $ditolak = [];

                            foreach ($records as $baris) {
                                if (! $oleh->can('verifikasi', $baris)) {
                                    continue;
                                }

                                try {
                                    $verifikator->verifikasi($baris, $oleh);
                                    $berhasil++;
                                } catch (\RuntimeException $e) {
                                    $ditolak[] = $baris->butir->label_tabel.' '.$baris->tahun_acuan;
                                }
                            }

                            Notification::make()
                                ->title("{$berhasil} baris diverifikasi")
                                ->body($ditolak === [] ? null
                                    : count($ditolak).' ditolak karena buktinya belum layak: '
                                        .implode(', ', array_slice($ditolak, 0, 5)).'.')
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
