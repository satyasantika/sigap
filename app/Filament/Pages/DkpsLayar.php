<?php

namespace App\Filament\Pages;

use App\Enums\JenisTagihan;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Tagihan;
use App\Services\RingkasanProgres;
use App\Support\Izin;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Daftar Tabel DKPS dikelompokkan per pokja pemilik, dengan status tiap
 * lembar. Inilah layar progres DKPS ketua; pengisi berlingkup `pokjanya`
 * hanya melihat tabel pokjanya sendiri.
 *
 * Halaman ini hanya membaca. Isian dan verifikasi terjadi di TabelDkpsLayar.
 */
class DkpsLayar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Tabel DKPS';

    protected static ?string $title = 'Tabel DKPS';

    protected static ?string $slug = 'dkps';

    protected string $view = 'filament.pages.dkps';

    /** @var Collection<string, Tagihan>|null tagihan data_dkps per dkps_butir_id */
    private ?Collection $tagihan = null;

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::boleh(auth()->user(), 'tagihan.lihat');
    }

    /**
     * Menyembunyikan, bukan menolak: canAccess() di atas yang menolak.
     * Lihat App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && Izin::bolehMenu(auth()->user(), 'dkps_lembar');
    }

    public function periode(): ?Periode
    {
        return Periode::aktif()->first();
    }

    /**
     * Kode pokja yang boleh dilihat: semua bila lingkup `tagihan.lihat`
     * penggunanya `ya`, selain itu pokja tempat ia terdaftar.
     *
     * @return array<int, string>
     */
    public function pokjaTerlihat(): array
    {
        $u = auth()->user();
        $kueri = Pokja::query()->where('periode_id', $this->periode()?->id);

        if (! in_array('ya', Izin::lingkup($u, 'tagihan.lihat'), true)) {
            $kueri->whereIn('id', $u->idPokjanya());
        }

        return $kueri->pluck('kode')->unique()->values()->all();
    }

    /** @return array<int, array> hasil RingkasanProgres::progresDkpsPerPokja, tersaring menurut pokjaTerlihat() */
    public function progres(): array
    {
        $periode = $this->periode();

        if ($periode === null) {
            return [];
        }

        $terlihat = $this->pokjaTerlihat();

        return array_values(array_filter(
            app(RingkasanProgres::class)->progresDkpsPerPokja($periode),
            fn (array $p) => in_array($p['kode'], $terlihat, true),
        ));
    }

    /** Tagihan `data_dkps` tabel ini pada periode aktif; dimuat sekali untuk semua tabel. */
    public function tagihanTabel(DkpsButir $butir): ?Tagihan
    {
        $this->tagihan ??= ($periode = $this->periode()) === null
            ? collect()
            : Tagihan::where('periode_id', $periode->id)
                ->where('jenis', JenisTagihan::DataDkps)
                ->whereNotNull('dkps_butir_id')
                ->with('penanggungJawab:id,nama_lengkap')
                ->get()
                ->keyBy('dkps_butir_id');

        return $this->tagihan->get($butir->id);
    }

    /** Label dan warna lencana untuk satu status lembar. */
    public static function lencana(string $status): array
    {
        return match ($status) {
            'terverifikasi' => ['Terverifikasi', 'success'],
            'lengkap' => ['Lengkap', 'info'],
            'terisi' => ['Terisi', 'warning'],
            default => ['Kosong', 'gray'],
        };
    }
}
