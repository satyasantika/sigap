<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DkpsBaris\DkpsBarisResource;
use App\Livewire\ImporTempel;
use App\Livewire\LembarDkps;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Services\JendelaTs;
use App\Support\Impor\ImporLembarDkps;
use App\Support\Izin;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Satu tabel DKPS: kepala (jendela data bertahun nyata, pokja pemilik,
 * elemen yang membacanya), tab per lembar, dan grid LembarDkps.
 *
 * Tidak didaftarkan di navigasi; layar daftar tabel DKPS yang menautkannya.
 */
class TabelDkpsLayar extends Page
{
    protected static ?string $slug = 'dkps/tabel/{kode}';

    protected string $view = 'filament.pages.tabel-dkps';

    public DkpsButir $butir;

    public string $lembar;

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::boleh(auth()->user(), 'tagihan.lihat');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function urlUntuk(string $kode, ?string $lembar = null): string
    {
        return static::getUrl(array_filter(['kode' => $kode, 'lembar' => $lembar], fn ($v) => $v !== null));
    }

    public function mount(string $kode): void
    {
        try {
            $this->butir = DkpsButir::dariKode($kode);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $this->lembar = (string) request('lembar', $this->butir->kodeLembar()[0]);

        abort_unless(in_array($this->lembar, $this->butir->kodeLembar(), true), 404);
    }

    public function getTitle(): string
    {
        return "{$this->butir->label_tabel} — {$this->butir->nama}";
    }

    public function periode(): ?Periode
    {
        return Periode::aktif()->first();
    }

    public function jendela(): string
    {
        $periode = $this->periode();

        return $periode === null
            ? $this->butir->jendela_data
            : JendelaTs::keterangan($periode, $this->butir->jendela_data);
    }

    /** Nama pokja pemilik tabel pada periode aktif, atau kodenya saja. */
    public function namaPokja(): ?string
    {
        $periode = $this->periode();

        if ($this->butir->pokja_kode === null || $periode === null) {
            return null;
        }

        return Pokja::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $periode->id)
            ->where('kode', $this->butir->pokja_kode)
            ->value('nama');
    }

    /** @return array<string, string> kode lembar ⇒ judul */
    public function tabLembar(): array
    {
        return collect($this->butir->lembar ?? [])->mapWithKeys(fn (array $l) => [$l['kode'] => $l['judul']])->all();
    }

    /** @return array{sebelum: ?DkpsButir, sesudah: ?DkpsButir} tabel bertetangga milik pokja yang sama */
    public function tetangga(): array
    {
        $sepokja = DkpsButir::query()->where('pokja_kode', $this->butir->pokja_kode);

        return [
            'sebelum' => (clone $sepokja)->where('no', '<', $this->butir->no)->orderByDesc('no')->first(),
            'sesudah' => (clone $sepokja)->where('no', '>', $this->butir->no)->orderBy('no')->first(),
        ];
    }

    public function bolehIsi(): bool
    {
        $periode = $this->periode();

        return $periode !== null
            && Izin::boleh(auth()->user(), 'dkps.isi', LembarDkps::calonBaris($this->butir, $this->lembar, $periode));
    }

    protected function getHeaderActions(): array
    {
        $periode = $this->periode();

        return [
            Action::make('impor_tempel')
                ->label('Impor tempel')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->visible(fn () => $this->bolehIsi())
                ->modalHeading("Impor tempel — {$this->butir->skema($this->lembar)->judul()}")
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->schema(fn () => [
                    Livewire::make(ImporTempel::class, [
                        'profilKelas' => ImporLembarDkps::class,
                        'profilArgs' => [
                            'periodeId' => $periode->id,
                            'prodiId' => $periode->prodi_id,
                            'penggunaId' => auth()->id(),
                            'kodeTabel' => $this->butir->kode,
                            'kodeLembar' => $this->lembar,
                        ],
                        'periodeId' => $periode->id,
                    ])->key("impor-{$this->butir->kode}-{$this->lembar}"),
                ]),
            Action::make('verifikasi_dkps')
                ->label('Verifikasi DKPS')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('gray')
                ->visible(fn () => Izin::boleh(auth()->user(), 'dkps.verifikasi'))
                ->url(DkpsBarisResource::getUrl()),
        ];
    }
}
