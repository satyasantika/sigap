<?php

namespace Tests\Feature;

use App\Support\Pemasangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SIGAP dipasang di dua bentuk alamat yang berbeda.
 *
 * `http://localhost:8021` di akar domain, dan
 * `https://supportfkip.unsil.ac.id/sigap` di bawah subfolder. Yang kedua itu
 * yang berbahaya: tanpa penyesuaian, setiap tautan menunjuk ke akar domain —
 * `/panel/login`, bukan `/sigap/panel/login` — dan halaman mukanya TETAP
 * TAMPIL. Pemasangannya kelihatan berhasil sampai ada yang menekan tombol.
 *
 * Karena itu diuji di sini, bukan diserahkan pada percobaan manual setelah
 * penggelaran.
 */
class PemasanganTest extends TestCase
{
    use RefreshDatabase;

    private function pasangDi(string $url): void
    {
        config(['app.url' => $url]);

        // URL facade menyimpan akar yang dipaksa; dikosongkan dulu supaya tiap
        // kasus tidak mewarisi kasus sebelumnya.
        URL::forceRootUrl(null);
        Pemasangan::terapkan();
    }

    protected function tearDown(): void
    {
        URL::forceRootUrl(null);

        parent::tearDown();
    }

    /** @return array<string, array{string, string}> */
    public static function alamat(): array
    {
        return [
            'akar domain' => ['http://localhost:8021', ''],
            'subfolder https' => ['https://supportfkip.unsil.ac.id/sigap', '/sigap'],
            'subfolder dengan garis miring' => ['https://supportfkip.unsil.ac.id/sigap/', '/sigap'],
            'subfolder bertingkat' => ['https://contoh.test/apps/sigap', '/apps/sigap'],
        ];
    }

    #[Test]
    #[DataProvider('alamat')]
    public function subfolder_diturunkan_dari_app_url(string $url, string $harapan): void
    {
        config(['app.url' => $url]);

        $this->assertSame($harapan, Pemasangan::subfolder());
        $this->assertSame($harapan !== '', Pemasangan::diSubfolder());
    }

    #[Test]
    public function tautan_membawa_awalan_subfolder(): void
    {
        $this->pasangDi('https://supportfkip.unsil.ac.id/sigap');

        $this->assertSame('https://supportfkip.unsil.ac.id/sigap/panel', url('/panel'));
        $this->assertSame('https://supportfkip.unsil.ac.id/sigap/manual', url('/manual'));
        $this->assertSame('https://supportfkip.unsil.ac.id/sigap', route('beranda'));
    }

    #[Test]
    public function tautan_panel_filament_juga_membawa_awalannya(): void
    {
        $this->pasangDi('https://supportfkip.unsil.ac.id/sigap');

        // Filament membangun tautannya lewat route(), jadi seharusnya ikut —
        // tetapi "seharusnya" bukan jaminan, dan panel yang tautannya salah
        // berarti tidak ada yang bisa masuk sama sekali.
        $this->assertStringStartsWith(
            'https://supportfkip.unsil.ac.id/sigap/panel',
            \App\Filament\Pages\Dasbor::getUrl(),
        );

        $this->assertStringStartsWith(
            'https://supportfkip.unsil.ac.id/sigap/panel',
            \Filament\Facades\Filament::getLoginUrl(),
        );
    }

    #[Test]
    public function aset_terbangun_juga_membawa_awalannya(): void
    {
        $this->pasangDi('https://supportfkip.unsil.ac.id/sigap');

        // Aset yang jalurnya salah tidak melempar galat apa pun: halamannya
        // tetap dikirim, hanya saja tanpa gaya sama sekali. Persis kegagalan
        // yang sama seperti panel tanpa tema Vite.
        $this->assertStringStartsWith(
            'https://supportfkip.unsil.ac.id/sigap/build/',
            asset('build/manifest.json'),
        );

        preg_match('#href="([^"]+)"#', (string) app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css']), $m);

        $this->assertStringStartsWith('https://supportfkip.unsil.ac.id/sigap/build/', $m[1] ?? '');
    }

    #[Test]
    public function pemasangan_di_akar_domain_tidak_tersentuh(): void
    {
        $this->pasangDi('http://localhost:8021');

        $this->assertSame('http://localhost:8021/panel', url('/panel'));
    }

    #[Test]
    public function halaman_muka_di_subfolder_menautkan_dengan_awalan(): void
    {
        $this->pasangDi('https://supportfkip.unsil.ac.id/sigap');

        // Alamat ditulis penuh dengan sengaja. Permintaan uji yang relatif
        // dibangun dari config('app.url'), jadi `get('/')` akan meminta
        // `/sigap` — padahal server balik sudah memangkas awalan itu sebelum
        // permintaannya sampai ke PHP. Yang benar-benar dilihat Laravel adalah
        // jalur `/`, dan itulah yang diuji di sini.
        $this->get('http://supportfkip.unsil.ac.id/')
            ->assertOk()
            ->assertSee('https://supportfkip.unsil.ac.id/sigap/panel', escape: false)
            ->assertSee('https://supportfkip.unsil.ac.id/sigap/manual', escape: false);
    }

    #[Test]
    public function manual_menautkan_balik_secara_relatif(): void
    {
        // Tautan "Beranda" dan "Masuk" di manual WAJIB relatif. Jalur absolut
        // `/panel` akan menunjuk ke luar aplikasi begitu dipasang di /sigap,
        // dan manualnya berkas statis — tidak ada url() yang bisa menolongnya.
        foreach (glob(base_path('docs/manual/*.html')) as $halaman) {
            $isi = file_get_contents($halaman);
            $nama = basename($halaman);

            $this->assertStringContainsString('href="../"', $isi,
                "{$nama} kehilangan tautan relatif ke beranda.");
            $this->assertStringContainsString('href="../panel"', $isi,
                "{$nama} kehilangan tautan relatif ke halaman masuk.");
            $this->assertStringNotContainsString('href="/panel"', $isi,
                "{$nama} memakai jalur absolut yang patah di subfolder.");
        }
    }

    #[Test]
    public function tidak_ada_jalur_absolut_yang_ditulis_mati_di_tampilan(): void
    {
        $pelanggar = [];

        foreach ([resource_path('views'), app_path()] as $akar) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar));

            foreach ($iter as $f) {
                if (! in_array($f->getExtension(), ['php'], true)) {
                    continue;
                }

                $isi = file_get_contents($f->getPathname());

                // href/src/action yang menunjuk akar domain akan patah di
                // subfolder. Yang sah hanya lewat url(), route(), atau asset().
                if (preg_match('#(href|src|action)="/(?!/)#', $isi)) {
                    $pelanggar[] = str_replace(base_path().'/', '', $f->getPathname());
                }
            }
        }

        sort($pelanggar);

        $this->assertSame([], $pelanggar,
            "Jalur absolut patah begitu SIGAP dipasang di bawah subfolder.\n"
            ."Pakai url(), route(), atau Resource::getUrl().\n".implode("\n", $pelanggar));
    }
}
