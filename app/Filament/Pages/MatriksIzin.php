<?php

namespace App\Filament\Pages;

use App\Enums\PeranPengguna;
use App\Models\Izin as ModelIzin;
use App\Support\Izin;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Halaman HANYA-BACA yang menampilkan 144 sel apa adanya.
 *
 * vibecoding/docs/08-auth-dan-izin.md aturan 5: siapa pun yang bertanya
 * "kenapa saya tidak bisa menekan tombol itu" harus bisa melihat jawabannya
 * sendiri, bukan menunggu penjelasan lisan.
 *
 * Tidak ada penyuntingan di sini. Matriks diubah dengan menyunting
 * data/izin.json lalu menjalankan seeder — dan perubahan itu wajib jadi
 * commit tersendiri berjenis `data:`.
 */
class MatriksIzin extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Matriks Izin';

    protected static ?string $title = 'Matriks Izin';

    protected string $view = 'filament.pages.matriks-izin';

    /** @return array<string, string> kode aksi => label manusiawi */
    public function labelAksi(): array
    {
        $berkas = base_path('data/izin.json');

        if (! is_file($berkas)) {
            return [];
        }

        $isi = json_decode(file_get_contents($berkas), true);

        return collect($isi['aksi'] ?? [])
            ->mapWithKeys(fn (array $a) => [$a['kode'] => $a['label']])
            ->all();
    }

    /** @return array<string, array<string, string>> */
    public function matriks(): array
    {
        return Izin::matriks();
    }

    /** @return array<int, PeranPengguna> */
    public function peran(): array
    {
        return PeranPengguna::cases();
    }

    public function jumlahSel(): int
    {
        return ModelIzin::count();
    }

    /** Penjelasan kelima nilai lingkup, diambil dari data/izin.json. */
    public function lingkup(): array
    {
        $berkas = base_path('data/izin.json');

        if (! is_file($berkas)) {
            return [];
        }

        return json_decode(file_get_contents($berkas), true)['lingkup'] ?? [];
    }

    public static function canAccess(): bool
    {
        // Setiap orang yang bisa membuka dasbor boleh melihat aturannya.
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
        return static::canAccess() && Izin::bolehMenu(auth()->user(), 'matriks_izin');
    }
}
