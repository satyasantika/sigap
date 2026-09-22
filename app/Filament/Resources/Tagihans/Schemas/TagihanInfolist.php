<?php

namespace App\Filament\Resources\Tagihans\Schemas;

use App\Enums\AksesTautan;
use App\Enums\JenisTagihan;
use App\Enums\StatusTagihan;
use App\Enums\ValidasiBukti;
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

                    // Kalimat tegas untuk elemen berambang kuantitatif (E17 dan
                    // E51). Pada keduanya ambang skor 4 LEBIH RENDAH daripada
                    // ambang syarat perlu 5 tahun, jadi skor 4 sama sekali tidak
                    // menjamin syaratnya terpenuhi. Catatan di data menyiratkan
                    // ini pada E17 tetapi tidak pada E51, sementara keduanya
                    // sama-sama perangkap — jadi kalimatnya dinyatakan di sini.
                    TextEntry::make('peringatan_skor_empat')->hiddenLabel()
                        ->state('Perhatikan: skor 4 pada matriks penilaian TIDAK berarti syarat perlu ini terpenuhi. Keduanya dihitung terpisah, dan ambang syarat perlu lebih tinggi.')
                        ->color('danger')->weight('bold')->columnSpanFull()
                        ->visible(fn (Tagihan $r) => $r->elemen?->syaratPerlu?->jenis_ambang === 'ambang_kuantitatif'),
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

            // --- Acuan dari instrumen, ditampilkan UTUH ---
            // Ini yang dibaca anggota pokja sambil bekerja. Memotongnya berarti
            // ia harus membuka PDF Buku 3, dan di situlah salah tafsir bermula.

            Section::make('Panduan')
                ->description('Dari Buku 3 — baca sebelum menulis.')
                ->visible(fn (Tagihan $r) => filled($r->elemen?->panduan))
                ->schema([TextEntry::make('elemen.panduan')->hiddenLabel()->prose()]),

            Section::make('Pertanyaan pemandu')
                ->visible(fn (Tagihan $r) => filled($r->elemen?->pertanyaan_pemandu))
                ->schema([TextEntry::make('elemen.pertanyaan_pemandu')->hiddenLabel()->prose()]),

            Section::make('Parameter pelampauan standar mutu')
                ->description('Keadaan yang harus dipenuhi untuk memperoleh skor tertinggi.')
                ->visible(fn (Tagihan $r) => filled($r->elemen?->parameter))
                ->schema([TextEntry::make('elemen.parameter')->hiddenLabel()->prose()]),

            Section::make('Bukti pendukung yang diminta')
                ->visible(fn (Tagihan $r) => filled($r->elemen?->bukti_pendukung))
                ->schema([TextEntry::make('elemen.bukti_pendukung')->hiddenLabel()->prose()]),

            // Dua kotak TERPISAH untuk elemen refleksi. Evaluasi menjawab "apa
            // yang terjadi", tindak lanjut menjawab "apa yang dilakukan";
            // menggabungkannya membuat yang kedua sering hilang.
            Section::make('Acuan: Evaluasi dan Refleksi')
                ->visible(fn (Tagihan $r) => filled($r->elemen?->evaluasi_refleksi))
                ->schema([TextEntry::make('elemen.evaluasi_refleksi')->hiddenLabel()->prose()]),

            Section::make('Acuan: Tindak Lanjut')
                ->visible(fn (Tagihan $r) => filled($r->elemen?->tindak_lanjut))
                ->schema([TextEntry::make('elemen.tindak_lanjut')->hiddenLabel()->prose()]),

            Section::make('Bukti tertaut')
                ->description('Setiap data yang diinputkan wajib punya bukti. Tagihan tanpa bukti tidak bisa diajukan.')
                ->schema([
                    RepeatableEntry::make('bukti')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('judul')->label('Bukti')->columnSpan(2),
                        TextEntry::make('akses_status')->label('Keterbacaan')->badge()
                            ->formatStateUsing(fn (AksesTautan $state) => $state->label())
                            ->color(fn (AksesTautan $state) => $state->warna()),
                        TextEntry::make('validasi_status')->label('Keabsahan')->badge()
                            ->formatStateUsing(fn (ValidasiBukti $state) => $state->label())
                            ->color(fn (ValidasiBukti $state) => $state->warna()),
                    ]),
                    TextEntry::make('bukti_kosong')->hiddenLabel()
                        ->state('Belum ada bukti tertaut.')
                        ->color('danger')
                        ->visible(fn (Tagihan $r) => $r->bukti()->count() === 0),
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
