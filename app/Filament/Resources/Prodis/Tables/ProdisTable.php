<?php

namespace App\Filament\Resources\Prodis\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProdisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->wrap(),
                TextColumn::make('jenjang')->label('Jenjang')
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->badge(),
                TextColumn::make('upps')->label('UPPS')->wrap()->toggleable(),
                TextColumn::make('perguruan_tinggi')->label('Perguruan tinggi')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()->label('Sunting')])
            ->emptyStateHeading('Belum ada prodi')
            ->emptyStateDescription('Tambahkan prodi lebih dulu sebelum membuat periode akreditasi.');
    }
}
