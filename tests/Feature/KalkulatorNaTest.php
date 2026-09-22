<?php

namespace Tests\Feature;

use App\Enums\LevelSyaratPerlu;
use App\Enums\PeranPengguna;
use App\Models\Elemen;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\StatusSyaratPerlu;
use App\Models\User;
use App\Services\KalkulatorNa;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kelima patokan dari vibecoding/docs/03-perhitungan.md, dihitung tangan di
 * dokumen itu dan SUDAH PASTI.
 *
 * Patokan 363,25 termasuk uji yang tidak boleh dihapus selamanya: ia bukti
 * bahwa langit-langit instrumen ini hanya 2,25 di atas ambang Unggul bila
 * seluruh elemen berjenis `data` berskor 3.
 */
class KalkulatorNaTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private User $penilai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->prodi = Prodi::firstOrFail();
        $this->penilai = User::where('peran', PeranPengguna::Ketua)->firstOrFail();
    }

    private function kalkulator(): KalkulatorNa
    {
        return app(KalkulatorNa::class);
    }

    /** Memberi skor pada elemen tertentu; null berarti seluruhnya. */
    private function nilai(int $skor, ?callable $saring = null, ?User $oleh = null): void
    {
        $elemen = Elemen::get();

        if ($saring !== null) {
            $elemen = $elemen->filter($saring);
        }

        foreach ($elemen as $e) {
            Penilaian::updateOrCreate(
                [
                    'periode_id' => $this->periode->id,
                    'elemen_id' => $e->id,
                    'penilai_id' => ($oleh ?? $this->penilai)->id,
                ],
                ['prodi_id' => $this->prodi->id, 'skor' => $skor, 'tanggal' => now()],
            );
        }
    }

    private function tetapkanSyaratPerlu(LevelSyaratPerlu $level): void
    {
        foreach (Elemen::bersyaratPerlu()->get() as $e) {
            StatusSyaratPerlu::updateOrCreate(
                ['periode_id' => $this->periode->id, 'elemen_id' => $e->id],
                ['prodi_id' => $this->prodi->id, 'level' => $level],
            );
        }
    }

    // ---- Kelima patokan --------------------------------------------------

    #[Test]
    public function seluruh_elemen_berskor_3_menghasilkan_300(): void
    {
        $this->nilai(3);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(300.0, $h->na);
        $this->assertSame(0.0, $h->surplus);
        $this->assertSame('Terakreditasi', $h->status);
        $this->assertSame(5, $h->masaBerlaku);
    }

    #[Test]
    public function seluruh_elemen_berskor_4_menghasilkan_400(): void
    {
        $this->nilai(4);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(400.0, $h->na);
        $this->assertSame(100.0, $h->surplus);
        $this->assertSame(100.0, $h->bobotSkor4, 'Seluruh 100 bobot berskor 4.');
    }

    #[Test]
    public function seluruh_elemen_berskor_1_menghasilkan_100(): void
    {
        $this->nilai(1);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(100.0, $h->na);
        $this->assertSame('Tidak Terakreditasi', $h->status);
        $this->assertSame(0, $h->masaBerlaku);
    }

    #[Test]
    public function rubrik_dan_refleksi_4_data_3_menghasilkan_363_25(): void
    {
        // UJI YANG TIDAK BOLEH DIHAPUS.
        // 4 × 63,25 + 3 × 36,75 = 363,25 — hanya 2,25 di atas ambang Unggul.
        // Inilah langit-langit instrumen bila elemen berjenis `data` tidak
        // digarap, dan alasan elemen `data` tidak bisa diabaikan.
        $this->nilai(4, fn (Elemen $e) => in_array($e->jenis->value, ['rubrik', 'refleksi'], true));
        $this->nilai(3, fn (Elemen $e) => $e->jenis->value === 'data');

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(363.25, $h->na);
        $this->assertSame(63.25, $h->bobotSkor4);
        $this->assertSame(0, $h->jumlahElemenBelumDinilai);
    }

    #[Test]
    public function skor_3_kecuali_elemen_58_berskor_2_menghasilkan_297(): void
    {
        // E58 berbobot 3,00 — bobot tunggal terbesar di seluruh instrumen.
        $this->nilai(3);
        $this->nilai(2, fn (Elemen $e) => $e->no === 58);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(297.0, $h->na);
        $this->assertSame(-3.0, $h->surplus);
    }

    // ---- Elemen yang belum dinilai --------------------------------------

    #[Test]
    public function elemen_belum_dinilai_diandaikan_berskor_3_dan_cacahnya_dilaporkan(): void
    {
        // Tanpa satu pun penilaian, NA proyeksinya 300 — tetapi itu tebakan,
        // bukan perkiraan, dan angkanya harus menyatakan begitu.
        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(300.0, $h->na);
        $this->assertSame(59, $h->jumlahElemenBelumDinilai);
        $this->assertStringContainsString('59 elemen belum dinilai', $h->kalimatKeyakinan());
    }

    #[Test]
    public function kalimat_keyakinan_menyatakan_utuh_bila_semua_dinilai(): void
    {
        $this->nilai(3);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(0, $h->jumlahElemenBelumDinilai);
        $this->assertStringContainsString('Seluruh 59 elemen sudah dinilai', $h->kalimatKeyakinan());
    }

    // ---- Penentuan status: kasus batas ----------------------------------

    /** @return array<string, array{int, string, string, int}> */
    public static function tabelStatus(): array
    {
        return [
            'NA 400 dengan syarat lima' => [4, 'lima', 'Unggul', 5],
            'NA 400 dengan syarat tiga' => [4, 'tiga', 'Unggul', 3],
            'NA 400 tanpa syarat perlu' => [4, 'belum', 'Terakreditasi', 5],
            'NA 300 dengan syarat lima' => [3, 'lima', 'Terakreditasi', 5],
            'NA 100 dengan syarat lima' => [1, 'lima', 'Tidak Terakreditasi', 0],
        ];
    }

    #[Test]
    #[DataProvider('tabelStatus')]
    public function status_mengikuti_tabel_dokumen_03(int $skor, string $level, string $status, int $masa): void
    {
        $this->nilai($skor);
        $this->tetapkanSyaratPerlu(LevelSyaratPerlu::from($level));

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame($status, $h->status);
        $this->assertSame($masa, $h->masaBerlaku);
    }

    #[Test]
    public function na_tertinggi_tanpa_syarat_perlu_tetap_bukan_unggul(): void
    {
        // Kasus yang paling mudah keliru, dan alasan ubin gerbang tidak boleh
        // dipisahkan dari ubin progres: progres 100% tanpa syarat perlu tidak
        // menghasilkan Unggul.
        $this->nilai(4);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertSame(400.0, $h->na);
        $this->assertSame('Terakreditasi', $h->status, 'NA 400 tanpa syarat perlu bukan Unggul.');
        $this->assertStringContainsString('Terakreditasi — masa berlaku 5 tahun', $h->kalimatStatus());
    }

    #[Test]
    public function empat_dari_lima_syarat_perlu_tetap_berarti_tidak_terpenuhi(): void
    {
        $this->nilai(4);
        $this->tetapkanSyaratPerlu(LevelSyaratPerlu::Lima);

        // Satu diturunkan ke `belum` — kelimanya, bukan sebagian.
        $satu = Elemen::bersyaratPerlu()->first();
        StatusSyaratPerlu::where('elemen_id', $satu->id)->update(['level' => 'belum']);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertFalse($h->syarat5);
        $this->assertFalse($h->syarat3);
        $this->assertSame('Terakreditasi', $h->status);
    }

    #[Test]
    public function level_lima_juga_memenuhi_ambang_tiga_tahun(): void
    {
        $this->nilai(3);
        $this->tetapkanSyaratPerlu(LevelSyaratPerlu::Lima);

        $h = $this->kalkulator()->hitung($this->periode, $this->penilai);

        $this->assertTrue($h->syarat3, 'Terpenuhi di level lima tahun otomatis memenuhi tiga tahun.');
        $this->assertTrue($h->syarat5);
    }

    // ---- Dua penilai -----------------------------------------------------

    #[Test]
    public function dua_penilai_menskor_terpisah_tanpa_saling_menimpa(): void
    {
        $lain = User::where('peran', PeranPengguna::Auditor)->firstOrFail();

        $this->nilai(4);
        $this->nilai(2, oleh: $lain);

        $this->assertSame(400.0, $this->kalkulator()->hitung($this->periode, $this->penilai)->na);
        $this->assertSame(200.0, $this->kalkulator()->hitung($this->periode, $lain)->na);
    }

    #[Test]
    public function selisih_antarpenilai_terurut_dari_yang_terbesar(): void
    {
        $lain = User::where('peran', PeranPengguna::Auditor)->firstOrFail();

        $this->nilai(3);
        $this->nilai(3, oleh: $lain);

        // Dua elemen diperselisihkan dengan rentang berbeda.
        $e1 = Elemen::where('no', 1)->firstOrFail();
        $e2 = Elemen::where('no', 2)->firstOrFail();
        Penilaian::where('elemen_id', $e1->id)->where('penilai_id', $lain->id)->update(['skor' => 1]);
        Penilaian::where('elemen_id', $e2->id)->where('penilai_id', $lain->id)->update(['skor' => 2]);

        $selisih = $this->kalkulator()->selisihAntarPenilai($this->periode);

        $this->assertCount(2, $selisih);
        // Rentang 2 (3 vs 1) lebih dulu daripada rentang 1 (3 vs 2): itu yang
        // paling perlu didiskusikan.
        $this->assertSame(2, $selisih[0]['rentang']);
        $this->assertSame(1, $selisih[1]['rentang']);
        $this->assertSame(1, $selisih[0]['elemen']->no);
    }

    #[Test]
    public function elemen_yang_disepakati_tidak_masuk_daftar_selisih(): void
    {
        $lain = User::where('peran', PeranPengguna::Auditor)->firstOrFail();

        $this->nilai(3);
        $this->nilai(3, oleh: $lain);

        $this->assertSame([], $this->kalkulator()->selisihAntarPenilai($this->periode));
    }
}
