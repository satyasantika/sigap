<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Models\Bukti;
use App\Models\BuktiElemen;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\ImpersonasiSesi;
use App\Models\ImporBatch;
use App\Models\Izin;
use App\Models\Komentar;
use App\Models\Kriteria;
use App\Models\LogAktivitas;
use App\Models\Narasi;
use App\Models\NarasiVersi;
use App\Models\NilaiRumus;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\Rumus;
use App\Models\Simulasi;
use App\Models\StatusSyaratPerlu;
use App\Models\SyaratPerlu;
use App\Models\Tagihan;
use App\Models\TagihanRiwayat;
use App\Models\User;
use App\Models\VariabelDkps;
use App\Policies\ReferensiPolicy;
use App\Services\RingkasanProgres;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aturan 8: data akreditasi tidak pernah hilang permanen dari antarmuka.
 *
 * Ada DUA cara memenuhinya, dan keduanya dipakai — perbedaannya bukan selera:
 *
 *   lunak   Baris yang boleh dihapus manusia dari layar. `delete()` hanya
 *           mengisi `deleted_at`; barisnya tetap ada dan bisa dikembalikan.
 *   tolak   Baris yang TIDAK BOLEH hilang sama sekali. `delete()` melempar.
 *           Lebih keras daripada soft delete: tidak ada tombol yang bisa
 *           menyembunyikannya, dan tidak ada `deleted_at` yang bisa dipulihkan
 *           keliru.
 *
 * Tabel referensi instrumen tidak masuk keduanya: ReferensiPolicy menolak
 * seluruh penulisan, jadi tidak ada jalan menghapusnya sejak awal.
 *
 * Daftar di bawah adalah SATU-SATUNYA tempat keputusan itu tertulis. Tabel
 * baru yang tidak terdaftar akan membuat uji terakhir merah — itu disengaja,
 * supaya keputusannya diambil sadar, bukan terlewat.
 */
class PenghapusanDataTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, class-string<Model>> */
    private const LUNAK = [
        'prodi' => Prodi::class,
        'periode' => Periode::class,
        'pokja' => Pokja::class,
        'users' => User::class,
        'tagihan' => Tagihan::class,
        'bukti' => Bukti::class,
        'narasi' => Narasi::class,
        'dkps_baris' => DkpsBaris::class,
        'penilaian' => Penilaian::class,
        'komentar' => Komentar::class,
        'simulasi' => Simulasi::class,
    ];

    /** @var array<string, class-string<Model>> */
    private const TOLAK = [
        'tagihan_riwayat' => TagihanRiwayat::class,
        'narasi_versi' => NarasiVersi::class,
        'log_aktivitas' => LogAktivitas::class,
        'nilai_rumus' => NilaiRumus::class,
        'status_syarat_perlu' => StatusSyaratPerlu::class,
        'impor_batch' => ImporBatch::class,
        'impersonasi_sesi' => ImpersonasiSesi::class,
    ];

    /** Referensi instrumen: tidak bisa ditulis sama sekali dari layar. */
    private const REFERENSI = [
        'elemen' => Elemen::class,
        'kriteria' => Kriteria::class,
        'rumus' => Rumus::class,
        'syarat_perlu' => SyaratPerlu::class,
        'dkps_butir' => DkpsButir::class,
        'variabel_dkps' => VariabelDkps::class,
        'izin' => Izin::class,
    ];

    /** @return array<string, array{string, string}> */
    public static function tabelLunak(): array
    {
        return collect(self::LUNAK)->mapWithKeys(fn ($k, $t) => [$t => [$t, $k]])->all();
    }

    /** @return array<string, array{string, string}> */
    public static function tabelTolak(): array
    {
        return collect(self::TOLAK)->mapWithKeys(fn ($k, $t) => [$t => [$t, $k]])->all();
    }

    #[Test]
    #[DataProvider('tabelLunak')]
    public function tabel_lunak_punya_kolom_dan_trait_sekaligus(string $tabel, string $kelas): void
    {
        // Keduanya, bukan salah satu. Trait tanpa kolom membuat setiap kueri
        // gagal dengan galat SQL; kolom tanpa trait membuat delete() menghapus
        // permanen padahal kolomnya ada dan seolah-olah aman.
        $this->assertTrue(Schema::hasColumn($tabel, 'deleted_at'),
            "Tabel {$tabel} tidak punya kolom deleted_at.");

        $this->assertContains(SoftDeletes::class, class_uses_recursive($kelas),
            "{$kelas} tidak memakai trait SoftDeletes, jadi delete() menghapus permanen.");
    }

    #[Test]
    #[DataProvider('tabelLunak')]
    public function tabel_lunak_menyembunyikan_lalu_mengembalikan(string $tabel, string $kelas): void
    {
        $baris = $this->satuBaris($kelas);

        if ($baris === null) {
            $this->markTestSkipped("Belum ada cara membuat satu baris {$tabel} di uji ini.");
        }

        $baris->delete();

        $this->assertSoftDeleted($tabel, ['id' => $baris->getKey()]);
        $this->assertNull($kelas::find($baris->getKey()), 'Baris terhapus masih terbaca kueri biasa.');
        $this->assertNotNull($kelas::withTrashed()->find($baris->getKey()), 'Baris terhapus hilang dari basis data.');

        $kelas::withTrashed()->find($baris->getKey())->restore();

        $this->assertNotNull($kelas::find($baris->getKey()), 'Baris tidak bisa dikembalikan.');
    }

    #[Test]
    #[DataProvider('tabelTolak')]
    public function tabel_tolak_menolak_dihapus(string $tabel, string $kelas): void
    {
        $baris = $this->satuBaris($kelas);

        if ($baris === null) {
            $this->markTestSkipped("Belum ada cara membuat satu baris {$tabel} di uji ini.");
        }

        $this->expectException(\RuntimeException::class);

        $baris->delete();
    }

    #[Test]
    #[DataProvider('tabelTolak')]
    public function tabel_tolak_tidak_memakai_soft_delete(string $tabel, string $kelas): void
    {
        // Kolom deleted_at pada tabel yang menolak dihapus akan membingungkan:
        // ia menyiratkan ada baris tersembunyi yang bisa dikembalikan, padahal
        // tidak pernah ada satu pun baris yang terhapus.
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive($kelas),
            "{$kelas} menolak dihapus, jadi ia tidak perlu SoftDeletes.");
        $this->assertFalse(Schema::hasColumn($tabel, 'deleted_at'),
            "Tabel {$tabel} menolak dihapus, jadi kolom deleted_at hanya menyesatkan.");
    }

    #[Test]
    public function tagihan_terhapus_keluar_dari_hitungan_progres(): void
    {
        // Inilah bagian soft delete yang paling sering patah diam-diam. Bila
        // baris terhapus masih ikut dijumlahkan, progresnya salah TANPA satu
        // pun galat — dan salahnya baru ketahuan saat dibandingkan dengan
        // hitungan tangan menjelang unggah.
        //
        // Yang diuji: satu elemen yang BELUM selesai karena masih ada tagihan
        // tertahan. Menghapus tagihan penahannya harus membuat elemen itu
        // terhitung selesai dan bobotnya masuk. Kalau kueri progres tidak
        // menghormati deleted_at, angkanya tidak akan bergerak sama sekali.
        $this->seed(DemoSeeder::class);

        $periode = Periode::aktif()->firstOrFail();
        $progres = app(RingkasanProgres::class);

        $elemen = Elemen::whereIn(
            'id',
            Tagihan::where('periode_id', $periode->id)
                ->whereNotNull('elemen_id')
                ->where('status', '!=', StatusTagihan::Disetujui)
                ->pluck('elemen_id')
                ->unique(),
        )->firstOrFail();

        $penahan = Tagihan::where('periode_id', $periode->id)
            ->where('elemen_id', $elemen->id)
            ->where('status', '!=', StatusTagihan::Disetujui)
            ->get();

        $sebelum = $progres->bobotSelesai($periode);

        $penahan->each->delete();

        $this->assertEqualsWithDelta(
            $sebelum + (float) $elemen->bobot,
            $progres->bobotSelesai($periode),
            0.001,
            'Tagihan yang dihapus masih dihitung sebagai penahan; kueri progres mengabaikan deleted_at.',
        );

        $penahan->each->restore();

        $this->assertEqualsWithDelta($sebelum, $progres->bobotSelesai($periode), 0.001,
            'Bobot tidak kembali seperti semula setelah tagihan dipulihkan.');
    }

    #[Test]
    public function bukti_terhapus_keluar_dari_hitungan_elemen_tanpa_bukti(): void
    {
        $this->seed(DemoSeeder::class);

        $periode = Periode::aktif()->firstOrFail();
        $progres = app(RingkasanProgres::class);

        $sebelum = $progres->elemenTanpaBukti($periode)->count();

        // Satu bukti yang menopang tepat satu elemen; menghapusnya harus
        // membuat elemen itu kembali dihitung sebagai elemen tanpa bukti.
        $bukti = Bukti::where('periode_id', $periode->id)
            ->withCount('elemen')
            ->having('elemen_count', '=', 1)
            ->first();

        if ($bukti === null) {
            $this->markTestSkipped('DemoSeeder tidak menghasilkan bukti bertautan tunggal.');
        }

        $bukti->delete();

        $this->assertGreaterThanOrEqual($sebelum, $progres->elemenTanpaBukti($periode)->count(),
            'Bukti yang dihapus masih dianggap menopang elemennya.');
    }

    #[Test]
    public function setiap_model_sudah_diputuskan_nasibnya(): void
    {
        $terdaftar = array_merge(
            array_values(self::LUNAK),
            array_values(self::TOLAK),
            array_values(self::REFERENSI),
            // Pivot murni: tidak punya model sendiri, ikut induknya.
            [BuktiElemen::class],
        );

        $semua = collect(glob(app_path('Models/*.php')))
            ->map(fn (string $f) => 'App\\Models\\'.basename($f, '.php'))
            ->values();

        $belum = $semua->diff($terdaftar)->values()->all();

        $this->assertSame([], $belum,
            "Model berikut belum diputuskan: boleh dihapus lunak, ditolak, atau referensi?\n"
            ."Daftarkan di PenghapusanDataTest supaya keputusannya tertulis.\n"
            .implode("\n", $belum));
    }

    #[Test]
    public function tabel_referensi_tidak_bisa_ditulis_dari_layar(): void
    {
        $u = User::factory()->peran(PeranPengguna::Ketua)->create([
            'prodi_id' => Prodi::create([
                'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
                'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
            ])->id,
        ]);

        $policy = new ReferensiPolicy;
        $palsu = new Elemen;

        $this->assertFalse($policy->create($u));
        $this->assertFalse($policy->delete($u, $palsu));
        $this->assertFalse($policy->forceDelete($u, $palsu));
    }

    // --- pembuat baris seperlunya -------------------------------------------

    private function satuBaris(string $kelas): ?Model
    {
        $this->seed(DemoSeeder::class);

        return match ($kelas) {
            Prodi::class => Prodi::first(),
            Periode::class => Periode::first(),
            Pokja::class => Pokja::first(),
            User::class => User::first(),
            Tagihan::class => Tagihan::first(),
            Bukti::class => Bukti::first(),
            Narasi::class => Narasi::first(),
            DkpsBaris::class => DkpsBaris::first(),
            Penilaian::class => Penilaian::first(),
            NilaiRumus::class => NilaiRumus::first(),
            StatusSyaratPerlu::class => StatusSyaratPerlu::first(),
            TagihanRiwayat::class => TagihanRiwayat::first(),
            NarasiVersi::class => NarasiVersi::first(),
            Komentar::class => Komentar::create([
                'commentable_type' => Tagihan::class,
                'commentable_id' => Tagihan::first()->id,
                'user_id' => User::first()->id,
                'isi' => 'Catatan uji.',
            ]),
            Simulasi::class => Simulasi::create([
                'prodi_id' => Prodi::first()->id,
                'periode_id' => Periode::first()->id,
                'jenis' => 'skor', 'nama' => 'Uji',
                'dibuat_oleh' => User::first()->id,
            ]),
            LogAktivitas::class => LogAktivitas::create([
                'user_id' => User::first()->id,
                'aksi' => 'uji.catat',
            ]),
            ImpersonasiSesi::class => ImpersonasiSesi::create([
                'admin_id' => User::first()->id,
                'target_id' => User::skip(1)->first()->id,
                'alasan' => 'uji',
            ]),
            ImporBatch::class => ImporBatch::create([
                'periode_id' => Periode::first()->id,
                'profil' => 'bukti',
                'dijalankan_oleh' => User::first()->id,
            ]),
            default => null,
        };
    }
}
