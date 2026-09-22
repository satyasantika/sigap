<?php

namespace Tests\Feature;

use App\Enums\AksesTautan;
use App\Enums\LevelSyaratPerlu;
use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Models\Bukti;
use App\Models\Elemen;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\StatusSyaratPerlu;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembangkitTagihan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dasbor bento K1-K9.
 *
 * Dokumen 10 memuat tiga aturan yang paling mudah dilanggar, dan ketiganya
 * diuji di sini: K2 dari bobot bukan cacah, K1 tidak dipisah dari K2, dan
 * K6 tidak dicampur ke K2.
 */
class DasborTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->prodi = Prodi::firstOrFail();

        app(PembangkitTagihan::class)->untuk($this->periode);
    }

    private function pengguna(PeranPengguna $peran): User
    {
        return User::create([
            'name' => 'u', 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@dasbor.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);
    }

    private function buka(PeranPengguna $peran = PeranPengguna::Ketua)
    {
        return $this->actingAs($this->pengguna($peran))->get('/panel/dasbor');
    }

    // ---- K1: gerbang, dan skalanya bukan persen -------------------------

    #[Test]
    public function k1_menampilkan_na_proyeksi_dengan_dua_desimal_koma(): void
    {
        foreach (Elemen::get() as $e) {
            Penilaian::create([
                'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
                'elemen_id' => $e->id, 'skor' => in_array($e->jenis->value, ['rubrik', 'refleksi'], true) ? 4 : 3,
                'penilai_id' => User::firstOrFail()->id, 'tanggal' => now(),
            ]);
        }

        $this->buka()
            ->assertSuccessful()
            // 363,25 dengan koma, bukan 363.25 dan bukan 363.
            ->assertSee('363,25');
    }

    #[Test]
    public function k1_memakai_skala_100_sampai_400_bukan_persen(): void
    {
        // Ambang Unggul adalah 361, bukan 90%. Menampilkannya sebagai persen
        // akan membuat orang mengira ambangnya 90%.
        $this->buka()
            ->assertSuccessful()
            ->assertSee('100')->assertSee('200')->assertSee('321')
            ->assertSee('361')->assertSee('400');
    }

    #[Test]
    public function k1_menyebut_berapa_elemen_belum_dinilai(): void
    {
        // Tanpa ini, proyeksi NA dibaca sebagai kepastian.
        $this->buka()
            ->assertSuccessful()
            ->assertSee('59 elemen belum dinilai');
    }

    #[Test]
    public function k1_dan_k2_tidak_pernah_dipisah(): void
    {
        // Aturan paling mudah dilanggar: progres tinggi tanpa syarat perlu
        // tetap berujung "Terakreditasi". Orang yang hanya melihat K2 akan
        // mengira sudah aman.
        $isi = $this->buka()->assertSuccessful()->getContent();

        $posK1 = strpos($isi, 'Gerbang Unggul');
        $posK2 = strpos($isi, 'Progres bobot');

        $this->assertNotFalse($posK1);
        $this->assertNotFalse($posK2);
        $this->assertLessThan($posK2, $posK1, 'K1 harus mendahului K2, dan keduanya berdampingan.');
    }

    // ---- K2: bobot, tidak pernah cacah ----------------------------------

    #[Test]
    public function k2_menampilkan_angka_absolut_di_samping_bobot(): void
    {
        $this->buka()
            ->assertSuccessful()
            // "58,25 dari 100 bobot", bukan "58%".
            ->assertSee('dari 100 bobot');
    }

    #[Test]
    public function k2_tidak_pernah_menampilkan_cacah_tagihan(): void
    {
        $e = Elemen::where('no', 58)->firstOrFail();
        Tagihan::where('elemen_id', $e->id)->update(['status' => StatusTagihan::Disetujui]);

        $isi = $this->buka()->assertSuccessful()->getContent();

        // Bobotnya 3,00 — bukan "2 tagihan" atau "2 dari 137".
        $this->assertStringContainsString('3,00', $isi);
        $this->assertStringNotContainsString('dari 137 tagihan', $isi);
    }

    // ---- K6: tidak dicampur ke K2 ---------------------------------------

    #[Test]
    public function k6_dkps_tidak_ikut_dihitung_ke_k2(): void
    {
        $isi = $this->buka()->assertSuccessful()->getContent();

        $this->assertStringContainsString('Kesiapan DKPS', $isi);
        $this->assertStringContainsString('0 / 28', $isi);
        // Butir DKPS tidak berbobot; mencampurnya menggeser angka yang
        // seharusnya berjumlah 100.
        $this->assertStringContainsString('tidak berbobot', $isi);
    }

    // ---- K4, K5, K7, K8, K9 ---------------------------------------------

    #[Test]
    public function k4_menghitung_bukti_bermasalah(): void
    {
        $b = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Tautan mati', 'jenis' => 'tautan',
            'url_kanonik' => 'https://drive.google.com/file/d/X/view',
            'tanggal_kejadian' => now()->subMonth(), 'sumber' => 'manual',
            'diunggah_oleh' => User::firstOrFail()->id,
        ]);
        $b->forceFill(['akses_status' => AksesTautan::PerluIzin])->save();

        $this->buka()
            ->assertSuccessful()
            ->assertSee('Bukti bermasalah')
            ->assertSee('klaim yang akan mati di tangan asesor');
    }

    #[Test]
    public function k5_menyebut_bobot_tertahan_bukan_hanya_cacah(): void
    {
        Tagihan::whereHas('elemen', fn ($q) => $q->where('no', 58))
            ->update(['tenggat' => now()->subWeek()]);

        // Dua angka: sepuluh tagihan kecil terlambat tidak sama gawatnya
        // dengan satu tagihan berbobot 3,00 yang terlambat.
        $this->buka()
            ->assertSuccessful()
            ->assertSee('Tagihan terlambat')
            ->assertSee('menahan');
    }

    #[Test]
    public function k7_menampilkan_pokja_data_dengan_keterangan(): void
    {
        $this->buka()
            ->assertSuccessful()
            ->assertSee('POKJA-DATA')
            // Tanpa keterangan, POKJA-DATA dibaca sebagai belum mengerjakan
            // apa pun padahal ia memegang 28 butir DKPS.
            ->assertSee('Diukur dengan DKPS, bukan bobot', escape: false);
    }

    #[Test]
    public function k8_tidak_menampilkan_tak_hingga_saat_laju_nol(): void
    {
        $isi = $this->buka()->assertSuccessful()->getContent();

        $this->assertStringContainsString('belum ada tagihan yang disetujui', $isi);
        $this->assertStringNotContainsString('INF', $isi);
        $this->assertStringNotContainsString('∞', $isi);
    }

    #[Test]
    public function k9_adalah_daftar_yang_bisa_diklik(): void
    {
        $this->buka()
            ->assertSuccessful()
            ->assertSee('Yang perlu dikerjakan')
            ->assertSee('Tagihan tanpa penanggung jawab')
            ->assertSee('Elemen tanpa bukti')
            ->assertSee('Naskah di bawah 200 kata')
            // Bisa diklik menuju daftarnya, bukan grafik buntu.
            ->assertSee('/panel/tagihans?tableFilters', escape: false);
    }

    // ---- Dasbor per peran ------------------------------------------------

    /** @return array<string, array{string, array<int, string>, array<int, string>}> */
    public static function ubinPerPeran(): array
    {
        return [
            // Penanda K5 adalah kata "menahan", bukan judulnya: "Tagihan
            // terlambat" juga muncul sebagai salah satu kelompok di dalam K9,
            // jadi memakai judulnya akan menabrak ubin yang lain.
            'ketua melihat semuanya' => ['ketua',
                ['Gerbang Unggul', 'Progres bobot', 'menahan', 'Yang perlu dikerjakan'], []],
            'pimpinan tanpa K5 dan K9' => ['pimpinan',
                ['Gerbang Unggul', 'Progres bobot', 'Kesiapan DKPS'],
                ['menahan', 'Yang perlu dikerjakan']],
            'anggota hanya K2 dan K9' => ['anggota',
                ['Progres bobot', 'Yang perlu dikerjakan'],
                ['Gerbang Unggul', 'menahan']],
            'auditor seperti pimpinan plus K9' => ['auditor',
                ['Gerbang Unggul', 'Yang perlu dikerjakan'],
                ['menahan']],
        ];
    }

    #[Test]
    #[DataProvider('ubinPerPeran')]
    public function ubin_terlihat_sesuai_peran(string $peran, array $ada, array $tidakAda): void
    {
        $respons = $this->buka(PeranPengguna::from($peran))->assertSuccessful();

        foreach ($ada as $ubin) {
            $respons->assertSee($ubin);
        }

        foreach ($tidakAda as $ubin) {
            $respons->assertDontSee($ubin);
        }
    }

    #[Test]
    public function pimpinan_tidak_melihat_satu_pun_tombol_aksi(): void
    {
        $isi = $this->buka(PeranPengguna::Pimpinan)->assertSuccessful()->getContent();

        foreach (['Tugaskan', 'Setujui', 'Kembalikan', 'Verifikasi'] as $tombol) {
            $this->assertStringNotContainsString($tombol, $isi, "Pimpinan tidak boleh melihat tombol {$tombol}.");
        }
    }

    // ---- Terbaca di layar sempit ----------------------------------------

    #[Test]
    public function urutan_menumpuk_menaikkan_daftar_kerja_ke_atas(): void
    {
        $isi = $this->buka()->assertSuccessful()->getContent();

        // Di bawah 700 piksel urutannya K1, K2, K9, ... — ubin daftar naik
        // karena di ponsel orang membuka dasbor untuk tahu apa yang harus
        // dikerjakan, bukan untuk mengagumi angka.
        $posK9 = strpos($isi, 'order-3');
        $posK3 = strpos($isi, 'order-4');

        $this->assertNotFalse($posK9, 'K9 harus bernomor urut 3 pada tumpukan sempit.');
        $this->assertNotFalse($posK3);
        $this->assertLessThan($posK3, $posK9);
    }

    #[Test]
    public function seluruh_kisi_memakai_satu_kolom_di_layar_sempit(): void
    {
        // grid-cols-1 sebagai bawaan, lg:grid-cols-12 hanya di layar lebar —
        // itulah yang membuatnya terbaca di lebar 400 piksel.
        $this->buka()
            ->assertSuccessful()
            ->assertSee('grid-cols-1', escape: false)
            ->assertSee('lg:grid-cols-12', escape: false);
    }

    // ---- Layar syarat perlu ---------------------------------------------

    #[Test]
    public function layar_syarat_perlu_menampilkan_kedua_ambang_berdampingan(): void
    {
        $e = Elemen::where('no', 17)->firstOrFail();

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get('/panel/syarat-perlu')
            ->assertSuccessful()
            ->assertSee('Ambang 3 tahun')
            ->assertSee('Ambang 5 tahun')
            ->assertSee($e->syaratPerlu->ambang_5_tahun)
            // Catatan E17 menyebut skor 4 tidak berarti syaratnya terpenuhi.
            ->assertSee('Skor 4 TIDAK berarti syarat perlu terpenuhi', escape: false);
    }

    #[Test]
    public function layar_syarat_perlu_menyebut_kelimanya_harus_terpenuhi(): void
    {
        foreach (Elemen::bersyaratPerlu()->get() as $e) {
            StatusSyaratPerlu::create([
                'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
                'elemen_id' => $e->id, 'level' => LevelSyaratPerlu::Lima,
            ]);
        }

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get('/panel/syarat-perlu')
            ->assertSuccessful()
            ->assertSee('5 dari 5 syarat perlu di level 5 tahun')
            ->assertSee('Empat dari lima tetap berarti tidak terpenuhi');
    }

    #[Test]
    public function hanya_ketua_yang_bisa_mengubah_level_syarat_perlu(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Koordinator))
            ->get('/panel/syarat-perlu')
            ->assertSuccessful()
            ->assertDontSee('Tetapkan level');
    }
}
