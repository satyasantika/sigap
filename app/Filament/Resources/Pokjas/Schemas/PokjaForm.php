<?php

namespace App\Filament\Resources\Pokjas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PokjaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('periode_id')
                ->label('Periode')
                ->relationship('periode', 'nama')
                ->required()
                ->native(false),
            TextInput::make('kode')
                ->label('Kode')
                ->placeholder('POKJA-DIK')
                ->required()
                ->maxLength(20),
            TextInput::make('nama')
                ->label('Nama pokja')
                ->required()
                ->maxLength(120),
            Select::make('koordinator_id')
                ->label('Koordinator')
                ->relationship('koordinator', 'nama_lengkap')
                ->searchable()
                ->preload()
                ->placeholder('Belum ditunjuk')
                ->native(false),
            Textarea::make('catatan')
                ->label('Catatan')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }
}
