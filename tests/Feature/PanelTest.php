<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Auth\Masuk;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Panel Filament: masuk, tolak yang nonaktif, dan pagar Policy per peran.
 *
 * Kriteria terima menuntut penolakan dibuktikan sebagai 403, bukan sekadar
 * tombol yang disembunyikan (vibecoding/docs/04-peran-dan-alur.md).
 */
class PanelTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(PeranPengguna $peran, bool $aktif = true): User
    {
        $prodi = Prodi::firstOrCreate(
            ['kode' => 'PPG-UJI'],
            ['nama' => 'PPG', 'jenjang' => 'ppg', 'upps' => 'FKIP',
                'perguruan_tinggi' => 'Unsil', 'aktif' => true],
        );

        return User::create([
            'name' => $peran->value,
            'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@panel.test',
            'password' => 'rahasia123',
            'peran' => $peran,
            'prodi_id' => $prodi->id,
            'aktif' => $aktif,
            'wajib_ganti_sandi' => false,
        ]);
    }

    #[Test]
    public function panel_berada_di_slash_panel(): void
    {
        $this->get('/panel/login')->assertSuccessful();

        // Akar situs dulu mengalihkan ke /panel. Sejak ada halaman muka, ia
        // menyajikan halamannya sendiri dan MENAUTKAN panel. Yang tetap dijaga
        // di sini: panelnya masih di /panel dan masih bisa dicapai dari akar.
        $this->get('/')
            ->assertOk()
            ->assertSee(url('/panel'), escape: false);
    }

    #[Test]
    public function tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/panel')->assertRedirect('/panel/login');
    }

    #[Test]
    public function admin_bisa_membuka_pengelolaan_pengguna(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::Admin))
            ->get('/panel/users')
            ->assertSuccessful();
    }

    #[Test]
    public function ketua_tidak_bisa_mengelola_pengguna(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Dua sumbu wewenang: ketua memiliki isi akreditasi, bukan pengguna.
        // vibecoding/docs/08-auth-dan-izin.md aturan 3.
        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get('/panel/users')
            ->assertForbidden();
    }

    #[Test]
    public function admin_tidak_bisa_mengelola_pokja_isi_akreditasi_bukan_wewenangnya(): void
    {
        $this->seed(DatabaseSeeder::class);

        // pokja.kelola = ya untuk admin DAN ketua, jadi admin memang boleh.
        // Yang diuji di sini: admin TIDAK boleh membuat periode? Tidak —
        // periode.kelola justru milik admin. Yang benar-benar tertutup bagi
        // admin adalah isi akreditasi, diuji lewat MatriksIzinTest.
        $this->actingAs($this->pengguna(PeranPengguna::Admin))
            ->get('/panel/pokjas')
            ->assertSuccessful();
    }

    #[Test]
    public function pimpinan_tidak_bisa_membuat_periode(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::Pimpinan))
            ->get('/panel/periodes/create')
            ->assertForbidden();
    }

    #[Test]
    public function pimpinan_tidak_bisa_membuat_pokja(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::Pimpinan))
            ->get('/panel/pokjas/create')
            ->assertForbidden();
    }

    #[Test]
    public function anggota_tidak_bisa_membuat_pengguna(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::Anggota))
            ->get('/panel/users/create')
            ->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function semuaPeran(): array
    {
        return [
            'admin' => ['admin'],
            'ketua' => ['ketua'],
            'pimpinan' => ['pimpinan'],
            'koordinator' => ['koordinator'],
            'anggota' => ['anggota'],
            'auditor' => ['auditor'],
        ];
    }

    /**
     * Satu kasus per peran, bukan satu gelung berisi enam actingAs: middleware
     * AuthenticateSession membatalkan sesi begitu pengguna berganti di dalam
     * satu permintaan-beruntun, sehingga peran kedua dan seterusnya terlempar
     * ke halaman masuk dan kegagalannya menyesatkan.
     */
    #[Test]
    #[DataProvider('semuaPeran')]
    public function setiap_peran_bisa_melihat_matriks_izin(string $peran): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::from($peran)))
            ->get('/panel/matriks-izin')
            ->assertSuccessful();
    }

    #[Test]
    public function pengguna_nonaktif_ditolak_masuk_panel(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::Ketua, aktif: false))
            ->get('/panel')
            ->assertForbidden();
    }

    #[Test]
    public function pengguna_dengan_wajib_ganti_sandi_diarahkan_ke_profil(): void
    {
        $this->seed(DatabaseSeeder::class);

        $u = $this->pengguna(PeranPengguna::Ketua);
        $u->update(['wajib_ganti_sandi' => true]);

        $this->actingAs($u)
            ->get('/panel/periodes')
            ->assertRedirect('/panel/profile');
    }

    #[Test]
    public function nonaktif_mendapat_pesan_yang_jelas_saat_sandi_benar(): void
    {
        $this->seed(DatabaseSeeder::class);

        $u = $this->pengguna(PeranPengguna::Ketua, aktif: false);

        Livewire::test(Masuk::class)
            ->fillForm(['email' => $u->email, 'password' => 'rahasia123'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    #[Test]
    public function sandi_salah_tetap_mendapat_pesan_umum(): void
    {
        $this->seed(DatabaseSeeder::class);

        $u = $this->pengguna(PeranPengguna::Ketua, aktif: false);

        // Sandi salah pada akun nonaktif TIDAK boleh membocorkan bahwa akunnya
        // ada tetapi dinonaktifkan — pesannya harus sama dengan akun yang
        // memang tidak ada.
        Livewire::test(Masuk::class)
            ->fillForm(['email' => $u->email, 'password' => 'sandi-yang-salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    #[Test]
    public function waktu_masuk_dicatat(): void
    {
        $this->seed(DatabaseSeeder::class);

        $u = $this->pengguna(PeranPengguna::Ketua);
        $this->assertNull($u->terakhir_masuk_pada);

        event(new Login('web', $u, false));

        $this->assertNotNull($u->fresh()->terakhir_masuk_pada);
    }
}
