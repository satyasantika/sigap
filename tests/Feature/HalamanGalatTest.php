<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Exceptions\KodeRujukan;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Prodi;
use App\Models\User;
use App\Support\Jati;
use Database\Seeders\IzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Halaman galat harus menjelaskan, bukan sekadar menolak.
 *
 * Tiga hal yang dijaga, dan ketiganya pernah hilang dari aplikasi mana pun yang
 * memakai halaman galat bawaan: APA yang terjadi, MENGAPA, dan APA YANG BISA
 * DILAKUKAN sekarang. Halaman 403 yang hanya berbunyi "403 Forbidden"
 * menghasilkan satu tiket dukungan per kejadian; halaman yang menunjuk Matriks
 * Izin menghasilkan nol.
 */
class HalamanGalatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        KodeRujukan::lupakan();
    }

    /** @return array<string, array{string}> */
    public static function kodeGalat(): array
    {
        return collect(['401', '403', '404', '419', '429', '500', '503'])
            ->mapWithKeys(fn (string $k) => [$k => [$k]])
            ->all();
    }

    #[Test]
    #[DataProvider('kodeGalat')]
    public function setiap_halaman_galat_menjelaskan_dan_memberi_langkah(string $kode): void
    {
        $html = view("errors.{$kode}", [
            'exception' => new HttpException((int) $kode, ''),
        ])->render();

        $this->assertStringContainsString("<h1>", $html, "Halaman {$kode} tanpa judul.");
        $this->assertStringContainsString('Yang bisa dilakukan sekarang', $html,
            "Halaman {$kode} tidak memberi langkah lanjut — itu bagian yang paling dibutuhkan.");
        $this->assertStringContainsString('<li>', $html,
            "Halaman {$kode} memuat judul bagian langkah tetapi tidak satu pun langkahnya.");
        $this->assertStringContainsString(Jati::hakCipta(), $html,
            "Halaman {$kode} kehilangan footer hak cipta.");
        $this->assertStringContainsString('Kembali ke SIGAP', $html,
            "Halaman {$kode} tidak menyediakan jalan pulang.");
        $this->assertStringNotContainsString('Whoops', $html);
    }

    #[Test]
    public function halaman_403_menyebut_peran_dan_menunjuk_matriks_izin(): void
    {
        $prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
        $u = User::factory()->peran(PeranPengguna::Anggota)->create([
            'prodi_id' => $prodi->id, 'nama_lengkap' => 'Anggota Uji',
        ]);

        $this->actingAs($u);

        $html = view('errors.403', ['exception' => new HttpException(403, '')])->render();

        $this->assertStringContainsString('Anggota Uji', $html);
        $this->assertStringContainsString('Anggota Pokja', $html);
        $this->assertStringContainsString('matriks-izin', $html);
    }

    #[Test]
    public function peran_tanpa_wewenang_mendapat_halaman_403_yang_menjelaskan(): void
    {
        $this->seed(IzinSeeder::class);

        $prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
        $u = User::factory()->peran(PeranPengguna::Anggota)->create(['prodi_id' => $prodi->id]);

        $this->actingAs($u)
            ->get(ListUsers::getUrl())
            ->assertForbidden()
            ->assertSee('bukan wewenang Anda')
            ->assertSee('Yang bisa dilakukan sekarang')
            ->assertSee('Matriks Izin');
    }

    #[Test]
    public function alamat_yang_tidak_dikenal_mendapat_halaman_404_yang_menjelaskan(): void
    {
        $this->get('/alamat-yang-tidak-pernah-ada')
            ->assertNotFound()
            ->assertSee('Halaman ini tidak ada')
            ->assertSee('Yang bisa dilakukan sekarang');
    }

    #[Test]
    public function halaman_500_menampilkan_kode_rujukan_yang_bisa_dicari_di_log(): void
    {
        $html = view('errors.500', ['exception' => new HttpException(500, '')])->render();

        $this->assertMatchesRegularExpression('/SIGAP-[A-Z0-9]{8}/', $html,
            'Halaman 500 wajib menampilkan kode rujukan; tanpa itu laporan galat tidak bisa ditelusuri.');
        $this->assertStringContainsString(KodeRujukan::kode(), $html,
            'Kode di layar harus SAMA dengan kode yang masuk ke log.');
    }

    #[Test]
    public function kode_rujukan_tetap_sama_sepanjang_satu_permintaan(): void
    {
        $a = KodeRujukan::kode();
        $b = KodeRujukan::kode();

        $this->assertSame($a, $b,
            'Satu permintaan yang memicu beberapa baris log harus membawa satu kode.');

        KodeRujukan::lupakan();

        $this->assertNotSame($a, KodeRujukan::kode(),
            'Permintaan berikutnya harus mendapat kode baru.');
    }

    #[Test]
    public function halaman_galat_berdiri_sendiri_tanpa_aset_yang_bisa_gagal_dimuat(): void
    {
        // Halaman galat harus tetap tampil ketika yang rusak justru panelnya.
        // Satu <link rel=stylesheet> ke berkas yang gagal dibangun sudah cukup
        // membuat halaman galat ikut tidak terbaca.
        foreach (['401', '403', '404', '419', '429', '500', '503'] as $kode) {
            $html = view("errors.{$kode}", ['exception' => new HttpException((int) $kode, '')])->render();

            $this->assertStringNotContainsString('<link rel="stylesheet"', $html,
                "Halaman {$kode} bergantung pada CSS luar.");
            $this->assertStringNotContainsString('@vite', $html);
            $this->assertStringContainsString('<style>', $html,
                "Halaman {$kode} harus membawa gayanya sendiri.");
        }
    }
}
