<?php

namespace App\Filament\Resources\Pokjas\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PokjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->badge()->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->wrap(),
                TextColumn::make('periode.nama')->label('Periode')->toggleable(),
                TextColumn::make('koordinator.nama_lengkap')->label('Koordinator')
                    ->placeholder('belum ditunjuk'),
                TextColumn::make('anggota_count')->label('Anggota')->counts('anggota'),
            ])
            ->filters([
                SelectFilter::make('periode')->label('Periode')->relationship('periode', 'nama'),
            ])
            ->recordActions([EditAction::make()->label('Sunting')])
            ->defaultSort('kode')
            ->emptyStateHeading('Belum ada pokja')
            ->emptyStateDescription('Enam pokja disemai dari data/pokja.json saat basis data dibangun.');
    }
}
