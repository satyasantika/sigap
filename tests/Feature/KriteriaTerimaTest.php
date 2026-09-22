<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Narasi;
use App\Models\NarasiVersi;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\TagihanRiwayat;
use App\Models\User;
use App\Services\PengelolaBukti;
use App\Support\Impor\NormalisasiKunci;
use App\Support\Izin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menutup butir [uji] di vibecoding/docs/06-kriteria-terima.md yang belum
 * punya berkas sendiri.
 *
 * Berkas ini sengaja bukan tempat sampah: tiap uji di sini menguji sesuatu
 * yang tidak wajar diletakkan di berkas uji layanannya sendiri.
 */
class KriteriaTerimaTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    private Periode $periode;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PengelolaBukti::DISK);

        $this->seed(DatabaseSeeder::class);
        $this->prodi = Prodi::firstOrFail();
        $this->periode = Periode::firstOrFail();
    }

    private function pengguna(PeranPengguna $peran, ?string $kodePokja = null): User
    {
        $u = User::create([
            'name' => 'u', 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@kt.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    // ---- Tahap 4: bukti wajib bertanggal dan bersumber ------------------

    #[Test]
    public function bukti_tanpa_tanggal_kejadian_ditolak(): void
    {
        // Ditolak di tingkat basis data, bukan sekadar diperingatkan di layar.
        // Bukti tanpa tanggal kejadian tidak bisa ditempatkan di jendela data
        // mana pun, jadi ia tidak berguna bagi asesor.
        $this->expectException(QueryException::class);

        Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Tanpa tanggal', 'jenis' => 'berkas',
            'sumber' => SumberData::Manual,
            'diunggah_oleh' => User::firstOrFail()->id,
        ]);
    }

    #[Test]
    public function bukti_tanpa_sumber_ditolak(): void
    {
        // Ditolak di tingkat model, BUKAN diandalkan pada basis data: MariaDB
        // memperlakukan kolom ENUM NOT NULL tanpa default dengan memakai nilai
        // pertama enum secara diam-diam, dan nilai pertamanya `siakad` —
        // sumber yang paling dipercaya asesor. Bukti yang lupa diberi sumber
        // akan tercatat berasal dari SIAKAD.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/wajib diisi/');

        Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Tanpa sumber', 'jenis' => 'berkas',
            'tanggal_kejadian' => now()->subMonth(),
            'diunggah_oleh' => User::firstOrFail()->id,
        ]);
    }

    #[Test]
    public function baris_dkps_tanpa_sumber_juga_ditolak(): void
    {
        $this->expectException(\RuntimeException::class);

        DkpsBaris::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'dkps_butir_id' => DkpsButir::where('no', 2)->firstOrFail()->id,
            'tahun_acuan' => 'TS', 'data' => ['jumlah' => 1],
        ]);
    }

    // ---- Tahap 4: lingkup validasi bukti --------------------------------

    #[Test]
    public function koordinator_memvalidasi_bukti_pokjanya_dan_ditolak_di_pokja_lain(): void
    {
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, 'POKJA-DIK');

        // Pokja bukti diturunkan dari elemen yang ditopangnya.
        $milikDik = $this->buktiUntukElemen(Elemen::where('pokja_kode', 'POKJA-DIK')->firstOrFail());
        $milikSdm = $this->buktiUntukElemen(Elemen::where('pokja_kode', 'POKJA-SDM')->firstOrFail());

        $this->assertTrue(Izin::boleh($koordinator, 'bukti.validasi', $milikDik));
        $this->assertFalse(Izin::boleh($koordinator, 'bukti.validasi', $milikSdm));
    }

    #[Test]
    public function ketua_memvalidasi_bukti_pokja_mana_pun(): void
    {
        $ketua = $this->pengguna(PeranPengguna::Ketua);

        foreach (['POKJA-DIK', 'POKJA-SDM', 'POKJA-MUTU'] as $kode) {
            $bukti = $this->buktiUntukElemen(Elemen::where('pokja_kode', $kode)->firstOrFail());

            $this->assertTrue(Izin::boleh($ketua, 'bukti.validasi', $bukti), $kode);
        }
    }

    #[Test]
    public function anggota_tidak_bisa_memvalidasi_bukti_bahkan_di_pokjanya(): void
    {
        // Yang mengumpulkan tidak menyatakan kumpulannya sendiri sah.
        $anggota = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');
        $bukti = $this->buktiUntukElemen(Elemen::where('pokja_kode', 'POKJA-DIK')->firstOrFail());

        $this->assertFalse(Izin::boleh($anggota, 'bukti.validasi', $bukti));
    }

    private function buktiUntukElemen(Elemen $elemen): Bukti
    {
        $b = Bukti::create([
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => "Bukti E{$elemen->no}", 'jenis' => 'berkas',
            'tanggal_kejadian' => now()->subMonth(), 'sumber' => SumberData::Manual,
            'diunggah_oleh' => User::firstOrFail()->id,
        ]);

        $b->elemen()->attach($elemen->id);

        return $b->refresh();
    }

    // ---- Tahap 4: kunci duplikasi ternormalkan --------------------------

    #[Test]
    public function kunci_normal_mengabaikan_spasi_ganda_huruf_besar_dan_tanda_baca_ujung(): void
    {
        // Tanpa ini, "SK Dekan  No. 2401" dan "sk dekan no 2401" dianggap dua
        // baris berbeda — dan itulah yang sebenarnya terjadi saat data
        // ditempel dari beberapa sumber.
        $acuan = NormalisasiKunci::dari('SK Dekan No 2401');

        foreach ([
            'sk dekan no 2401',
            'SK  Dekan   No 2401',
            '  SK Dekan No 2401.  ',
            'SK Dekan No 2401,',
            'SK DEKAN NO 2401;',
        ] as $variasi) {
            $this->assertSame($acuan, NormalisasiKunci::dari($variasi), "Variasi: `{$variasi}`");
        }
    }

    #[Test]
    public function kunci_normal_tetap_membedakan_yang_memang_berbeda(): void
    {
        // Normalisasi tidak boleh sampai menyatukan dua hal yang berlainan.
        $this->assertNotSame(
            NormalisasiKunci::dari('SK Dekan No 2401'),
            NormalisasiKunci::dari('SK Dekan No 2402'),
        );

        // Bagiannya bergabung dengan pemisah, jadi urutannya bermakna.
        $this->assertNotSame(
            NormalisasiKunci::dari('Ahmad', 'Judul A'),
            NormalisasiKunci::dari('Judul A', 'Ahmad'),
        );
    }

    // ---- Tahap 4: sha256 dan peringatan unggahan kembar -----------------

    #[Test]
    public function unggahan_berkas_identik_terdeteksi_lewat_sha256(): void
    {
        $pengelola = app(PengelolaBukti::class);
        $pengunggah = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');

        $dasar = [
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Rekap', 'tanggal_kejadian' => now()->subMonth()->toDateString(),
            'sumber' => SumberData::Manual,
        ];

        $isi = 'isi berkas yang sama persis';
        $pertama = $pengelola->simpanBerkas(
            UploadedFile::fake()->createWithContent('a.pdf', $isi), $dasar, $pengunggah,
        );

        $this->assertSame(hash('sha256', $isi), $pertama->sha256);

        // Diberi peringatan, bukan ditolak: kadang memang perlu dua salinan,
        // tetapi pengunggah ditawari menautkan yang lama.
        $kembar = $pengelola->kembaranDi($this->periode->id, hash('sha256', $isi));

        $this->assertNotNull($kembar);
        $this->assertSame($pertama->id, $kembar->id);
    }

    #[Test]
    public function berkas_berbeda_tidak_dianggap_kembar(): void
    {
        $pengelola = app(PengelolaBukti::class);
        $pengunggah = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');

        $dasar = [
            'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
            'judul' => 'Rekap', 'tanggal_kejadian' => now()->subMonth()->toDateString(),
            'sumber' => SumberData::Manual,
        ];

        $pengelola->simpanBerkas(UploadedFile::fake()->createWithContent('a.pdf', 'isi A'), $dasar, $pengunggah);

        $this->assertNull($pengelola->kembaranDi($this->periode->id, hash('sha256', 'isi B')));
    }

    #[Test]
    public function kembaran_dicari_dalam_periode_yang_sama_saja(): void
    {
        $pengelola = app(PengelolaBukti::class);
        $pengunggah = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK');

        $isi = 'berkas yang sama';
        $pengelola->simpanBerkas(
            UploadedFile::fake()->createWithContent('a.pdf', $isi),
            [
                'prodi_id' => $this->prodi->id, 'periode_id' => $this->periode->id,
                'judul' => 'Rekap', 'tanggal_kejadian' => now()->subMonth()->toDateString(),
                'sumber' => SumberData::Manual,
            ],
            $pengunggah,
        );

        $periodeLain = Periode::create([
            'prodi_id' => $this->prodi->id, 'nama' => 'PPG berikutnya', 'ts_tahun' => 2030,
            'versi_instrumen' => 'IAPSK 3.0', 'status' => 'persiapan',
        ]);

        // Bukti yang sama pada periode berbeda memang bukti berbeda: jendela
        // datanya lain, dan tanggal kejadiannya dinilai ulang.
        $this->assertNull($pengelola->kembaranDi($periodeLain->id, hash('sha256', $isi)));
    }

    // ---- Tahap 4: validasi menuntut catatan -----------------------------

    #[Test]
    public function sah_tidak_menuntut_catatan_tetapi_dua_lainnya_menuntut(): void
    {
        $this->assertFalse(ValidasiBukti::Sah->butuhCatatan());
        $this->assertFalse(ValidasiBukti::BelumDivalidasi->butuhCatatan());
        $this->assertTrue(ValidasiBukti::Meragukan->butuhCatatan());
        $this->assertTrue(ValidasiBukti::TidakSah->butuhCatatan());
    }

    // ---- Tahap 1: pagar yang masih harus berdiri ------------------------

    #[Test]
    public function seluruh_model_transaksional_memakai_soft_delete(): void
    {
        // Data akreditasi tidak pernah hilang permanen dari antarmuka
        // (AGENTS.md aturan 8).
        $wajib = [
            Tagihan::class,
            Bukti::class,
            Narasi::class,
            DkpsBaris::class,
            Penilaian::class,
        ];

        foreach ($wajib as $kelas) {
            $this->assertContains(
                SoftDeletes::class,
                class_uses_recursive($kelas),
                "{$kelas} harus memakai SoftDeletes."
            );
        }
    }

    #[Test]
    public function tabel_append_only_tidak_punya_soft_delete(): void
    {
        // Sebaliknya: riwayat justru tidak boleh bisa dihapus sama sekali,
        // bahkan lunak — soft delete akan menyembunyikannya dari antarmuka
        // dan itu sama saja dengan menghapusnya.
        foreach ([TagihanRiwayat::class, NarasiVersi::class] as $kelas) {
            $this->assertNotContains(
                SoftDeletes::class,
                class_uses_recursive($kelas),
                "{$kelas} append only; soft delete justru menyembunyikan jejaknya."
            );
        }
    }

    #[Test]
    public function seluruh_model_memakai_uuid_sebagai_kunci(): void
    {
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Models')));
        $diperiksa = 0;

        foreach ($iter as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }

            $kelas = 'App\\Models\\'.$f->getBasename('.php');

            if (! class_exists($kelas)) {
                continue;
            }

            $this->assertContains(
                HasUuids::class,
                class_uses_recursive($kelas),
                "{$kelas} harus memakai HasUuids — AGENTS.md aturan 4b tanpa kecuali."
            );
            $diperiksa++;
        }

        $this->assertGreaterThan(15, $diperiksa, 'Seluruh model harus terperiksa.');
    }
}
