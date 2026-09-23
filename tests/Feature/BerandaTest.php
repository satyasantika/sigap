<?php

namespace Tests\Feature;

use App\Support\Jati;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman muka: terbuka untuk siapa pun, jadi batasnya keras.
 *
 * Dua hal yang dijaga berkas ini, dan yang kedua lebih penting daripada yang
 * pertama:
 *
 *   1. Halamannya utuh — tautan ke manual dan ke halaman masuk benar-benar ada,
 *      dan angkanya cocok dengan data/*.json.
 *   2. Halamannya TIDAK menyentuh basis data sama sekali. Satu kueri yang
 *      diselipkan nanti — "sekalian tampilkan progres periode berjalan" —
 *      membuat angka akreditasi bocor ke halaman yang bisa dibuka siapa saja,
 *      dan itu tidak akan terasa salah saat ditulis.
 */
class BerandaTest extends TestCase
{
    private function elemen(): array
    {
        return json_decode(file_get_contents(base_path('data/elemen.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function halaman_muka_terbuka_tanpa_masuk(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(Jati::nama())
            ->assertSee(Jati::namaPanjang())
            ->assertSee(Jati::hakCipta(), escape: false);

        $this->assertGuest();
    }

    #[Test]
    public function halaman_muka_tidak_menyentuh_basis_data(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get('/')->assertOk();

        $kueri = DB::getQueryLog();

        $this->assertSame([], $kueri,
            "Halaman muka terbuka tanpa masuk. Satu kueri saja membuka jalan bagi angka\n"
            .'akreditasi bocor ke publik. Kueri yang terjadi: '
            .implode(' | ', array_column($kueri, 'query')));
    }

    #[Test]
    public function angka_instrumen_dibaca_dari_berkas_yang_sama_dengan_seeder(): void
    {
        $elemen = $this->elemen();
        $bobot = number_format(round(array_sum(array_column($elemen, 'bobot')), 2), 2, ',', '.');

        $this->get('/')
            ->assertOk()
            ->assertSee((string) count($elemen))
            ->assertSee($bobot)
            ->assertSee('elemen penilaian')
            ->assertSee('total bobot');
    }

    #[Test]
    public function halaman_muka_menautkan_manual_dan_halaman_masuk(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(url('/manual'), escape: false)
            ->assertSee(url('/panel'), escape: false)
            ->assertSee('Baca manual pengguna');
    }

    /** @return array<string, array{string, string}> */
    public static function peranPublik(): array
    {
        $isi = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/data/peran.json'),
            true, flags: JSON_THROW_ON_ERROR
        );

        return collect($isi)
            ->mapWithKeys(fn (array $p) => [$p['kode'] => [$p['kode'], $p['nama']]])
            ->all();
    }

    #[Test]
    #[DataProvider('peranPublik')]
    public function setiap_peran_tampil_dan_menautkan_manualnya(string $kode, string $nama): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee($nama)
            ->assertSee(url("/manual/{$kode}.html"), escape: false);
    }

    #[Test]
    public function halaman_muka_tidak_diindeks_mesin_pencari(): void
    {
        // Aplikasi internal. Halaman muka boleh dibuka siapa pun yang tahu
        // alamatnya, tetapi tidak perlu muncul di hasil pencarian.
        $this->get('/')->assertOk()->assertSee('name="robots"', escape: false);
    }

    #[Test]
    public function manual_dialihkan_ke_berkas_indeksnya(): void
    {
        // Tautan di dalam manual bersifat relatif. Dari `/manual` tanpa garis
        // miring, `admin.html` akan meresolusi ke `/admin.html` yang tidak ada.
        $this->get('/manual')->assertRedirect('/manual/index.html');
    }

    #[Test]
    public function symlink_manual_menunjuk_ke_dalam_docs(): void
    {
        $tautan = public_path('manual');

        $this->assertTrue(is_link($tautan) || is_dir($tautan),
            "public/manual tidak ada. Buat ulang dengan:\n  ln -s ../docs/manual public/manual");

        $this->assertSame(
            realpath(base_path('docs/manual')),
            realpath($tautan),
            'public/manual harus menunjuk docs/manual — satu salinan, bukan dua.'
        );
    }

    #[Test]
    public function berkas_yang_dirujuk_halaman_muka_benar_benar_tersaji(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#src="[^"]*/manual/([^"]+)"#', $html, $cocok);

        $this->assertNotEmpty($cocok[1], 'Halaman muka tidak memuat satu pun gambar dari manual.');

        foreach ($cocok[1] as $jalur) {
            $this->assertFileExists(
                base_path("docs/manual/{$jalur}"),
                "Halaman muka merujuk /manual/{$jalur} yang tidak ada. Jalankan tools/tangkap-layar.py."
            );
        }
    }
}
