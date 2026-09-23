<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Pages\Dasbor;
use App\Filament\Sistem\MenuPengguna;
use App\Models\Prodi;
use App\Models\User;
use App\Services\Impersonasi;
use App\Support\Jati;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tiga hal yang diminta manusia dan mudah hilang tanpa disadari: tema terang
 * sebagai bawaan, footer hak cipta di setiap halaman, dan konfirmasi sebelum
 * keluar.
 *
 * Ketiganya dipasang lewat AdminPanelProvider — satu baris yang terhapus saat
 * menyunting panel akan menghilangkannya dari SELURUH aplikasi sekaligus,
 * tanpa satu pun uji lain menjadi merah.
 */
class TampilanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Dasbor menanyakan `dasbor.lihat` ke tabel izin. Tanpa semaian,
        // seluruh peran ditolak dan halamannya menjawab 403 — bukan karena
        // footernya hilang.
        $this->seed(\Database\Seeders\IzinSeeder::class);
    }

    private function pengguna(PeranPengguna $peran = PeranPengguna::Ketua): User
    {
        $prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);

        return User::factory()->peran($peran)->create(['prodi_id' => $prodi->id]);
    }

    #[Test]
    public function tema_terang_adalah_bawaan(): void
    {
        $this->assertSame(
            ThemeMode::Light,
            Filament::getPanel('panel')->getDefaultThemeMode(),
            'CLAUDE.md bagian 7 butir 10: tampilan pertama memakai tema terang.'
        );
    }

    #[Test]
    public function footer_hak_cipta_muncul_di_halaman_panel(): void
    {
        $this->actingAs($this->pengguna());

        $this->get(Dasbor::getUrl())
            ->assertOk()
            ->assertSee(Jati::hakCipta(), escape: false)
            ->assertSee(Jati::namaPanjang());
    }

    #[Test]
    public function footer_hak_cipta_muncul_di_halaman_masuk(): void
    {
        // Halaman masuk memakai tata letak sederhana, bukan tata letak panel.
        // Keduanya memanggil render hook panels::footer — uji ini yang menjaga
        // agar pernyataan itu tetap benar bila Filament berubah.
        $this->get(Filament::getLoginUrl())
            ->assertOk()
            ->assertSee(Jati::hakCipta(), escape: false);
    }

    #[Test]
    public function tahun_hak_cipta_dibaca_dari_konfigurasi(): void
    {
        $this->assertSame('2026', Jati::tahunHakCipta());
        $this->assertSame('Satya Santika', Jati::pemilik());
        $this->assertStringContainsString('2026', Jati::hakCipta());
        $this->assertStringContainsString('Satya Santika', Jati::hakCipta());
    }

    #[Test]
    public function keluar_meminta_konfirmasi_lebih_dulu(): void
    {
        $this->actingAs($this->pengguna());

        $keluar = MenuPengguna::item()['logout'];

        $this->assertTrue($keluar->isConfirmationRequired(),
            'Keluar tanpa konfirmasi berarti satu salah klik menghapus isian yang belum tersimpan.');
        $this->assertSame('Keluar dari SIGAP?', $keluar->getModalHeading());
        $this->assertNotNull($keluar->getModalSubmitActionLabel());
    }

    #[Test]
    public function tombol_kembali_ke_akun_saya_tersembunyi_saat_tidak_menyamar(): void
    {
        $this->actingAs($this->pengguna());

        $this->assertFalse(MenuPengguna::item()['kembali_admin']->isVisible());
    }

    #[Test]
    public function tombol_kembali_ke_akun_saya_muncul_saat_menyamar(): void
    {
        $prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
        $admin = User::factory()->peran(PeranPengguna::Admin)->create(['prodi_id' => $prodi->id]);
        $ketua = User::factory()->peran(PeranPengguna::Ketua)->create(['prodi_id' => $prodi->id]);

        $this->actingAs($admin);
        app(Impersonasi::class)->mulai($admin, $ketua, 'memeriksa layar ketua');

        $this->assertTrue(MenuPengguna::item()['kembali_admin']->isVisible());
    }

    #[Test]
    public function spanduk_penyamaran_tampil_hanya_saat_menyamar(): void
    {
        $prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
        $admin = User::factory()->peran(PeranPengguna::Admin)->create(['prodi_id' => $prodi->id]);
        $ketua = User::factory()->peran(PeranPengguna::Ketua)->create([
            'prodi_id' => $prodi->id, 'nama_lengkap' => 'Ketua Gugus Uji',
        ]);

        $this->actingAs($admin);
        $this->get(Dasbor::getUrl())->assertDontSee('sedang');

        app(Impersonasi::class)->mulai($admin, $ketua, 'memeriksa layar ketua');

        $this->get(Dasbor::getUrl())
            ->assertOk()
            ->assertSee('Ketua Gugus Uji')
            ->assertSee('menyamar');
    }
}
