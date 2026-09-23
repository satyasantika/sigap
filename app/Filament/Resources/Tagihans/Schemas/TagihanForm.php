<?php

namespace App\Filament\Resources\Tagihans\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Yang bisa disunting hanya penugasan dan tenggat.
 *
 * Judul, jenis, elemen, pokja, dan bobot lahir dari pembangkit dan tidak boleh
 * diubah tangan: bobotnya bagian dari jumlah 100,000, dan menyuntingnya satu
 * baris merusak seluruh perhitungan progres. Status juga tidak ada di sini —
 * satu-satunya pintunya AlurTagihan.
 */
class TagihanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('judul')->label('Judul')->disabled()->columnSpanFull(),

            Select::make('penanggung_jawab_id')
                ->label('Penanggung jawab')
                ->options(fn () => User::bisaDitugaskan()->orderBy('nama_lengkap')
                    ->pluck('nama_lengkap', 'id')->all())
                ->searchable()
                ->placeholder('Belum ditugaskan')
                ->native(false),

            DatePicker::make('tenggat')
                ->label('Tenggat')
                ->displayFormat('d F Y')
                ->native(false),

            Select::make('prioritas')
                ->label('Prioritas')
                ->options(['biasa' => 'Biasa', 'tinggi' => 'Tinggi', 'kritis' => 'Kritis'])
                ->native(false),

            Textarea::make('deskripsi')
                ->label('Deskripsi')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }
}
