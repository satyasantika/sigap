<?php

namespace App\Filament\Resources\Rumuses\Schemas;

use App\Models\Rumus;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ekspresi dan aturan skor ditampilkan apa adanya sebagai teks.
 *
 * Keduanya memang ditulis untuk dibaca manusia, bukan diurai mesin:
 * notasinya bercampur, koma desimalnya gaya Indonesia, dan satu di antaranya
 * belum punya rumus sama sekali. Perhitungannya ditulis tangan di tahap 5.
 */
class RumusInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('kode')->label('Kode')->badge(),
                TextEntry::make('elemen.no')->label('Elemen terkait')
                    ->placeholder('Tidak terikat elemen (penjumlah global)')
                    ->formatStateUsing(fn ($state, Rumus $record) => 'E'.$state.' — '.$record->elemen?->nama),
                TextEntry::make('jendela')->label('Jendela data')->badge(),
                TextEntry::make('nama')->label('Nama')->columnSpanFull()->size('lg')->weight('bold'),
            ]),

            Section::make('Ekspresi')->schema([
                TextEntry::make('ekspresi')->hiddenLabel()->prose(),
            ]),

            Section::make('Variabel')
                ->visible(fn (Rumus $record) => filled($record->variabel))
                ->schema([
                    KeyValueEntry::make('variabel')->hiddenLabel()
                        ->keyLabel('Variabel')->valueLabel('Arti'),
                ]),

            Section::make('Aturan skor')->schema([
                TextEntry::make('aturan_skor')->hiddenLabel()->prose(),
            ]),

            Section::make('Catatan')
                ->visible(fn (Rumus $record) => filled($record->catatan))
                ->schema([
                    TextEntry::make('catatan')->hiddenLabel()->prose()->color('warning'),
                ]),
        ]);
    }
}
