<?php

namespace App\Filament\Resources\Buktis\Schemas;

use App\Enums\JenisBukti;
use App\Enums\SumberData;
use App\Models\Elemen;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class BuktiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas bukti')->columns(2)->schema([
                TextInput::make('judul')
                    ->label('Judul')
                    ->placeholder('SK Dekan Nomor 2401 tentang Penugasan Dosen')
                    ->required()->maxLength(200)->columnSpanFull(),

                DatePicker::make('tanggal_kejadian')
                    ->label('Tanggal kejadian')
                    ->helperText('Tanggal peristiwanya terjadi, BUKAN tanggal berkasnya diunggah. Jendela data dihitung dari tanggal ini.')
                    ->displayFormat('d F Y')
                    ->required()
                    ->maxDate(now())
                    ->native(false),

                Select::make('sumber')
                    ->label('Sumber data')
                    ->options(SumberData::pilihan())
                    ->helperText('Tandai jujur. Data yang sebenarnya ada di SIAKAD tetapi diketik manual adalah hal pertama yang diragukan asesor.')
                    ->required()
                    ->native(false),

                Textarea::make('keterangan')
                    ->label('Keterangan isi')
                    ->helperText('Bagian mana yang relevan — halaman berapa, baris mana, notulen tanggal berapa. Ini yang dibaca ketua saat memvalidasi.')
                    ->rows(3)->columnSpanFull(),
            ]),

            Section::make('Berkas atau tautan')->schema([
                Select::make('jenis')
                    ->label('Jenis bukti')
                    ->options(JenisBukti::pilihan())
                    ->default(JenisBukti::Berkas->value)
                    ->live()
                    ->required()
                    ->native(false),

                FileUpload::make('berkas')
                    ->label('Unggah berkas')
                    ->disk('bukti')
                    ->visible(fn ($get) => $get('jenis') === JenisBukti::Berkas->value)
                    ->helperText('Berkas disimpan di server dengan nama acak; nama aslinya tetap tercatat.')
                    ->maxSize(20480)
                    ->columnSpanFull(),

                // Kotak bantuan MUNCUL SEBELUM kolom isian tautan, bukan
                // sesudahnya. Memberi tahu cara membuka akses setelah orang
                // gagal tidak menolong siapa pun — tautan yang salah sudah
                // terlanjur menempel di beberapa elemen.
                Text::make(new HtmlString(
                    '<div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm dark:border-warning-500/30 dark:bg-warning-500/10">'
                    .'<p class="font-medium">Sebelum menempel tautan Drive, buka aksesnya dulu:</p>'
                    .'<p class="mt-2">Bagikan → Akses umum → ubah <strong>"Dibatasi"</strong> menjadi '
                    .'<strong>"Siapa saja yang memiliki link"</strong> → peran <strong>"Pelihat"</strong> → Salin link.</p>'
                    .'<p class="mt-2 text-warning-700 dark:text-warning-400">Tautan yang hanya bisa Anda buka sendiri akan '
                    .'ditolak sistem, dan asesor akan melihat halaman masuk Google — bukan bukti Anda.</p>'
                    .'</div>'
                ))->visible(fn ($get) => $get('jenis') === JenisBukti::Tautan->value)->columnSpanFull(),

                TextInput::make('url')
                    ->label('Tautan')
                    ->url()
                    ->placeholder('https://drive.google.com/file/d/.../view')
                    ->helperText('Segmen /u/0/ akan dibuang otomatis, dan keterbacaannya diperiksa seketika setelah disimpan.')
                    ->visible(fn ($get) => $get('jenis') === JenisBukti::Tautan->value)
                    ->required(fn ($get) => $get('jenis') === JenisBukti::Tautan->value)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]),

            Section::make('Elemen yang ditopang')->schema([
                Select::make('elemen')
                    ->label('Elemen')
                    ->relationship('elemen', 'nama')
                    ->getOptionLabelFromRecordUsing(fn (Elemen $record) => "E{$record->no} — {$record->nama}")
                    ->multiple()->searchable()->preload()
                    ->helperText('Satu bukti boleh menopang beberapa elemen. Alasannya per elemen diisi di halaman detail.')
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
