<?php

namespace Tests\Feature\Auth;

use App\Enums\PeranPengguna;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as PenggunaGoogle;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MasukGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.aktif' => true,
            'services.google.client_id' => 'id-uji',
            'services.google.client_secret' => 'rahasia',
        ]);
    }

    private function googleSebagai(string $email, array $tambahan = []): void
    {
        Socialite::fake('google', PenggunaGoogle::fake(array_merge([
            'id' => 'g-'.md5($email), 'name' => 'Nama Google', 'email' => $email, 'email_verified' => true,
        ], $tambahan)));
    }

    private function urlMasuk(): string
    {
        return route('filament.panel.auth.login');
    }

    private function urlPanel(): string
    {
        return Filament::getPanel('panel')->getUrl();
    }

    #[Test]
    public function fitur_nonaktif_kedua_rute_404_dan_halaman_masuk_tidak_memuat_tombol(): void
    {
        config(['services.google.aktif' => false]);

        $this->get(route('auth.google.redirect'))->assertNotFound();
        $this->get(route('auth.google.callback'))->assertNotFound();
        $this->get($this->urlMasuk())->assertOk()->assertDontSee('Masuk dengan Google');
    }

    #[Test]
    public function halaman_masuk_memuat_tombol_dan_tautannya(): void
    {
        $this->get($this->urlMasuk())
            ->assertOk()
            ->assertSee('Masuk dengan Google')
            ->assertSee(route('auth.google.redirect', ['asal' => $this->urlMasuk()]), false);
    }

    #[Test]
    public function mengarahkan_ke_google_dengan_redirect_uri_bawaan(): void
    {
        $lokasi = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/', $lokasi);
        parse_str(parse_url($lokasi, PHP_URL_QUERY), $q);
        $this->assertSame(route('auth.google.callback'), $q['redirect_uri']);
        $this->assertStringContainsString('openid', urldecode($q['scope']));
    }

    #[Test]
    public function memasukkan_akun_yang_sudah_ada_dan_menautkan_google_id(): void
    {
        $user = User::factory()->create(['email' => 'budi@unsil.ac.id']);
        $this->googleSebagai('budi@unsil.ac.id');

        $this->get(route('auth.google.callback'))->assertRedirect($this->urlPanel());

        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-'.md5('budi@unsil.ac.id'), $user->refresh()->google_id);
    }

    #[Test]
    public function mencocokkan_surel_tanpa_peduli_huruf(): void
    {
        $user = User::factory()->create(['email' => 'budi@unsil.ac.id']);
        $this->googleSebagai('Budi@Unsil.ac.id');

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    #[Test]
    public function membuat_akun_baru_untuk_surel_yang_belum_terdaftar(): void
    {
        $this->googleSebagai('baru@gmail.com');

        $this->get(route('auth.google.callback'))->assertRedirect($this->urlPanel());

        $u = User::firstWhere('email', 'baru@gmail.com');
        $this->assertNotNull($u);
        $this->assertSame('Nama Google', $u->name);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame('g-'.md5('baru@gmail.com'), $u->google_id);
        $this->assertTrue($u->aktif);
        $this->assertSame(PeranPengguna::Anggota, $u->peran);
        $this->assertAuthenticatedAs($u);
    }

    #[Test]
    public function menolak_surel_yang_belum_diverifikasi_google(): void
    {
        $this->googleSebagai('x@gmail.com', ['email_verified' => false]);

        $this->get(route('auth.google.callback'))
            ->assertSessionHas('galat_google', 'Surel akun Google Anda belum terverifikasi.');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    #[Test]
    public function menolak_akun_yang_sudah_tertaut_ke_google_lain(): void
    {
        User::factory()->create(['email' => 'budi@unsil.ac.id', 'google_id' => 'google-lain']);
        $this->googleSebagai('budi@unsil.ac.id');

        $this->get(route('auth.google.callback'))
            ->assertSessionHas('galat_google', 'Akun ini sudah ditautkan ke akun Google lain.');

        $this->assertGuest();
    }

    #[Test]
    public function membatalkan_di_google_kembali_ke_halaman_masuk(): void
    {
        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect($this->urlMasuk())
            ->assertSessionHas('galat_google', 'Masuk dengan Google dibatalkan.');

        $this->assertGuest();
    }

    #[Test]
    public function state_oauth_tidak_cocok_kembali_ke_halaman_masuk(): void
    {
        Socialite::fake('google', fn () => throw new InvalidStateException);

        $this->get(route('auth.google.callback'))
            ->assertRedirect($this->urlMasuk())
            ->assertSessionHas('galat_google', 'Sesi masuk Google kedaluwarsa. Silakan coba lagi.');

        $this->assertGuest();
    }

    #[Test]
    public function kembali_ke_asal_bila_asal_berada_di_dalam_aplikasi_mengabaikan_asal_di_luar_aplikasi(): void
    {
        $this->withSession(['google_asal' => url('/halaman-x')])
            ->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(url('/halaman-x'));

        $this->withSession(['google_asal' => 'https://evil.example/'])
            ->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect($this->urlMasuk());
    }

    #[Test]
    public function pengguna_yang_sudah_masuk_langsung_diarahkan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.google.redirect'))
            ->assertRedirect($this->urlPanel());
    }

    #[Test]
    public function membatasi_10_permintaan_per_menit_per_ip(): void
    {
        $this->googleSebagai('x@gmail.com', ['email_verified' => false]);

        for ($i = 0; $i < 10; $i++) {
            $this->get(route('auth.google.callback'));
        }

        $this->get(route('auth.google.callback'))->assertStatus(429);
    }

    #[Test]
    public function menolak_akun_nonaktif(): void
    {
        User::factory()->nonaktif()->create(['email' => 'budi@unsil.ac.id']);
        $this->googleSebagai('budi@unsil.ac.id');

        $this->get(route('auth.google.callback'))
            ->assertSessionHas('galat_google', 'Akun Anda dinonaktifkan. Hubungi admin.');

        $this->assertGuest();
    }
}
