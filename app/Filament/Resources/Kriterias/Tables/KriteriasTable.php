<?php

namespace App\Filament\Resources\Kriterias\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KriteriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('urutan')->label('#')->sortable()->width('1%'),
                TextColumn::make('kode')->label('Kode')->badge()->sortable(),
                TextColumn::make('nama')->label('Nama kriteria')->searchable()->wrap(),
                TextColumn::make('elemen_count')->label('Elemen')->counts('elemen'),
                TextColumn::make('bobot')->label('Bobot')->sortable()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                    ->summarize(Sum::make()->label('Total')
                        ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))),
            ])
            ->recordActions([ViewAction::make()->label('Lihat')])
            ->defaultSort('urutan')
            ->paginated(false);
    }
}
