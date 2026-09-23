<?php

namespace Tests\Feature;

use App\Enums\LevelSyaratPerlu;
use App\Enums\PeranPengguna;
use App\Models\Elemen;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Simulasi;
use App\Models\StatusSyaratPerlu;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\KalkulatorNa;
use App\Services\Simulator;
use App\Support\Na\Skenario;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Simulasi boleh mengandaikan apa saja, ASAL tidak menyentuh data sungguhan.
 *
 * Itu satu-satunya hal yang benar-benar dijaga berkas ini. Fitur simulasi yang
 * rusak hanya merepotkan; simulasi yang diam-diam menulis ke `penilaian` atau
 * ikut terhitung sebagai periode berjalan merusak nilai akreditasi, dan
 * kerusakannya baru ketahuan setelah laporan dikirim ke LAMDIK.
 */
class SimulasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    private function periode(): Periode
    {
        return Periode::aktif()->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('peran', PeranPengguna::Admin)->firstOrFail();
    }

    private function simulator(): Simulator
    {
        return app(Simulator::class);
    }

    // --- pengandaian skor ----------------------------------------------------

    #[Test]
    public function skenario_kosong_menghasilkan_na_yang_sama_persis(): void
    {
        $periode = $this->periode();
        $kalkulator = app(KalkulatorNa::class);

        $this->assertSame(
            $kalkulator->hitung($periode)->na,
            $kalkulator->hitung($periode, skenario: new Skenario)->na,
            'Skenario kosong harus menjadi jalur yang sama dengan perhitungan biasa.'
        );
    }

    #[Test]
    public function menaikkan_skor_satu_elemen_menaikkan_na_sebesar_bobotnya(): void
    {
        $periode = $this->periode();
        $kalkulator = app(KalkulatorNa::class);

        // Elemen yang sudah dinilai, supaya selisihnya bisa dihitung persis.
        $penilaian = Penilaian::where('periode_id', $periode->id)
            ->where('skor', '<', 4)
            ->with('elemen')
            ->firstOrFail();

        $elemen = $penilaian->elemen;
        $naik = 4 - $penilaian->skor;

        $sebelum = $kalkulator->hitung($periode)->na;
        $sesudah = $kalkulator->hitung($periode, skenario: new Skenario(skor: [$elemen->id => 4]))->na;

        $this->assertEqualsWithDelta(
            $naik * (float) $elemen->bobot,
            $sesudah - $sebelum,
            0.01,
            'NA = Σ (skor × bobot); menaikkan satu skor menggeser NA sebesar selisih × bobot.'
        );
    }

    #[Test]
    public function pengandaian_tidak_menulis_apa_pun_ke_tabel_penilaian(): void
    {
        $periode = $this->periode();
        $sebelum = Penilaian::where('periode_id', $periode->id)
            ->orderBy('elemen_id')
            ->pluck('skor', 'elemen_id')
            ->all();

        $elemen = Elemen::orderBy('no')->firstOrFail();

        $this->simulator()->buatSimulasiSkor(
            $this->admin(), $periode, 'Andai E1 sempurna',
            new Skenario(skor: [$elemen->id => 4]),
        );

        $sesudah = Penilaian::where('periode_id', $periode->id)
            ->orderBy('elemen_id')
            ->pluck('skor', 'elemen_id')
            ->all();

        $this->assertSame($sebelum, $sesudah,
            'Simulasi skor TIDAK BOLEH menyentuh tabel penilaian. Lihat App\\Services\\Simulator.');
    }

    #[Test]
    public function pengandaian_syarat_perlu_bisa_mengubah_status(): void
    {
        $periode = $this->periode();

        // Seluruh syarat perlu diandaikan terpenuhi untuk lima tahun.
        $semua = Elemen::bersyaratPerlu()->pluck('id')
            ->mapWithKeys(fn (string $id) => [$id => LevelSyaratPerlu::Lima])
            ->all();

        $hasil = app(KalkulatorNa::class)->hitung($periode, skenario: new Skenario(syaratPerlu: $semua));

        $this->assertTrue($hasil->syarat3);
        $this->assertTrue($hasil->syarat5);

        // Dan tabel sungguhannya tetap seperti semula.
        $this->assertFalse(app(KalkulatorNa::class)->hitung($periode)->syarat5,
            'DemoSeeder sengaja meninggalkan satu syarat perlu belum terpenuhi.');
    }

    #[Test]
    public function hasil_simulasi_tersimpan_dan_bisa_dibandingkan(): void
    {
        $periode = $this->periode();
        $elemen = Elemen::orderByDesc('bobot')->firstOrFail();

        $simulasi = $this->simulator()->buatSimulasiSkor(
            $this->admin(), $periode, 'Andai elemen terberat penuh',
            new Skenario(skor: [$elemen->id => 4]),
        );

        $this->assertNotNull($simulasi->hasil['na'] ?? null);
        $this->assertSame('skor', $simulasi->jenis);

        $banding = $this->simulator()->bandingkan($simulasi);

        $this->assertGreaterThanOrEqual(0, $banding['selisih']);
        $this->assertSame($banding['simulasi']->na, $simulasi->hasil['na']);
    }

    // --- periode latihan -----------------------------------------------------

    #[Test]
    public function periode_latihan_menyalin_kerangka_tanpa_isi(): void
    {
        $acuan = $this->periode();
        $tagihanAcuan = Tagihan::where('periode_id', $acuan->id)->count();

        $simulasi = $this->simulator()->buatSandbox($this->admin(), $acuan, 'Latihan anggota baru');
        $sandbox = $simulasi->periodeSandbox;

        $this->assertNotNull($sandbox);
        $this->assertTrue($sandbox->simulasi);
        $this->assertStringStartsWith('[SIMULASI]', $sandbox->nama);
        $this->assertSame($acuan->ts_tahun, $sandbox->ts_tahun);

        $this->assertSame(
            $acuan->pokja()->count(),
            $sandbox->pokja()->count(),
            'Pokja ikut disalin supaya orang berlatih di tempat yang sama.'
        );
        $this->assertSame($tagihanAcuan, Tagihan::where('periode_id', $sandbox->id)->count());

        // Yang TIDAK ikut: penilaian dan syarat perlu.
        $this->assertSame(0, Penilaian::where('periode_id', $sandbox->id)->count());
        $this->assertSame(0, StatusSyaratPerlu::where('periode_id', $sandbox->id)->count());
    }

    #[Test]
    public function periode_latihan_tidak_pernah_terbaca_sebagai_periode_berjalan(): void
    {
        $acuan = $this->periode();
        $sebelum = Periode::aktif()->pluck('id')->all();

        $this->simulator()->buatSandbox($this->admin(), $acuan, 'Latihan');

        $this->assertSame($sebelum, Periode::aktif()->pluck('id')->all(),
            'scopeAktif wajib mengecualikan periode simulasi.');
        $this->assertSame($sebelum, Periode::sungguhan()->aktif()->pluck('id')->all());
    }

    #[Test]
    public function dua_periode_latihan_bernama_sama_tetap_bisa_dibuat(): void
    {
        $acuan = $this->periode();

        $a = $this->simulator()->buatSandbox($this->admin(), $acuan, 'Latihan');
        $b = $this->simulator()->buatSandbox($this->admin(), $acuan, 'Latihan');

        $this->assertNotSame($a->periodeSandbox->nama, $b->periodeSandbox->nama,
            'Tabel periode memaksa nama unik per prodi; layanan wajib menyiasatinya sendiri.');
    }

    // --- menghapus -----------------------------------------------------------

    #[Test]
    public function menghapus_simulasi_skor_tidak_menyentuh_data_lain(): void
    {
        $periode = $this->periode();
        $elemen = Elemen::orderBy('no')->firstOrFail();
        $cacahPenilaian = Penilaian::count();

        $simulasi = $this->simulator()->buatSimulasiSkor(
            $this->admin(), $periode, 'Coba-coba', new Skenario(skor: [$elemen->id => 4]),
        );

        $this->simulator()->hapus($this->admin(), $simulasi);

        $this->assertSoftDeleted('simulasi', ['id' => $simulasi->id]);
        $this->assertSame($cacahPenilaian, Penilaian::count());
        $this->assertDatabaseHas('periode', ['id' => $periode->id, 'deleted_at' => null]);
    }

    #[Test]
    public function menghapus_periode_latihan_membuang_periodenya_sekaligus(): void
    {
        $acuan = $this->periode();
        $tagihanAcuan = Tagihan::where('periode_id', $acuan->id)->count();

        $simulasi = $this->simulator()->buatSandbox($this->admin(), $acuan, 'Latihan');
        $sandboxId = $simulasi->periode_sandbox_id;

        $this->simulator()->hapus($this->admin(), $simulasi);

        $this->assertSame(0, Periode::withTrashed()->whereKey($sandboxId)->count(),
            'Periode latihan dihapus permanen, bukan soft delete.');
        $this->assertSame(0, Tagihan::withTrashed()->where('periode_id', $sandboxId)->count());

        // Periode sungguhan dan isinya utuh.
        $this->assertDatabaseHas('periode', ['id' => $acuan->id, 'deleted_at' => null]);
        $this->assertSame($tagihanAcuan, Tagihan::where('periode_id', $acuan->id)->count());
    }

    // --- wewenang ------------------------------------------------------------

    /** @return array<string, array{PeranPengguna}> */
    public static function peranTanpaWewenangSimulasi(): array
    {
        return [
            'ketua' => [PeranPengguna::Ketua],
            'pimpinan' => [PeranPengguna::Pimpinan],
            'koordinator' => [PeranPengguna::Koordinator],
            'anggota' => [PeranPengguna::Anggota],
            'auditor' => [PeranPengguna::Auditor],
        ];
    }

    #[Test]
    #[DataProvider('peranTanpaWewenangSimulasi')]
    public function hanya_admin_yang_bisa_membuat_simulasi(PeranPengguna $peran): void
    {
        $u = User::where('peran', $peran)->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->simulator()->buatSimulasiSkor($u, $this->periode(), 'Coba', new Skenario);
    }

    #[Test]
    #[DataProvider('peranTanpaWewenangSimulasi')]
    public function hanya_admin_yang_bisa_menghapus_simulasi(PeranPengguna $peran): void
    {
        $simulasi = $this->simulator()->buatSimulasiSkor(
            $this->admin(), $this->periode(), 'Coba', new Skenario,
        );

        $u = User::where('peran', $peran)->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->simulator()->hapus($u, $simulasi);
    }

    #[Test]
    public function membuat_dan_menghapus_simulasi_tercatat_di_log_aktivitas(): void
    {
        $simulasi = $this->simulator()->buatSimulasiSkor(
            $this->admin(), $this->periode(), 'Tercatat', new Skenario,
        );

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'simulasi.buat',
            'user_id' => $this->admin()->id,
        ]);

        $this->simulator()->hapus($this->admin(), $simulasi);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'simulasi.hapus',
            'user_id' => $this->admin()->id,
        ]);
    }

    #[Test]
    public function simulasi_yang_dihapus_tidak_muncul_di_daftar(): void
    {
        $simulasi = $this->simulator()->buatSimulasiSkor(
            $this->admin(), $this->periode(), 'Sementara', new Skenario,
        );

        $this->assertSame(1, Simulasi::count());

        $this->simulator()->hapus($this->admin(), $simulasi);

        $this->assertSame(0, Simulasi::count());
        $this->assertSame(1, Simulasi::withTrashed()->count());
    }
}
