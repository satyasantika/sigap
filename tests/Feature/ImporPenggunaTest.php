<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Livewire\ImporTempel;
use App\Models\ImporBatch;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PelaksanaImpor;
use App\Support\Impor\ImporPengguna;
use App\Support\Impor\PratinjauImpor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Impor massal pengguna lewat tempel-tabel, dari tombol "Impor tempel" di
 * layar Pengguna. Hanya admin yang melihat tombolnya — lihat
 * UserPolicy/pengguna.kelola — jadi berkas ini tidak mengulang uji wewenang,
 * hanya perilaku profilnya dan wiring Livewire-nya.
 *
 * Satu hal yang diuji di sini dan tidak di berkas lain: bahwa ImporTempel
 * benar-benar bisa DIPASANG dan DIJALANKAN lewat Livewire::test() dari ujung
 * ke ujung. Lima profil lain hanya diuji lewat pemanggilan langsung ke kelas
 * pendukungnya (PratinjauImpor::susun(), dst.) — tidak satu pun menguji
 * bahwa ImporTempel sendiri bisa dimount dengan benar.
 */
class ImporPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
    }

    private function profil(): ImporPengguna
    {
        return new ImporPengguna;
    }

    // ---- Profil: normalisasi, validasi, duplikasi -----------------------

    #[Test]
    public function medan_wajib_mencakup_nama_surel_dan_peran(): void
    {
        $medan = $this->profil()->medan();

        $this->assertTrue($medan['nama_lengkap']['wajib']);
        $this->assertTrue($medan['email']['wajib']);
        $this->assertTrue($medan['peran']['wajib']);
        $this->assertFalse($medan['prodi_kode']['wajib']);
        $this->assertFalse($medan['nidn']['wajib']);
    }

    #[Test]
    public function baris_dengan_peran_tidak_dikenal_ditandai_galat(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Budi', 'email' => 'budi@unsil.ac.id', 'peran' => 'superadmin'],
        ], $this->profil(), null);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $this->assertStringContainsString('Peran harus', implode(' ', $pratinjau[0]['galat']));
    }

    #[Test]
    public function baris_dengan_surel_tidak_sah_ditandai_galat(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Budi', 'email' => 'bukan-surel', 'peran' => 'anggota'],
        ], $this->profil(), null);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $this->assertStringContainsString('tidak sah', implode(' ', $pratinjau[0]['galat']));
    }

    #[Test]
    public function kode_prodi_yang_tidak_ada_ditandai_galat(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Budi', 'email' => 'budi@unsil.ac.id', 'peran' => 'anggota',
                'prodi_kode' => 'TIDAK-ADA'],
        ], $this->profil(), null);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $this->assertStringContainsString('tidak ditemukan', implode(' ', $pratinjau[0]['galat']));
    }

    #[Test]
    public function duplikat_terdeteksi_terhadap_basis_data_lewat_surel(): void
    {
        User::factory()->peran(PeranPengguna::Anggota)->create([
            'prodi_id' => $this->prodi->id, 'email' => 'ada@unsil.ac.id',
        ]);

        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Nama Baru', 'email' => 'ADA@unsil.ac.id', 'peran' => 'anggota'],
        ], $this->profil(), null);

        // Huruf besar/kecil tidak boleh membuat email yang sama lolos sebagai baru.
        $this->assertSame(PratinjauImpor::DUPLIKAT_BASIS_DATA, $pratinjau[0]['status']);
        $this->assertNotNull($pratinjau[0]['id_lama']);
    }

    #[Test]
    public function duplikat_terdeteksi_di_dalam_tempelan_yang_sama(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Pertama', 'email' => 'kembar@unsil.ac.id', 'peran' => 'anggota'],
            ['nama_lengkap' => 'Kedua', 'email' => 'kembar@unsil.ac.id', 'peran' => 'anggota'],
        ], $this->profil(), null);

        $this->assertSame(PratinjauImpor::BARU, $pratinjau[0]['status']);
        $this->assertSame(PratinjauImpor::DUPLIKAT_TEMPELAN, $pratinjau[1]['status']);
    }

    // ---- Penjalanan: sandi acak, wajib ganti sandi, prodi ----------------

    #[Test]
    public function impor_berjalan_membuat_pengguna_dengan_sandi_acak_dan_wajib_ganti_sandi(): void
    {
        $admin = User::factory()->peran(PeranPengguna::Admin)->create(['prodi_id' => $this->prodi->id]);

        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Dosen Baru', 'email' => 'dosen.baru@unsil.ac.id', 'peran' => 'anggota',
                'prodi_kode' => 'PPG-UJI'],
        ], $this->profil(), null);

        $profil = $this->profil();
        $batch = app(PelaksanaImpor::class)->jalankan($pratinjau, [], $profil, null, $admin);

        $this->assertSame(1, $batch->jumlah_impor);
        $this->assertNull($batch->periode_id, 'Impor pengguna tidak terikat periode.');

        $baru = User::where('email', 'dosen.baru@unsil.ac.id')->firstOrFail();
        $this->assertSame('Dosen Baru', $baru->nama_lengkap);
        $this->assertSame($this->prodi->id, $baru->prodi_id);
        $this->assertTrue($baru->wajib_ganti_sandi, 'Pengguna baru wajib ganti sandi saat masuk pertama.');
        $this->assertSame($batch->id, $baru->impor_batch_id);
        $this->assertNotNull($baru->kunci_normal);

        $sandi = $profil->sandiBaruDibuat();
        $this->assertCount(1, $sandi);
        $this->assertSame('dosen.baru@unsil.ac.id', $sandi[0]['email']);
        $this->assertNotEmpty($sandi[0]['sandi']);
        $this->assertTrue(Hash::check($sandi[0]['sandi'], $baru->fresh()->password),
            'Sandi yang ditunjukkan ke admin harus sandi yang sesungguhnya tersimpan.');
    }

    #[Test]
    public function memperbarui_tidak_mengganti_sandi_pengguna_yang_sudah_ada(): void
    {
        $admin = User::factory()->peran(PeranPengguna::Admin)->create(['prodi_id' => $this->prodi->id]);
        $lama = User::factory()->peran(PeranPengguna::Anggota)->create([
            'prodi_id' => $this->prodi->id, 'email' => 'lama@unsil.ac.id', 'jabatan' => 'Jabatan lama',
        ]);
        $sandiLama = $lama->password;

        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Nama Diperbarui', 'email' => 'lama@unsil.ac.id', 'peran' => 'anggota',
                'jabatan' => 'Jabatan baru'],
        ], $this->profil(), null);

        $batch = app(PelaksanaImpor::class)->jalankan(
            $pratinjau, [1 => 'perbarui'], $this->profil(), null, $admin,
        );

        $this->assertSame(1, $batch->jumlah_perbarui);
        $this->assertSame('Nama Diperbarui', $lama->fresh()->nama_lengkap);
        $this->assertSame('Jabatan baru', $lama->fresh()->jabatan);
        $this->assertSame($sandiLama, $lama->fresh()->password,
            'Memperbarui lewat impor tidak boleh mengganti sandi orang yang sedang bekerja.');
    }

    #[Test]
    public function pembatalan_menghapus_pengguna_yang_baru_dibuat(): void
    {
        $admin = User::factory()->peran(PeranPengguna::Admin)->create(['prodi_id' => $this->prodi->id]);

        $pratinjau = PratinjauImpor::susun([
            ['nama_lengkap' => 'Akan Dibatalkan', 'email' => 'batal@unsil.ac.id', 'peran' => 'anggota'],
        ], $this->profil(), null);

        $batch = app(PelaksanaImpor::class)->jalankan($pratinjau, [], $this->profil(), null, $admin);
        $this->assertDatabaseHas('users', ['email' => 'batal@unsil.ac.id']);

        app(PelaksanaImpor::class)->batalkan($batch, $this->profil(), $admin);

        $this->assertSoftDeleted('users', ['email' => 'batal@unsil.ac.id']);
    }

    // ---- Wiring Livewire end-to-end --------------------------------------

    #[Test]
    public function komponen_impor_tempel_bisa_dipasang_dan_dijalankan_untuk_profil_pengguna(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('peran', PeranPengguna::Admin)->firstOrFail();
        $this->actingAs($admin);

        $tempelan = "nama_lengkap\temail\tperan\nPengguna Tempelan\tpengguna.tempelan@unsil.ac.id\tanggota";

        $test = Livewire::test(ImporTempel::class, ['profilKelas' => ImporPengguna::class])
            ->set('tempelan', $tempelan)
            ->call('uraikan')
            ->assertSet('langkah', 2)
            ->call('pratinjaukan')
            ->assertSet('langkah', 3)
            ->call('jalankan')
            ->assertSet('langkah', 4);

        $this->assertDatabaseHas('users', ['email' => 'pengguna.tempelan@unsil.ac.id']);

        $sandiBaru = $test->get('sandiBaru');
        $this->assertCount(1, $sandiBaru);
        $this->assertSame('pengguna.tempelan@unsil.ac.id', $sandiBaru[0]['email']);

        $batch = ImporBatch::where('profil', 'Pengguna')->firstOrFail();
        $this->assertNull($batch->periode_id);
        $this->assertSame(1, $batch->jumlah_impor);
    }
}
