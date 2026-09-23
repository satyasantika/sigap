<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use Filament\Facades\Filament;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Manual pengguna harus lengkap dan gambarnya harus benar-benar ada.
 *
 * Manual rusak tidak pernah membuat aplikasi gagal, jadi tanpa uji ini
 * kerusakannya baru ketahuan saat ada yang membukanya — biasanya orang baru,
 * di hari pertama, yang justru paling butuh manualnya utuh.
 *
 * Yang dijaga: satu halaman per peran, setiap <img> menunjuk berkas yang ada,
 * dan tidak ada gambar yatim yang tertinggal setelah layarnya dihapus.
 */
class ManualPenggunaTest extends TestCase
{
    private function akar(): string
    {
        return base_path('docs/manual');
    }

    /** @return array<string, array{string}> */
    public static function peran(): array
    {
        return collect(PeranPengguna::cases())
            ->mapWithKeys(fn (PeranPengguna $p) => [$p->value => [$p->value]])
            ->all();
    }

    #[Test]
    #[DataProvider('peran')]
    public function setiap_peran_punya_halaman_manual(string $peran): void
    {
        $berkas = $this->akar()."/{$peran}.html";

        $this->assertFileExists($berkas, "Manual peran {$peran} belum ada. Jalankan tools/susun-manual.py.");
        $this->assertStringContainsString('<img', file_get_contents($berkas),
            "Manual peran {$peran} harus bergambar — itu permintaan yang eksplisit.");
    }

    #[Test]
    #[DataProvider('peran')]
    public function setiap_gambar_yang_dirujuk_manual_benar_benar_ada(string $peran): void
    {
        $berkas = $this->akar()."/{$peran}.html";
        $isi = file_get_contents($berkas);

        preg_match_all('/<img[^>]+src="([^"]+)"/', $isi, $cocok);

        $this->assertNotEmpty($cocok[1], "Manual {$peran} tidak merujuk gambar apa pun.");

        foreach ($cocok[1] as $jalur) {
            $this->assertFileExists(
                $this->akar()."/{$jalur}",
                "Manual {$peran} merujuk {$jalur} yang tidak ada. Jalankan tools/tangkap-layar.py."
            );
        }
    }

    #[Test]
    public function halaman_indeks_menautkan_keenam_peran(): void
    {
        $isi = file_get_contents($this->akar().'/index.html');

        foreach (PeranPengguna::cases() as $p) {
            $this->assertStringContainsString("{$p->value}.html", $isi,
                "Indeks manual tidak menautkan peran {$p->value}.");
        }
    }

    #[Test]
    public function tidak_ada_gambar_yatim(): void
    {
        $dirujuk = [];

        foreach (glob($this->akar().'/*.html') as $halaman) {
            preg_match_all('/<img[^>]+src="([^"]+)"/', file_get_contents($halaman), $cocok);
            $dirujuk = [...$dirujuk, ...$cocok[1]];
        }

        $dirujuk = array_map(fn (string $j) => $this->akar()."/{$j}", $dirujuk);

        $ada = glob($this->akar().'/gambar/*/*.png');

        $yatim = array_values(array_diff($ada, $dirujuk));

        // Gambar yatim bukan kesalahan fatal, tetapi ia menumpuk di repo dan
        // membuat orang menebak-nebak layar mana yang sudah tidak ada.
        $this->assertSame([], $yatim,
            "Gambar berikut tidak dirujuk manual mana pun:\n".implode("\n", $yatim));
    }

    #[Test]
    public function tema_panel_terpasang(): void
    {
        // Tanpa tema sendiri, kelas Tailwind di Blade kita tidak terkompilasi
        // dan dasbor bento tampil sebagai teks polos. Lihat CLAUDE.md bagian 7
        // butir 14.
        $this->assertNotNull(
            Filament::getPanel('panel')->getTheme(),
            'Panel wajib memakai tema Vite sendiri.'
        );
        $this->assertFileExists(base_path('resources/css/filament/panel/theme.css'));
    }
}
