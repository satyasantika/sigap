<?php

namespace Tests\Feature;

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
use App\Models\VariabelDkps;
use App\Services\PembacaVariabelDkps;
use App\Services\PengisiDkps;
use App\Services\VerifikatorDkps;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Katalog variabel menjadi angka: setiap fixture ditulis lewat PengisiDkps,
 * jalur yang sama dengan formulir, supaya pembaca diuji terhadap bentuk data
 * yang sungguh tersimpan (data hanya kolom isian; kolom hitung dari skema).
 * TS 2027.
 */
class PembacaVariabelDkpsTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private PengisiDkps $pengisi;

    private PembacaVariabelDkps $pembaca;

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
        $this->pembaca = app(PembacaVariabelDkps::class);
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

    private function nilai(string $kode): ?float
    {
        return $this->pembaca->satu($this->periode, $kode)->nilai;
    }

    private function buktiLayak(): Bukti
    {
        $b = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'SK rekognisi', 'jenis' => 'berkas',
            'tanggal_kejadian' => now()->subMonth(), 'sumber' => SumberData::Manual,
            'diunggah_oleh' => User::firstOrFail()->id,
        ]);
        $b->forceFill(['validasi_status' => ValidasiBukti::Sah])->save();

        return $b->refresh();
    }

    private function kerjaSama(string $lembar, string $mitra, string $tingkat): void
    {
        $this->isi($lembar, [
            'lembaga_mitra' => $mitra, $tingkat => 'V', 'judul_kegiatan' => 'Kegiatan '.$mitra,
            'tanggal_awal' => '01/02/2026', 'tanggal_akhir' => '01/02/2028',
        ]);
    }

    private function dosen(string $lembar, string $nama, string $nidn, string $jabatan, ?string $doktor = null, string $bidang = 'Matematika'): DkpsBaris
    {
        $data = ['nidn_nidk' => $nidn, 'jabatan_akademik' => $jabatan, 'doktor' => $doktor];

        return $this->isi($lembar, $lembar === '6'
            ? $data + ['nama_dtps' => $nama]
            : $data + ['nama_dosen' => $nama, 'nama_bidang_studi' => $bidang]);
    }

    #[Test]
    public function lembar_kosong_memberi_null(): void
    {
        $hasil = $this->pembaca->satu($this->periode, 'N_KERJASAMA_PENDIDIKAN');

        $this->assertNull($hasil->nilai);
        $this->assertSame(['1.1'], $hasil->lembar);
        $this->assertSame(0, $hasil->jumlahBaris);
        $this->assertStringContainsString('1.1', $hasil->catatan);
    }

    #[Test]
    public function cacah_baris_dan_centang_tabel_1(): void
    {
        $this->kerjaSama('1.1', 'UniSZA', 'internasional');
        $this->kerjaSama('1.1', 'Dinas Pendidikan Jabar', 'nasional');
        $this->kerjaSama('1.2', 'BRIN', 'nasional');

        $this->assertSame(2.0, $this->nilai('N_KERJASAMA_PENDIDIKAN'));
        $this->assertSame(1.0, $this->nilai('N_KERJASAMA_PENELITIAN'));
        $this->assertSame(1.0, $this->nilai('N_KERJASAMA_INTERNASIONAL'));
        $this->assertSame(2.0, $this->nilai('N_KERJASAMA_NASIONAL'));
        $this->assertSame(0.0, $this->nilai('N_KERJASAMA_WILAYAH'));
        $this->assertNull($this->nilai('N_KERJASAMA_PKM'));

        $ni = $this->pembaca->satu($this->periode, 'N_KERJASAMA_INTERNASIONAL');
        $this->assertSame(['1.1', '1.2', '1.3'], $ni->lembar);
        $this->assertSame(3, $ni->jumlahBaris);
        $this->assertNull($ni->catatan);
    }

    #[Test]
    public function lembar_terisi_tanpa_centang_memberi_nol_bukan_null(): void
    {
        // Aturan `tepat_satu` Tabel 3 mewajibkan satu tingkat; baris ini
        // nasional, jadi kolom internasional tidak tercentang.
        $this->isi('3', ['nama_kegiatan' => 'LKTI', 'tanggal_perolehan' => '01/05/2026', 'nasional' => 'V', 'prestasi_dicapai' => 'Juara 1']);

        $this->assertSame(0.0, $this->nilai('N_PRESTASI_INTERNASIONAL'));
        $this->assertSame(1.0, $this->nilai('N_PRESTASI_NASIONAL'));
    }

    #[Test]
    public function nilai_sel_dan_jumlah_sel_tabel_2(): void
    {
        $this->isi('2', ['aktif_calon_guru' => 173, 'aktif_guru_tertentu' => 7348], 'TS');
        $this->isi('2', ['aktif_calon_guru' => 99, 'aktif_guru_tertentu' => 1], 'TS-1');

        $hasil = $this->pembaca->satu($this->periode, 'N_MHS_PPG');
        $this->assertSame(7521.0, $hasil->nilai);
        $this->assertSame(1, $hasil->jumlahBaris);
    }

    #[Test]
    public function sel_baris_tetap_yang_hilang_dibaca_nol_bila_lembar_terisi(): void
    {
        $this->isi('23', ['jumlah_lulusan' => 104, 'terlacak' => 87, 'wt_kurang_6' => 87], 'TS-2');

        $this->assertSame(104.0, $this->nilai('N_LULUSAN_3TH'));
        $this->assertSame(87.0, $this->nilai('N_LULUSAN_WT_6BLN'));
        $this->assertSame(0.0, $this->nilai('N_LULUSAN_WT_12BLN'));

        $kosong = $this->pembaca->satu($this->periode, 'N_TKP_TERLACAK_3TH');
        $this->assertNull($kosong->nilai);
        $this->assertStringContainsString('24.2', $kosong->catatan);
    }

    #[Test]
    public function ndtps_menghitung_nidn_unik_gabungan_tabel_6_dan_7(): void
    {
        $this->dosen('6', 'Dr. Ani', '0401010101', 'Lektor');
        $this->dosen('6', 'Dr. Budi', '0402020202', 'Lektor Kepala', 'Universitas Pendidikan Indonesia');
        $this->dosen('7', 'Dr. Boedi', ' 0402020202 ', 'Lektor Kepala');
        $this->dosen('7', 'Prof. Citra', '0403030303', 'Guru Besar');

        $this->assertSame(3.0, $this->nilai('N_DTPS'));
        $this->assertSame(1.0, $this->nilai('N_DTPS_DOKTOR'));
        $this->assertSame(1.0, $this->nilai('N_DTPS_LK'));
        $this->assertSame(1.0, $this->nilai('N_DTPS_GB'));
        $this->assertSame(1.0, $this->nilai('N_DTPS_LEKTOR'));
        $this->assertSame(4, $this->pembaca->satu($this->periode, 'N_DTPS')->jumlahBaris);
    }

    #[Test]
    public function jabatan_berbeda_antarlembar_mengambil_tabel_7(): void
    {
        $this->dosen('6', 'Dr. Ani', '0401010101', 'Lektor');
        $this->dosen('7', 'Dr. Ani', '0401010101', 'Lektor Kepala');

        $this->assertSame(1.0, $this->nilai('N_DTPS'));
        $this->assertSame(0.0, $this->nilai('N_DTPS_LEKTOR'));
        $this->assertSame(1.0, $this->nilai('N_DTPS_LK'));
    }

    #[Test]
    public function nidn_kosong_tidak_digabung(): void
    {
        // NIDN wajib pada formulir, tetapi baris lama/impor bisa tanpa NIDN;
        // keadaan itu dibuat dengan mengosongkan data sesudah disimpan.
        foreach (['Drs. Dedi', 'Dra. Euis'] as $i => $nama) {
            $baris = $this->dosen('7', $nama, 'SEMENTARA-'.$i, 'Lektor', bidang: 'Bidang '.$i);
            $baris->forceFill(['data' => ['nidn_nidk' => '  '] + $baris->data])->save();
        }

        $this->assertSame(2.0, $this->nilai('N_DTPS'));
    }

    #[Test]
    public function kolom_hitung_dibaca_dari_skema(): void
    {
        $this->isi('8', ['nama_dtps' => 'Dr. Ani', 'sks_ps_diakreditasi' => 20, 'sks_penelitian' => 8]);
        $this->isi('8', ['nama_dtps' => 'Dr. Budi', 'sks_ps_diakreditasi' => 24]);

        $this->assertSame(13.0, $this->nilai('RBK_DTPS'));
    }

    #[Test]
    public function cacah_unik_dan_pilihan_tabel_13_dan_19(): void
    {
        $guru = [
            ['Ida', 'Magister/Magister Terapan', 'Guru Madya', '1234'],
            ['Joko', 'Magister/Magister Terapan', 'Guru Muda', '5678'],
            ['Kiki', 'Sarjana/Sarjana Terapan', 'Guru Pertama', null],
        ];

        foreach ($guru as [$nama, $pendidikan, $jabatan, $serdik]) {
            $this->isi('13', [
                'bidang_studi' => 'Matematika', 'nama_guru_pamong' => $nama, 'nama_sekolah' => 'SMAN 1',
                'pendidikan_tertinggi' => $pendidikan, 'jabatan_fungsional' => $jabatan, 'no_serdik' => $serdik,
            ]);
        }

        $this->assertSame(3.0, $this->nilai('N_GP'));
        $this->assertSame(2.0, $this->nilai('N_GP_MAGISTER_DOKTOR'));
        $this->assertSame(1.0, $this->nilai('N_GP_MADYA_UTAMA'));
        $this->assertSame(2.0, $this->nilai('N_GP_SERDIK'));

        $integrasi = [
            ['Dr. Ani', 'Penelitian A', 'Geometri'],
            ['dr. ani ', 'Penelitian B', 'Aljabar'],
            ['Dr. Budi', 'PkM C', 'Geometri'],
        ];

        foreach ($integrasi as [$dosen, $judul, $mk]) {
            $this->isi('19', ['nama_dtps' => $dosen, 'judul_penelitian_pkm' => $judul, 'mata_kuliah' => $mk, 'ts' => 'V']);
        }

        $this->assertSame(2.0, $this->nilai('N_INTEGRASI'));
        $this->assertSame(2.0, $this->nilai('N_INTEGRASI_MK'));
    }

    #[Test]
    public function rt_pembimbingan_per_dosen_per_tahun(): void
    {
        $this->isi('20', ['nama_dosen_pembimbing' => 'Dr. Ani', 'pertemuan_ts2' => 4, 'pertemuan_ts1' => 6, 'bimbingan_ts2' => 3, 'bimbingan_ts1' => 3]);
        $this->isi('20', ['nama_dosen_pembimbing' => 'Dr. Budi', 'pertemuan_ts2' => 2]);

        $this->assertSame(4.0, $this->nilai('RT_PEMBIMBINGAN_PPL'));
    }

    #[Test]
    public function rt_masa_studi_tertimbang_jumlah_lulusan(): void
    {
        // TS-1: 10 lulus akhir TS-1 (1 th) + 10 lulus akhir TS (2 th) → 1,5; TS: 20 lulus akhir TS (1 th) → 1,0.
        $this->isi('22', ['diterima' => 30, 'lulus_akhir_ts1' => 10, 'lulus_akhir_ts' => 10], 'TS-1');
        $this->isi('22', ['diterima' => 25, 'lulus_akhir_ts' => 20], 'TS');
        $this->isi('22', ['diterima' => 40, 'lulus_akhir_ts2' => 40], 'TS-2');

        $hasil = $this->pembaca->satu($this->periode, 'RT_MS_LULUSAN');
        $this->assertEqualsWithDelta((1.5 * 20 + 1.0 * 20) / 40, $hasil->nilai, 1e-9);
        $this->assertSame(2, $hasil->jumlahBaris);
    }

    #[Test]
    public function n_dtps_publikasi_menghitung_nama_unik_tabel_27(): void
    {
        foreach ([['Dr. Ani', 'Artikel 1'], ['Dr. Ani', 'Artikel 2'], ['Dr. Budi', 'Artikel 3']] as [$nama, $judul]) {
            $this->isi('27', [
                'nama_dtps' => $nama, 'judul_artikel' => $judul, 'peran_penulis' => 'Penulis Pertama',
                'jenis_jurnal' => 'Jurnal Internasional', 'terindeks' => 'Scopus Q2', 'tanggal_terbit' => '01/03/2026',
            ]);
        }

        $this->assertSame(2.0, $this->nilai('N_DTPS_PUBLIKASI'));
        $this->assertSame(3.0, $this->nilai('N_ARTIKEL_PUBLIKASI'));
    }

    #[Test]
    public function belum_terverifikasi_dilaporkan(): void
    {
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $rekognisi = fn (string $deskripsi) => $this->isi('9', [
            'nama_dtps' => 'Dr. Ani', 'deskripsi_rekognisi' => $deskripsi,
            'jenis_rekognisi' => 'Editor atau mitra bestari', 'tahun' => '2026', 'tingkat' => 'Nasional',
        ]);

        $pertama = $rekognisi('Reviewer jurnal A');
        $rekognisi('Reviewer jurnal B');
        $pertama->bukti()->attach($this->buktiLayak()->id);
        app(VerifikatorDkps::class)->verifikasi($pertama, $ketua);

        $hasil = $this->pembaca->satu($this->periode, 'N_REKOGNISI_DTPS');
        $this->assertSame(2.0, $hasil->nilai);
        $this->assertSame(2, $hasil->jumlahBaris);
        $this->assertSame(1, $hasil->belumTerverifikasi);
    }

    #[Test]
    public function baris_terhapus_tidak_dihitung(): void
    {
        $this->kerjaSama('1.1', 'UniSZA', 'internasional');
        $baris = DkpsBaris::withoutGlobalScope('lingkup_periode')->firstOrFail();
        $baris->delete();

        $this->assertNull($this->nilai('N_KERJASAMA_PENDIDIKAN'));
    }

    #[Test]
    public function semua_mengembalikan_setiap_kode_katalog(): void
    {
        $this->kerjaSama('1.1', 'UniSZA', 'internasional');

        $semua = $this->pembaca->semua($this->periode);

        $this->assertCount(VariabelDkps::count(), $semua);
        $this->assertSame(VariabelDkps::orderBy('urutan')->pluck('kode')->all(), array_keys($semua));
        $this->assertSame(1.0, $semua['N_KERJASAMA_PENDIDIKAN']->nilai);
    }

    #[Test]
    public function kode_tak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pembaca->satu($this->periode, 'TIDAK_ADA');
    }

    #[Test]
    public function variabel_khusus_tak_dikenal_ditolak(): void
    {
        VariabelDkps::create([
            'kode' => 'KHUSUS_ASING', 'label' => 'Asing', 'lembar' => ['6'],
            'cara' => 'khusus', 'rumus' => [], 'urutan' => 999,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->pembaca->satu($this->periode, 'KHUSUS_ASING');
    }
}
