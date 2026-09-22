<?php

namespace App\Filament\Resources\Elemens\Tables;

use App\Enums\JenisElemen;
use App\Models\Elemen;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ElemensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')->label('No')->sortable()->width('1%'),
                TextColumn::make('nama')->label('Elemen')->searchable()->wrap()
                    // Lencana syarat perlu menempel pada nama, bukan di kolom
                    // terpisah yang bisa disembunyikan: kelima elemen inilah
                    // gerbang status Unggul, dan orang harus melihatnya tanpa
                    // perlu tahu kolom mana yang harus dinyalakan.
                    ->description(fn ($record) => $record->syarat_perlu ? '⚠ SYARAT PERLU' : null)
                    ->color(fn ($record) => $record->syarat_perlu ? 'danger' : null),
                TextColumn::make('kriteria.kode')->label('Kriteria')->badge()->sortable(),
                TextColumn::make('bobot')->label('Bobot')->sortable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->summarize(Sum::make()
                        ->label('Total')
                        ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (JenisElemen $state) => $state->label())
                    ->color(fn (JenisElemen $state) => $state->warna()),
                TextColumn::make('pokja_kode')->label('Pokja')->badge()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kriteria')->label('Kriteria')
                    ->relationship('kriteria', 'kode'),
                SelectFilter::make('jenis')->label('Jenis')->options(JenisElemen::pilihan()),
                SelectFilter::make('pokja_kode')->label('Pokja')->options(
                    fn () => Elemen::query()
                        ->distinct()->orderBy('pokja_kode')->pluck('pokja_kode', 'pokja_kode')->all()
                ),
                Filter::make('syarat_perlu')->label('Hanya syarat perlu')
                    ->query(fn (Builder $q) => $q->where('syarat_perlu', true)),
            ])
            ->recordActions([ViewAction::make()->label('Lihat')])
            ->defaultSort('no')
            ->paginated([25, 50, 'all'])
            ->defaultPaginationPageOption(25);
    }
}
