<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Exceptions\IsianDkpsTidakSah;
use App\Exceptions\TahunDiLuarJendela;
use App\Models\Bukti;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengisiDkps;
use App\Services\VerifikatorDkps;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Layanan tulis baris DKPS di atas skema lembar; TS 2027.
 */
class PengisiDkpsTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private PengisiDkps $pengisi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->periode->forceFill(['ts_tahun' => 2027])->save();
        $this->prodi = Prodi::firstOrFail();
        $this->pengisi = app(PengisiDkps::class);
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

    private function buktiLayak(): Bukti
    {
        $b = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Rekap sitasi', 'jenis' => 'berkas',
            'tanggal_kejadian' => now()->subMonth(), 'sumber' => SumberData::Manual,
            'diunggah_oleh' => User::firstOrFail()->id,
        ]);
        $b->forceFill(['validasi_status' => ValidasiBukti::Sah])->save();

        return $b->refresh();
    }

    /** Baris Tabel 28 (POKJA-SDM) yang sudah bertaut bukti layak dan diverifikasi ketua. */
    private function barisTerverifikasi(): DkpsBaris
    {
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $baris = $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode,
            ['nama_dtps' => 'Dr. Ani', 'judul_artikel' => 'Jurnal X, 3, 2026, 1, 1-10', 'jumlah_sitasi' => 12],
            SumberData::Manual, $ketua);
        $baris->bukti()->attach($this->buktiLayak()->id);

        $baris = app(VerifikatorDkps::class)->verifikasi($baris, $ketua);
        $this->assertTrue($baris->terverifikasi());

        return $baris;
    }

    #[Test]
    public function menyimpan_baris_bebas_mengisi_tahun_kunci_dan_pokja(): void
    {
        $b = $this->pengisi->simpan(DkpsButir::dariKode('9'), '9', $this->periode,
            ['nama_dtps' => 'Dr. Ani', 'deskripsi_rekognisi' => 'Reviewer', 'jenis_rekognisi' => 'Editor atau mitra bestari', 'tahun' => '2026', 'tingkat' => 'Nasional'],
            SumberData::Manual, $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'));
        $this->assertSame('TS-1', $b->tahun_acuan);
        $this->assertSame('9', $b->lembar_kode);
        $this->assertSame(Pokja::where('kode', 'POKJA-SDM')->firstOrFail()->id, $b->pokja_id);
        $this->assertSame(2026, $b->nilai('tahun'));
        $this->assertNotNull($b->kunci_normal);
    }

    #[Test]
    public function baris_dengan_tahun_di_luar_jendela_ditolak(): void
    {
        $this->expectException(TahunDiLuarJendela::class);
        $this->pengisi->simpan(DkpsButir::dariKode('3'), '3', $this->periode,
            ['nama_kegiatan' => 'LKTI', 'tanggal_perolehan' => '01/05/2019', 'nasional' => 'V', 'prestasi_dicapai' => 'Juara 1'],
            SumberData::Manual, $this->pengguna(PeranPengguna::Ketua));
    }

    #[Test]
    public function kerja_sama_lama_tetap_diterima(): void
    {
        $b = $this->pengisi->simpan(DkpsButir::dariKode('1'), '1.1', $this->periode,
            ['lembaga_mitra' => 'UniSZA', 'internasional' => 'V', 'judul_kegiatan' => 'Seminar', 'tanggal_awal' => '25/07/2019', 'tanggal_akhir' => '25/07/2028'],
            SumberData::Manual, $this->pengguna(PeranPengguna::Ketua));
        $this->assertSame('TS', $b->tahun_acuan);
    }

    #[Test]
    public function isian_tidak_sah_melempar_galat_per_kolom(): void
    {
        try {
            $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode, ['nama_dtps' => '', 'jumlah_sitasi' => -1], SumberData::Manual, $this->pengguna(PeranPengguna::Ketua));
            $this->fail();
        } catch (IsianDkpsTidakSah $e) {
            $this->assertSame(['nama_dtps', 'judul_artikel', 'jumlah_sitasi'], array_keys($e->galat));
        }
    }

    #[Test]
    public function anggota_pokja_lain_ditolak(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->pengisi->simpan(DkpsButir::dariKode('18'), '18', $this->periode, ['kode_mk' => 'PG1', 'nama_mk' => 'X'], SumberData::Manual, $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'));
    }

    #[Test]
    public function baris_tetap_diperbarui_bukan_digandakan(): void
    {
        $butir = DkpsButir::dariKode('21');
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $this->pengisi->simpan($butir, '21', $this->periode, ['jumlah_lulusan' => 100, 'ipk_rata' => '3,8'], SumberData::Siakad, $ketua, null, 'TS-2');
        $this->pengisi->simpan($butir, '21', $this->periode, ['jumlah_lulusan' => 104, 'ipk_rata' => '3,84'], SumberData::Siakad, $ketua, null, 'TS-2');
        $this->assertSame(1, DkpsBaris::where('lembar_kode', '21')->count());
        $this->assertSame(104, DkpsBaris::where('lembar_kode', '21')->first()->nilai('jumlah_lulusan'));
        $this->assertSame('TS-2', DkpsBaris::where('lembar_kode', '21')->first()->tahun_acuan);
    }

    #[Test]
    public function sunting_baris_terverifikasi_mencabut_verifikasi(): void
    {
        $baris = $this->barisTerverifikasi();
        $butir = DkpsButir::dariKode('28');
        $dataBaru = ['nama_dtps' => 'Dr. Ani', 'judul_artikel' => 'Jurnal X, 3, 2026, 1, 1-10', 'jumlah_sitasi' => 15];

        try {
            $this->pengisi->simpan($butir, '28', $this->periode, $dataBaru, SumberData::Manual,
                $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'), $baris);
            $this->fail('Anggota tidak boleh menyunting baris terverifikasi.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Baris sudah diverifikasi; hanya verifikator yang boleh mengubahnya.', $e->getMessage());
        }
        $this->assertTrue($baris->fresh()->terverifikasi());
        $this->assertSame(12, $baris->fresh()->nilai('jumlah_sitasi'));

        $hasil = $this->pengisi->simpan($butir, '28', $this->periode, $dataBaru, SumberData::Manual,
            $this->pengguna(PeranPengguna::Koordinator, 'POKJA-SDM'), $baris);

        $this->assertSame($baris->id, $hasil->id);
        $this->assertSame(15, $baris->fresh()->nilai('jumlah_sitasi'));
        $this->assertFalse($baris->fresh()->terverifikasi());
    }

    #[Test]
    public function hapus_baris_terverifikasi_ditolak_dan_tautkan_bukti_menautkan(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $baris = $this->barisTerverifikasi();
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, 'POKJA-SDM');

        try {
            $this->pengisi->hapus($baris, $koordinator);
            $this->fail('Baris terverifikasi tidak boleh dihapus.');
        } catch (RuntimeException $e) {
            $this->assertSame('Baris terverifikasi tidak bisa dihapus; cabut verifikasinya lebih dulu.', $e->getMessage());
        }
        $this->assertNotNull(DkpsBaris::find($baris->id));

        $lain = $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode,
            ['nama_dtps' => 'Dr. Budi', 'judul_artikel' => 'Jurnal Y, 1, 2025, 2, 5-9', 'jumlah_sitasi' => 3],
            SumberData::Manual, $koordinator);

        $bukti = $this->pengisi->tautkanBuktiTautan($lain, 'https://drive.google.com/file/d/XYZ/view', [
            'judul' => 'Tangkapan Google Scholar', 'tanggal_kejadian' => now()->subMonth()->toDateString(),
            'sumber' => SumberData::Manual,
        ], $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'));

        $this->assertSame(1, $lain->bukti()->count());
        $this->assertSame($bukti->id, $lain->bukti()->first()->id);
        $this->assertSame($lain->periode_id, $bukti->periode_id);
        $this->assertSame($lain->prodi_id, $bukti->prodi_id);

        $this->pengisi->hapus($lain, $koordinator);
        $this->assertNull(DkpsBaris::find($lain->id));
        $this->assertNotNull(DkpsBaris::withTrashed()->find($lain->id));
    }
}
