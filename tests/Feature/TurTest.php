<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tur terpandu: terbuka, tanpa sesi, tanpa basis data.
 *
 * Naskah dan gambarnya berasal dari `docs/manual/tur.json`, berkas yang sama
 * yang menyusun manual. Uji di sini menjaga tiga hal: berkas itu ada dan utuh,
 * turnya benar-benar tidak menyentuh basis data, dan setiap gambar yang
 * dirujuknya benar-benar tersaji.
 */
class TurTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function peran(): array
    {
        return collect(PeranPengguna::cases())
            ->mapWithKeys(fn (PeranPengguna $p) => [$p->value => [$p->value]])
            ->all();
    }

    #[Test]
    public function halaman_tur_terbuka_tanpa_masuk(): void
    {
        $this->get('/tur')->assertOk()->assertSee('Tur terpandu');
        $this->assertGuest();
    }

    #[Test]
    public function tur_tidak_menyentuh_basis_data(): void
    {
        // Alasan yang sama dengan halaman muka: tur terbuka untuk siapa saja.
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get('/tur')->assertOk();
        $this->get('/tur/ketua')->assertOk();

        $this->assertSame([], DB::getQueryLog(),
            'Tur menjalankan kueri. Halaman terbuka tidak boleh menyentuh data.');
    }

    #[Test]
    #[DataProvider('peran')]
    public function setiap_peran_punya_tur_yang_bisa_ditelusuri(string $peran): void
    {
        $tur = json_decode(file_get_contents(base_path('docs/manual/tur.json')), true);
        $jumlah = count($tur['peran'][$peran]['langkah']);

        $this->assertGreaterThan(0, $jumlah, "Tur peran {$peran} tidak punya langkah.");

        // Langkah pertama, tengah, dan terakhir — cukup untuk membuktikan
        // penomorannya tidak lepas di ujung.
        foreach ([1, (int) ceil($jumlah / 2), $jumlah] as $ke) {
            $this->get("/tur/{$peran}?langkah={$ke}")
                ->assertOk()
                ->assertSee("Langkah {$ke}")
                ->assertSee("{$ke} / {$jumlah}");
        }
    }

    #[Test]
    public function nomor_langkah_di_luar_jangkauan_dijepit_bukan_meledak(): void
    {
        $this->get('/tur/ketua?langkah=999')->assertOk();
        $this->get('/tur/ketua?langkah=-4')->assertOk()->assertSee('Langkah 1');
        $this->get('/tur/ketua?langkah=bukan-angka')->assertOk()->assertSee('Langkah 1');
    }

    #[Test]
    public function peran_yang_tidak_dikenal_menghasilkan_404_yang_menjelaskan(): void
    {
        $this->get('/tur/ngawur')->assertNotFound()->assertSee('Yang bisa dilakukan sekarang');
    }

    #[Test]
    public function setiap_gambar_yang_dirujuk_tur_benar_benar_ada(): void
    {
        $tur = json_decode(file_get_contents(base_path('docs/manual/tur.json')), true);

        foreach ($tur['peran'] as $kode => $p) {
            foreach ($p['langkah'] as $langkah) {
                $this->assertFileExists(
                    base_path('docs/manual/gambar/'.$langkah['gambar']),
                    "Tur {$kode} merujuk gambar {$langkah['gambar']} yang tidak ada. "
                    .'Jalankan tools/tangkap-layar.py lalu tools/susun-manual.py.'
                );
            }
        }
    }

    #[Test]
    public function tur_dan_manual_memakai_gambar_yang_sama(): void
    {
        // Manual dan tur yang menceritakan dua versi sistem yang berbeda lebih
        // buruk daripada salah satunya tidak ada.
        $tur = json_decode(file_get_contents(base_path('docs/manual/tur.json')), true);

        foreach ($tur['peran'] as $kode => $p) {
            $manual = file_get_contents(base_path("docs/manual/{$kode}.html"));

            foreach ($p['langkah'] as $langkah) {
                $this->assertStringContainsString(
                    'gambar/'.$langkah['gambar'],
                    $manual,
                    "Gambar {$langkah['gambar']} ada di tur tetapi tidak di manual {$kode}."
                );
            }
        }
    }
}
