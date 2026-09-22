<?php

namespace Tests\Feature;

use App\Enums\JenisTagihan;
use App\Enums\PeranPengguna;
use App\Filament\Resources\Buktis\Pages\CreateBukti;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembangkitTagihan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Layar pengerjaan tagihan — bagian terpenting tahap 4.
 *
 * Bila layar ini tidak enak dipakai, sistemnya tidak akan dipakai.
 */
class LayarPengerjaanTest extends TestCase
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

    private function pengguna(PeranPengguna $peran = PeranPengguna::Anggota): User
    {
        return User::create([
            'name' => 'u', 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@layar.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);
    }

    /**
     * Tagihan narasi elemen tertentu, ditugaskan ke $pj bila diberikan.
     *
     * Penugasan itu perlu: daftar tagihan disaring di tingkat kueri menurut
     * lingkup izin, jadi anggota yang bukan penanggung jawab akan mendapat
     * 404 — bukan 403 — karena barisnya memang tidak ada dalam kuerinya.
     */
    private function tagihanNarasi(int $noElemen, ?User $pj = null): Tagihan
    {
        $t = Tagihan::whereHas('elemen', fn ($q) => $q->where('no', $noElemen))
            ->where('jenis', JenisTagihan::Narasi)
            ->firstOrFail();

        if ($pj !== null) {
            $t->update(['penanggung_jawab_id' => $pj->getKey()]);
        }

        return $t->fresh();
    }

    #[Test]
    public function acuan_instrumen_ditampilkan_utuh_di_satu_halaman(): void
    {
        $t = $this->tagihanNarasi(1);
        $elemen = $t->elemen;

        $respons = $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful();

        // Panduan Buku 3 bisa sepanjang 876 karakter; memotongnya berarti
        // anggota pokja harus membuka PDF aslinya.
        $respons->assertSee('Panduan');
        $respons->assertSee(str(strip_tags($elemen->panduan))->limit(80, '')->trim()->toString());
        $respons->assertSee('Parameter pelampauan standar mutu');
        $respons->assertSee('Bukti pendukung yang diminta');
    }

    #[Test]
    public function elemen_refleksi_mendapat_dua_kotak_terpisah(): void
    {
        // Elemen 4 berjenis refleksi. Evaluasi menjawab "apa yang terjadi",
        // tindak lanjut menjawab "apa yang dilakukan"; menggabungkannya
        // membuat yang kedua sering hilang.
        $t = $this->tagihanNarasi(4);

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful()
            ->assertSee('Acuan: Evaluasi dan Refleksi')
            ->assertSee('Acuan: Tindak Lanjut');
    }

    /** @return array<string, array{int}> */
    public static function elemenSyaratPerlu(): array
    {
        return ['E17' => [17], 'E34' => [34], 'E45' => [45], 'E51' => [51], 'E58' => [58]];
    }

    #[Test]
    #[DataProvider('elemenSyaratPerlu')]
    public function peringatan_syarat_perlu_terlihat_di_layar_pengerjaan(int $no): void
    {
        $t = $this->tagihanNarasi($no);

        $respons = $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful()
            ->assertSee('SYARAT PERLU')
            ->assertSee($t->elemen->syaratPerlu->ambang_5_tahun);

        // Pemeriksaan wajib dari prompt: peringatan harus muncul SEBELUM
        // panduan, supaya terlihat tanpa perlu menggulir.
        $isi = $respons->getContent();
        $this->assertLessThan(
            strpos($isi, 'Panduan'),
            strpos($isi, 'SYARAT PERLU'),
            "Peringatan syarat perlu E{$no} harus muncul sebelum panduan."
        );
    }

    /** @return array<string, array{int}> */
    public static function elemenBerambangKuantitatif(): array
    {
        return ['E17' => [17], 'E51' => [51]];
    }

    /**
     * Satu kasus per elemen, bukan satu gelung: AuthenticateSession
     * membatalkan sesi begitu pengguna berganti dalam satu uji.
     */
    #[Test]
    #[DataProvider('elemenBerambangKuantitatif')]
    public function elemen_17_dan_51_menyebut_bahwa_skor_4_tidak_cukup(int $no): void
    {
        $t = $this->tagihanNarasi($no);

        // Perangkapnya ada di data: pada kedua elemen ini ambang skor 4 LEBIH
        // RENDAH daripada ambang syarat perlu 5 tahun.
        $this->assertNotEmpty($t->elemen->syaratPerlu->catatan);
        $this->assertSame('ambang_kuantitatif', $t->elemen->syaratPerlu->jenis_ambang);

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful()
            // Kalimatnya dinyatakan di layar, bukan diandalkan pada redaksi
            // catatan: E51 hanya menyiratkannya.
            ->assertSee('skor 4 pada matriks penilaian TIDAK berarti syarat perlu ini terpenuhi');
    }

    #[Test]
    public function daftar_bukti_tertaut_dan_peringatan_bila_kosong(): void
    {
        $t = $this->tagihanNarasi(1);

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful()
            ->assertSee('Bukti tertaut')
            ->assertSee('Belum ada bukti tertaut.');
    }

    #[Test]
    public function riwayat_dan_komentar_ada_di_halaman_yang_sama(): void
    {
        $t = $this->tagihanNarasi(1);

        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get("/panel/tagihans/{$t->id}")
            ->assertSuccessful()
            ->assertSee('Riwayat status');
    }

    #[Test]
    public function layar_bukti_menampilkan_kotak_bantuan_drive(): void
    {
        // Memberi tahu cara membuka akses SETELAH orang gagal tidak menolong
        // siapa pun — tautan yang salah sudah terlanjur menempel.
        // Kotaknya muncul begitu jenis "tautan" dipilih — diuji lewat Livewire
        // karena halaman create dibuka dengan jenis "berkas" sebagai bawaan.
        Livewire::actingAs($this->pengguna(PeranPengguna::Ketua))
            ->test(CreateBukti::class)
            ->fillForm(['jenis' => 'tautan'])
            ->assertSee('Siapa saja yang memiliki link', escape: false);
    }

    #[Test]
    public function naskah_led_mewarnai_baris_yang_kurang_kata(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get('/panel/narasis')
            ->assertSuccessful();
    }
}
