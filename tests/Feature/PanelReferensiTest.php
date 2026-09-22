<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Kriteria;
use App\Models\Prodi;
use App\Models\Rumus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Layar Referensi: terbuka untuk semua peran, tertutup untuk penyuntingan
 * oleh siapa pun — termasuk admin.
 */
class PanelReferensiTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(PeranPengguna $peran): User
    {
        $prodi = Prodi::firstOrCreate(
            ['kode' => 'PPG-UJI'],
            ['nama' => 'PPG', 'jenjang' => 'ppg', 'upps' => 'FKIP',
                'perguruan_tinggi' => 'Unsil', 'aktif' => true],
        );

        return User::create([
            'name' => $peran->value,
            'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@ref.test',
            'password' => 'rahasia123',
            'peran' => $peran,
            'prodi_id' => $prodi->id,
            'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);
    }

    /** @return array<string, array{string}> */
    public static function halamanReferensi(): array
    {
        return [
            'elemen' => ['/panel/elemens'],
            'kriteria' => ['/panel/kriterias'],
            'rumus' => ['/panel/rumuses'],
            'butir dkps' => ['/panel/dkps-butirs'],
        ];
    }

    #[Test]
    #[DataProvider('halamanReferensi')]
    public function daftar_referensi_terbuka_untuk_anggota_pokja(string $jalur): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::Anggota))
            ->get($jalur)
            ->assertSuccessful();
    }

    /** @return array<string, array{string}> */
    public static function semuaPeran(): array
    {
        return [
            'admin' => ['admin'], 'ketua' => ['ketua'], 'pimpinan' => ['pimpinan'],
            'koordinator' => ['koordinator'], 'anggota' => ['anggota'], 'auditor' => ['auditor'],
        ];
    }

    #[Test]
    #[DataProvider('semuaPeran')]
    public function setiap_peran_bisa_membaca_daftar_elemen(string $peran): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->pengguna(PeranPengguna::from($peran)))
            ->get('/panel/elemens')
            ->assertSuccessful();
    }

    #[Test]
    public function halaman_detail_elemen_menampilkan_teks_panduan_utuh(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Elemen 1 dipilih karena panduannya panjang; kalau dipotong, potongan
        // terakhirnya tidak akan muncul di halaman.
        $e = Elemen::where('no', 1)->firstOrFail();

        $this->actingAs($this->pengguna(PeranPengguna::Anggota))
            ->get("/panel/elemens/{$e->id}")
            ->assertSuccessful()
            ->assertSee($e->nama)
            ->assertSee(str(strip_tags($e->panduan))->limit(60, '')->trim()->toString());
    }

    #[Test]
    public function detail_elemen_syarat_perlu_menampilkan_catatan_peringatan(): void
    {
        $this->seed(DatabaseSeeder::class);

        // E17 dan E51 adalah perangkapnya: skor 4 di sana TIDAK berarti syarat
        // perlunya terpenuhi, dan catatan itu harus terlihat di layar.
        $e = Elemen::where('no', 17)->firstOrFail();
        $catatan = $e->syaratPerlu->catatan;

        $this->assertNotEmpty($catatan, 'E17 harus punya catatan peringatan.');

        $this->actingAs($this->pengguna(PeranPengguna::Anggota))
            ->get("/panel/elemens/{$e->id}")
            ->assertSuccessful()
            ->assertSee($e->syaratPerlu->ambang_3_tahun)
            ->assertSee($e->syaratPerlu->ambang_5_tahun);
    }

    /** @return array<string, array{string}> */
    public static function jalurSunting(): array
    {
        return [
            'buat elemen' => ['/panel/elemens/create'],
            'buat kriteria' => ['/panel/kriterias/create'],
            'buat rumus' => ['/panel/rumuses/create'],
            'buat butir dkps' => ['/panel/dkps-butirs/create'],
        ];
    }

    #[Test]
    #[DataProvider('jalurSunting')]
    public function instrumen_tidak_bisa_disunting_bahkan_oleh_admin(string $jalur): void
    {
        $this->seed(DatabaseSeeder::class);

        // Mengubah instrumen harus lewat data/*.json dan commit `data:`, bukan
        // lewat layar — kalau bisa lewat layar, jejaknya hilang dan
        // verifikasi.py tidak lagi menjaga apa pun.
        $this->actingAs($this->pengguna(PeranPengguna::Admin))
            ->get($jalur)
            ->assertNotFound();
    }

    #[Test]
    public function policy_referensi_menolak_seluruh_penulisan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = $this->pengguna(PeranPengguna::Admin);
        $ketua = $this->pengguna(PeranPengguna::Ketua);

        foreach ([Elemen::first(), Kriteria::first(), Rumus::first(), DkpsButir::first()] as $baris) {
            foreach ([$admin, $ketua] as $u) {
                $this->assertTrue($u->can('view', $baris), 'Referensi harus bisa dibaca.');
                $this->assertFalse($u->can('create', $baris::class), 'Referensi tidak boleh dibuat lewat layar.');
                $this->assertFalse($u->can('update', $baris), 'Referensi tidak boleh disunting lewat layar.');
                $this->assertFalse($u->can('delete', $baris), 'Referensi tidak boleh dihapus lewat layar.');
            }
        }
    }
}
