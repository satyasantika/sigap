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
                TextColumn::make('skor_tepat')->label('Skor A')->sortable()
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
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

                // Variabel DKPS yang dipakai, supaya angka bisa dilacak ke lembar
                // sumbernya. Hasil hitungan lama (sebelum katalog variabel) tidak
                // punya kunci `variabel` dan tampil kosong. State dibentuk
                // langsung menjadi teks: kolom berstate larik dipecah Filament
                // menjadi satu entri per elemen, bukan satu daftar.
                TextColumn::make('variabel_dkps')->label('Variabel DKPS')->wrap()->toggleable()
                    ->state(fn (NilaiRumus $record) => self::daftarVariabel($record->komponen))
                    ->html()
                    ->placeholder('—'),

                TextColumn::make('dihitung_pada')->label('Dihitung')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('catatan')->label('Catatan')->wrap()->toggleable()
                    ->color('warning')->placeholder('—'),
            ])
            ->filters([
                Filter::make('terakhir')->label('Hanya hasil terakhir tiap rumus')
                    ->default()
                    ->query(function (Builder $query) {
                        $periode = Periode::aktif()->first();

                        return $periode === null ? $query : $query->terakhir($periode->id);
                    }),
                Filter::make('syarat_perlu')->label('Hanya rumus terkait syarat perlu')
                    ->query(fn (Builder $query) => $query->whereNotNull('memenuhi_syarat_5_tahun')),
            ])
            ->defaultSort('dihitung_pada', 'desc')
            ->emptyStateHeading('Belum ada perhitungan')
            ->emptyStateDescription('Nilai muncul setelah data DKPS diisi dan rumusnya dihitung.');
    }

    /**
     * `komponen['variabel']` sebagai baris "KODE = nilai (lembar)".
     *
     * @param  array<string, mixed>|null  $komponen  null atau tanpa `variabel` ⇒ null
     */
    public static function daftarVariabel(?array $komponen): ?string
    {
        $variabel = $komponen['variabel'] ?? null;

        if (! is_array($variabel) || $variabel === []) {
            return null;
        }

        return collect($variabel)
            ->map(fn (array $v) => e(sprintf('%s = %s (%s)',
                $v['kode'] ?? '?',
                self::angka($v['nilai'] ?? null),
                implode(', ', (array) ($v['lembar'] ?? [])))))
            ->implode('<br>');
    }

    /** Bulat tanpa desimal, selain itu dua desimal berkoma; null ⇒ "—". */
    public static function angka(int|float|null $n): string
    {
        if ($n === null) {
            return '—';
        }

        return floor((float) $n) === (float) $n
            ? number_format((float) $n, 0, ',', '.')
            : number_format((float) $n, 2, ',', '.');
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
