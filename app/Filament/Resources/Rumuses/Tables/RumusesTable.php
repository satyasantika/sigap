<?php

namespace App\Filament\Resources\Rumuses\Tables;

use App\Models\Rumus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RumusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->badge()->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->wrap(),
                TextColumn::make('elemen.no')->label('Elemen')
                    // NA tidak terikat elemen mana pun — ia penjumlah global.
                    ->placeholder('global')
                    ->formatStateUsing(fn ($state) => 'E'.$state),
                TextColumn::make('ekspresi')->label('Ekspresi')->wrap()->toggleable(),
                TextColumn::make('jendela')->label('Jendela data')->badge()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('jendela')->label('Jendela data')->options(
                    fn () => Rumus::query()
                        ->distinct()->orderBy('jendela')->pluck('jendela', 'jendela')->all()
                ),
            ])
            ->recordActions([ViewAction::make()->label('Lihat')])
            ->defaultSort('kode')
            ->paginated(false);
    }
}
