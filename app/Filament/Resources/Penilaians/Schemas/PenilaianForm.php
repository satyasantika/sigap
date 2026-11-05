<?php

namespace App\Filament\Resources\Penilaians\Schemas;

use App\Models\Elemen;
use App\Models\NilaiRumus;
use App\Models\Periode;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                ->live()
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

            // Angka hitungan di samping pemilih skor: penilai tetap memutuskan,
            // tetapi tidak lagi menskor elemen kuantitatif tanpa melihat datanya.
            // TextEntry, bukan Placeholder (usang di Filament 5): hanya tampil,
            // tidak ikut tersimpan ke model.
            TextEntry::make('skor_a')
                ->label('Skor A dari rumus')
                ->state(fn (Get $get) => static::skorA($get('elemen_id')))
                ->columnSpanFull(),

            Textarea::make('catatan')
                ->label('Catatan')
                ->helperText('Alasan skor ini. Dibaca saat menyandingkan dengan penilai lain.')
                ->rows(4)->columnSpanFull(),
        ]);
    }

    /**
     * Hasil terakhir tiap rumus elemen itu pada periode aktif, satu per baris.
     */
    public static function skorA(?string $elemenId): HtmlString|string
    {
        if ($elemenId === null || $elemenId === '') {
            return 'Pilih elemen dulu.';
        }

        $kodeRumus = Elemen::find($elemenId)?->rumus()->orderBy('kode')->pluck('kode') ?? collect();

        if ($kodeRumus->isEmpty()) {
            return 'Elemen ini tidak punya rumus kuantitatif.';
        }

        $periode = Periode::aktif()->first();

        $baris = $kodeRumus->map(function (string $kode) use ($periode) {
            $nilai = $periode === null ? null : NilaiRumus::where('periode_id', $periode->id)
                ->where('rumus_kode', $kode)
                ->orderByDesc('dihitung_pada')->orderByDesc('id')
                ->first();

            if ($nilai === null) {
                return e("{$kode}: Belum dihitung dari DKPS");
            }

            $angka = $nilai->nilai === null ? '—' : number_format((float) $nilai->nilai, 2, ',', '.');
            $skor = $nilai->skor_tepat === null
                ? 'tanpa skor'
                : 'Skor A '.number_format((float) $nilai->skor_tepat, 2, ',', '.');

            return e("{$kode}: {$angka} → {$skor} (dihitung {$nilai->dihitung_pada->translatedFormat('d F Y')})");
        });

        return new HtmlString($baris->implode('<br>'));
    }
}
