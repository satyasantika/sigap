<?php

namespace App\Filament\Resources\Buktis\Schemas;

use App\Enums\AksesTautan;
use App\Enums\JenisBukti;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Layar validasi: pratinjau bukti, keterangan isinya, dan keterangan per
 * elemen ditampilkan BERDAMPINGAN.
 *
 * Validator harus bisa menilai tanpa membuka tiga halaman berbeda; kalau
 * harus, sebagian akan menilai tanpa membaca alasannya sama sekali.
 */
class BuktiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('judul')->label('Bukti')->columnSpanFull()->size('lg')->weight('bold'),
                TextEntry::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (JenisBukti $state) => $state->label()),
                TextEntry::make('tanggal_kejadian')->label('Tanggal kejadian')->date('d F Y'),
                TextEntry::make('sumber')->label('Sumber')->badge()
                    ->formatStateUsing(fn (SumberData $state) => $state->label())
                    ->color(fn (SumberData $state) => $state->warna()),
                TextEntry::make('versi')->label('Versi'),
            ]),

            Section::make('Dua sumbu penilaian')
                ->description('Keduanya harus hijau sebelum tagihan yang memakainya bisa disetujui.')
                ->columns(2)
                ->schema([
                    TextEntry::make('akses_status')->label('Keterbacaan (diperiksa mesin)')->badge()
                        ->formatStateUsing(fn (AksesTautan $state) => $state->label())
                        ->color(fn (AksesTautan $state) => $state->warna())
                        ->helperText(fn (Bukti $record) => $record->akses_diperiksa_pada
                            ? 'Terakhir diperiksa '.$record->akses_diperiksa_pada->diffForHumans()
                            : 'Belum pernah diperiksa'),
                    TextEntry::make('validasi_status')->label('Keabsahan (dinilai manusia)')->badge()
                        ->formatStateUsing(fn (ValidasiBukti $state) => $state->label())
                        ->color(fn (ValidasiBukti $state) => $state->warna())
                        ->helperText(fn (Bukti $record) => $record->validator
                            ? 'Dinilai '.$record->validator->nama_lengkap
                            : 'Belum dinilai siapa pun'),
                    TextEntry::make('akses_pesan')->label('Catatan pemeriksaan')
                        ->placeholder('—')->columnSpan(1),
                    TextEntry::make('catatan_validasi')->label('Catatan validator')
                        ->placeholder('—')->columnSpan(1),
                    TextEntry::make('url_kanonik')->label('Tautan kanonik')
                        ->url(fn (Bukti $record) => $record->url_kanonik)
                        ->openUrlInNewTab()
                        ->visible(fn (Bukti $record) => filled($record->url_kanonik))
                        ->columnSpanFull(),
                ]),

            Section::make('Keterangan isi')
                ->description('Bagian mana dari bukti ini yang relevan.')
                ->visible(fn (Bukti $record) => filled($record->keterangan))
                ->schema([TextEntry::make('keterangan')->hiddenLabel()->prose()]),

            // Keterangan per elemen: mengapa bukti ini menopang elemen
            // TERTENTU. Satu SK bisa menopang tiga elemen dengan alasan
            // berbeda-beda, dan alasan itulah yang dinilai.
            Section::make('Elemen yang ditopang')
                ->schema([
                    RepeatableEntry::make('elemen')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('no')->label('Elemen')
                            ->formatStateUsing(fn ($state) => 'E'.$state),
                        TextEntry::make('nama')->label('Nama')->columnSpan(2),
                        TextEntry::make('pivot.keterangan')->label('Mengapa menopang elemen ini')
                            ->placeholder('belum dijelaskan')->columnSpanFull()->prose(),
                    ]),
                ]),

            Section::make('Riwayat penilaian')
                ->visible(fn (Bukti $record) => $record->komentar()->exists())
                ->schema([
                    RepeatableEntry::make('komentar')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('user.nama_lengkap')->label('Oleh'),
                        TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y, H:i'),
                        TextEntry::make('isi')->label('Isi')->columnSpanFull()->prose(),
                    ]),
                ]),
        ]);
    }
}
