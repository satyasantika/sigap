<?php

namespace App\Filament\Resources\Prodis\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProdiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')
                ->label('Kode')
                ->placeholder('PPG-UNSIL')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            TextInput::make('nama')
                ->label('Nama prodi')
                ->placeholder('Pendidikan Profesi Guru')
                ->required()
                ->maxLength(150),
            Select::make('jenjang')
                ->label('Jenjang')
                ->options([
                    'ppg' => 'PPG',
                    's1' => 'Sarjana (S1)',
                    's2' => 'Magister (S2)',
                    's3' => 'Doktor (S3)',
                ])
                ->required()
                ->native(false),
            TextInput::make('upps')
                ->label('UPPS')
                ->helperText('Unit Pengelola Program Studi, misalnya fakultas.')
                ->required()
                ->maxLength(150),
            TextInput::make('perguruan_tinggi')
                ->label('Perguruan tinggi')
                ->required()
                ->maxLength(150),
            Toggle::make('aktif')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
