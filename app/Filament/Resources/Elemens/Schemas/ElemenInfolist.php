<?php

namespace App\Filament\Resources\Elemens\Schemas;

use App\Enums\JenisElemen;
use App\Models\Elemen;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Halaman ini dipakai anggota pokja sambil mengerjakan elemennya, jadi
 * teksnya ditampilkan UTUH dalam bagian bertingkat — bukan dipadatkan ke satu
 * sel tabel. Panduan Buku 3 bisa sepanjang 876 karakter; memotongnya berarti
 * orang harus membuka PDF aslinya, dan di situlah salah tafsir bermula.
 */
class ElemenInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')
                ->columns(4)
                ->schema([
                    TextEntry::make('no')->label('Nomor elemen'),
                    TextEntry::make('kriteria.kode')->label('Kriteria')->badge()
                        ->formatStateUsing(fn ($state, Elemen $record) => $state.' — '.$record->kriteria->nama),
                    TextEntry::make('bobot')->label('Bobot')
                        ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                    TextEntry::make('jenis')->label('Jenis')->badge()
                        ->formatStateUsing(fn (JenisElemen $state) => $state->label())
                        ->color(fn (JenisElemen $state) => $state->warna())
                        ->helperText(fn (JenisElemen $state) => $state->ringkas()),
                    TextEntry::make('nama')->label('Nama elemen')->columnSpanFull()->size('lg')->weight('bold'),
                    TextEntry::make('pokja_kode')->label('Pemilik')->badge(),
                ]),

            // Kotak peringatan syarat perlu ditempatkan DI ATAS panduan, bukan
            // di bawah: orang yang membuka elemen 17 atau 51 harus membacanya
            // sebelum menyimpulkan skor 4 sudah cukup.
            Section::make('⚠ Elemen syarat perlu')
                ->description('Elemen ini menentukan status Unggul secara terpisah dari Nilai Akreditasi.')
                ->visible(fn (Elemen $record) => $record->syarat_perlu)
                ->columns(2)
                ->schema([
                    TextEntry::make('syaratPerlu.ambang_3_tahun')->label('Ambang masa 3 tahun'),
                    TextEntry::make('syaratPerlu.ambang_5_tahun')->label('Ambang masa 5 tahun'),
                    TextEntry::make('syaratPerlu.jenis_ambang')->label('Jenis ambang')->badge()
                        ->formatStateUsing(fn (?string $state) => match ($state) {
                            'ambang_skor' => 'Ambang skor',
                            'ambang_kuantitatif' => 'Ambang kuantitatif',
                            default => '—',
                        }),
                    TextEntry::make('syaratPerlu.nomor_di_tabel_1_3')->label('Nomor di Tabel 1.3 Buku 4')
                        ->helperText('Bisa berbeda dari nomor elemen. Selisih itu memang ada di dokumen aslinya.'),
                    TextEntry::make('syaratPerlu.catatan')->label('Catatan penting')
                        ->columnSpanFull()->color('danger')->weight('medium'),
                ]),

            Section::make('Panduan')
                ->description('Dari Buku 3 — dibaca saat mengerjakan elemen ini.')
                ->schema([
                    TextEntry::make('panduan')->hiddenLabel()->prose(),
                ]),

            Section::make('Pertanyaan pemandu')
                ->visible(fn (Elemen $record) => filled($record->pertanyaan_pemandu))
                ->schema([
                    TextEntry::make('pertanyaan_pemandu')->hiddenLabel()->prose(),
                ]),

            Section::make('Parameter pelampauan standar mutu')
                ->description('Keadaan yang harus dipenuhi untuk memperoleh skor tertinggi.')
                ->visible(fn (Elemen $record) => filled($record->parameter))
                ->schema([
                    TextEntry::make('parameter')->hiddenLabel()->prose(),
                ]),

            Section::make('Bukti pendukung yang diminta')
                ->visible(fn (Elemen $record) => filled($record->bukti_pendukung))
                ->schema([
                    TextEntry::make('bukti_pendukung')->hiddenLabel()->prose(),
                ]),

            // Dua kotak terpisah untuk elemen refleksi — bukan satu kotak
            // gabungan. Evaluasi menjawab "apa yang terjadi", tindak lanjut
            // menjawab "apa yang dilakukan"; menggabungkannya membuat yang
            // kedua sering hilang.
            Section::make('Evaluasi dan refleksi')
                ->visible(fn (Elemen $record) => filled($record->evaluasi_refleksi))
                ->schema([
                    TextEntry::make('evaluasi_refleksi')->hiddenLabel()->prose(),
                ]),

            Section::make('Tindak lanjut')
                ->visible(fn (Elemen $record) => filled($record->tindak_lanjut))
                ->schema([
                    TextEntry::make('tindak_lanjut')->hiddenLabel()->prose(),
                ]),

            Section::make('Rumus terkait')
                ->visible(fn (Elemen $record) => $record->rumus()->exists())
                ->schema([
                    TextEntry::make('rumus.kode')->hiddenLabel()->badge()->separator(','),
                    TextEntry::make('rumus.ekspresi')->label('Ekspresi')->listWithLineBreaks(),
                ]),
        ]);
    }
}
