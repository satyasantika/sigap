<?php

namespace Tests\Feature;

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
        'prodi' => \App\Models\Prodi::class,
        'periode' => \App\Models\Periode::class,
        'pokja' => \App\Models\Pokja::class,
        'users' => \App\Models\User::class,
        'tagihan' => \App\Models\Tagihan::class,
        'bukti' => \App\Models\Bukti::class,
        'narasi' => \App\Models\Narasi::class,
        'dkps_baris' => \App\Models\DkpsBaris::class,
        'penilaian' => \App\Models\Penilaian::class,
        'komentar' => \App\Models\Komentar::class,
        'simulasi' => \App\Models\Simulasi::class,
    ];

    /** @var array<string, class-string<Model>> */
    private const TOLAK = [
        'tagihan_riwayat' => \App\Models\TagihanRiwayat::class,
        'narasi_versi' => \App\Models\NarasiVersi::class,
        'log_aktivitas' => \App\Models\LogAktivitas::class,
        'nilai_rumus' => \App\Models\NilaiRumus::class,
        'status_syarat_perlu' => \App\Models\StatusSyaratPerlu::class,
        'impor_batch' => \App\Models\ImporBatch::class,
        'impersonasi_sesi' => \App\Models\ImpersonasiSesi::class,
    ];

    /** Referensi instrumen: tidak bisa ditulis sama sekali dari layar. */
    private const REFERENSI = [
        'elemen' => \App\Models\Elemen::class,
        'kriteria' => \App\Models\Kriteria::class,
        'rumus' => \App\Models\Rumus::class,
        'syarat_perlu' => \App\Models\SyaratPerlu::class,
        'dkps_butir' => \App\Models\DkpsButir::class,
        'izin' => \App\Models\Izin::class,
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
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $periode = \App\Models\Periode::aktif()->firstOrFail();
        $progres = app(\App\Services\RingkasanProgres::class);

        $elemen = \App\Models\Elemen::whereIn(
            'id',
            \App\Models\Tagihan::where('periode_id', $periode->id)
                ->whereNotNull('elemen_id')
                ->where('status', '!=', \App\Enums\StatusTagihan::Disetujui)
                ->pluck('elemen_id')
                ->unique(),
        )->firstOrFail();

        $penahan = \App\Models\Tagihan::where('periode_id', $periode->id)
            ->where('elemen_id', $elemen->id)
            ->where('status', '!=', \App\Enums\StatusTagihan::Disetujui)
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
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $periode = \App\Models\Periode::aktif()->firstOrFail();
        $progres = app(\App\Services\RingkasanProgres::class);

        $sebelum = $progres->elemenTanpaBukti($periode)->count();

        // Satu bukti yang menopang tepat satu elemen; menghapusnya harus
        // membuat elemen itu kembali dihitung sebagai elemen tanpa bukti.
        $bukti = \App\Models\Bukti::where('periode_id', $periode->id)
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
            [\App\Models\BuktiElemen::class],
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
        $u = \App\Models\User::factory()->peran(\App\Enums\PeranPengguna::Ketua)->create([
            'prodi_id' => \App\Models\Prodi::create([
                'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
                'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
            ])->id,
        ]);

        $policy = new \App\Policies\ReferensiPolicy;
        $palsu = new \App\Models\Elemen;

        $this->assertFalse($policy->create($u));
        $this->assertFalse($policy->delete($u, $palsu));
        $this->assertFalse($policy->forceDelete($u, $palsu));
    }

    // --- pembuat baris seperlunya -------------------------------------------

    private function satuBaris(string $kelas): ?Model
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);

        return match ($kelas) {
            \App\Models\Prodi::class => \App\Models\Prodi::first(),
            \App\Models\Periode::class => \App\Models\Periode::first(),
            \App\Models\Pokja::class => \App\Models\Pokja::first(),
            \App\Models\User::class => \App\Models\User::first(),
            \App\Models\Tagihan::class => \App\Models\Tagihan::first(),
            \App\Models\Bukti::class => \App\Models\Bukti::first(),
            \App\Models\Narasi::class => \App\Models\Narasi::first(),
            \App\Models\DkpsBaris::class => \App\Models\DkpsBaris::first(),
            \App\Models\Penilaian::class => \App\Models\Penilaian::first(),
            \App\Models\NilaiRumus::class => \App\Models\NilaiRumus::first(),
            \App\Models\StatusSyaratPerlu::class => \App\Models\StatusSyaratPerlu::first(),
            \App\Models\TagihanRiwayat::class => \App\Models\TagihanRiwayat::first(),
            \App\Models\NarasiVersi::class => \App\Models\NarasiVersi::first(),
            \App\Models\Komentar::class => \App\Models\Komentar::create([
                'commentable_type' => \App\Models\Tagihan::class,
                'commentable_id' => \App\Models\Tagihan::first()->id,
                'user_id' => \App\Models\User::first()->id,
                'isi' => 'Catatan uji.',
            ]),
            \App\Models\Simulasi::class => \App\Models\Simulasi::create([
                'prodi_id' => \App\Models\Prodi::first()->id,
                'periode_id' => \App\Models\Periode::first()->id,
                'jenis' => 'skor', 'nama' => 'Uji',
                'dibuat_oleh' => \App\Models\User::first()->id,
            ]),
            \App\Models\LogAktivitas::class => \App\Models\LogAktivitas::create([
                'user_id' => \App\Models\User::first()->id,
                'aksi' => 'uji.catat',
            ]),
            \App\Models\ImpersonasiSesi::class => \App\Models\ImpersonasiSesi::create([
                'admin_id' => \App\Models\User::first()->id,
                'target_id' => \App\Models\User::skip(1)->first()->id,
                'alasan' => 'uji',
            ]),
            \App\Models\ImporBatch::class => \App\Models\ImporBatch::create([
                'periode_id' => \App\Models\Periode::first()->id,
                'profil' => 'bukti',
                'dijalankan_oleh' => \App\Models\User::first()->id,
            ]),
            default => null,
        };
    }
}
