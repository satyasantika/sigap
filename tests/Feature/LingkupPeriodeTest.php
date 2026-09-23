<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Bukti;
use App\Models\Narasi;
use App\Models\Periode;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\Simulator;
use App\Support\LingkupPeriode;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Periode latihan tidak boleh muncul di layar kerja sungguhan.
 *
 * Sebelum ada pemisahan ini, satu periode latihan membuat ketua melihat 274
 * tagihan alih-alih 137 — bercampur, tanpa penanda, tanpa cara membedakan mana
 * yang sungguhan. Fitur yang dimaksudkan sebagai tempat berlatih justru
 * merusak layar yang dipakai bekerja.
 *
 * Penyebabnya bukan kecerobohan satu tempat: `TagihanResource` menyaring
 * menurut peran dengan rapi dan tetap melewatkan periode, karena penyaringan
 * periode belum terpikir saat itu ditulis. Karena itu penjagaannya sekarang
 * berupa global scope, bukan catatan yang harus diingat di delapan Resource.
 */
class LingkupPeriodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        LingkupPeriode::lupakan();
    }

    private function buatSandbox(): Periode
    {
        $admin = User::where('peran', PeranPengguna::Admin)->firstOrFail();

        return app(Simulator::class)
            ->buatSandbox($admin, Periode::aktif()->firstOrFail(), 'Latihan uji')
            ->periodeSandbox;
    }

    /** @return array<string, array{string}> */
    public static function peranKerja(): array
    {
        return [
            'ketua' => ['ketua'],
            'pimpinan' => ['pimpinan'],
            'koordinator' => ['koordinator'],
            'anggota' => ['anggota'],
            'auditor' => ['auditor'],
            'admin' => ['admin'],
        ];
    }

    #[Test]
    #[DataProvider('peranKerja')]
    public function tidak_ada_peran_yang_melihat_baris_periode_latihan(string $peran): void
    {
        $sandbox = $this->buatSandbox();

        $this->assertGreaterThan(0, Tagihan::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $sandbox->id)->count(),
            'Prasyarat uji gagal: periode latihan tidak berisi tagihan.');

        $this->actingAs(User::where('peran', $peran)->firstOrFail());

        $this->assertSame(0, Tagihan::where('periode_id', $sandbox->id)->count(),
            "Peran {$peran} melihat tagihan periode latihan.");
        $this->assertSame(0, Narasi::where('periode_id', $sandbox->id)->count(),
            "Peran {$peran} melihat narasi periode latihan.");
        $this->assertSame(0, Bukti::where('periode_id', $sandbox->id)->count(),
            "Peran {$peran} melihat bukti periode latihan.");
    }

    #[Test]
    public function jumlah_tagihan_ketua_tidak_berubah_setelah_periode_latihan_dibuat(): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        $this->actingAs($ketua);
        $sebelum = Tagihan::count();

        $this->buatSandbox();

        $this->assertSame($sebelum, Tagihan::count(),
            'Membuat periode latihan menggandakan daftar tagihan ketua. '
            .'Inilah cacat yang menyebabkan 274 alih-alih 137.');
    }

    #[Test]
    public function pengguna_demo_hanya_melihat_periode_demonya(): void
    {
        $sandbox = $this->buatSandbox();
        $nyata = Periode::aktif()->firstOrFail();

        $demo = User::factory()->peran(PeranPengguna::Ketua)->create([
            'prodi_id' => $nyata->prodi_id,
            'demo' => true,
            'periode_demo_id' => $sandbox->id,
        ]);

        $this->actingAs($demo);

        $this->assertGreaterThan(0, Tagihan::where('periode_id', $sandbox->id)->count(),
            'Pengguna demo tidak melihat periode demonya sendiri.');
        $this->assertSame(0, Tagihan::where('periode_id', $nyata->id)->count(),
            'Pengguna demo melihat data akreditasi sungguhan.');
    }

    #[Test]
    public function pengguna_demo_tanpa_periode_tidak_melihat_apa_pun(): void
    {
        // Akun demo yatim — periodenya sudah dibuang tetapi akunnya tertinggal.
        // Ia harus menolak, bukan diam-diam berubah menjadi pengguna biasa.
        $yatim = User::factory()->peran(PeranPengguna::Ketua)->create([
            'prodi_id' => Periode::aktif()->firstOrFail()->prodi_id,
            'demo' => true,
            'periode_demo_id' => null,
        ]);

        $this->actingAs($yatim);

        $this->assertSame(0, Tagihan::count(),
            'Akun demo tanpa periode melihat data sungguhan.');
    }

    #[Test]
    public function tanpa_pengguna_yang_masuk_lingkupnya_tidak_membatasi(): void
    {
        // Seeder, perintah artisan, dan penjadwal berjalan tanpa pengguna.
        // Membatasi di sana akan membuat `tagihan:bangkitkan` gagal mengisi
        // periode yang baru saja dibuat.
        $sandbox = $this->buatSandbox();

        $this->assertNull(LingkupPeriode::idYangBolehDilihat());
        $this->assertGreaterThan(0, Tagihan::where('periode_id', $sandbox->id)->count());
    }

    #[Test]
    public function admin_tetap_bisa_melihat_lintas_periode_bila_memang_perlu(): void
    {
        $sandbox = $this->buatSandbox();

        $this->actingAs(User::where('peran', PeranPengguna::Admin)->firstOrFail());

        // Jalan keluar yang disengaja, dipakai layar yang memang lintas periode.
        $this->assertGreaterThan(0,
            Tagihan::withoutGlobalScope('lingkup_periode')->where('periode_id', $sandbox->id)->count(),
            'withoutGlobalScope tidak lagi menembus saringan periode.');
    }

    #[Test]
    public function daftar_simulasi_tetap_menampilkan_periode_latihannya(): void
    {
        $admin = User::where('peran', PeranPengguna::Admin)->firstOrFail();
        $simulasi = app(Simulator::class)
            ->buatSandbox($admin, Periode::aktif()->firstOrFail(), 'Latihan uji');

        $this->actingAs($admin);

        // Layar Simulasi harus tetap bisa menyebut nama periode latihannya;
        // kalau tidak, admin kehilangan cara melihat apa yang ia buat.
        $this->assertNotNull($simulasi->fresh()->periodeSandbox);
        $this->assertStringStartsWith('[SIMULASI]', $simulasi->fresh()->periodeSandbox->nama);
    }
}
