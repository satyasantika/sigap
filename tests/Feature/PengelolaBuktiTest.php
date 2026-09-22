<?php

namespace Tests\Feature;

use App\Enums\AksesTautan;
use App\Enums\JenisBukti;
use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengelolaBukti;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PengelolaBuktiTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    private Periode $periode;

    private User $pengunggah;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PengelolaBukti::DISK);
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->seed(DatabaseSeeder::class);
        $this->prodi = Prodi::firstOrFail();
        $this->periode = Periode::firstOrFail();
        $this->pengunggah = User::where('peran', PeranPengguna::Anggota)->firstOrFail();
    }

    private function dasar(): array
    {
        return [
            'prodi_id' => $this->prodi->id,
            'periode_id' => $this->periode->id,
            'judul' => 'SK Dekan 2401',
            'tanggal_kejadian' => now()->subMonths(3)->toDateString(),
            'sumber' => SumberData::Manual,
        ];
    }

    private function pengelola(): PengelolaBukti
    {
        return app(PengelolaBukti::class);
    }

    #[Test]
    public function berkas_disimpan_dengan_nama_acak_dan_nama_asli_terpisah(): void
    {
        $berkas = UploadedFile::fake()->create('SK Dekan Nomor 2401.pdf', 120, 'application/pdf');

        $bukti = $this->pengelola()->simpanBerkas($berkas, $this->dasar(), $this->pengunggah);

        // Kalau nama pengguna dipakai apa adanya sebagai jalur, isi folder
        // bukti bisa ditebak dari luar — dan isinya nama dosen serta nomor
        // serdik.
        $this->assertStringNotContainsString('SK Dekan', $bukti->path);
        $this->assertSame('SK Dekan Nomor 2401.pdf', $bukti->nama_asli);
        Storage::disk(PengelolaBukti::DISK)->assertExists($bukti->path);
    }

    #[Test]
    public function sha256_dihitung_dan_dipakai_sebagai_kunci_duplikasi(): void
    {
        $berkas = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');

        $bukti = $this->pengelola()->simpanBerkas($berkas, $this->dasar(), $this->pengunggah);

        $this->assertSame(64, strlen($bukti->sha256));
        $this->assertSame($bukti->sha256, $bukti->kunci_normal);
    }

    #[Test]
    public function unggahan_identik_terdeteksi_sebagai_kembaran(): void
    {
        $isi = 'isi yang sama persis';

        $pertama = $this->pengelola()->simpanBerkas(
            UploadedFile::fake()->createWithContent('a.pdf', $isi), $this->dasar(), $this->pengunggah,
        );

        $kembar = $this->pengelola()->kembaranDi($this->periode->id, hash('sha256', $isi));

        // Tidak ditolak — kadang memang perlu dua salinan. Tetapi pengunggah
        // ditawari menautkan yang lama alih-alih mengunggah ulang.
        $this->assertNotNull($kembar);
        $this->assertSame($pertama->id, $kembar->id);
    }

    #[Test]
    public function tautan_dinormalkan_dan_diperiksa_seketika(): void
    {
        $bukti = $this->pengelola()->simpanTautan(
            'https://drive.google.com/drive/u/0/folders/ABC123',
            $this->dasar(), $this->pengunggah,
        );

        $this->assertSame(JenisBukti::Tautan, $bukti->jenis);
        $this->assertSame('https://drive.google.com/drive/folders/ABC123', $bukti->url_kanonik);
        $this->assertSame('folder', $bukti->tautan_bentuk);

        // Diperiksa di sini, bukan menunggu penjadwalan harian: pengunggah
        // harus tahu sebelum meninggalkan halaman.
        $this->assertSame(AksesTautan::Terbuka, $bukti->akses_status);
        $this->assertNotNull($bukti->akses_diperiksa_pada);
    }

    #[Test]
    public function url_tidak_sah_ditolak_saat_disimpan(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/URL tidak sah/');

        $this->pengelola()->simpanTautan('bukan url sama sekali', $this->dasar(), $this->pengunggah);
    }

    #[Test]
    public function versi_baru_tidak_menghapus_yang_lama(): void
    {
        $lama = $this->pengelola()->simpanBerkas(
            UploadedFile::fake()->create('v1.pdf', 10), $this->dasar(), $this->pengunggah,
        );
        $elemen = Elemen::where('no', 17)->firstOrFail();
        $lama->elemen()->attach($elemen->id, ['keterangan' => 'Menopang klaim DTPS.']);

        $baru = $this->pengelola()->gantiVersi($lama, UploadedFile::fake()->create('v2.pdf', 10), $this->pengunggah);

        // Bukti lama tetap ada: tagihan yang sudah disetujui mungkin
        // menautkannya, dan menghapusnya membuat persetujuan itu menunjuk
        // ke ruang kosong.
        $this->assertNotNull($lama->fresh());
        $this->assertSame(2, $baru->versi);
        $this->assertSame($lama->id, $baru->bukti_induk_id);

        // Keterangan per elemen ikut pindah, bukan hilang.
        $this->assertSame('Menopang klaim DTPS.', $baru->elemen()->first()->pivot->keterangan);
    }

    #[Test]
    public function menyunting_bukti_mengembalikan_status_validasi(): void
    {
        $bukti = $this->pengelola()->simpanBerkas(
            UploadedFile::fake()->create('a.pdf', 10), $this->dasar(), $this->pengunggah,
        );

        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();
        $this->pengelola()->validasi($bukti, ValidasiBukti::Sah, $ketua);

        $this->assertSame(ValidasiBukti::Sah, $bukti->fresh()->validasi_status);

        $this->pengelola()->sunting($bukti->fresh(), ['judul' => 'Judul yang diubah'], $this->pengunggah);

        // Tanpa ini, seseorang bisa mendapat cap `sah` untuk satu dokumen lalu
        // menukar isinya diam-diam.
        $this->assertSame(ValidasiBukti::BelumDivalidasi, $bukti->fresh()->validasi_status);
        $this->assertNull($bukti->fresh()->divalidasi_oleh);
    }

    #[Test]
    public function meragukan_dan_tidak_sah_menuntut_catatan(): void
    {
        $bukti = $this->pengelola()->simpanBerkas(
            UploadedFile::fake()->create('a.pdf', 10), $this->dasar(), $this->pengunggah,
        );
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        foreach ([ValidasiBukti::Meragukan, ValidasiBukti::TidakSah] as $status) {
            try {
                $this->pengelola()->validasi($bukti->fresh(), $status, $ketua);
                $this->fail("{$status->value} seharusnya menuntut catatan.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('wajib disertai catatan', $e->getMessage());
            }
        }

        // `sah` tidak menuntut catatan.
        $this->pengelola()->validasi($bukti->fresh(), ValidasiBukti::Sah, $ketua);
        $this->assertSame(ValidasiBukti::Sah, $bukti->fresh()->validasi_status);
    }

    #[Test]
    public function setiap_perubahan_validasi_menulis_komentar_otomatis(): void
    {
        $bukti = $this->pengelola()->simpanBerkas(
            UploadedFile::fake()->create('a.pdf', 10), $this->dasar(), $this->pengunggah,
        );
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        $this->pengelola()->validasi($bukti, ValidasiBukti::TidakSah, $ketua, 'Tanggalnya di luar jendela data.');

        // Keputusan validasi ikut terbaca di lini masa bukti, bukan hanya
        // sebagai satu kolom yang mudah terlewat.
        $komentar = $bukti->fresh()->komentar()->first();

        $this->assertNotNull($komentar);
        $this->assertStringContainsString('Tidak sah', $komentar->isi);
        $this->assertStringContainsString('Tanggalnya di luar jendela data.', $komentar->isi);
    }

    #[Test]
    public function satu_bukti_bisa_menopang_beberapa_elemen_dengan_keterangan_berbeda(): void
    {
        $bukti = $this->pengelola()->simpanBerkas(
            UploadedFile::fake()->create('sk.pdf', 10), $this->dasar(), $this->pengunggah,
        );

        $e17 = Elemen::where('no', 17)->firstOrFail();
        $e51 = Elemen::where('no', 51)->firstOrFail();

        $bukti->elemen()->attach($e17->id, ['keterangan' => 'Halaman 2: daftar kualifikasi DTPS.']);
        $bukti->elemen()->attach($e51->id, ['keterangan' => 'Halaman 5: daftar publikasi.']);

        // Alasan per pasangan inilah yang dibaca ketua saat memvalidasi.
        // Pivot tanpa id sendiri tidak bisa menyimpannya.
        $ket = $bukti->fresh()->elemen->mapWithKeys(
            fn ($e) => [$e->no => $e->pivot->keterangan]
        );

        $this->assertSame('Halaman 2: daftar kualifikasi DTPS.', $ket[17]);
        $this->assertSame('Halaman 5: daftar publikasi.', $ket[51]);
    }

    #[Test]
    public function berkas_terunggah_tidak_punya_sumbu_keterbacaan(): void
    {
        $bukti = $this->pengelola()->simpanBerkas(
            UploadedFile::fake()->create('a.pdf', 10), $this->dasar(), $this->pengunggah,
        );

        // Berkas ada di disk kita sendiri, jadi asesor pasti bisa membukanya
        // lewat ekspor — tidak ada yang perlu diperiksa keterbacaannya.
        $this->assertTrue($bukti->aksesLayak());
        $this->assertFalse($bukti->layakDipakai(), 'Masih perlu divalidasi keabsahannya.');
    }

    #[Test]
    public function alasan_belum_layak_menyebut_dua_sumbu_terpisah(): void
    {
        $bukti = $this->pengelola()->simpanTautan(
            'https://drive.google.com/file/d/XYZ/view', $this->dasar(), $this->pengunggah,
        );
        $bukti->forceFill([
            'akses_status' => AksesTautan::PerluIzin,
            'validasi_status' => ValidasiBukti::TidakSah,
        ])->save();

        $alasan = $bukti->fresh()->alasanBelumLayak();

        $this->assertCount(2, $alasan, 'Dua sumbu bermasalah harus menghasilkan dua alasan terpisah.');
    }

    #[Test]
    public function antrean_validasi_berisi_yang_belum_diputuskan_dan_yang_diragukan(): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        $belum = $this->pengelola()->simpanBerkas(UploadedFile::fake()->create('a.pdf', 10), $this->dasar(), $this->pengunggah);
        $ragu = $this->pengelola()->simpanBerkas(UploadedFile::fake()->create('b.pdf', 10), $this->dasar(), $this->pengunggah);
        $sah = $this->pengelola()->simpanBerkas(UploadedFile::fake()->create('c.pdf', 10), $this->dasar(), $this->pengunggah);

        $this->pengelola()->validasi($ragu, ValidasiBukti::Meragukan, $ketua, 'Perlu dicek ulang.');
        $this->pengelola()->validasi($sah, ValidasiBukti::Sah, $ketua);

        $antrean = Bukti::perluDitinjau()->pluck('id');

        $this->assertTrue($antrean->contains($belum->id));
        $this->assertTrue($antrean->contains($ragu->id));
        $this->assertFalse($antrean->contains($sah->id));
    }
}
