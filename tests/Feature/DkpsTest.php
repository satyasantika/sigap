<?php

namespace Tests\Feature;

use App\Enums\AksesTautan;
use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PelaksanaImpor;
use App\Services\VerifikatorDkps;
use App\Support\Impor\ImporDtps;
use App\Support\Impor\ImporKerjaSama;
use App\Support\Impor\ImporPublikasiDtps;
use App\Support\Impor\PratinjauImpor;
use App\Support\Izin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DkpsTest extends TestCase
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

    private function baris(int $noButir = 2, array $ubah = []): DkpsBaris
    {
        return DkpsBaris::create(array_merge([
            'prodi_id' => $this->prodi->id,
            'periode_id' => $this->periode->id,
            'dkps_butir_id' => DkpsButir::where('no', $noButir)->firstOrFail()->id,
            'tahun_acuan' => 'TS',
            'data' => ['jumlah' => 42],
            'sumber' => SumberData::Siakad,
        ], $ubah));
    }

    private function buktiLayak(): Bukti
    {
        $b = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Rekap SIAKAD', 'jenis' => 'berkas',
            'tanggal_kejadian' => now()->subMonth(), 'sumber' => SumberData::Siakad,
            'diunggah_oleh' => User::firstOrFail()->id,
        ]);
        $b->forceFill(['validasi_status' => ValidasiBukti::Sah])->save();

        return $b->refresh();
    }

    // ---- Wewenang: pokja_data bergantung keanggotaan --------------------

    #[Test]
    public function anggota_pokja_data_boleh_mengisi_dkps(): void
    {
        $u = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DATA');

        $this->assertTrue(Izin::boleh($u, 'dkps.isi'));
    }

    #[Test]
    public function koordinator_di_luar_pokja_data_tidak_boleh_mengisi_dkps(): void
    {
        // Lingkup `pokja_data` bergantung keanggotaan, BUKAN peran. Seorang
        // koordinator POKJA-DIK ditolak; anggota biasa POKJA-DATA justru boleh.
        $u = $this->pengguna(PeranPengguna::Koordinator, 'POKJA-DIK');

        $this->assertFalse(Izin::boleh($u, 'dkps.isi'));
    }

    #[Test]
    public function anggota_pokja_data_tidak_boleh_memverifikasi(): void
    {
        // Mengisi dan memverifikasi dipisah: yang mengisi tidak boleh
        // menyatakan isiannya sendiri sudah benar.
        $anggota = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DATA');
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, 'POKJA-DATA');

        $this->assertFalse(Izin::boleh($anggota, 'dkps.verifikasi'));
        $this->assertTrue(Izin::boleh($koordinator, 'dkps.verifikasi'));
    }

    // ---- Verifikasi menuntut bukti terbuka DAN sah ----------------------

    #[Test]
    public function baris_tanpa_bukti_tidak_bisa_diverifikasi(): void
    {
        $baris = $this->baris();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/belum punya bukti tertaut/');

        app(VerifikatorDkps::class)->verifikasi($baris, $this->pengguna(PeranPengguna::Ketua));
    }

    #[Test]
    public function baris_dengan_bukti_tidak_terbuka_tidak_bisa_diverifikasi(): void
    {
        $baris = $this->baris();
        $bukti = $this->buktiLayak();
        $bukti->forceFill([
            'jenis' => 'tautan',
            'url_kanonik' => 'https://drive.google.com/file/d/X/view',
            'akses_status' => AksesTautan::PerluIzin,
        ])->save();
        $baris->bukti()->attach($bukti->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak bisa dibuka tanpa izin/');

        app(VerifikatorDkps::class)->verifikasi($baris->fresh(), $this->pengguna(PeranPengguna::Ketua));
    }

    #[Test]
    public function baris_dengan_bukti_belum_divalidasi_tidak_bisa_diverifikasi(): void
    {
        $baris = $this->baris();
        $bukti = $this->buktiLayak();
        $bukti->forceFill(['validasi_status' => ValidasiBukti::BelumDivalidasi])->save();
        $baris->bukti()->attach($bukti->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/keabsahannya belum divalidasi/');

        app(VerifikatorDkps::class)->verifikasi($baris->fresh(), $this->pengguna(PeranPengguna::Ketua));
    }

    #[Test]
    public function baris_dengan_satu_bukti_layak_bisa_diverifikasi(): void
    {
        $baris = $this->baris();
        $baris->bukti()->attach($this->buktiLayak()->id);

        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $hasil = app(VerifikatorDkps::class)->verifikasi($baris->fresh(), $ketua);

        $this->assertTrue($hasil->terverifikasi());
        $this->assertSame($ketua->id, $hasil->diverifikasi_oleh);
        $this->assertNotNull($hasil->diverifikasi_pada);
    }

    #[Test]
    public function satu_bukti_layak_cukup_meski_ada_yang_bermasalah(): void
    {
        $baris = $this->baris();
        $baris->bukti()->attach($this->buktiLayak()->id);

        $rusak = $this->buktiLayak();
        $rusak->forceFill(['validasi_status' => ValidasiBukti::TidakSah])->save();
        $baris->bukti()->attach($rusak->id);

        $hasil = app(VerifikatorDkps::class)->verifikasi($baris->fresh(), $this->pengguna(PeranPengguna::Ketua));

        $this->assertTrue($hasil->terverifikasi());
    }

    // ---- Penandaan calon selisih ----------------------------------------

    #[Test]
    public function baris_manual_yang_seharusnya_dari_siakad_ditandai(): void
    {
        // Butir 2 (Mahasiswa) ada di SIAKAD; baris manual di sana adalah calon
        // selisih pada asesmen lapangan.
        $manual = $this->baris(2, ['sumber' => SumberData::Manual]);
        $siakad = $this->baris(2, ['sumber' => SumberData::Siakad, 'tahun_acuan' => 'TS-1']);
        // Butir 13 (Penggunaan Dana) memang tidak ada di SIAKAD.
        $wajar = $this->baris(13, ['sumber' => SumberData::Manual]);

        $this->assertTrue($manual->fresh()->calonSelisih());
        $this->assertFalse($siakad->fresh()->calonSelisih());
        $this->assertFalse($wajar->fresh()->calonSelisih());
    }

    // ---- Profil impor DKPS ----------------------------------------------

    #[Test]
    public function impor_dtps_memakai_nidn_sebagai_kunci_duplikasi(): void
    {
        $u = $this->pengguna(PeranPengguna::Ketua);
        $profil = new ImporDtps($this->periode->id, $this->prodi->id, $u->id);

        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => 'TS', 'sumber' => 'siakad', 'nidn' => '0412088001',
                'nama_dosen' => 'Dr. Ahmad Fauzi, M.Pd.'],
            // Ejaan nama berbeda, NIDN sama — orang yang sama.
            ['tahun_acuan' => 'TS', 'sumber' => 'siakad', 'nidn' => '0412-0880-01',
                'nama_dosen' => 'Ahmad Fauzi'],
        ], $profil, $this->periode->id);

        $this->assertSame(PratinjauImpor::BARU, $pratinjau[0]['status']);
        $this->assertSame(PratinjauImpor::DUPLIKAT_TEMPELAN, $pratinjau[1]['status']);
    }

    #[Test]
    public function impor_dtps_menolak_nidn_yang_bukan_sepuluh_digit(): void
    {
        $u = $this->pengguna(PeranPengguna::Ketua);
        $profil = new ImporDtps($this->periode->id, $this->prodi->id, $u->id);

        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => 'TS', 'sumber' => 'siakad', 'nidn' => '12345', 'nama_dosen' => 'A'],
        ], $profil, $this->periode->id);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $this->assertStringContainsString('bukan sepuluh digit', implode(' ', $pratinjau[0]['galat']));
    }

    #[Test]
    public function impor_publikasi_memakai_doi_lebih_dulu(): void
    {
        $u = $this->pengguna(PeranPengguna::Ketua);
        $profil = new ImporPublikasiDtps($this->periode->id, $this->prodi->id, $u->id);

        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => 'TS', 'sumber' => 'manual', 'nama_dosen' => 'Ahmad',
                'judul' => 'Judul A', 'doi' => 'https://doi.org/10.1234/abcd'],
            // DOI sama walau ditempel dengan awalan URL berbeda dan judul beda.
            ['tahun_acuan' => 'TS', 'sumber' => 'manual', 'nama_dosen' => 'Budi',
                'judul' => 'Judul B', 'doi' => '10.1234/abcd'],
        ], $profil, $this->periode->id);

        $this->assertSame(PratinjauImpor::DUPLIKAT_TEMPELAN, $pratinjau[1]['status']);
    }

    #[Test]
    public function impor_publikasi_jatuh_ke_nama_dan_judul_bila_doi_kosong(): void
    {
        $u = $this->pengguna(PeranPengguna::Ketua);
        $profil = new ImporPublikasiDtps($this->periode->id, $this->prodi->id, $u->id);

        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => 'TS', 'sumber' => 'manual', 'nama_dosen' => 'Ahmad',
                'judul' => 'Pengembangan model pembelajaran', 'doi' => ''],
            // Ejaan berbeda (spasi ganda, huruf besar, titik di ujung) tetapi
            // judul dan penulisnya sama.
            ['tahun_acuan' => 'TS', 'sumber' => 'manual', 'nama_dosen' => 'AHMAD',
                'judul' => 'Pengembangan  Model Pembelajaran.', 'doi' => ''],
        ], $profil, $this->periode->id);

        $this->assertSame(PratinjauImpor::DUPLIKAT_TEMPELAN, $pratinjau[1]['status']);
    }

    #[Test]
    public function impor_kerja_sama_memakai_mitra_judul_dan_tahun(): void
    {
        $u = $this->pengguna(PeranPengguna::Ketua);
        $profil = new ImporKerjaSama($this->periode->id, $this->prodi->id, $u->id);

        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => 'TS', 'sumber' => 'manual', 'lembaga_mitra' => 'Dinas Pendidikan',
                'judul' => 'Pendampingan PPL', 'tahun' => '2024', 'tingkat' => 'nasional'],
            // Tahun berbeda: kerja sama yang diperbarui, bukan duplikat.
            ['tahun_acuan' => 'TS', 'sumber' => 'manual', 'lembaga_mitra' => 'Dinas Pendidikan',
                'judul' => 'Pendampingan PPL', 'tahun' => '2025', 'tingkat' => 'nasional'],
        ], $profil, $this->periode->id);

        $this->assertSame(PratinjauImpor::BARU, $pratinjau[0]['status']);
        $this->assertSame(PratinjauImpor::BARU, $pratinjau[1]['status']);
    }

    #[Test]
    public function impor_menolak_tahun_acuan_mutlak(): void
    {
        $u = $this->pengguna(PeranPengguna::Ketua);
        $profil = new ImporDtps($this->periode->id, $this->prodi->id, $u->id);

        // aturan proyek 3: tahun acuan selalu relatif terhadap TS.
        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => '2027', 'sumber' => 'siakad', 'nidn' => '0412088001', 'nama_dosen' => 'A'],
        ], $profil, $this->periode->id);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $this->assertStringContainsString('bukan tahun mutlak', implode(' ', $pratinjau[0]['galat']));
    }

    #[Test]
    public function impor_dkps_tersimpan_ke_kolom_json_dan_bisa_dibatalkan(): void
    {
        $u = $this->pengguna(PeranPengguna::Ketua);
        $profil = new ImporDtps($this->periode->id, $this->prodi->id, $u->id);

        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => 'TS', 'sumber' => 'siakad', 'nidn' => '0412088001',
                'nama_dosen' => 'Ahmad Fauzi', 'pendidikan' => 'S3', 'jabatan_akademik' => 'Lektor Kepala'],
        ], $profil, $this->periode->id);

        $batch = app(PelaksanaImpor::class)->jalankan($pratinjau, [], $profil, $this->periode, $u);

        $baris = DkpsBaris::where('impor_batch_id', $batch->id)->firstOrFail();

        // Mendarat di butir 6, bukan di tabel dosen tersendiri — keputusan yang
        // diambil karena tabel dosen sengaja belum dibuat.
        $this->assertSame(6, $baris->butir->no);
        $this->assertSame('0412088001', $baris->nilai('nidn'));
        $this->assertSame('S3', $baris->nilai('pendidikan'));

        app(PelaksanaImpor::class)->batalkan($batch, $profil, $u);

        $this->assertSame(0, DkpsBaris::where('impor_batch_id', $batch->id)->count());
    }
}
