<?php

namespace App\Filament\Resources\Periodes\Tables;

use App\Enums\StatusPeriode;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PeriodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Periode')->searchable()->sortable(),
                TextColumn::make('prodi.nama')->label('Prodi')->toggleable(),
                TextColumn::make('ts_tahun')->label('TS')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusPeriode $state) => $state->label())
                    ->color(fn (StatusPeriode $state) => $state->warna()),
                TextColumn::make('tanggal_target_unggah')->label('Target unggah')
                    ->date('d F Y')->placeholder('belum ditetapkan'),
                TextColumn::make('versi_instrumen')->label('Instrumen')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(StatusPeriode::pilihan()),
            ])
            ->recordActions([EditAction::make()->label('Sunting')])
            ->defaultSort('ts_tahun', 'desc')
            ->emptyStateHeading('Belum ada periode')
            ->emptyStateDescription('Periode menentukan tahun acuan (TS) untuk seluruh perhitungan.');
    }
}
