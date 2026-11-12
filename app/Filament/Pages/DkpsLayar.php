<?php

namespace App\Filament\Pages;

use App\Enums\JenisTagihan;
use App\Enums\PeranPengguna;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\TagihanDitugaskan;
use App\Services\RingkasanProgres;
use App\Support\Izin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Daftar Tabel DKPS dikelompokkan per pokja pemilik, dengan status tiap
 * lembar. Inilah layar progres DKPS ketua; pengisi berlingkup `pokjanya`
 * hanya melihat tabel pokjanya sendiri.
 *
 * Isian dan verifikasi terjadi di TabelDkpsLayar. Yang bisa diubah di sini
 * hanya delegasinya: anggota pokja dan penanggung jawab tiap tabel.
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

    /**
     * Hasil RingkasanProgres::progresDkpsPerPokja, tersaring: pokja yang
     * terlihat tampil utuh; pokja lain hanya tampil sebatas tabel yang
     * penanggung jawabnya pengguna ini, ditandai `sebagian`.
     *
     * @return array<int, array>
     */
    public function progres(): array
    {
        $periode = $this->periode();

        if ($periode === null) {
            return [];
        }

        $terlihat = $this->pokjaTerlihat();
        $saya = auth()->id();
        $hasil = [];

        foreach (app(RingkasanProgres::class)->progresDkpsPerPokja($periode) as $p) {
            if (in_array($p['kode'], $terlihat, true)) {
                $hasil[] = $p + ['sebagian' => false];

                continue;
            }

            $titipan = array_values(array_filter(
                $p['tabel'],
                fn (array $t) => $this->tagihanTabel($t['butir'])?->penanggung_jawab_id === $saya,
            ));

            if ($titipan !== []) {
                $hasil[] = ['tabel' => $titipan, 'sebagian' => true] + $p;
            }
        }

        return $hasil;
    }

    // ── Delegasi ────────────────────────────────────────────────────────

    /**
     * Menetapkan penanggung jawab satu tabel. Dikosongkan berarti tabel itu
     * tanggung jawab seluruh pokja pemiliknya. Orang dari pokja lain boleh
     * dipilih; ia lalu boleh mengisi tabel itu saja (lihat Izin).
     */
    public function tugaskanAction(): Action
    {
        return Action::make('tugaskan')
            ->label('Tugaskan penanggung jawab')
            ->tooltip('Tugaskan penanggung jawab')
            ->icon(Heroicon::OutlinedUserPlus)
            ->iconButton()
            ->color('gray')
            ->size('sm')
            ->visible(fn (array $arguments) => $this->bolehTugaskan($this->tagihanDari($arguments)))
            ->modalHeading(fn (array $arguments) => 'Penanggung jawab — '.$this->butirDari($arguments)?->label_tabel)
            ->modalDescription('Kosongkan untuk menyerahkannya kepada seluruh anggota pokja pemilik. '
                .'Orang dari pokja lain boleh dipilih; ia hanya mendapat akses mengisi tabel ini.')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('lg')
            ->fillForm(fn (array $arguments) => [
                'penanggung_jawab_id' => $this->tagihanDari($arguments)?->penanggung_jawab_id,
            ])
            ->schema(fn (array $arguments) => [
                Select::make('penanggung_jawab_id')
                    ->label('Penanggung jawab')
                    ->options($this->pilihanPenanggungJawab($this->butirDari($arguments)))
                    ->placeholder('Seluruh anggota pokja')
                    ->searchable()
                    ->native(false),
            ])
            ->action(function (array $data, array $arguments): void {
                $tagihan = $this->tagihanDari($arguments);

                // Argumen datang dari klien; wewenang diperiksa ulang di sini.
                abort_unless($this->bolehTugaskan($tagihan), 403);

                $pj = filled($data['penanggung_jawab_id'] ?? null)
                    ? User::bisaDitugaskan()->findOrFail($data['penanggung_jawab_id'])
                    : null;

                $tagihan->update(['penanggung_jawab_id' => $pj?->getKey()]);
                $pj?->notify(new TagihanDitugaskan($tagihan, auth()->user()));
                $this->tagihan = null;

                Notification::make()
                    ->title($pj === null
                        ? 'Tabel diserahkan kepada seluruh pokja'
                        : "Tabel ditugaskan kepada {$pj->nama_lengkap}")
                    ->success()->send();
            });
    }

    /**
     * Menambah dan melepas anggota satu pokja tanpa pindah ke menu Pokja.
     * Keanggotaan inilah yang membuka hak mengisi seluruh tabel pokja itu.
     */
    public function anggotaAction(): Action
    {
        return Action::make('anggota')
            ->label('Anggota')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('gray')
            ->size('sm')
            ->visible(fn (array $arguments) => ($pokja = $this->pokjaDari($arguments)) !== null
                && Izin::boleh(auth()->user(), 'pokja.anggota', $pokja))
            ->modalHeading(fn (array $arguments) => 'Anggota '.$this->pokjaDari($arguments)?->nama)
            ->modalDescription('Semua anggota pokja boleh mengisi seluruh tabel milik pokja ini. '
                .'Melepas seseorang langsung mencabut akses itu.')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('lg')
            ->fillForm(fn (array $arguments) => [
                'anggota' => $this->pokjaDari($arguments)?->anggota()->pluck('users.id')->all() ?? [],
            ])
            ->schema([
                Select::make('anggota')
                    ->label('Anggota')
                    ->options(fn () => $this->pilihanPengguna())
                    ->multiple()
                    ->searchable()
                    ->native(false),
            ])
            ->action(function (array $data, array $arguments): void {
                $pokja = $this->pokjaDari($arguments);

                abort_unless($pokja !== null && Izin::boleh(auth()->user(), 'pokja.anggota', $pokja), 403);

                $dipilih = $data['anggota'] ?? [];
                $kini = $pokja->anggota()->pluck('users.id')->all();

                // Hanya yang memang bisa dipilih di daftar yang boleh terlepas:
                // anggota nonaktif tidak tampil di pilihan, dan ketiadaannya di
                // kiriman bukan berarti ia sengaja dilepas.
                $lepas = array_diff(array_intersect($kini, array_keys($this->pilihanPengguna())), $dipilih);
                $tambah = array_diff($dipilih, $kini);

                $pokja->anggota()->detach($lepas);
                $pokja->anggota()->attach(array_fill_keys($tambah, ['peran_dalam_pokja' => 'anggota']));

                Notification::make()
                    ->title('Anggota pokja diperbarui')
                    ->body(count($tambah).' ditambahkan, '.count($lepas).' dilepas.')
                    ->success()->send();
            });
    }

    private function bolehTugaskan(?Tagihan $tagihan): bool
    {
        return $tagihan !== null && Izin::boleh(auth()->user(), 'tagihan.tugaskan', $tagihan);
    }

    private function butirDari(array $arguments): ?DkpsButir
    {
        return DkpsButir::find($arguments['butir'] ?? null);
    }

    private function tagihanDari(array $arguments): ?Tagihan
    {
        $butir = $this->butirDari($arguments);

        return $butir === null ? null : $this->tagihanTabel($butir);
    }

    private function pokjaDari(array $arguments): ?Pokja
    {
        return Pokja::query()
            ->where('periode_id', $this->periode()?->id)
            ->where('kode', $arguments['pokja'] ?? null)
            ->first();
    }

    /** @return array<string, string> id ⇒ "Nama (Peran)" seluruh pengguna yang bisa ditugaskan */
    private function pilihanPengguna(): array
    {
        return User::bisaDitugaskan()->orderBy('nama_lengkap')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => "{$u->nama_lengkap} ({$u->labelPeran()})"])->all();
    }

    /**
     * Koordinator dan anggota saja — hanya dua peran itu yang hak mengisinya
     * bergantung penugasan — dikelompokkan: pokja pemilik dulu, lalu pokja lain.
     *
     * @return array<string, array<string, string>>
     */
    private function pilihanPenanggungJawab(?DkpsButir $butir): array
    {
        $pemilik = Pokja::query()
            ->where('periode_id', $this->periode()?->id)
            ->where('kode', $butir?->pokja_kode)
            ->first();
        $idAnggota = $pemilik?->anggota()->pluck('users.id')->push($pemilik->koordinator_id)->filter()->all() ?? [];

        $calon = User::bisaDitugaskan()
            ->where(fn ($q) => $q->berperan(PeranPengguna::Koordinator)->orWhere(fn ($w) => $w->berperan(PeranPengguna::Anggota)))
            ->orderBy('nama_lengkap')->get();

        [$dalam, $luar] = $calon->partition(fn (User $u) => in_array($u->id, $idAnggota, true));
        $label = fn ($daftar) => $daftar->mapWithKeys(fn (User $u) => [$u->id => "{$u->nama_lengkap} ({$u->labelPeran()})"])->all();

        return array_filter([
            'Anggota '.($pemilik?->kode ?? 'pokja pemilik') => $label($dalam),
            'Pokja lain' => $label($luar),
        ]);
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
