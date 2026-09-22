<?php

namespace App\Filament\Resources\Penilaians\Schemas;

use App\Models\Elemen;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PenilaianForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('elemen_id')
                ->label('Elemen')
                ->options(fn () => Elemen::orderBy('no')->get()
                    ->mapWithKeys(fn (Elemen $e) => [
                        $e->id => "E{$e->no} — {$e->nama}".($e->syarat_perlu ? ' ⚠ SYARAT PERLU' : ''),
                    ])->all())
                ->searchable()->required()->native(false)
                ->columnSpanFull(),

            Radio::make('skor')
                ->label('Skor')
                ->options([
                    1 => '1 — jauh di bawah standar',
                    2 => '2 — belum memenuhi standar',
                    3 => '3 — memenuhi standar',
                    4 => '4 — melampaui standar',
                ])
                ->helperText('Elemen yang belum dinilai diandaikan berskor 3 pada proyeksi NA.')
                ->required()
                ->columnSpanFull(),

            Textarea::make('catatan')
                ->label('Catatan')
                ->helperText('Alasan skor ini. Dibaca saat menyandingkan dengan penilai lain.')
                ->rows(4)->columnSpanFull(),
        ]);
    }
}
