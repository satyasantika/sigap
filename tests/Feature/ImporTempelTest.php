<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Bukti;
use App\Models\ImporBatch;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PelaksanaImpor;
use App\Support\Impor\ImporBukti;
use App\Support\Impor\PenguraiTempelan;
use App\Support\Impor\PratinjauImpor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ImporTempelTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->prodi = Prodi::firstOrFail();
        $this->pengguna = User::where('peran', PeranPengguna::Ketua)->firstOrFail();
    }

    private function profil(): ImporBukti
    {
        return new ImporBukti($this->periode->id, $this->prodi->id, $this->pengguna->id);
    }

    private function tanggal(): string
    {
        return now()->subMonths(3)->format('Y-m-d');
    }

    // ---- Penguraian tempelan --------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function pemisah(): array
    {
        return [
            'tab (Excel, Google Sheets)' => ["\t", 'tab'],
            'titik koma (Excel lokal Eropa)' => [';', 'titik koma'],
            'koma (CSV)' => [',', 'koma'],
        ];
    }

    #[Test]
    #[DataProvider('pemisah')]
    public function ketiga_pemisah_dikenali(string $pemisah, string $nama): void
    {
        $tempelan = implode($pemisah, ['judul', 'url', 'tanggal'])."\n"
            .implode($pemisah, ['SK Dekan', 'https://drive.google.com/file/d/A/view', '2020-01-01']);

        $hasil = PenguraiTempelan::urai($tempelan);

        $this->assertSame($pemisah, $hasil['pemisah'], "Pemisah {$nama} seharusnya terdeteksi.");
        $this->assertSame(['judul', 'url', 'tanggal'], $hasil['kepala']);
        $this->assertCount(1, $hasil['baris']);
        $this->assertSame('SK Dekan', $hasil['baris'][0][0]);
    }

    #[Test]
    public function tab_dicoba_lebih_dulu_daripada_koma(): void
    {
        // Judul bukti sering mengandung koma. Menebak koma lebih dulu akan
        // memecah "SK Dekan, Nomor 2401" menjadi dua kolom.
        $tempelan = "judul\turl\nSK Dekan, Nomor 2401\thttps://drive.google.com/file/d/A/view";

        $hasil = PenguraiTempelan::urai($tempelan);

        $this->assertSame("\t", $hasil['pemisah']);
        $this->assertSame('SK Dekan, Nomor 2401', $hasil['baris'][0][0]);
    }

    #[Test]
    public function sel_bertanda_kutip_boleh_mengandung_pemisah_dan_baris_baru(): void
    {
        $tempelan = "judul,keterangan\n\"SK, Dekan\",\"Halaman 2\nbaris kedua\"";

        $hasil = PenguraiTempelan::urai($tempelan);

        $this->assertSame('SK, Dekan', $hasil['baris'][0][0]);
        $this->assertSame("Halaman 2\nbaris kedua", $hasil['baris'][0][1]);
    }

    #[Test]
    public function tempelan_melebihi_seribu_baris_ditolak_bukan_dipotong(): void
    {
        $tempelan = collect(range(1, PenguraiTempelan::BATAS_BARIS + 1))
            ->map(fn ($i) => "Judul {$i}\thttps://drive.google.com/file/d/{$i}/view")
            ->join("\n");

        $this->expectException(RuntimeException::class);
        // Memotong diam-diam akan membuat sebagian data hilang tanpa disadari.
        $this->expectExceptionMessageMatches('/melebihi batas/');

        PenguraiTempelan::urai($tempelan, barisPertamaKepala: false);
    }

    #[Test]
    public function pemetaan_kolom_ditebak_tanpa_peka_huruf_dan_spasi(): void
    {
        $peta = PenguraiTempelan::tebakPemetaan(
            ['Judul Bukti', 'TAUTAN', 'tanggal_kejadian', 'Sumber Data'],
            $this->profil()->medan(),
        );

        $this->assertSame(0, $peta['judul']);
        $this->assertSame(1, $peta['url']);
        $this->assertSame(2, $peta['tanggal_kejadian']);
        $this->assertSame(3, $peta['sumber']);
        $this->assertNull($peta['keterangan'], 'Kolom yang tidak ada seharusnya null.');
    }

    // ---- Deteksi duplikasi dua arah -------------------------------------

    #[Test]
    public function duplikat_terdeteksi_terhadap_basis_data(): void
    {
        Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Yang sudah ada', 'jenis' => 'tautan',
            'url_kanonik' => 'https://drive.google.com/file/d/SAMA/view',
            'kunci_normal' => 'https://drive.google.com/file/d/SAMA/view',
            'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual',
            'diunggah_oleh' => $this->pengguna->id,
        ]);

        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Tempelan baru', 'url' => 'https://drive.google.com/file/d/SAMA/edit',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
        ], $this->profil(), $this->periode->id);

        // Bentuk URL-nya berbeda (/edit vs /view) tetapi menunjuk berkas yang
        // sama — itulah gunanya normalisasi kanonik.
        $this->assertSame(PratinjauImpor::DUPLIKAT_BASIS_DATA, $pratinjau[0]['status']);
        $this->assertNotNull($pratinjau[0]['id_lama']);
    }

    #[Test]
    public function duplikat_terdeteksi_di_dalam_tempelan_yang_sama(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Pertama', 'url' => 'https://drive.google.com/file/d/KEMBAR/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
            ['judul' => 'Kedua', 'url' => 'https://drive.google.com/open?id=KEMBAR',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
        ], $this->profil(), $this->periode->id);

        // Memeriksa satu arah saja adalah kesalahan yang paling sering
        // terjadi: orang menempel daftar yang di dalamnya sendiri sudah ada
        // kembaran, dan keduanya lolos karena belum ada di basis data.
        $this->assertSame(PratinjauImpor::BARU, $pratinjau[0]['status']);
        $this->assertSame(PratinjauImpor::DUPLIKAT_TEMPELAN, $pratinjau[1]['status']);
        $this->assertStringContainsString('baris 1', $pratinjau[1]['galat'][0]);
    }

    #[Test]
    public function baris_galat_ditandai_dengan_alasan_yang_menyebut_kolomnya(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['judul' => '', 'url' => 'bukan url', 'tanggal_kejadian' => 'kemarin', 'sumber' => 'entah'],
        ], $this->profil(), $this->periode->id);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $galat = implode(' ', $pratinjau[0]['galat']);

        $this->assertStringContainsString('Judul kosong', $galat);
        $this->assertStringContainsString('Tautan kosong atau tidak sah', $galat);
        $this->assertStringContainsString('tidak terbaca', $galat);
        $this->assertStringContainsString('Sumber data harus', $galat);
    }

    #[Test]
    public function ringkasan_dibaca_manusia_sebelum_menjalankan(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'A', 'url' => 'https://drive.google.com/file/d/A/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
            ['judul' => 'A lagi', 'url' => 'https://drive.google.com/file/d/A/edit',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
            ['judul' => '', 'url' => '', 'tanggal_kejadian' => '', 'sumber' => ''],
        ], $this->profil(), $this->periode->id);

        $kalimat = PratinjauImpor::kalimat(PratinjauImpor::ringkas($pratinjau));

        $this->assertStringContainsString('3 baris', $kalimat);
        $this->assertStringContainsString('1 baru', $kalimat);
        $this->assertStringContainsString('1 kembar di tempelan', $kalimat);
        $this->assertStringContainsString('1 galat', $kalimat);
    }

    // ---- Penjalanan -----------------------------------------------------

    #[Test]
    public function impor_berjalan_dan_mencatat_batch(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Bukti A', 'url' => 'https://drive.google.com/file/d/A/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
            ['judul' => 'Bukti B', 'url' => 'https://drive.google.com/file/d/B/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'siakad'],
        ], $this->profil(), $this->periode->id);

        $batch = app(PelaksanaImpor::class)->jalankan(
            $pratinjau, [], $this->profil(), $this->periode, $this->pengguna,
        );

        $this->assertSame(2, $batch->jumlah_impor);
        $this->assertSame(2, Bukti::where('impor_batch_id', $batch->id)->count());
    }

    #[Test]
    public function duplikat_dilewati_secara_bawaan_bukan_ditimpa(): void
    {
        $lama = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Judul asli yang tidak boleh hilang', 'jenis' => 'tautan',
            'url_kanonik' => 'https://drive.google.com/file/d/A/view',
            'kunci_normal' => 'https://drive.google.com/file/d/A/view',
            'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual',
            'diunggah_oleh' => $this->pengguna->id,
        ]);

        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Judul dari tempelan', 'url' => 'https://drive.google.com/file/d/A/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
        ], $this->profil(), $this->periode->id);

        $batch = app(PelaksanaImpor::class)->jalankan(
            $pratinjau, [], $this->profil(), $this->periode, $this->pengguna,
        );

        // Memperbarui diam-diam menimpa pekerjaan orang lain; pilihan antara
        // melewati dan memperbarui selalu keputusan manusia.
        $this->assertSame(1, $batch->jumlah_lewati);
        $this->assertSame('Judul asli yang tidak boleh hilang', $lama->fresh()->judul);
    }

    #[Test]
    public function memperbarui_menyimpan_nilai_lama_untuk_pembatalan(): void
    {
        $lama = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Judul lama', 'jenis' => 'tautan',
            'url_kanonik' => 'https://drive.google.com/file/d/A/view',
            'kunci_normal' => 'https://drive.google.com/file/d/A/view',
            'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual',
            'diunggah_oleh' => $this->pengguna->id,
        ]);

        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Judul baru', 'url' => 'https://drive.google.com/file/d/A/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
        ], $this->profil(), $this->periode->id);

        $batch = app(PelaksanaImpor::class)->jalankan(
            $pratinjau, [1 => 'perbarui'], $this->profil(), $this->periode, $this->pengguna,
        );

        $this->assertSame(1, $batch->jumlah_perbarui);
        $this->assertSame('Judul baru', $lama->fresh()->judul);

        app(PelaksanaImpor::class)->batalkan($batch, $this->profil(), $this->pengguna);

        $this->assertSame('Judul lama', $lama->fresh()->judul);
        $this->assertTrue($batch->fresh()->dibatalkan());
    }

    #[Test]
    public function pembatalan_menghapus_baris_yang_dibuat(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Bukti A', 'url' => 'https://drive.google.com/file/d/A/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
        ], $this->profil(), $this->periode->id);

        $batch = app(PelaksanaImpor::class)->jalankan(
            $pratinjau, [], $this->profil(), $this->periode, $this->pengguna,
        );

        $this->assertSame(1, Bukti::where('impor_batch_id', $batch->id)->count());

        app(PelaksanaImpor::class)->batalkan($batch, $this->profil(), $this->pengguna);

        $this->assertSame(0, Bukti::where('impor_batch_id', $batch->id)->count());
    }

    #[Test]
    public function pembatalan_ditutup_setelah_barisnya_disunting_orang_lain(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Bukti A', 'url' => 'https://drive.google.com/file/d/A/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
        ], $this->profil(), $this->periode->id);

        $batch = app(PelaksanaImpor::class)->jalankan(
            $pratinjau, [], $this->profil(), $this->periode, $this->pengguna,
        );

        $this->assertTrue(app(PelaksanaImpor::class)->bolehDibatalkan($batch));

        // Membatalkan setelah orang lain menyunting akan menghapus pekerjaan
        // mereka.
        $bukti = Bukti::where('impor_batch_id', $batch->id)->first();
        $bukti->touch();
        $bukti->update(['keterangan' => 'Disunting orang lain.']);

        $this->assertFalse(app(PelaksanaImpor::class)->bolehDibatalkan($batch));
    }

    #[Test]
    public function satu_baris_gagal_membatalkan_seluruh_impor(): void
    {
        $pratinjau = PratinjauImpor::susun([
            ['judul' => 'Bukti A', 'url' => 'https://drive.google.com/file/d/A/view',
                'tanggal_kejadian' => $this->tanggal(), 'sumber' => 'manual'],
        ], $this->profil(), $this->periode->id);

        // Baris kedua dipalsukan agar gagal saat disimpan: tanggalnya null,
        // padahal kolomnya NOT NULL.
        $pratinjau[] = [
            'no' => 2, 'status' => PratinjauImpor::BARU, 'galat' => [], 'id_lama' => null,
            'data' => ['judul' => 'Bukti rusak', 'url' => 'https://drive.google.com/file/d/B/view',
                'url_kanonik' => 'https://drive.google.com/file/d/B/view',
                'tanggal_kejadian' => null, 'sumber' => 'manual'],
        ];

        try {
            app(PelaksanaImpor::class)->jalankan(
                $pratinjau, [], $this->profil(), $this->periode, $this->pengguna,
            );
            $this->fail('Seharusnya gagal.');
        } catch (\Throwable $e) {
            // Impor separuh jalan jauh lebih buruk daripada impor yang gagal
            // seluruhnya: orang tidak tahu sampai mana yang masuk.
            $this->assertSame(0, Bukti::count());
            $this->assertSame(0, ImporBatch::count());
        }
    }
}
