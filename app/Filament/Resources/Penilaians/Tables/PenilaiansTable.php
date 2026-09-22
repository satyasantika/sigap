<?php

namespace App\Filament\Resources\Penilaians\Tables;

use App\Models\Elemen;
use App\Models\Penilaian;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenilaiansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('elemen.no')->label('No')->sortable()->width('1%'),
                TextColumn::make('elemen.nama')->label('Elemen')->wrap()->searchable()
                    ->description(fn (Penilaian $record) => $record->elemen?->syarat_perlu ? '⚠ SYARAT PERLU' : null)
                    ->color(fn (Penilaian $record) => $record->elemen?->syarat_perlu ? 'danger' : null),
                TextColumn::make('elemen.bobot')->label('Bobot')->toggleable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('skor')->label('Skor')->badge()->sortable()
                    ->color(fn (int $state) => match ($state) {
                        4 => 'success', 3 => 'info', 2 => 'warning', default => 'danger',
                    }),
                TextColumn::make('penilai.nama_lengkap')->label('Penilai')->searchable(),

                // Selisih antarpenilai ditampilkan di baris: elemen yang dinilai
                // 4 oleh satu orang dan 2 oleh yang lain hampir selalu elemen
                // yang buktinya belum meyakinkan.
                TextColumn::make('selisih')->label('Selisih')
                    ->state(function (Penilaian $record) {
                        $lain = Penilaian::where('periode_id', $record->periode_id)
                            ->where('elemen_id', $record->elemen_id)
                            ->where('id', '!=', $record->id)
                            ->with('penilai:id,nama_lengkap')->get();

                        if ($lain->isEmpty()) {
                            return null;
                        }

                        return $lain->map(fn (Penilaian $p) => $p->penilai->nama_lengkap.': '.$p->skor)->join(', ');
                    })
                    ->placeholder('—')
                    ->color(fn ($state) => $state === null ? null : 'warning')
                    ->wrap(),

                TextColumn::make('tanggal')->label('Tanggal')->date('d F Y')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('skor')->label('Skor')
                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4']),
                SelectFilter::make('penilai_id')->label('Penilai')->relationship('penilai', 'nama_lengkap'),
                Filter::make('syarat_perlu')->label('Hanya elemen syarat perlu')
                    ->query(fn (Builder $q) => $q->whereHas('elemen', fn (Builder $e) => $e->where('syarat_perlu', true))),
                Filter::make('diperselisihkan')->label('Diperselisihkan antarpenilai')
                    ->query(fn (Builder $q) => $q->whereIn('elemen_id', function ($sub) {
                        $sub->select('elemen_id')->from('penilaian')
                            ->whereNull('deleted_at')
                            ->groupBy('elemen_id', 'periode_id')
                            ->havingRaw('COUNT(DISTINCT skor) > 1');
                    })),
            ])
            ->recordActions([EditAction::make()->label('Sunting')])
            ->defaultSort('elemen.no')
            ->paginated([25, 59, 100])
            ->emptyStateHeading('Belum ada penilaian')
            ->emptyStateDescription('Elemen yang belum dinilai diandaikan berskor 3 pada proyeksi NA.');
    }
}
