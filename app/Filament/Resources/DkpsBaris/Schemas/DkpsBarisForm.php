<?php

namespace App\Filament\Resources\DkpsBaris\Schemas;

use App\Enums\SumberData;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Services\JendelaTs;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Bentuk tabel tiap butir BERBEDA, jadi isinya dikumpulkan sebagai pasangan
 * kunci-nilai bebas dan divalidasi di tingkat aplikasi.
 *
 * `keterangan` dan `jendela_data` butir ditampilkan di layar supaya pengisi
 * tahu tahun mana yang diminta tanpa membuka Buku 3 — dan itulah satu-satunya
 * cara memastikan ia mengisi jendela yang benar.
 */
class DkpsBarisForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Butir dan tahun')->columns(2)->schema([
                Select::make('dkps_butir_id')
                    ->label('Butir DKPS')
                    ->options(fn () => DkpsButir::orderBy('no')
                        ->get()->mapWithKeys(fn (DkpsButir $b) => [
                            $b->id => "{$b->label_tabel} — {$b->nama}",
                        ])->all())
                    ->searchable()->required()->live()
                    ->native(false)
                    ->columnSpanFull(),

                // Keterangan butir dan jendela datanya, langsung dari Buku 3.
                Text::make(function ($get) {
                    $butir = DkpsButir::find($get('dkps_butir_id'));

                    if ($butir === null) {
                        return new HtmlString('<p class="text-sm text-gray-500">Pilih butir lebih dulu.</p>');
                    }

                    $periode = Periode::aktif()->first();
                    $jendela = $periode
                        ? JendelaTs::keterangan($periode, $butir->jendela_data)
                        : $butir->jendela_data;

                    return new HtmlString(
                        '<div class="rounded-lg border border-info-300 bg-info-50 p-4 text-sm dark:border-info-500/30 dark:bg-info-500/10">'
                        .'<p class="font-medium">Jendela data: '.e($jendela).'</p>'
                        .'<p class="mt-2 text-gray-700 dark:text-gray-300">'.e($butir->keterangan).'</p>'
                        .'</div>'
                    );
                })->columnSpanFull(),

                Select::make('tahun_acuan')
                    ->label('Tahun acuan')
                    ->options(function ($get) {
                        $butir = DkpsButir::find($get('dkps_butir_id'));
                        $periode = Periode::aktif()->first();

                        if ($butir === null || $periode === null) {
                            return ['TS' => 'TS'];
                        }

                        // Label relatif disandingkan dengan tahun sesungguhnya:
                        // "TS-2 (2025)". Pengisi tidak perlu menghitung sendiri.
                        return collect(JendelaTs::labelDalamJendela($butir->jendela_data))
                            ->mapWithKeys(fn (string $l) => [
                                $l => $l.' ('.JendelaTs::tahunDariLabel($periode, $l).')',
                            ])->all();
                    })
                    ->required()->native(false),

                Select::make('sumber')
                    ->label('Sumber data')
                    ->options(SumberData::pilihan())
                    ->helperText('Tandai jujur. Baris bersumber manual yang sebenarnya ada di SIAKAD adalah calon selisih pada asesmen lapangan.')
                    ->required()->native(false),
            ]),

            Section::make('Isi baris')
                ->description('Bentuk tabel tiap butir berbeda; isi kolom sesuai yang diminta Buku 3.')
                ->schema([
                    KeyValue::make('data')
                        ->hiddenLabel()
                        ->keyLabel('Nama kolom')
                        ->valueLabel('Isi')
                        ->addActionLabel('Tambah kolom')
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
