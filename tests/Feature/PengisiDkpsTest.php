<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
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

        // Kunci baris tetap yang asing menjadi galat isian, bukan InvalidArgumentException.
        try {
            $this->pengisi->simpan($butir, '21', $this->periode, ['jumlah_lulusan' => 1, 'ipk_rata' => '3'], SumberData::Siakad, $ketua, null, 'TS-9');
            $this->fail('Kunci baris asing harus ditolak.');
        } catch (IsianDkpsTidakSah $e) {
            $this->assertSame(['_baris'], array_keys($e->galat));
        }
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
            // Periode asing dari pemanggil tidak boleh menang atas periode baris.
            'periode_id' => Periode::create([
                'prodi_id' => $this->prodi->id, 'nama' => 'Periode lain', 'ts_tahun' => 2027,
                'versi_instrumen' => 'IAPSK 3.0',
            ])->id,
        ], $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'));

        $this->assertSame(1, $lain->bukti()->count());
        $this->assertSame($bukti->id, $lain->bukti()->first()->id);
        $this->assertSame($lain->periode_id, $bukti->periode_id);
        $this->assertSame($lain->prodi_id, $bukti->prodi_id);

        $this->pengisi->hapus($lain, $koordinator);
        $this->assertNull(DkpsBaris::find($lain->id));
        $this->assertNotNull(DkpsBaris::withTrashed()->find($lain->id));
    }

    #[Test]
    public function periode_terkunci_menolak_baris_baru_dan_tautan_bukti(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $data = ['nama_dtps' => 'Dr. Ani', 'judul_artikel' => 'Jurnal X, 3, 2026, 1, 1-10', 'jumlah_sitasi' => 12];
        $baris = $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode, $data, SumberData::Manual, $ketua);

        $this->periode->forceFill(['status' => StatusPeriode::Dikunci])->save();

        try {
            $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode,
                ['jumlah_sitasi' => 1, 'nama_dtps' => 'Dr. Budi', 'judul_artikel' => 'Jurnal Y'], SumberData::Manual, $ketua);
            $this->fail('Baris baru di periode terkunci harus ditolak.');
        } catch (AuthorizationException) {
            $this->assertSame(1, DkpsBaris::where('lembar_kode', '28')->count());
        }

        $this->expectException(AuthorizationException::class);
        $this->pengisi->tautkanBuktiTautan($baris, 'https://drive.google.com/file/d/XYZ/view', [
            'judul' => 'Tangkapan Google Scholar', 'tanggal_kejadian' => now()->subMonth()->toDateString(),
            'sumber' => SumberData::Manual,
        ], $ketua);
    }

    #[Test]
    public function baris_dari_lembar_lain_ditolak(): void
    {
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $baris = $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode,
            ['nama_dtps' => 'Dr. Ani', 'judul_artikel' => 'Jurnal X, 3, 2026, 1, 1-10', 'jumlah_sitasi' => 12], SumberData::Manual, $ketua);

        try {
            $this->pengisi->simpan(DkpsButir::dariKode('9'), '9', $this->periode,
                ['nama_dtps' => 'Dr. Ani', 'deskripsi_rekognisi' => 'Reviewer', 'jenis_rekognisi' => 'Editor atau mitra bestari', 'tahun' => '2026', 'tingkat' => 'Nasional'],
                SumberData::Manual, $ketua, $baris);
            $this->fail('Baris lembar lain tidak boleh dipindahkan.');
        } catch (IsianDkpsTidakSah $e) {
            $this->assertSame(['_baris' => 'Baris tidak termasuk lembar ini.'], $e->galat);
        }

        $this->assertSame('28', $baris->fresh()->lembar_kode);
    }

    #[Test]
    public function panel_verifikasi_tidak_punya_halaman_buat_dan_sunting(): void
    {
        // Halaman buat/sunting bawaan Filament dulu jalan tulis kedua yang
        // melewati PengisiDkps: policy `create` tanpa obyek lolos bagi
        // koordinator mana pun, lalu kait `creating` menstempel pokja
        // pemilik tabel lain. Kini satu-satunya jalan tulis adalah grid.
        $baris = $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode,
            ['nama_dtps' => 'Dr. Ani', 'judul_artikel' => 'Jurnal X, 3, 2026, 1, 1-10', 'jumlah_sitasi' => 12],
            SumberData::Manual, $this->pengguna(PeranPengguna::Ketua));

        foreach ([$this->pengguna(PeranPengguna::Koordinator, 'POKJA-DIK'), $this->pengguna(PeranPengguna::Ketua)] as $u) {
            // Sesi dikosongkan antarpengguna: AuthenticateSession Filament
            // mengeluarkan pengguna yang hash sandinya beda dari pemilik sesi.
            $this->flushSession();
            $this->actingAs($u)->get('/panel/dkps-baris/create')->assertNotFound();
            $this->actingAs($u)->get('/panel/dkps-baris/'.$baris->id.'/edit')->assertNotFound();
        }

        $this->flushSession();
        $this->actingAs($this->pengguna(PeranPengguna::Ketua))
            ->get('/panel/dkps-baris/'.$baris->id)->assertOk()
            ->assertDontSee('/panel/dkps-baris/'.$baris->id.'/edit');
    }
}
