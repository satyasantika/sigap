<?php

namespace App\Filament\Resources\Narasis\Tables;

use App\Models\Kriteria;
use App\Models\Narasi;
use App\Services\PengelolaNarasi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NarasisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('elemen.no')->label('No')->sortable()->width('1%'),
                TextColumn::make('elemen.nama')->label('Elemen')->searchable()->wrap()
                    ->description(fn (Narasi $record) => $record->elemen?->syarat_perlu ? '⚠ SYARAT PERLU' : null)
                    ->color(fn (Narasi $record) => $record->elemen?->syarat_perlu ? 'danger' : null),
                TextColumn::make('elemen.kriteria.kode')->label('Kriteria')->badge()->sortable(),

                // Baris di bawah 200 kata diwarnai — itu yang harus dikerjakan,
                // dan mata harus menemukannya tanpa menyaring dulu.
                TextColumn::make('jumlah_kata')->label('Kata')->sortable()
                    ->color(fn (Narasi $record) => match (true) {
                        $record->jumlah_kata === 0 => 'gray',
                        $record->jumlah_kata < PengelolaNarasi::MINIMAL_KATA => 'danger',
                        $record->jumlah_kata > PengelolaNarasi::MAKSIMAL_KATA => 'warning',
                        default => 'success',
                    })
                    ->weight(fn (Narasi $record) => $record->jumlah_kata < PengelolaNarasi::MINIMAL_KATA ? 'bold' : null)
                    ->description(fn (Narasi $record) => match (true) {
                        $record->jumlah_kata === 0 => 'belum ditulis',
                        $record->jumlah_kata < PengelolaNarasi::MINIMAL_KATA => 'kurang '
                            .(PengelolaNarasi::MINIMAL_KATA - $record->jumlah_kata).' kata',
                        $record->jumlah_kata > PengelolaNarasi::MAKSIMAL_KATA => 'melewati anjuran',
                        default => null,
                    }),

                TextColumn::make('bukti_count')->label('Bukti')
                    ->state(fn (Narasi $record) => DB::table('bukti_elemen')
                        ->join('bukti', 'bukti.id', '=', 'bukti_elemen.bukti_id')
                        ->where('bukti_elemen.elemen_id', $record->elemen_id)
                        ->where('bukti.periode_id', $record->periode_id)
                        ->whereNull('bukti.deleted_at')->count())
                    ->color(fn ($state) => $state === 0 ? 'danger' : 'success')
                    ->description(fn ($state) => $state === 0 ? 'belum ada bukti' : null),

                TextColumn::make('versi')->label('Versi')->toggleable(),
                TextColumn::make('penulis.nama_lengkap')->label('Penulis terakhir')
                    ->placeholder('—')->toggleable(),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kriteria')->label('Kriteria')
                    ->options(fn () => Kriteria::orderBy('urutan')->pluck('kode', 'id')->all())
                    ->query(fn (Builder $q, array $data) => $q->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $v) => $q->whereHas('elemen', fn (Builder $e) => $e->where('kriteria_id', $v)),
                    )),
                Filter::make('kurang_kata')->label('Di bawah 200 kata')
                    ->query(fn (Builder $q) => $q->where('jumlah_kata', '<', PengelolaNarasi::MINIMAL_KATA)),
                Filter::make('tanpa_bukti')->label('Belum punya bukti')
                    ->query(fn (Builder $q) => $q->whereDoesntHave('elemen', fn (Builder $e) => $e
                        ->whereHas('bukti'))),
                Filter::make('syarat_perlu')->label('Hanya elemen syarat perlu')
                    ->query(fn (Builder $q) => $q->whereHas('elemen', fn (Builder $e) => $e->where('syarat_perlu', true))),
            ])
            ->defaultSort('elemen.no')
            ->paginated([25, 59, 100])
            ->defaultPaginationPageOption(59)
            ->emptyStateHeading('Belum ada naskah')
            ->emptyStateDescription('Naskah lahir saat layar pengerjaan tagihan narasi dibuka pertama kali.');
    }
}
