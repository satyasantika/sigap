<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Exceptions\RiwayatTidakBolehDiubah;
use App\Models\Bukti;
use App\Models\Elemen;
use App\Models\Narasi;
use App\Models\NarasiVersi;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengelolaNarasi;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PengelolaNarasiTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    private Periode $periode;

    private User $penulis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->prodi = Prodi::firstOrFail();
        $this->periode = Periode::firstOrFail();
        $this->penulis = User::where('peran', PeranPengguna::Anggota)->firstOrFail();
    }

    private function pengelola(): PengelolaNarasi
    {
        return app(PengelolaNarasi::class);
    }

    private function narasiUntuk(int $no = 1): Narasi
    {
        return $this->pengelola()->untukElemen(
            $this->periode->id,
            Elemen::where('no', $no)->firstOrFail()->id,
            $this->prodi->id,
        );
    }

    private function teks(int $kata): string
    {
        return implode(' ', array_fill(0, $kata, 'kata'));
    }

    #[Test]
    public function jumlah_kata_dihitung_dengan_pemisahan_spasi(): void
    {
        $p = $this->pengelola();

        // Teks contoh yang jumlah katanya sudah diketahui.
        $this->assertSame(0, $p->jumlahKata(null));
        $this->assertSame(0, $p->jumlahKata('   '));
        $this->assertSame(1, $p->jumlahKata('satu'));
        $this->assertSame(5, $p->jumlahKata('Program studi ini telah terakreditasi'));

        // Spasi berlebih, baris baru, dan tab tidak menambah cacah.
        $this->assertSame(5, $p->jumlahKata("Program  studi\nini\ttelah   terakreditasi"));

        // Tanda baca menempel pada kata, bukan kata tersendiri.
        $this->assertSame(4, $p->jumlahKata('Visi, misi, tujuan, sasaran.'));
    }

    #[Test]
    public function setiap_penyimpanan_menulis_satu_baris_versi(): void
    {
        $n = $this->narasiUntuk();

        $this->pengelola()->simpan($n, $this->teks(10), $this->penulis);
        $this->pengelola()->simpan($n->fresh(), $this->teks(20), $this->penulis);
        $this->pengelola()->simpan($n->fresh(), $this->teks(30), $this->penulis);

        $this->assertSame(3, NarasiVersi::where('narasi_id', $n->id)->count());
        $this->assertSame(3, $n->fresh()->versi);
        $this->assertSame(30, $n->fresh()->jumlah_kata);
    }

    #[Test]
    public function versi_lama_tetap_tersimpan_utuh(): void
    {
        $n = $this->narasiUntuk();

        $this->pengelola()->simpan($n, 'Kalimat pertama yang kemudian dihapus orang lain.', $this->penulis);
        $this->pengelola()->simpan($n->fresh(), 'Kalimat kedua.', $this->penulis);

        // Naskah akreditasi ditulis berbulan-bulan oleh banyak orang. Tanpa
        // jejak versi, kalimat yang hilang tidak bisa dikembalikan.
        $isiLama = NarasiVersi::where('narasi_id', $n->id)->orderBy('created_at')->first()->isi;

        $this->assertSame('Kalimat pertama yang kemudian dihapus orang lain.', $isiLama);
    }

    #[Test]
    public function versi_narasi_menolak_disunting_dan_dihapus(): void
    {
        $n = $this->narasiUntuk();
        $this->pengelola()->simpan($n, 'isi', $this->penulis);

        $versi = NarasiVersi::where('narasi_id', $n->id)->first();

        try {
            $versi->update(['isi' => 'disisipkan diam-diam']);
            $this->fail('narasi_versi seharusnya menolak update.');
        } catch (RiwayatTidakBolehDiubah $e) {
            $this->assertStringContainsString('append only', $e->getMessage());
        }

        $this->expectException(RiwayatTidakBolehDiubah::class);
        $versi->delete();
    }

    #[Test]
    public function narasi_di_bawah_200_kata_tidak_bisa_diajukan(): void
    {
        $n = $this->narasiUntuk();
        $this->pengelola()->simpan($n, $this->teks(199), $this->penulis);

        $alasan = $this->pengelola()->bolehDiajukan($n->fresh());

        $this->assertNotEmpty($alasan);
        $this->assertStringContainsString('kurang 1 kata', implode(' ', $alasan));
    }

    #[Test]
    public function narasi_kosong_ditolak_dengan_alasan_yang_jelas(): void
    {
        $n = $this->narasiUntuk();

        $this->assertStringContainsString('masih kosong', implode(' ', $this->pengelola()->bolehDiajukan($n)));
    }

    #[Test]
    public function narasi_tanpa_bukti_tertaut_tidak_bisa_diajukan(): void
    {
        $n = $this->narasiUntuk();
        $this->pengelola()->simpan($n, $this->teks(250), $this->penulis);

        $alasan = $this->pengelola()->bolehDiajukan($n->fresh());

        // Klaim tanpa bukti tidak bisa dinilai asesor.
        $this->assertStringContainsString('belum punya bukti tertaut', implode(' ', $alasan));
    }

    #[Test]
    public function narasi_layak_bila_cukup_kata_dan_ada_bukti(): void
    {
        $elemen = Elemen::where('no', 1)->firstOrFail();
        $n = $this->narasiUntuk(1);
        $this->pengelola()->simpan($n, $this->teks(250), $this->penulis);

        $bukti = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Bukti', 'jenis' => 'berkas',
            'tanggal_kejadian' => now()->subMonth(), 'sumber' => SumberData::Manual,
            'diunggah_oleh' => $this->penulis->id,
        ]);
        $bukti->elemen()->attach($elemen->id);

        $this->assertSame([], $this->pengelola()->bolehDiajukan($n->fresh()));
    }

    #[Test]
    public function lebih_dari_600_kata_hanya_diperingatkan_tidak_menghalangi(): void
    {
        $elemen = Elemen::where('no', 1)->firstOrFail();
        $n = $this->narasiUntuk(1);
        $this->pengelola()->simpan($n, $this->teks(700), $this->penulis);

        $bukti = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Bukti', 'jenis' => 'berkas',
            'tanggal_kejadian' => now()->subMonth(), 'sumber' => SumberData::Manual,
            'diunggah_oleh' => $this->penulis->id,
        ]);
        $bukti->elemen()->attach($elemen->id);

        // Batas atas adalah anjuran, bukan larangan — naskah 620 kata yang
        // bagus lebih berguna daripada naskah 600 kata yang dipotong asal.
        $this->assertSame([], $this->pengelola()->bolehDiajukan($n->fresh()));
    }

    #[Test]
    public function alasan_dikembalikan_sebagai_daftar_bukan_satu_pesan(): void
    {
        $n = $this->narasiUntuk();
        $this->pengelola()->simpan($n, $this->teks(10), $this->penulis);

        $alasan = $this->pengelola()->bolehDiajukan($n->fresh());

        // Orang yang naskahnya ditolak harus tahu SEMUA yang kurang sekaligus,
        // bukan menemukannya satu per satu lewat percobaan berulang.
        $this->assertCount(2, $alasan);
    }

    #[Test]
    public function satu_narasi_per_elemen_per_periode(): void
    {
        $a = $this->narasiUntuk(5);
        $b = $this->narasiUntuk(5);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Narasi::where('elemen_id', $a->elemen_id)->count());
    }
}
