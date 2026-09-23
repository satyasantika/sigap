<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Periode;
use App\Models\Simulasi;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\Demo;
use App\Services\Impersonasi;
use App\Services\Simulator;
use App\Support\LingkupPeriode;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Demo hidup: sesi sungguhan di dalam aplikasi, untuk orang di luar organisasi.
 *
 * Inilah fitur paling berisiko di SIGAP, dan ujinya harus mencerminkan itu.
 * Yang dijaga bukan "demonya jalan" melainkan **demonya tidak bisa menyentuh
 * apa pun yang sungguhan**: tidak melihat periode sungguhan, tidak muncul di
 * daftar pengguna, tidak bisa ditiru, dan lenyap tanpa sisa saat ditutup.
 */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        LingkupPeriode::lupakan();
    }

    private function admin(): User
    {
        return User::where('peran', PeranPengguna::Admin)->firstOrFail();
    }

    private function demoTerbuka(int $hari = 14): array
    {
        $simulasi = app(Simulator::class)->buatSandbox(
            $this->admin(),
            Periode::aktif()->firstOrFail(),
            'Latihan demo',
        );

        $kode = app(Demo::class)->buka($this->admin(), $simulasi->fresh(), $hari);

        return [$simulasi->fresh(), $kode];
    }

    // --- membuka dan menutup -------------------------------------------------

    #[Test]
    public function membuka_demo_menghasilkan_kode_dan_akun_tiap_peran(): void
    {
        [$simulasi, $kode] = $this->demoTerbuka();

        $this->assertMatchesRegularExpression('/^DEMO-[A-Z2-9]{6}$/', $kode);
        $this->assertTrue($simulasi->demoTerbuka());

        $akun = User::where('demo', true)->where('periode_demo_id', $simulasi->periode_sandbox_id)->get();

        $this->assertCount(count(Demo::peranYangBisaDicoba()), $akun,
            'Satu akun demo per peran yang bisa dicoba.');
        $this->assertNotContains(PeranPengguna::Admin, $akun->pluck('peran')->all(),
            'Tidak boleh ada akun demo berperan admin.');
    }

    #[Test]
    public function kode_tidak_memuat_huruf_yang_mudah_tertukar(): void
    {
        [, $kode] = $this->demoTerbuka();

        // Kode ini akan didiktekan lewat telepon.
        foreach (['O', '0', 'I', '1', 'L'] as $rancu) {
            $this->assertStringNotContainsString($rancu, substr($kode, 5),
                "Kode demo memuat karakter {$rancu} yang mudah tertukar saat didiktekan.");
        }
    }

    #[Test]
    public function pengandaian_skor_tidak_bisa_dibukakan_demo(): void
    {
        $skor = app(Simulator::class)->buatSimulasiSkor(
            $this->admin(),
            Periode::aktif()->firstOrFail(),
            'Andai',
            new \App\Support\Na\Skenario,
        );

        $this->expectException(RuntimeException::class);
        app(Demo::class)->buka($this->admin(), $skor, 7);
    }

    /** @return array<string, array{PeranPengguna}> */
    public static function peranTanpaWewenang(): array
    {
        return [
            'ketua' => [PeranPengguna::Ketua],
            'pimpinan' => [PeranPengguna::Pimpinan],
            'koordinator' => [PeranPengguna::Koordinator],
            'anggota' => [PeranPengguna::Anggota],
            'auditor' => [PeranPengguna::Auditor],
        ];
    }

    #[Test]
    #[DataProvider('peranTanpaWewenang')]
    public function hanya_admin_yang_bisa_membuka_demo(PeranPengguna $peran): void
    {
        $simulasi = app(Simulator::class)->buatSandbox(
            $this->admin(), Periode::aktif()->firstOrFail(), 'Latihan',
        );

        $this->expectException(RuntimeException::class);
        app(Demo::class)->buka(User::where('peran', $peran)->firstOrFail(), $simulasi, 7);
    }

    #[Test]
    public function menutup_demo_mencabut_kode_dan_menghapus_akunnya(): void
    {
        [$simulasi] = $this->demoTerbuka();
        $periodeDemo = $simulasi->periode_sandbox_id;

        app(Demo::class)->tutup($this->admin(), $simulasi->fresh());

        $this->assertNull($simulasi->fresh()->kode_demo);
        $this->assertSame(0, User::where('periode_demo_id', $periodeDemo)->count(),
            'Akun demo masih bisa dipakai setelah demonya ditutup.');
    }

    #[Test]
    public function membuang_periode_latihan_ikut_melenyapkan_akun_demonya(): void
    {
        [$simulasi] = $this->demoTerbuka();
        $periodeDemo = $simulasi->periode_sandbox_id;

        app(Simulator::class)->hapus($this->admin(), $simulasi->fresh());

        $this->assertSame(0, User::where('periode_demo_id', $periodeDemo)->count(),
            'Akun demo masih bisa dipakai setelah periode latihannya dibuang.');
    }

    #[Test]
    public function demo_yang_sudah_pernah_dipakai_tetap_bisa_ditutup(): void
    {
        // Inilah urutan yang sungguhan terjadi, dan inilah yang luput dari uji
        // pertama: demo dibuka, SESEORANG MASUK, lalu demonya ditutup. Begitu
        // akun demo pernah dipakai, `log_aktivitas` menunjuk kepadanya, dan
        // penghapusan permanen menabrak kendala kunci asing:
        //
        //   Cannot delete or update a parent row (log_aktivitas_user_id_foreign)
        //
        // Uji pertama menutup demo yang belum pernah dimasuki, jadi ia hijau
        // atas kegagalan yang nyata. Yang menemukannya adalah mencobanya
        // sungguhan di peramban.
        [$simulasi] = $this->demoTerbuka();

        $akun = app(Demo::class)->masuk($simulasi->fresh(), PeranPengguna::Ketua);
        Auth::logout();

        $this->assertDatabaseHas('log_aktivitas', ['user_id' => $akun->id, 'aksi' => 'demo.masuk']);

        app(Demo::class)->tutup($this->admin(), $simulasi->fresh());

        $this->assertSoftDeleted('users', ['id' => $akun->id]);
        $this->assertDatabaseHas('log_aktivitas', ['user_id' => $akun->id, 'aksi' => 'demo.masuk']);
    }

    #[Test]
    public function jejak_demo_tetap_terbaca_setelah_akunnya_dihapus(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $akun = app(Demo::class)->masuk($simulasi->fresh(), PeranPengguna::Anggota);
        Auth::logout();

        app(Demo::class)->tutup($this->admin(), $simulasi->fresh());

        $log = \App\Models\LogAktivitas::where('user_id', $akun->id)
            ->where('aksi', 'demo.masuk')
            ->firstOrFail();

        // Jejak yang masih ada tetapi tidak lagi bisa dibaca sama saja dengan
        // jejak yang hilang.
        $this->assertStringContainsString($akun->nama_lengkap, $log->kalimat());
        $this->assertStringNotContainsString('Pengguna terhapus', $log->kalimat());
    }

    #[Test]
    public function akun_demo_yang_ditutup_tidak_bisa_masuk_lagi(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $akun = app(Demo::class)->masuk($simulasi->fresh(), PeranPengguna::Ketua);
        Auth::logout();

        app(Demo::class)->tutup($this->admin(), $simulasi->fresh());

        // Penyedia autentikasi Laravel tidak pernah menemukan baris yang
        // ter-soft-delete, jadi pintunya benar-benar tertutup.
        $this->assertNull(User::find($akun->id));
    }

    // --- pagar demo ----------------------------------------------------------

    #[Test]
    public function sesi_demo_tidak_melihat_satu_pun_baris_periode_sungguhan(): void
    {
        [$simulasi] = $this->demoTerbuka();
        $nyata = Periode::aktif()->firstOrFail();

        $akun = app(Demo::class)->masuk($simulasi->fresh(), PeranPengguna::Ketua);

        $this->assertTrue($akun->akunDemo());
        $this->assertSame($akun->id, Auth::id());

        $this->assertSame(0, Tagihan::where('periode_id', $nyata->id)->count(),
            'Sesi demo melihat tagihan periode sungguhan.');
        $this->assertGreaterThan(0, Tagihan::where('periode_id', $simulasi->periode_sandbox_id)->count(),
            'Sesi demo tidak melihat periode latihannya sendiri.');
    }

    #[Test]
    public function peran_admin_ditolak_di_demo(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $this->expectException(RuntimeException::class);
        app(Demo::class)->masuk($simulasi->fresh(), PeranPengguna::Admin);
    }

    #[Test]
    public function demo_kedaluwarsa_menolak_dimasuki(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $simulasi->fresh()->update(['demo_berlaku_sampai' => now()->subMinute()]);

        $this->assertNull(app(Demo::class)->dariKode($simulasi->fresh()->kode_demo),
            'Demo kedaluwarsa masih bisa ditemukan lewat kodenya.');

        $this->expectException(RuntimeException::class);
        app(Demo::class)->masuk($simulasi->fresh(), PeranPengguna::Ketua);
    }

    #[Test]
    public function akun_demo_tidak_muncul_di_daftar_pengguna(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $this->actingAs($this->admin());

        $terlihat = \App\Filament\Resources\Users\UserResource::getEloquentQuery()
            ->where('periode_demo_id', $simulasi->periode_sandbox_id)
            ->count();

        $this->assertSame(0, $terlihat, 'Akun demo bocor ke daftar Pengguna.');
    }

    #[Test]
    public function akun_demo_tidak_bisa_ditiru(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $target = User::where('demo', true)
            ->where('periode_demo_id', $simulasi->periode_sandbox_id)
            ->firstOrFail();

        $this->actingAs($this->admin());

        $this->assertNotContains(
            $target->id,
            app(Impersonasi::class)->targetTersedia($this->admin())->pluck('id')->all(),
            'Akun demo muncul sebagai sasaran penyamaran.',
        );

        $this->expectException(RuntimeException::class);
        app(Impersonasi::class)->mulai($this->admin(), $target, 'mencoba meniru akun demo');
    }

    #[Test]
    public function akun_demo_tidak_bisa_ditugaskan_tagihan_sungguhan(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $id = User::bisaDitugaskan()->pluck('id')->all();
        $demo = User::where('demo', true)->where('periode_demo_id', $simulasi->periode_sandbox_id)
            ->pluck('id')->all();

        foreach ($demo as $d) {
            $this->assertNotContains($d, $id,
                'Akun demo bisa dipilih sebagai penanggung jawab tagihan sungguhan.');
        }
    }

    // --- lewat HTTP ----------------------------------------------------------

    #[Test]
    public function halaman_demo_terbuka_tanpa_masuk(): void
    {
        $this->get('/demo')->assertOk()->assertSee('Kode demo');
        $this->assertGuest();
    }

    #[Test]
    public function kode_salah_ditolak_tanpa_membocorkan_apa_pun(): void
    {
        $this->demoTerbuka();

        $balasan = $this->post(route('demo.kode'), ['kode' => 'DEMO-SALAH'])
            ->assertSessionHasErrors('kode');

        $pesan = session('errors')->first('kode');

        // Satu pesan untuk kode salah DAN kode kedaluwarsa. Membedakannya
        // memberi tahu penebak bahwa kodenya pernah benar.
        $this->assertStringContainsString('tidak dikenali atau masa berlakunya sudah lewat', $pesan);
    }

    #[Test]
    public function alur_lengkap_dari_kode_sampai_masuk_panel(): void
    {
        [$simulasi, $kode] = $this->demoTerbuka();

        $this->post(route('demo.kode'), ['kode' => strtolower($kode)])
            ->assertRedirect(route('demo'));

        $this->get('/demo')->assertOk()->assertSee('Pilih peran');

        $this->post(route('demo.masuk'), ['peran' => 'ketua'])
            ->assertRedirect(\Filament\Facades\Filament::getUrl());

        $this->assertTrue(Auth::user()->akunDemo());
        $this->assertSame($simulasi->periode_sandbox_id, Auth::user()->periode_demo_id);
    }

    #[Test]
    public function keluar_demo_mengakhiri_sesinya(): void
    {
        [$simulasi] = $this->demoTerbuka();

        app(Demo::class)->masuk($simulasi->fresh(), PeranPengguna::Anggota);
        $this->assertTrue(Auth::check());

        $this->post(route('demo.keluar'))->assertRedirect(route('demo'));

        $this->assertGuest();
    }

    #[Test]
    public function membuka_dan_menutup_demo_tercatat_di_log_aktivitas(): void
    {
        [$simulasi] = $this->demoTerbuka();

        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'demo.buka']);

        app(Demo::class)->tutup($this->admin(), $simulasi->fresh());

        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'demo.tutup']);
    }
}
