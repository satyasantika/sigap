<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
use App\Enums\SumberData;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PenghitungRumusDkps;
use App\Services\PengisiDkps;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Jembatan katalog variabel → KalkulatorRumus → nilai_rumus. Fixture ditulis
 * lewat PengisiDkps seperti PembacaVariabelDkpsTest. TS 2027.
 */
class PenghitungRumusDkpsTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private PengisiDkps $pengisi;

    private PenghitungRumusDkps $penghitung;

    /** @var array<string, User> koordinator per kode pokja */
    private array $koordinator = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->periode->forceFill(['ts_tahun' => 2027])->save();
        $this->prodi = Prodi::firstOrFail();
        $this->pengisi = app(PengisiDkps::class);
        $this->penghitung = app(PenghitungRumusDkps::class);
    }

    private function pengguna(PeranPengguna $peran, ?string $kodePokja = null): User
    {
        $u = User::create([
            'name' => 'u', 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@dkps.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    /** Menulis satu baris lewat PengisiDkps oleh koordinator pokja pemilik tabel. */
    private function isi(string $kodeLembar, array $data, ?string $kunciBaris = null): DkpsBaris
    {
        $butir = DkpsButir::dariKode(explode('.', $kodeLembar)[0]);
        $pokja = $butir->pokja_kode;
        $this->koordinator[$pokja] ??= $this->pengguna(PeranPengguna::Koordinator, $pokja);

        return $this->pengisi->simpan($butir, $kodeLembar, $this->periode, $data,
            SumberData::Manual, $this->koordinator[$pokja], kunciBaris: $kunciBaris);
    }

    private function kerjaSama(string $lembar, string $mitra, string $tingkat): void
    {
        $this->isi($lembar, [
            'lembaga_mitra' => $mitra, $tingkat => 'V', 'judul_kegiatan' => 'Kegiatan '.$mitra,
            'tanggal_awal' => '01/02/2026', 'tanggal_akhir' => '01/02/2028',
        ]);
    }

    private function dosen(string $lembar, string $nama, string $nidn, string $jabatan, ?string $doktor = null): void
    {
        $data = ['nidn_nidk' => $nidn, 'jabatan_akademik' => $jabatan, 'doktor' => $doktor];

        $this->isi($lembar, $lembar === '6'
            ? $data + ['nama_dtps' => $nama]
            : $data + ['nama_dosen' => $nama, 'nama_bidang_studi' => 'Matematika']);
    }

    /** Tabel 1 ketiga lembar terisi. */
    private function tabel1(): void
    {
        $this->kerjaSama('1.1', 'UniSZA', 'internasional');
        $this->kerjaSama('1.1', 'Dinas Pendidikan Jabar', 'nasional');
        $this->kerjaSama('1.2', 'BRIN', 'nasional');
        $this->kerjaSama('1.3', 'SMAN 1 Tasikmalaya', 'wilayah_lokal');
    }

    /** Tiga DTPS unik dari Tabel 6 dan 7. */
    private function tigaDosen(): void
    {
        $this->dosen('6', 'Dr. Ani', '0401010101', 'Lektor', 'Universitas Pendidikan Indonesia');
        $this->dosen('6', 'Dr. Budi', '0402020202', 'Lektor Kepala', 'Universitas Pendidikan Indonesia');
        $this->dosen('7', 'Prof. Citra', '0403030303', 'Guru Besar');
    }

    private function nilaiTerakhir(string $kode): NilaiRumus
    {
        return NilaiRumus::where('periode_id', $this->periode->id)->where('rumus_kode', $kode)
            ->orderByDesc('dihitung_pada')->orderByDesc('id')->firstOrFail();
    }

    #[Test]
    public function menghitung_rk_dari_tabel_1_dan_menyimpan_variabel(): void
    {
        $this->tabel1();
        $this->tigaDosen();

        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertArrayHasKey('RK', $laporan->disimpan);
        $terakhir = $this->nilaiTerakhir('RK');
        $this->assertSame($laporan->disimpan['RK']->id, $terakhir->id);

        $variabel = collect($terakhir->komponen['variabel'])->keyBy('kode');
        $this->assertSame(['1.1'], $variabel['N_KERJASAMA_PENDIDIKAN']['lembar']);
        $this->assertEquals(2, $variabel['N_KERJASAMA_PENDIDIKAN']['nilai']);
        $this->assertEquals(3, $variabel['N_DTPS']['nilai']);
        $this->assertSame(2, $variabel['N_KERJASAMA_PENDIDIKAN']['jumlah_baris']);
        $this->assertArrayHasKey('belum_terverifikasi', $variabel['N_KERJASAMA_PENDIDIKAN']);
        $this->assertCount(7, $variabel);
        $this->assertNotNull($terakhir->skor_tepat);

        // Rumus lain yang hanya butuh Tabel 6/7 ikut terhitung.
        $this->assertArrayHasKey('PDS3', $laporan->disimpan);
        $this->assertArrayHasKey('PGBLKL', $laporan->disimpan);
    }

    #[Test]
    public function rumus_dengan_variabel_kosong_dilewati_dengan_alasan(): void
    {
        $this->tabel1();
        $this->tigaDosen();

        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertArrayHasKey('PRESMA', $laporan->dilewati);
        $this->assertStringContainsString('N_MHS_PPG', $laporan->dilewati['PRESMA']);
        $this->assertStringContainsString('Tabel 2', $laporan->dilewati['PRESMA']);
        $this->assertArrayNotHasKey('PRESMA', $laporan->disimpan);
        $this->assertSame(0, NilaiRumus::where('rumus_kode', 'PRESMA')->count());
    }

    #[Test]
    public function hasil_identik_tidak_disimpan_dua_kali(): void
    {
        $this->tabel1();
        $this->tigaDosen();

        $this->penghitung->hitungSemua($this->periode);
        $kedua = $this->penghitung->hitungSemua($this->periode);

        $this->assertSame(1, NilaiRumus::where('rumus_kode', 'RK')->count());
        $this->assertSame('sama dengan hasil terakhir', $kedua->tidakBerubah['RK']);
        $this->assertSame([], $kedua->disimpan);
        $this->assertStringStartsWith('Disimpan 0, tidak berubah '.count($kedua->tidakBerubah), $kedua->ringkasan());
    }

    #[Test]
    public function perubahan_data_menghasilkan_baris_baru(): void
    {
        $this->tabel1();
        $this->tigaDosen();
        $this->penghitung->hitungSemua($this->periode);

        $this->kerjaSama('1.1', 'Universitas Negeri Malang', 'nasional');
        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertSame(2, NilaiRumus::where('rumus_kode', 'RK')->count());
        $this->assertArrayHasKey('RK', $laporan->disimpan);
        $this->assertArrayHasKey('PDS3', $laporan->tidakBerubah);
    }

    #[Test]
    public function periode_terkunci_ditolak(): void
    {
        $this->tabel1();
        $this->tigaDosen();
        $this->periode->forceFill(['status' => StatusPeriode::Dikunci])->save();
        $sebelum = NilaiRumus::count();

        try {
            $this->penghitung->hitungSemua($this->periode->refresh());
            $this->fail('Periode terkunci seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertSame('Periode terkunci; rumus tidak dihitung ulang.', $e->getMessage());
        }

        $this->assertSame($sebelum, NilaiRumus::count());
    }

    #[Test]
    public function pembagi_nol_dilaporkan_bukan_dilempar(): void
    {
        $this->tabel1();

        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertArrayHasKey('RK', $laporan->dilewati);
        $this->assertStringContainsString('N_DTPS', $laporan->dilewati['RK']);
        $this->assertSame(0, NilaiRumus::where('rumus_kode', 'RK')->count());

        // Pembagi nol sungguhan: Tabel 23 terisi tetapi lulusan tiga tahun 0 —
        // InvalidArgumentException kalkulator menjadi alasan lewat.
        $this->isi('23', ['jumlah_lulusan' => 0, 'terlacak' => 0, 'wt_kurang_6' => 0], 'TS-2');
        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertSame('Jumlah lulusan tiga tahun nol; aturan responden tidak bisa diterapkan.', $laporan->dilewati['WTMP']);
    }

    #[Test]
    public function sel_kosong_pada_baris_yang_ada_melewatkan_rumus_dengan_alasan(): void
    {
        $this->isi('21', ['jumlah_lulusan' => 100, 'ipk_min' => 3.0, 'ipk_rata' => 3.4, 'ipk_maks' => 3.9], 'TS-2');
        $this->isi('21', ['jumlah_lulusan' => 110, 'ipk_min' => 3.1, 'ipk_rata' => 3.5, 'ipk_maks' => 3.9], 'TS-1');
        $this->isi('21', ['jumlah_lulusan' => 120, 'ipk_min' => 3.0, 'ipk_maks' => 4.0], 'TS');

        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertArrayNotHasKey('RIPK', $laporan->disimpan);
        $this->assertStringContainsString('RT_IPK_TS', $laporan->dilewati['RIPK']);
        $this->assertStringContainsString('Tabel 21 baris TS:', $laporan->dilewati['RIPK']);
        $this->assertSame(0, NilaiRumus::where('rumus_kode', 'RIPK')->count());
    }

    #[Test]
    public function na_tidak_dihitung_jembatan(): void
    {
        $this->tabel1();
        $this->tigaDosen();

        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertArrayNotHasKey('NA', $laporan->disimpan);
        $this->assertArrayNotHasKey('NA', $laporan->dilewati);
        $this->assertArrayNotHasKey('NA', $laporan->tidakBerubah);
        $this->assertSame(27, count($laporan->disimpan) + count($laporan->dilewati) + count($laporan->tidakBerubah));
        $this->assertSame(0, NilaiRumus::where('rumus_kode', 'NA')->count());
    }

    #[Test]
    public function rms_disimpan_tanpa_skor(): void
    {
        $this->isi('22', ['diterima' => 30, 'lulus_akhir_ts1' => 10, 'lulus_akhir_ts' => 10], 'TS-1');
        $this->isi('22', ['diterima' => 25, 'lulus_akhir_ts' => 20], 'TS');

        $laporan = $this->penghitung->hitungSemua($this->periode);

        $rms = $laporan->disimpan['RMS'];
        $this->assertNull($rms->skor);
        $this->assertNull($rms->skor_tepat);
        $this->assertNotNull($rms->nilai);
        $this->assertEqualsWithDelta(1.25, (float) $rms->nilai, 1e-4);
    }

    #[Test]
    public function ringkasan_menghitung_ketiga_daftar(): void
    {
        $this->tabel1();
        $this->tigaDosen();

        $laporan = $this->penghitung->hitungSemua($this->periode);

        $this->assertSame(sprintf('Disimpan %d, tidak berubah 0, dilewati %d',
            count($laporan->disimpan), count($laporan->dilewati)), $laporan->ringkasan());
    }
}
