<?php

namespace Tests\Feature;

use App\Exceptions\TahunDiLuarJendela;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Support\Impor\NormalisasiKunci;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Skema lembar dibaca dari data/dkps-tabel.json yang sudah diseed; TS 2027.
 */
class SkemaLembarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Periode::firstOrFail()->forceFill(['ts_tahun' => 2027])->save();
    }

    #[Test]
    public function kepala_tabel_8_berkedalaman_tiga_baris(): void
    {
        $k = DkpsButir::dariKode('8')->skema('8')->kepala();
        $this->assertCount(3, $k);
        $this->assertSame(['label' => 'Nama DTPS', 'colspan' => 1, 'rowspan' => 3], $k[0][1]);  // indeks 0 = kolom "No."
        $this->assertSame(6, $k[0][2]['colspan']);   // "Beban Kerja Mengajar …" membentang C:H
        $this->assertSame('Pendidikan: Pembelajaran dan Pembimbingan', $k[1][0]['label']);
        $this->assertSame(3, $k[1][0]['colspan']);
    }

    #[Test]
    public function tanggal_dinormalkan_dari_tiga_bentuk(): void
    {
        $s = DkpsButir::dariKode('3')->skema('3');
        foreach (['20/06/2026', '2026-06-20', '2026-06-20 00:00:00'] as $masukan) {
            $this->assertSame('2026-06-20', $s->normalkan(['tanggal_perolehan' => $masukan])['tanggal_perolehan']);
        }
        $this->assertArrayHasKey('tanggal_perolehan', $s->validasi($s->normalkan(['tanggal_perolehan' => 'Juni 2026'])));
    }

    #[Test]
    public function jumlah_persen_harus_100(): void
    {
        $s = DkpsButir::dariKode('5')->skema('5');
        $galat = $s->validasi($s->normalkan(['sangat_baik' => '94,32', 'baik' => '5.68', 'cukup' => 0, 'kurang_baik' => 1], 'reliability'), 'reliability');
        $this->assertSame('Jumlah persentase harus 100 (sekarang 101).', $galat['_baris']);
        $this->assertSame([], $s->validasi($s->normalkan(['sangat_baik' => '94,32', 'baik' => '5.68', 'cukup' => 0, 'kurang_baik' => 0], 'reliability'), 'reliability'));
    }

    #[Test]
    public function tepat_satu_tingkat_pada_kerja_sama(): void
    {
        $s = DkpsButir::dariKode('1')->skema('1.2');
        $d = $s->normalkan(['lembaga_mitra' => 'UiTM', 'judul_kegiatan' => 'Riset', 'tanggal_awal' => '19/03/2023', 'nasional' => 'V', 'internasional' => 'V']);
        $this->assertSame('Pilih tepat satu: Wilayah/Lokal, Nasional, Internasional.', $s->validasi($d)['_baris']);
        $this->assertSame(5, $s->hitung($d + ['tanggal_akhir' => '2028-03-19'])['durasi_tahun']);
    }

    #[Test]
    public function kolom_nonaktif_tabel_22_dan_rata_masa_studi(): void
    {
        $s = DkpsButir::dariKode('22')->skema('22');
        $this->assertSame(['lulus_akhir_ts2', 'lulus_akhir_ts1'], $s->kolomNonaktif('TS'));
        $d = $s->normalkan(['diterima' => 100, 'lulus_akhir_ts2' => 60, 'lulus_akhir_ts1' => 30, 'lulus_akhir_ts' => 10], 'TS-2');
        $this->assertSame(100, $s->hitung($d, 'TS-2')['jumlah_lulusan']);
        $this->assertSame(1.5, $s->hitung($d, 'TS-2')['rata_masa_studi']);   // (60·1 + 30·2 + 10·3)/100
        $this->assertSame('TS-2', $s->tahunAcuan(Periode::firstOrFail(), $d, 'TS-2'));
    }

    #[Test]
    public function tahun_acuan_dari_kolom_dan_penolakan_di_luar_jendela(): void
    {
        $p = Periode::firstOrFail();
        $s = DkpsButir::dariKode('9')->skema('9');
        $this->assertSame('TS-1', $s->tahunAcuan($p, ['tahun' => 2026]));
        $this->expectException(TahunDiLuarJendela::class);
        $this->expectExceptionMessage('Tahun 2019 di luar jendela data TS-2 s.d. TS (2025–2027).');
        $s->tahunAcuan($p, ['tahun' => 2019]);
    }

    #[Test]
    public function kunci_baris_dan_kelengkapan(): void
    {
        $s = DkpsButir::dariKode('28')->skema('28');
        $d = $s->normalkan(['nama_dtps' => ' Prof. Firmansyah ', 'judul_artikel' => 'Judul', 'jumlah_sitasi' => '65']);
        $this->assertSame(NormalisasiKunci::dari('28', '28', 'Prof. Firmansyah', 'Judul'), $s->kunciBaris($d));
        $this->assertTrue($s->lengkap($d));
        $this->assertFalse($s->lengkap($s->normalkan(['nama_dtps' => 'X'])));
    }
}
