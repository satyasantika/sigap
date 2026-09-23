<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\ImpersonasiSesi;
use App\Models\LogAktivitas;
use App\Models\Prodi;
use App\Models\User;
use App\Services\Impersonasi;
use App\Support\Izin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Impersonasi: boleh, tetapi seluruhnya tercatat.
 *
 * `vibecoding/docs/08-auth-dan-izin.md` melarang fitur ini. Larangan itu
 * dibatalkan keputusan manusia dengan satu syarat — jejaknya tidak boleh
 * hilang. Uji di berkas ini menjaga syarat itu, bukan sekadar menjaga fiturnya
 * jalan. Bila salah satu dari uji ini menjadi merah dan Anda tergoda
 * menghapusnya, yang sebenarnya hilang adalah pertanggungjawaban atas
 * persetujuan akreditasi.
 */
class ImpersonasiTest extends TestCase
{
    use RefreshDatabase;

    private function prodi(): Prodi
    {
        return Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
    }

    private function pengguna(PeranPengguna $peran, ?Prodi $prodi = null): User
    {
        return User::factory()->peran($peran)->create([
            'prodi_id' => ($prodi ?? $this->prodi())->id,
        ]);
    }

    private function layanan(): Impersonasi
    {
        return app(Impersonasi::class);
    }

    // --- matriks izin sistem -------------------------------------------------

    /** @return array<string, array{string, string, string}> */
    public static function selSistem(): array
    {
        $berkas = dirname(__DIR__, 2).'/data/izin-sistem.json';
        $isi = json_decode(file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);

        $kasus = [];

        foreach ($isi['aksi'] as $aksi) {
            foreach ($isi['peran'] as $peran) {
                $kasus["{$aksi['kode']} / {$peran}"] = [$aksi['kode'], $peran, $aksi[$peran]];
            }
        }

        return $kasus;
    }

    #[Test]
    #[DataProvider('selSistem')]
    public function setiap_sel_matriks_sistem_ditelusuri(string $aksi, string $peran, string $harapan): void
    {
        $u = $this->pengguna(PeranPengguna::from($peran));

        $this->assertSame(
            $harapan === 'ya',
            Izin::bolehSistem($u, $aksi),
            "Sel {$aksi}.{$peran} seharusnya {$harapan}."
        );
    }

    #[Test]
    public function matriks_sistem_berisi_24_sel_dan_tidak_menyentuh_144_sel_akreditasi(): void
    {
        $matriks = Izin::matriksSistem();

        $this->assertCount(4, $matriks, 'Empat aksi sistem.');
        $this->assertCount(24, self::selSistem(), '4 aksi x 6 peran.');

        // Yang dijaga di sini: aksi sistem TIDAK bocor ke tabel izin, karena
        // angka 144 dipatok seeder, uji MatriksIzinTest, dan data/verifikasi.py.
        foreach (array_keys($matriks) as $aksi) {
            $this->assertArrayNotHasKey(
                $aksi,
                Izin::matriks(),
                "Aksi sistem {$aksi} tidak boleh ikut tersemai ke tabel izin."
            );
        }
    }

    #[Test]
    public function pengguna_nonaktif_kehilangan_seluruh_wewenang_sistem(): void
    {
        $u = $this->pengguna(PeranPengguna::Admin);
        $u->update(['aktif' => false]);

        $this->assertFalse(Izin::bolehSistem($u->fresh(), 'impersonasi.mulai'));
    }

    // --- memulai dan mengakhiri ---------------------------------------------

    #[Test]
    public function admin_bisa_menyamar_dan_pengguna_yang_masuk_berganti(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);

        $this->actingAs($admin);

        $sesi = $this->layanan()->mulai($admin, $ketua, 'menelusuri laporan tombol hilang');

        $this->assertSame($ketua->id, Auth::id(), 'Setelah menyamar, yang masuk adalah sasarannya.');
        $this->assertTrue($this->layanan()->sedangBerlangsung());
        $this->assertSame($admin->id, $this->layanan()->adminAsli()?->id);
        $this->assertSame($admin->id, $this->layanan()->adminDiBalikLayar());
        $this->assertNull($sesi->diakhiri_pada);
        $this->assertTrue($sesi->berjalan());
    }

    #[Test]
    public function memulai_penyamaran_menulis_baris_log_berisi_alasan(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);

        $this->actingAs($admin);
        $this->layanan()->mulai($admin, $ketua, 'memeriksa laporan pengguna');

        $log = LogAktivitas::where('aksi', 'impersonasi.mulai')->firstOrFail();

        $this->assertSame($ketua->id, $log->user_id, 'Pelakunya sasaran yang ditiru.');
        $this->assertSame($admin->id, $log->impersonasi_oleh, 'Admin aslinya ikut tersimpan.');
        $this->assertStringContainsString('memeriksa laporan pengguna', $log->keterangan);
    }

    #[Test]
    public function mengakhiri_penyamaran_mengembalikan_admin_dan_menutup_sesi(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);

        $this->actingAs($admin);
        $sesi = $this->layanan()->mulai($admin, $ketua, 'sekadar memeriksa tampilan');

        $kembali = $this->layanan()->akhiri();

        $this->assertSame($admin->id, $kembali?->id);
        $this->assertSame($admin->id, Auth::id());
        $this->assertFalse($this->layanan()->sedangBerlangsung());
        $this->assertNotNull($sesi->fresh()->diakhiri_pada);
        $this->assertFalse($sesi->fresh()->berjalan());

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'impersonasi.akhiri',
            'impersonasi_oleh' => $admin->id,
        ]);
    }

    #[Test]
    public function mengakhiri_saat_tidak_menyamar_tidak_meledak(): void
    {
        $admin = $this->pengguna(PeranPengguna::Admin);
        $this->actingAs($admin);

        $this->assertNull($this->layanan()->akhiri());
        $this->assertSame($admin->id, Auth::id());
    }

    // --- pagar ---------------------------------------------------------------

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
    public function peran_selain_admin_ditolak_menyamar(PeranPengguna $peran): void
    {
        $prodi = $this->prodi();
        $pelaku = $this->pengguna($peran, $prodi);
        $korban = $this->pengguna(PeranPengguna::Anggota, $prodi);

        $this->actingAs($pelaku);

        $this->expectException(RuntimeException::class);
        $this->layanan()->mulai($pelaku, $korban, 'mencoba-coba saja');
    }

    #[Test]
    public function admin_tidak_bisa_menyamar_sebagai_admin_lain(): void
    {
        $prodi = $this->prodi();
        $a = $this->pengguna(PeranPengguna::Admin, $prodi);
        $b = $this->pengguna(PeranPengguna::Admin, $prodi);

        $this->actingAs($a);

        $this->expectException(RuntimeException::class);
        $this->layanan()->mulai($a, $b, 'ingin melihat punya kawan');
    }

    #[Test]
    public function admin_tidak_bisa_menyamar_sebagai_pengguna_nonaktif(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $mati = User::factory()->peran(PeranPengguna::Anggota)->nonaktif()->create(['prodi_id' => $prodi->id]);

        $this->actingAs($admin);

        $this->expectException(RuntimeException::class);
        $this->layanan()->mulai($admin, $mati, 'memeriksa akun lama');
    }

    #[Test]
    public function penyamaran_bertingkat_ditolak(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);
        $anggota = $this->pengguna(PeranPengguna::Anggota, $prodi);

        $this->actingAs($admin);
        $this->layanan()->mulai($admin, $ketua, 'memeriksa layar ketua');

        $this->expectException(RuntimeException::class);
        $this->layanan()->mulai($admin, $anggota, 'sekalian melihat layar anggota');
    }

    #[Test]
    public function daftar_sasaran_tidak_memuat_admin_maupun_diri_sendiri(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $admin2 = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);
        $mati = User::factory()->peran(PeranPengguna::Anggota)->nonaktif()->create(['prodi_id' => $prodi->id]);

        $id = $this->layanan()->targetTersedia($admin)->pluck('id')->all();

        $this->assertContains($ketua->id, $id);
        $this->assertNotContains($admin->id, $id, 'Diri sendiri tidak masuk daftar.');
        $this->assertNotContains($admin2->id, $id, 'Admin lain tidak masuk daftar.');
        $this->assertNotContains($mati->id, $id, 'Pengguna nonaktif tidak masuk daftar.');
    }

    // --- jejak pada tabel riwayat -------------------------------------------

    #[Test]
    public function komentar_yang_lahir_selama_penyamaran_membawa_id_admin(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);

        $this->actingAs($admin);
        $this->layanan()->mulai($admin, $ketua, 'memeriksa alur komentar');

        $komentar = \App\Models\Komentar::create([
            'commentable_type' => User::class,
            'commentable_id' => $ketua->id,
            'user_id' => $ketua->id,
            'isi' => 'Catatan yang ditulis selama penyamaran.',
        ]);

        $this->assertSame($admin->id, $komentar->impersonasi_oleh,
            'Trait MencatatImpersonasi wajib mengisi kolom ini tanpa diminta pemanggil.');
        $this->assertTrue($komentar->lewatImpersonasi());
        $this->assertSame($admin->id, $komentar->admin->id);
    }

    #[Test]
    public function komentar_di_luar_penyamaran_tidak_membawa_id_admin(): void
    {
        $prodi = $this->prodi();
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);

        $this->actingAs($ketua);

        $komentar = \App\Models\Komentar::create([
            'commentable_type' => User::class,
            'commentable_id' => $ketua->id,
            'user_id' => $ketua->id,
            'isi' => 'Catatan biasa.',
        ]);

        $this->assertNull($komentar->impersonasi_oleh);
        $this->assertFalse($komentar->lewatImpersonasi());
    }

    #[Test]
    public function log_aktivitas_menolak_diubah_dan_dihapus(): void
    {
        $admin = $this->pengguna(PeranPengguna::Admin);
        $this->actingAs($admin);

        $log = $this->layanan()->catat('uji.catat', $admin, keterangan: 'sebuah tindakan');

        $this->expectException(\App\Exceptions\RiwayatTidakBolehDiubah::class);
        $log->update(['keterangan' => 'diubah diam-diam']);
    }

    #[Test]
    public function log_aktivitas_menolak_dihapus(): void
    {
        $admin = $this->pengguna(PeranPengguna::Admin);
        $this->actingAs($admin);

        $log = $this->layanan()->catat('uji.catat', $admin, keterangan: 'sebuah tindakan');

        $this->expectException(\App\Exceptions\RiwayatTidakBolehDiubah::class);
        $log->delete();
    }

    #[Test]
    public function sesi_lama_yang_menggantung_ditutup_saat_penyamaran_baru_dimulai(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);
        $anggota = $this->pengguna(PeranPengguna::Anggota, $prodi);

        // Sesi yatim: baris tertinggal karena peramban ditutup tanpa menekan
        // "Kembali ke akun saya". Tanpa penutupan otomatis, daftar sesi berjalan
        // akan penuh baris yang sebenarnya sudah mati.
        $yatim = ImpersonasiSesi::create([
            'admin_id' => $admin->id, 'target_id' => $ketua->id, 'alasan' => 'sesi tertinggal',
        ]);

        $this->actingAs($admin);
        $this->layanan()->mulai($admin, $anggota, 'penyamaran berikutnya');

        $this->assertNotNull($yatim->fresh()->diakhiri_pada);
        $this->assertSame(1, ImpersonasiSesi::masihBerjalan()->count());
    }

    // --- lewat HTTP ----------------------------------------------------------

    #[Test]
    public function rute_akhiri_mengembalikan_admin(): void
    {
        $prodi = $this->prodi();
        $admin = $this->pengguna(PeranPengguna::Admin, $prodi);
        $ketua = $this->pengguna(PeranPengguna::Ketua, $prodi);

        $this->actingAs($admin);
        $this->layanan()->mulai($admin, $ketua, 'memeriksa layar ketua');

        $this->post(route('sigap.impersonasi.akhiri'))->assertRedirect();

        $this->assertSame($admin->id, Auth::id());
        $this->assertFalse($this->layanan()->sedangBerlangsung());
    }

    #[Test]
    public function tamu_yang_memanggil_rute_akhiri_diarahkan_ke_halaman_masuk(): void
    {
        $this->post(route('sigap.impersonasi.akhiri'))
            ->assertRedirect(\Filament\Facades\Filament::getLoginUrl());

        $this->assertGuest();
    }
}
