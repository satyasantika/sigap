<?php

namespace App\Filament\Resources\DkpsButirs\Tables;

use App\Models\DkpsButir;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DkpsButirsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')->label('No')->sortable()->width('1%'),
                TextColumn::make('nama')->label('Butir')->searchable()->wrap(),
                // Label Buku 3 melompati "Tabel 13", jadi ditampilkan terpisah
                // dari nomor urut supaya rujukan ke buku tidak meleset.
                TextColumn::make('label_tabel')->label('Label di Buku 3')->badge(),
                TextColumn::make('jendela_data')->label('Jendela data')->badge(),
            ])
            ->filters([
                SelectFilter::make('jendela_data')->label('Jendela data')->options(
                    fn () => DkpsButir::query()
                        ->distinct()->orderBy('jendela_data')->pluck('jendela_data', 'jendela_data')->all()
                ),
            ])
            ->recordActions([ViewAction::make()->label('Lihat')])
            ->defaultSort('no')
            ->paginated([28, 50])
            ->defaultPaginationPageOption(28);
    }
}
