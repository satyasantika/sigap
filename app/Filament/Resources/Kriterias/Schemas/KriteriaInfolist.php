<?php

namespace App\Filament\Resources\Kriterias\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KriteriaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('kode')->label('Kode')->badge(),
                TextEntry::make('urutan')->label('Urutan'),
                TextEntry::make('bobot')->label('Bobot')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.').' dari 100'),
                TextEntry::make('nama')->label('Nama')->columnSpanFull()->size('lg')->weight('bold'),
            ]),

            Section::make('Elemen di dalamnya')
                ->schema([
                    RepeatableEntry::make('elemen')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('no')->label('No'),
                        TextEntry::make('nama')->label('Elemen')->columnSpan(2),
                        TextEntry::make('bobot')->label('Bobot')
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                    ]),
                ]),
        ]);
    }
}
