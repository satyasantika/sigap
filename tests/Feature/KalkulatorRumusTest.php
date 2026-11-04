<?php

namespace Tests\Feature;

use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Services\KalkulatorRumus;
use App\Support\Rumus\HasilRumus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Patokan dari vibecoding/docs/03-perhitungan.md.
 *
 * Angka-angka di sini dihitung tangan di dokumen itu dan SUDAH PASTI. Bila
 * salah satunya merah, yang salah adalah kodenya — bukan ujinya.
 *
 * Dua uji di berkas ini termasuk yang tidak boleh dihapus selamanya:
 * PDS3 = 41,67 memberi skor 4 tetapi syarat lima tahun TIDAK terpenuhi, dan
 * PPDTPS menghitung dosen bukan artikel.
 */
class KalkulatorRumusTest extends TestCase
{
    use RefreshDatabase;

    private function kalkulator(): KalkulatorRumus
    {
        return app(KalkulatorRumus::class);
    }

    // ---- PDS3: perangkap utama instrumen --------------------------------

    #[Test]
    public function pds3_enam_dari_dua_belas_memenuhi_syarat_lima_tahun(): void
    {
        // NDLK + NDGB = 4 disertakan: syarat lima tahun menuntut PDS3 >= 50
        // DAN sekurangnya empat DTPS berjabatan lektor kepala ke atas.
        // Dokumen 03 menyebut patokannya tanpa angka itu; tanpa keduanya,
        // "syarat terpenuhi" tidak bisa disimpulkan.
        $h = $this->kalkulator()->pds3(nds3: 6, ndtps: 12, ndlk: 3, ndgb: 1);

        $this->assertSame(50.0, $h->nilai);
        $this->assertSame(4, $h->skor);
        $this->assertTrue($h->memenuhiSyarat5Tahun);
    }

    #[Test]
    public function pds3_4167_memberi_skor_4_tetapi_syarat_lima_tahun_tidak_terpenuhi(): void
    {
        // UJI YANG TIDAK BOLEH DIHAPUS.
        // Inilah perangkap paling mahal di seluruh instrumen: ambang skor 4
        // adalah 40, sementara ambang syarat perlu lima tahun adalah 50.
        // Sistem yang menyamakan keduanya akan melaporkan "aman" padahal
        // status Unggul lima tahun tidak akan diperoleh.
        $h = $this->kalkulator()->pds3(nds3: 5, ndtps: 12, ndlk: 3, ndgb: 1);

        $this->assertSame(41.67, $h->nilai);
        $this->assertSame(4, $h->skor, 'Skor penuh, karena ambang skor 4 adalah 40.');
        $this->assertFalse($h->memenuhiSyarat5Tahun, 'Syarat lima tahun menuntut 50, bukan 40.');
        $this->assertStringContainsString('BELUM terpenuhi', $h->catatan);
    }

    #[Test]
    public function pds3_cukup_persen_tetapi_kurang_lektor_kepala_tetap_gagal(): void
    {
        // Dua syarat, bukan satu: persentase doktor DAN cacah lektor kepala.
        $h = $this->kalkulator()->pds3(nds3: 8, ndtps: 12, ndlk: 2, ndgb: 0);

        $this->assertSame(66.67, $h->nilai);
        $this->assertSame(4, $h->skor);
        $this->assertFalse($h->memenuhiSyarat5Tahun, 'NDLK + NDGB hanya 2, butuh 4.');
    }

    #[Test]
    public function pds3_menolak_ndtps_nol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/NDTPS bernilai 0/');

        $this->kalkulator()->pds3(nds3: 3, ndtps: 0);
    }

    // ---- PPDTPS: dosen, bukan artikel -----------------------------------

    #[Test]
    public function ppdtps_menghitung_dosen_bukan_artikel(): void
    {
        // UJI YANG TIDAK BOLEH DIHAPUS.
        // Tiga dosen dengan total dua puluh artikel dari dua belas DTPS
        // menghasilkan 25,00 — bukan 166,67. Seorang dosen dengan sepuluh
        // artikel tetap dihitung satu.
        $h = $this->kalkulator()->ppdtps(dosenBerpublikasi: 3, ndtps: 12, jumlahArtikel: 20);

        $this->assertSame(25.0, $h->nilai);
        $this->assertSame(4, $h->skor, 'Ambang skor 4 adalah 20%.');
        $this->assertFalse($h->memenuhiSyarat5Tahun, 'Syarat lima tahun menuntut 40%, dua kali lipatnya.');
        $this->assertSame(20, $h->komponen['jumlahArtikel'], 'Cacah artikel dicatat, tetapi tidak dipakai menghitung.');
    }

    #[Test]
    public function ppdtps_lima_dari_dua_belas_memenuhi_syarat_lima_tahun(): void
    {
        $h = $this->kalkulator()->ppdtps(dosenBerpublikasi: 5, ndtps: 12);

        $this->assertSame(41.67, $h->nilai);
        $this->assertSame(4, $h->skor);
        $this->assertTrue($h->memenuhiSyarat5Tahun);
    }

    #[Test]
    public function ppdtps_menolak_cacah_dosen_melebihi_ndtps(): void
    {
        // Pagar terhadap kesalahan paling sering: menyerahkan cacah ARTIKEL
        // ke parameter yang meminta cacah DOSEN.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cacah dosen, bukan cacah artikel/');

        $this->kalkulator()->ppdtps(dosenBerpublikasi: 20, ndtps: 12);
    }

    #[Test]
    public function ppdtps_batas_skor_setengah_terbuka(): void
    {
        // 20 masuk ke skor 4, 15 masuk ke skor 3 — bukan sebaliknya.
        // Dokumen 03 menulisnya ambigu ("15-20 → 3"); rumus.json yang menang.
        $this->assertSame(4, $this->kalkulator()->ppdtps(1, 5)->skor);   // 20,00
        $this->assertSame(3, $this->kalkulator()->ppdtps(3, 20)->skor);  // 15,00
        $this->assertSame(2, $this->kalkulator()->ppdtps(1, 10)->skor);  // 10,00
        $this->assertSame(1, $this->kalkulator()->ppdtps(1, 11)->skor);  //  9,09
    }

    #[Test]
    public function ppdtps_ambang_dibandingkan_pada_rasio_mentah(): void
    {
        // 1504/7521 = 19,997…%: tampil 20,0 setelah dibulatkan, tetapi rasio
        // mentahnya belum mencapai 20, jadi skornya 3 dan syarat perlu tiga
        // tahun belum terpenuhi.
        $h = $this->kalkulator()->ppdtps(dosenBerpublikasi: 1504, ndtps: 7521);

        $this->assertSame(20.0, $h->nilai);
        $this->assertSame(3, $h->skor);
        $this->assertSame(3.0, $h->skorTepat);
        $this->assertFalse($h->memenuhiSyarat3Tahun);
    }

    // ---- Rumus lain ------------------------------------------------------

    #[Test]
    public function pgblkl_sesuai_patokan(): void
    {
        $h = $this->kalkulator()->pgblkl(ndgb: 1, ndlk: 3, ndl: 4, ndtps: 12);

        $this->assertSame(66.67, $h->nilai);
    }

    #[Test]
    public function rk_sesuai_patokan(): void
    {
        // RK = (3×2 + 2×3 + 1×4) / 12 = 16/12 = 1,33 → A.1 = 1,33.
        // A.2 dari NI/NN/NW mentah (a=2, b=6, c=9): pita 2–3,
        // 2 + 1/2 + 3/6 − (1×3)/(2×6) = 2,75.
        // Skor A = (2 × 1,33 + 2,75) / 3 = 1,80.
        $h = $this->kalkulator()->rk(n1: 2, n2: 3, n3: 4, ni: 1, nn: 3, nw: 5, ndtps: 12);

        $this->assertSame(1.33, $h->nilai);
        $this->assertSame(1.33, $h->komponen['a1']);
        $this->assertSame(2.75, $h->komponen['a2']);
        $this->assertSame(1.80, $h->skorTepat);
        $this->assertSame(1, $h->skor);
    }

    #[Test]
    public function rk_kertas_kerja_memberi_empat(): void
    {
        // Contoh kertas kerja: (3×71 + 2×24 + 46) / 28 = 307/28 = 10,96,
        // NI = 19 ≥ 2. Kedua bagian penuh.
        $h = $this->kalkulator()->rk(71, 24, 46, 19, 24, 98, 28);

        $this->assertSame(10.96, $h->nilai);
        $this->assertSame(4.0, $h->skorTepat);
    }

    #[Test]
    public function rsa_sesuai_patokan(): void
    {
        // 100/12 = 8,33 → skor 3 (ambang skor 4 adalah 9)
        $h = $this->kalkulator()->rsa(nas: 100, ndtps: 12);

        $this->assertSame(8.33, $h->nilai);
        $this->assertSame(3, $h->skor);
    }

    #[Test]
    public function skor_elemen_17_menggabungkan_pds3_pgblkl_dan_analisis(): void
    {
        $pds3 = $this->kalkulator()->pds3(nds3: 6, ndtps: 12, ndlk: 3, ndgb: 1);
        $pgblkl = $this->kalkulator()->pgblkl(ndgb: 1, ndlk: 3, ndl: 4, ndtps: 12);

        // (3 × (4 + 3,9048) + 4) / 7
        $skor = $this->kalkulator()->skorElemen17($pds3, $pgblkl, skorAnalisis: 4.0);

        $this->assertGreaterThan(3.0, $skor);
        $this->assertLessThanOrEqual(4.0, $skor);
    }

    // ---- TKM: normalisasi /4 sesuai kertas kerja AK (sel E207) ---------

    #[Test]
    public function tkm_seluruh_responden_sangat_baik_menjadi_seratus_persen(): void
    {
        // Nilai mentah 400 dibagi 4 menjadi 100%, seperti rumus sel E207
        // kertas kerja AK IAPSK 3.0; lihat komentar di KalkulatorRumus::tkm.
        $h = $this->kalkulator()->tkm([['a' => 100, 'b' => 0, 'c' => 0, 'd' => 0]]);

        $this->assertSame(100.0, $h->nilai);
        $this->assertSame(400.0, $h->komponen['rata_mentah']);
        $this->assertSame(4, $h->skor);
    }

    /**
     * Ambang kertas kerja AK (sel E232) dan rumus.json: 75 / 50 / 25 %.
     * Setiap batas diuji tepat di batasnya dan sedikit di bawahnya.
     */
    public static function batasTkm(): array
    {
        return [
            'tepat 75% → 4' => [['a' => 0, 'b' => 100, 'c' => 0, 'd' => 0], 75.0, 4],
            'di bawah 75% → 3' => [['a' => 0, 'b' => 99, 'c' => 1, 'd' => 0], 74.75, 3],
            'tepat 50% → 3' => [['a' => 0, 'b' => 0, 'c' => 100, 'd' => 0], 50.0, 3],
            'di bawah 50% → 2' => [['a' => 0, 'b' => 0, 'c' => 99, 'd' => 1], 49.75, 2],
            'tepat 25% → 2' => [['a' => 0, 'b' => 0, 'c' => 0, 'd' => 100], 25.0, 2],
            'di bawah 25% → 1' => [['a' => 0, 'b' => 0, 'c' => 0, 'd' => 99], 24.75, 1],
        ];
    }

    #[Test]
    #[DataProvider('batasTkm')]
    public function tkm_memakai_ambang_kertas_kerja(array $dimensi, float $nilai, int $skor): void
    {
        $h = $this->kalkulator()->tkm([$dimensi]);

        $this->assertSame($nilai, $h->nilai);
        $this->assertSame($skor, $h->skor);
    }

    #[Test]
    public function tkm_menyebut_rujukan_normalisasinya_di_kertas_kerja(): void
    {
        // Orang yang membaca angkanya harus bisa menelusuri dari mana /4 berasal.
        $h = $this->kalkulator()->tkm([['a' => 50, 'b' => 30, 'c' => 15, 'd' => 5]]);

        $this->assertSame('Normalisasi /4 sesuai kertas kerja AK IAPSK 3.0 (sel E207)', $h->catatan);
        $this->assertSame(4, $h->komponen['pembagi_normalisasi']);
    }

    /**
     * Buku 4, kertas kerja (sel E569), dan rumus.json: RIPK ≥ 3,25 → 4;
     * 2,00 ≤ RIPK < 3,25 → (8·RIPK − 6)/5; di bawah 2,00 → 1. Tangga
     * 3,25 / 3,00 / 2,75 dari dokumen 03 tidak dipakai lagi.
     */
    #[Test]
    public function ripk_mengikuti_buku_4(): void
    {
        $k = $this->kalkulator();

        $this->assertSame(4.0, $k->ripk(3.31)->skorTepat);
        $this->assertSame(4, $k->ripk(3.31)->skor);
        $this->assertSame(3.6, $k->ripk(3.0)->skorTepat);
        $this->assertSame(3, $k->ripk(3.0)->skor);
        $this->assertSame(2.8, $k->ripk(2.5)->skorTepat);
        $this->assertSame(2, $k->ripk(2.5)->skor);
        $this->assertSame(2.0, $k->ripk(2.0)->skorTepat);
        $this->assertSame(1.0, $k->ripk(1.9)->skorTepat);
        $this->assertSame(1, $k->ripk(1.9)->skor);
    }

    #[Test]
    public function pgblkl_di_bawah_ambang_tidak_dibulatkan_ke_empat(): void
    {
        // 44/63 = 69,84 < 70 → skor(b) = 2 + (20 × 0,6984)/7 = 3,9954.
        // Dibulatkan akan menjadi 4,00 padahal ambang skor 4 belum
        // tercapai; skor tepat dipotong, bukan dibulatkan.
        $h = $this->kalkulator()->pgblkl(ndgb: 1, ndlk: 3, ndl: 40, ndtps: 63);

        $this->assertSame(69.84, $h->nilai);
        $this->assertSame(3, $h->skor);
        $this->assertSame(3.99, $h->skorTepat);
    }

    #[Test]
    public function ripk_tepat_di_bawah_ambang_tetap_di_bawah_empat(): void
    {
        // (8 × 3,249 − 6)/5 = 3,9984 → dipotong 3,99, bukan dibulatkan 4,00.
        $h = $this->kalkulator()->ripk(3.249);

        $this->assertSame(3.99, $h->skorTepat);
        $this->assertSame(3, $h->skor);
    }

    #[Test]
    public function pds3_mengisi_skor_tepat(): void
    {
        // PDS3 41,67 ≥ 40 → skor(a) penuh.
        $this->assertSame(4.0, $this->kalkulator()->pds3(5, 12, 3, 1)->skorTepat);
    }

    // ---- Penyimpanan -----------------------------------------------------

    #[Test]
    public function hasil_disimpan_tanpa_menimpa_baris_lama(): void
    {
        $this->seed(DatabaseSeeder::class);
        $periode = Periode::firstOrFail();
        $k = $this->kalkulator();

        $k->simpan($k->pds3(nds3: 4, ndtps: 12, ndlk: 3, ndgb: 1), $periode);
        $k->simpan($k->pds3(nds3: 6, ndtps: 12, ndlk: 3, ndgb: 1), $periode);

        // Riwayatnya yang menjawab "kapan PDS3 kita turun di bawah 50?" —
        // pertanyaan yang justru muncul saat ada sengketa.
        $this->assertSame(2, NilaiRumus::where('rumus_kode', 'PDS3')->count());

        // Diurutkan menurut id, bukan dihitung_pada: dua perhitungan di detik
        // yang sama menghasilkan cap waktu identik, dan urutannya menjadi tak
        // pasti. UUIDv7 terurut waktu — justru itu alasan ia dipilih.
        $terakhir = NilaiRumus::where('rumus_kode', 'PDS3')->orderByDesc('id')->first();
        $this->assertSame('50.0000', $terakhir->nilai);
    }

    #[Test]
    public function hasil_disimpan_memuat_skor_tepat(): void
    {
        $this->seed(DatabaseSeeder::class);
        $k = $this->kalkulator();
        $hasil = $k->pds3(5, 12, 3, 1);

        $baris = $k->simpan($hasil, Periode::firstOrFail());

        $this->assertSame('4.00', $baris->fresh()->skor_tepat);
        $this->assertSame($hasil->skorTepat, (float) $baris->fresh()->skor_tepat);
    }

    #[Test]
    public function komponen_disimpan_agar_angkanya_bisa_ditelusuri(): void
    {
        $this->seed(DatabaseSeeder::class);
        $periode = Periode::firstOrFail();
        $k = $this->kalkulator();

        $baris = $k->simpan($k->ppdtps(dosenBerpublikasi: 3, ndtps: 12, jumlahArtikel: 20), $periode);

        // Tanpa komponen, angka 25,00 tidak bisa ditelusuri kembali ke
        // "3 dari 12 DTPS".
        $this->assertSame(3, $baris->komponen['dosenBerpublikasi']);
        $this->assertSame(12, $baris->komponen['ndtps']);
        $this->assertFalse($baris->memenuhi_syarat_5_tahun);
    }

    #[Test]
    public function rumus_tak_dikenal_ditolak_saat_disimpan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tidak ada di tabel rumus/');

        $this->kalkulator()->simpan(
            new HasilRumus(kode: 'RUMUS-PALSU', nilai: 1.0),
            Periode::firstOrFail(),
        );
    }

    #[Test]
    public function aturan_skor_dibaca_dari_tabel_bukan_konstanta(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Perubahan instrumen kelak cukup mengganti data/rumus.json lalu
        // menjalankan seeder — bukan menyunting kode.
        $aturan = $this->kalkulator()->aturanSkor('PDS3');

        $this->assertNotNull($aturan);
        $this->assertStringContainsString('PDS3', $aturan);
    }
}
