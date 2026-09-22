<?php

namespace App\Filament\Resources\NilaiRumuses\Tables;

use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\SyaratPerlu;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NilaiRumusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rumus_kode')->label('Rumus')->badge()->searchable()->sortable(),
                TextColumn::make('rumus.nama')->label('Nama')->wrap()->toggleable(),
                TextColumn::make('nilai')->label('Nilai terukur')->sortable()
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '—' : number_format((float) $state, 2, ',', '.')),
                TextColumn::make('skor')->label('Skor')->badge()
                    ->color(fn (?int $state) => match ($state) {
                        4 => 'success', 3 => 'info', 2 => 'warning', 1 => 'danger', default => 'gray',
                    })
                    ->placeholder('—'),

                // Ambang 3 dan 5 tahun BERDAMPINGAN dengan nilai terukur.
                // Menampilkan skor saja membuat orang menyimpulkan "skor 4,
                // berarti aman" — padahal pada PDS3 dan PPDTPS ambang syarat
                // perlu justru lebih tinggi daripada ambang skor penuh.
                TextColumn::make('ambang_3')->label('Ambang 3 tahun')->wrap()
                    ->state(fn (NilaiRumus $record) => self::ambang($record, 'ambang_3_tahun'))
                    ->placeholder('—')
                    ->description(fn (NilaiRumus $record) => $record->memenuhi_syarat_3_tahun === null
                        ? null : ($record->memenuhi_syarat_3_tahun ? '✓ terpenuhi' : '✗ belum terpenuhi'))
                    ->color(fn (NilaiRumus $record) => $record->memenuhi_syarat_3_tahun === null
                        ? null : ($record->memenuhi_syarat_3_tahun ? 'success' : 'danger')),

                TextColumn::make('ambang_5')->label('Ambang 5 tahun')->wrap()
                    ->state(fn (NilaiRumus $record) => self::ambang($record, 'ambang_5_tahun'))
                    ->placeholder('—')
                    ->description(fn (NilaiRumus $record) => $record->memenuhi_syarat_5_tahun === null
                        ? null : ($record->memenuhi_syarat_5_tahun ? '✓ terpenuhi' : '✗ belum terpenuhi'))
                    ->color(fn (NilaiRumus $record) => $record->memenuhi_syarat_5_tahun === null
                        ? null : ($record->memenuhi_syarat_5_tahun ? 'success' : 'danger')),

                TextColumn::make('dihitung_pada')->label('Dihitung')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('catatan')->label('Catatan')->wrap()->toggleable()
                    ->color('warning')->placeholder('—'),
            ])
            ->filters([
                Filter::make('terakhir')->label('Hanya hasil terakhir tiap rumus')
                    ->default()
                    ->query(function (Builder $q) {
                        $periode = Periode::aktif()->first();

                        return $periode === null ? $q : $q->terakhir($periode->id);
                    }),
                Filter::make('syarat_perlu')->label('Hanya rumus terkait syarat perlu')
                    ->query(fn (Builder $q) => $q->whereNotNull('memenuhi_syarat_5_tahun')),
            ])
            ->defaultSort('dihitung_pada', 'desc')
            ->emptyStateHeading('Belum ada perhitungan')
            ->emptyStateDescription('Nilai muncul setelah data DKPS diisi dan rumusnya dihitung.');
    }

    /** Ambang syarat perlu elemen yang ditopang rumus ini. */
    private static function ambang(NilaiRumus $record, string $kolom): ?string
    {
        $elemenId = $record->rumus?->elemen_id;

        if ($elemenId === null) {
            return null;
        }

        return SyaratPerlu::where('elemen_id', $elemenId)->value($kolom);
    }
}
