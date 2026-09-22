<?php

namespace Tests\Feature;

use App\Enums\AksesTautan;
use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use App\Services\EksporNaskah;
use App\Services\PengelolaNarasi;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EksporNaskahTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private User $penulis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->prodi = Prodi::firstOrFail();
        $this->penulis = User::where('peran', PeranPengguna::Anggota)->firstOrFail();
    }

    private function bukti(array $ubah = []): Bukti
    {
        return Bukti::create(array_merge([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'SK Dekan 2401', 'jenis' => 'tautan',
            'url_kanonik' => 'https://drive.google.com/file/d/ABC/view',
            'tanggal_kejadian' => now()->subMonths(6), 'sumber' => SumberData::Manual,
            'diunggah_oleh' => $this->penulis->id,
        ], $ubah));
    }

    #[Test]
    public function memuat_seluruh_59_elemen_terurut_kriteria(): void
    {
        $md = app(EksporNaskah::class)->markdown($this->periode);

        $this->assertSame(59, substr_count($md, "\n## E"));

        // Terurut kriteria, bukan sekadar nomor elemen.
        $posK1 = strpos($md, '## E1.');
        $posK9 = strpos($md, '## E59.');
        $this->assertLessThan($posK9, $posK1);
    }

    #[Test]
    public function naskah_yang_belum_ditulis_dinyatakan_terang_terangan(): void
    {
        $md = app(EksporNaskah::class)->markdown($this->periode);

        // Elemen kosong tidak boleh terlihat seperti elemen yang sudah selesai.
        $this->assertStringContainsString('Naskah belum ditulis', $md);
    }

    #[Test]
    public function naskah_yang_sudah_ditulis_ikut_beserta_cacah_katanya(): void
    {
        $elemen = Elemen::where('no', 1)->firstOrFail();
        $n = app(PengelolaNarasi::class)->untukElemen($this->periode->id, $elemen->id, $this->prodi->id);
        app(PengelolaNarasi::class)->simpan($n, 'Visi keilmuan program studi ini dirumuskan bersama.', $this->penulis);

        $md = app(EksporNaskah::class)->markdown($this->periode);

        $this->assertStringContainsString('Visi keilmuan program studi ini dirumuskan bersama.', $md);
        $this->assertStringContainsString('7 kata', $md);
    }

    #[Test]
    public function bukti_dicantumkan_beserta_url_kanonik_dan_status_aksesnya(): void
    {
        $elemen = Elemen::where('no', 1)->firstOrFail();
        $bukti = $this->bukti();
        $bukti->forceFill(['akses_status' => AksesTautan::Terbuka, 'validasi_status' => ValidasiBukti::Sah])->save();
        $bukti->elemen()->attach($elemen->id, ['keterangan' => 'Halaman 3, rumusan visi.']);

        $md = app(EksporNaskah::class)->markdown($this->periode);

        // Asesor yang membaca ekspor ini harus bisa menilai sendiri apakah
        // tautannya masih hidup.
        $this->assertStringContainsString('https://drive.google.com/file/d/ABC/view', $md);
        $this->assertStringContainsString('keterbacaan: Terbuka', $md);
        $this->assertStringContainsString('keabsahan: Sah', $md);
        $this->assertStringContainsString('Halaman 3, rumusan visi.', $md);
    }

    #[Test]
    public function ekspor_tetap_berjalan_meski_ada_bukti_bermasalah(): void
    {
        $bukti = $this->bukti(['judul' => 'Tautan yang mati']);
        $bukti->forceFill(['akses_status' => AksesTautan::PerluIzin])->save();

        $md = app(EksporNaskah::class)->markdown($this->periode);

        // Menolak mengekspor akan membuat orang menunda memeriksa sampai
        // tenggat; menyembunyikannya akan membuat naskah dikirim dengan
        // tautan mati di dalamnya.
        $this->assertStringContainsString('## E1.', $md, 'Ekspor tetap berisi naskahnya.');
        $this->assertStringContainsString('bukti bermasalah', $md);
        $this->assertStringContainsString('Tautan yang mati', $md);
    }

    #[Test]
    public function halaman_ringkasan_muncul_di_depan_sebelum_naskah(): void
    {
        $bukti = $this->bukti(['judul' => 'Tautan bermasalah']);
        $bukti->forceFill(['akses_status' => AksesTautan::TidakDitemukan])->save();

        $md = app(EksporNaskah::class)->markdown($this->periode);

        $posRingkasan = strpos($md, 'bukti bermasalah');
        $posElemenPertama = strpos($md, '## E1.');

        // Di depan, bukan di belakang: peringatan yang ditaruh di halaman 40
        // tidak akan dibaca siapa pun.
        $this->assertLessThan($posElemenPertama, $posRingkasan);
    }

    #[Test]
    public function tanpa_bukti_bermasalah_tidak_ada_halaman_peringatan(): void
    {
        $md = app(EksporNaskah::class)->markdown($this->periode);

        $this->assertStringNotContainsString('bukti bermasalah', $md);
    }

    #[Test]
    public function elemen_syarat_perlu_ditandai_di_ekspor(): void
    {
        $md = app(EksporNaskah::class)->markdown($this->periode);

        // Lima kali: elemen 17, 34, 45, 51, 58.
        $this->assertSame(5, substr_count($md, '**SYARAT PERLU**'));
    }

    #[Test]
    public function nama_berkas_memuat_nama_periode_dan_cap_waktu(): void
    {
        $nama = app(EksporNaskah::class)->namaBerkas($this->periode);

        $this->assertStringStartsWith('naskah-led-ppg-2027-', $nama);
        $this->assertStringEndsWith('.md', $nama);
    }
}
