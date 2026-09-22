<?php

namespace App\Filament\Resources\Buktis\Tables;

use App\Enums\AksesTautan;
use App\Enums\JenisBukti;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use App\Services\PengelolaBukti;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BuktisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->label('Bukti')->searchable()->wrap()
                    ->description(fn (Bukti $record) => $record->nama_asli ?? $record->url_kanonik),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (JenisBukti $state) => $state->label())
                    ->color(fn (JenisBukti $state) => $state->warna()),
                TextColumn::make('elemen_count')->label('Elemen')->counts('elemen')
                    ->tooltip('Berapa elemen yang ditopang bukti ini'),

                // DUA LENCANA, selalu. Menampilkan salah satunya saja membuat
                // orang mengira buktinya beres padahal baru satu sumbu hijau.
                TextColumn::make('akses_status')->label('Keterbacaan')->badge()
                    ->formatStateUsing(fn (AksesTautan $state) => $state->label())
                    ->color(fn (AksesTautan $state) => $state->warna())
                    ->description(fn (Bukti $record) => $record->akses_diperiksa_pada?->diffForHumans())
                    ->placeholder('—'),
                TextColumn::make('validasi_status')->label('Keabsahan')->badge()
                    ->formatStateUsing(fn (ValidasiBukti $state) => $state->label())
                    ->color(fn (ValidasiBukti $state) => $state->warna())
                    ->description(fn (Bukti $record) => $record->validator?->nama_lengkap),

                TextColumn::make('tanggal_kejadian')->label('Tanggal kejadian')->date('d M Y')->sortable(),
                TextColumn::make('sumber')->label('Sumber')->badge()->toggleable()
                    ->formatStateUsing(fn (SumberData $state) => $state->label())
                    ->color(fn (SumberData $state) => $state->warna()),
            ])
            ->filters([
                SelectFilter::make('elemen')->label('Elemen')->relationship('elemen', 'no'),
                SelectFilter::make('jenis')->label('Jenis')->options(JenisBukti::pilihan()),
                SelectFilter::make('sumber')->label('Sumber')->options(SumberData::pilihan()),
                SelectFilter::make('akses_status')->label('Keterbacaan')->options(AksesTautan::pilihan())->multiple(),
                SelectFilter::make('validasi_status')->label('Keabsahan')->options(ValidasiBukti::pilihan())->multiple(),
                Filter::make('bermasalah')->label('Tidak bisa dibuka asesor')
                    ->query(fn (Builder $query) => $query->bermasalah()),
                Filter::make('perlu_ditinjau')->label('Antre divalidasi')
                    ->query(fn (Builder $query) => $query->perluDitinjau()),
                Filter::make('tanggal_kejadian')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal')->native(false),
                        DatePicker::make('sampai')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn (Builder $query, $v) => $query->whereDate('tanggal_kejadian', '>=', $v))
                        ->when($data['sampai'] ?? null, fn (Builder $query, $v) => $query->whereDate('tanggal_kejadian', '<=', $v))),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Sunting'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Aksi massal HANYA untuk menandai sah. `meragukan` dan
                    // `tidak_sah` menuntut catatan per bukti, dan catatan massal
                    // yang sama untuk sepuluh bukti berbeda tidak menolong siapa
                    // pun (vibecoding/docs/07 bagian validasi).
                    BulkAction::make('tandai_sah')
                        ->label('Tandai sah')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Hanya untuk bukti sejenis yang sudah Anda periksa. Menandai "meragukan" atau "tidak sah" harus satu per satu, karena keduanya menuntut catatan.')
                        ->action(function (Collection $records): void {
                            $oleh = auth()->user();
                            $pengelola = app(PengelolaBukti::class);
                            $berubah = 0;

                            foreach ($records as $bukti) {
                                if (! $oleh->can('validasi', $bukti)) {
                                    continue;
                                }

                                $pengelola->validasi($bukti, ValidasiBukti::Sah, $oleh);
                                $berubah++;
                            }

                            Notification::make()
                                ->title("{$berubah} bukti ditandai sah")
                                ->body($berubah < $records->count()
                                    ? ($records->count() - $berubah).' dilewati karena di luar wewenang Anda.'
                                    : null)
                                ->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
