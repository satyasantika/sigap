<?php

namespace App\Filament\Resources\DkpsButirs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DkpsButirInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('no')->label('Nomor butir'),
                TextEntry::make('label_tabel')->label('Label di Buku 3')->badge()
                    ->helperText('Buku 3 melompati "Tabel 13", jadi label ini bisa berbeda dari nomor butir.'),
                TextEntry::make('jendela_data')->label('Jendela data')->badge(),
                TextEntry::make('nama')->label('Nama butir')->columnSpanFull()->size('lg')->weight('bold'),
            ]),

            Section::make('Keterangan')
                ->description('Isi kolom tabel sebagaimana tertulis di Buku 3.')
                ->schema([
                    TextEntry::make('keterangan')->hiddenLabel()->prose(),
                ]),
        ]);
    }
}
