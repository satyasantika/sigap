<?php

namespace App\Filament\Resources\Pokjas\Schemas;

use App\Support\Izin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PokjaForm
{
    public static function configure(Schema $schema): Schema
    {
        // Koordinator membuka halaman ini hanya untuk mengelola anggota; isian
        // pokjanya sendiri tetap milik admin dan ketua.
        return $schema->disabled(fn () => ! Izin::boleh(auth()->user(), 'pokja.kelola'))->components([
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
                ->relationship(
                    'koordinator',
                    'nama_lengkap',
                    // Akun demo tidak boleh jadi koordinator pokja sungguhan:
                    // ia lenyap begitu demonya dibuang.
                    fn (Builder $query) => $query->bisaDitugaskan(),
                )
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
