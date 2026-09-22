<?php

namespace App\Filament\Resources\Periodes\Schemas;

use App\Enums\StatusPeriode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PeriodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('prodi_id')
                ->label('Prodi')
                ->relationship('prodi', 'nama')
                ->required()
                ->native(false),
            TextInput::make('nama')
                ->label('Nama periode')
                ->placeholder('PPG <tahun TS>')
                ->required()
                ->maxLength(80),
            TextInput::make('ts_tahun')
                ->label('Tahun acuan (TS)')
                ->helperText('Satu-satunya sumber tahun di seluruh aplikasi. Seluruh jendela data dihitung relatif terhadap angka ini.')
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100)
                ->required(),
            DatePicker::make('tanggal_target_unggah')
                ->label('Target unggah')
                ->helperText('Dasar linimasa mundur pada dasbor. Boleh dikosongkan.')
                ->displayFormat('d F Y')
                ->native(false),
            TextInput::make('versi_instrumen')
                ->label('Versi instrumen')
                ->default('IAPSK 3.0')
                ->required()
                ->maxLength(20),
            Select::make('status')
                ->label('Status')
                ->options(StatusPeriode::pilihan())
                ->default(StatusPeriode::Persiapan->value)
                ->helperText('Periode "dikunci" dan "selesai" menolak seluruh penulisan untuk semua peran.')
                ->required()
                ->native(false),
        ]);
    }
}
