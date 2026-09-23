<?php

namespace App\Filament\Pages;

use App\Enums\LevelSyaratPerlu;
use App\Models\Bukti;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\StatusSyaratPerlu;
use App\Services\KalkulatorNa;
use App\Services\RingkasanProgres;
use App\Support\Izin;
use App\Support\Na\HasilNa;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Dasbor bento sembilan ubin.
 *
 * Dokumen 10 yang menang atas dokumen 05 bila keduanya berbeda.
 *
 * Tiga aturan yang paling mudah dilanggar, dan konsekuensinya:
 *
 * 1. K2 dihitung dari BOBOT, tidak pernah dari cacah tagihan, dan setiap
 *    persen didampingi angka absolut. Persentase tanpa angka absolut membuat
 *    "90%" dibaca sebagai hampir selesai padahal 10 bobot yang tersisa bisa
 *    berisi seluruh elemen syarat perlu.
 *
 * 2. K1 dan K2 TIDAK BOLEH dipisah. Progres tinggi tanpa syarat perlu
 *    terpenuhi tetap berujung "Terakreditasi", bukan "Unggul" — dan orang
 *    yang hanya melihat K2 akan mengira sudah aman.
 *
 * 3. K6 (DKPS) tidak ikut dihitung ke dalam K2, karena butir DKPS tidak
 *    berbobot. Mencampurnya menggeser angka yang seharusnya berjumlah 100.
 */
class Dasbor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -10;

    protected static ?string $navigationLabel = 'Dasbor';

    protected static ?string $title = 'Dasbor';

    protected static ?string $slug = 'dasbor';

    protected string $view = 'filament.pages.dasbor';

    public static function canAccess(): bool
    {
        return auth()->check() && Izin::boleh(auth()->user(), 'dasbor.lihat');
    }

    /**
     * Menu ini muncul hanya bagi peran yang memang mengurusnya.
     *
     * Menyembunyikan, bukan menolak: canAccess() di atas yang menolak.
     * Lihat App\Support\Izin::bolehMenu() dan data/menu.json.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && Izin::bolehMenu(auth()->user(), 'dasbor');
    }

    public function periode(): ?Periode
    {
        return Periode::aktif()->first();
    }

    /**
     * Ubin yang dilihat peran ini.
     *
     * Pemetaannya ada di enum PeranPengguna bersama label dan warna — ia
     * tampilan, bukan wewenang. Tidak ada perbandingan peran di sini;
     * AGENTS.md aturan 10 melarangnya di luar App\Support\Izin, dan ada uji
     * yang menelusurinya.
     *
     * @return array<int, string>
     */
    public function ubinTerlihat(): array
    {
        return auth()->user()?->peran->ubinDasbor() ?? [];
    }

    public function lihat(string $ubin): bool
    {
        return in_array($ubin, $this->ubinTerlihat(), true);
    }

    // ---- K1: Gerbang Unggul ---------------------------------------------

    public function na(): ?HasilNa
    {
        $p = $this->periode();

        return $p === null ? null : app(KalkulatorNa::class)->hitung($p);
    }

    /** Lima titik syarat perlu, terisi bila level lima tahun. */
    public function titikSyaratPerlu(): array
    {
        $p = $this->periode();

        if ($p === null) {
            return [];
        }

        $level = StatusSyaratPerlu::where('periode_id', $p->id)->pluck('level', 'elemen_id');

        return Elemen::bersyaratPerlu()->orderBy('no')->get()->map(fn (Elemen $e) => [
            'no' => $e->no,
            'nama' => $e->nama,
            'level' => $level[$e->id] ?? LevelSyaratPerlu::Belum,
        ])->all();
    }

    /**
     * Posisi NA pada skala 100–400 sebagai persentase lebar bilah.
     *
     * Skalanya 100–400, BUKAN 0–100 persen: ambang Unggul adalah 361, dan
     * menampilkannya sebagai "90%" akan membuat orang mengira ambangnya 90%.
     */
    public function posisiNa(float $na): float
    {
        return round(max(0, min(100, (($na - 100) / 300) * 100)), 2);
    }

    public function penandaSkala(): array
    {
        return collect([200, 321, 361])
            ->mapWithKeys(fn (int $n) => [$n => $this->posisiNa($n)])->all();
    }

    // ---- K2 sampai K9 ----------------------------------------------------

    public function ringkasan(): RingkasanProgres
    {
        return app(RingkasanProgres::class);
    }

    public function bobotSelesai(): float
    {
        $p = $this->periode();

        return $p === null ? 0.0 : $this->ringkasan()->bobotSelesai($p);
    }

    public function buktiBermasalah(): int
    {
        $p = $this->periode();

        return $p === null ? 0 : Bukti::where('periode_id', $p->id)->bermasalah()->count();
    }

    public function dkps(): array
    {
        $p = $this->periode();

        return $p === null
            ? ['terverifikasi' => 0, 'total' => 28, 'rasio' => 0.0]
            : $this->ringkasan()->progresDkps($p);
    }

    public function progresPokja(): array
    {
        $p = $this->periode();

        return $p === null ? [] : $this->ringkasan()->progresPokja($p);
    }

    public function laju(): array
    {
        $p = $this->periode();

        return $p === null
            ? ['kalimat' => 'Belum ada periode aktif.', 'warna' => 'gray']
            : $this->ringkasan()->kalimatLaju($p);
    }

    public function lajuAngka(): array
    {
        $p = $this->periode();

        return $p === null ? [] : $this->ringkasan()->lajuDanPerkiraan($p);
    }

    /**
     * K9 — daftar, bukan grafik. Empat kelompok yang bisa diklik.
     *
     * Untuk anggota, daftarnya disaring menjadi miliknya sendiri: dasbor yang
     * menampilkan 137 tagihan orang lain tidak membantunya memulai hari.
     */
    public function yangPerluDikerjakan(): array
    {
        $p = $this->periode();

        if ($p === null) {
            return [];
        }

        $r = $this->ringkasan();
        $u = auth()->user();

        // Penyaringan diturunkan dari LINGKUP IZIN, bukan dari peran: pengguna
        // yang lingkup `tagihan.lihat`-nya `miliknya` memang hanya boleh
        // melihat tagihannya sendiri, dan daftar di sini harus tunduk pada
        // pagar yang sama dengan daftar di Resource.
        $hanyaMilikku = Izin::nilai('tagihan.lihat', $u->peran->value) === 'miliknya';

        $terlambat = $r->tagihanTerlambat($p);
        $tanpaPj = $r->tagihanTanpaPj($p);

        if ($hanyaMilikku) {
            $terlambat = $terlambat->where('penanggung_jawab_id', $u->getKey());
            $tanpaPj = collect();
        }

        return [
            ['judul' => 'Tagihan terlambat', 'jumlah' => $terlambat->count(),
                'warna' => 'danger', 'url' => '/panel/tagihans?tableFilters[terlambat][isActive]=true'],
            ['judul' => 'Tagihan tanpa penanggung jawab', 'jumlah' => $tanpaPj->count(),
                'warna' => 'warning', 'url' => '/panel/tagihans?tableFilters[tanpa_pj][isActive]=true'],
            ['judul' => 'Elemen tanpa bukti', 'jumlah' => $r->elemenTanpaBukti($p)->count(),
                'warna' => 'warning', 'url' => '/panel/buktis'],
            ['judul' => 'Naskah di bawah 200 kata', 'jumlah' => $r->narasiDiBawahMinimal($p)->count(),
                'warna' => 'info', 'url' => '/panel/narasis?tableFilters[kurang_kata][isActive]=true'],
        ];
    }

    public function tagihanTerlambat(): int
    {
        $p = $this->periode();

        return $p === null ? 0 : $this->ringkasan()->tagihanTerlambat($p)->count();
    }

    public function bobotTertahan(): float
    {
        $p = $this->periode();

        return $p === null ? 0.0 : $this->ringkasan()->bobotTertahan($p);
    }

    /** Dua desimal dengan koma, sesuai aturan tampilan dokumen 10. */
    public function angka(float $n, int $desimal = 2): string
    {
        return number_format($n, $desimal, ',', '.');
    }
}
