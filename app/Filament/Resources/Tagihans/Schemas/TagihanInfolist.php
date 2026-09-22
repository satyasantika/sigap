<?php

namespace App\Filament\Resources\Tagihans\Schemas;

use App\Enums\JenisTagihan;
use App\Enums\StatusTagihan;
use App\Models\Tagihan;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TagihanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('judul')->label('Tagihan')->columnSpanFull()->size('lg')->weight('bold'),
                TextEntry::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (JenisTagihan $state) => $state->label())
                    ->color(fn (JenisTagihan $state) => $state->warna()),
                TextEntry::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusTagihan $state) => $state->label())
                    ->color(fn (StatusTagihan $state) => $state->warna()),
                TextEntry::make('pokja.kode')->label('Pokja')->badge(),
                TextEntry::make('bobot_terkait')->label('Bobot dipikul')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 3, ',', '.')),
                TextEntry::make('penanggungJawab.nama_lengkap')->label('Penanggung jawab')
                    ->placeholder('belum ditugaskan'),
                TextEntry::make('tenggat')->label('Tenggat')->date('d F Y')->placeholder('—')
                    ->color(fn (Tagihan $r) => $r->terlambat() ? 'danger' : null)
                    ->helperText(fn (Tagihan $r) => $r->terlambat() ? 'Sudah lewat tenggat.' : null),
                TextEntry::make('penyetuju.nama_lengkap')->label('Disetujui oleh')
                    ->placeholder('belum disetujui'),
                TextEntry::make('disetujui_pada')->label('Disetujui pada')
                    ->dateTime('d F Y, H:i')->placeholder('—'),
            ]),

            // Peringatan syarat perlu di ATAS elemennya, bukan di bawah:
            // orang yang membuka tagihan E17 atau E51 harus tahu bahwa skor 4
            // di sana TIDAK berarti syarat perlunya terpenuhi.
            Section::make('⚠ SYARAT PERLU')
                ->description('Elemen ini menentukan status Unggul secara terpisah dari Nilai Akreditasi.')
                ->visible(fn (Tagihan $r) => $r->elemen?->syarat_perlu === true)
                ->columns(2)
                ->schema([
                    TextEntry::make('elemen.syaratPerlu.ambang_3_tahun')->label('Ambang masa 3 tahun'),
                    TextEntry::make('elemen.syaratPerlu.ambang_5_tahun')->label('Ambang masa 5 tahun'),
                    TextEntry::make('elemen.syaratPerlu.catatan')->label('Catatan penting')
                        ->columnSpanFull()->color('danger')->weight('medium'),
                ]),

            Section::make('Elemen terkait')
                ->visible(fn (Tagihan $r) => $r->elemen !== null)
                ->columns(3)
                ->schema([
                    TextEntry::make('elemen.no')->label('Nomor elemen'),
                    TextEntry::make('elemen.bobot')->label('Bobot elemen')
                        ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.')),
                    TextEntry::make('elemen.kriteria.kode')->label('Kriteria')->badge(),
                    TextEntry::make('elemen.nama')->label('Nama elemen')->columnSpanFull(),
                ]),

            Section::make('Deskripsi pekerjaan')
                ->visible(fn (Tagihan $r) => filled($r->deskripsi))
                ->schema([TextEntry::make('deskripsi')->hiddenLabel()->prose()]),

            // Riwayat ditampilkan penuh, bukan hanya perpindahan terakhir:
            // jejak audit gunanya justru untuk dibaca berurutan.
            Section::make('Riwayat status')
                ->description('Append only — tidak bisa disunting maupun dihapus.')
                ->schema([
                    RepeatableEntry::make('riwayat')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y, H:i'),
                        TextEntry::make('status_dari')->label('Dari')->badge()
                            ->formatStateUsing(fn (?StatusTagihan $s) => $s?->label() ?? 'awal')
                            ->color(fn (?StatusTagihan $s) => $s?->warna() ?? 'gray'),
                        TextEntry::make('status_ke')->label('Ke')->badge()
                            ->formatStateUsing(fn (StatusTagihan $state) => $state->label())
                            ->color(fn (StatusTagihan $state) => $state->warna()),
                        TextEntry::make('user.nama_lengkap')->label('Oleh'),
                        TextEntry::make('catatan')->label('Catatan')->columnSpanFull()
                            ->placeholder('—')->prose(),
                    ]),
                ]),

            Section::make('Komentar')
                ->visible(fn (Tagihan $r) => $r->komentar()->exists())
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
