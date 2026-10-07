<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Support\Izin;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kriteria terima Tahap 1 di luar matriks izin.
 * Lihat vibecoding/docs/06-kriteria-terima.md bagian Tahap 1.
 */
class FondasiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Isi berkas PHP tanpa komentar.
     *
     * Tanpa ini, kalimat "tidak ada Gate::before" di dalam docblock Izin.php
     * ikut tertangkap dan uji gagal karena dokumentasinya sendiri — positif
     * palsu yang menggoda orang melonggarkan ujinya.
     */
    private function kodeTanpaKomentar(string $jalur): string
    {
        $keluar = '';

        foreach (token_get_all(file_get_contents($jalur)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $keluar .= $token[1];
            } else {
                $keluar .= $token;
            }
        }

        return $keluar;
    }

    #[Test]
    public function tidak_ada_kunci_menaik_di_seluruh_migrasi(): void
    {
        $terlarang = [];

        foreach (glob(database_path('migrations/*.php')) as $berkas) {
            $isi = file_get_contents($berkas);

            foreach (['bigIncrements', '->id(', 'increments('] as $pola) {
                if (str_contains($isi, $pola)) {
                    $terlarang[] = basename($berkas).' memakai '.$pola;
                }
            }
        }

        $this->assertSame([], $terlarang,
            "aturan proyek 4b: seluruh kunci utama UUID.\n".implode("\n", $terlarang));
    }

    #[Test]
    public function id_yang_dibangkitkan_berformat_uuidv7(): void
    {
        $prodi = Prodi::create([
            'kode' => 'X', 'nama' => 'X', 'jenjang' => 'ppg',
            'upps' => 'X', 'perguruan_tinggi' => 'X', 'aktif' => true,
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $prodi->id,
            'Id harus UUIDv7 — digit versi (karakter ke-15) bernilai 7.'
        );
        $this->assertSame('7', substr($prodi->id, 14, 1));
    }

    #[Test]
    public function tidak_ada_perbandingan_peran_di_luar_kelas_izin(): void
    {
        $pelanggar = [];
        $berkas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($berkas as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }

            $jalur = $f->getPathname();

            // Izin.php memang tempatnya; enum PeranPengguna hanya memetakan label.
            if (str_ends_with($jalur, 'Support/Izin.php') || str_ends_with($jalur, 'Enums/PeranPengguna.php')) {
                continue;
            }

            $isi = $this->kodeTanpaKomentar($jalur);

            foreach (['peran ===', 'peran ==', 'peran->value ===', 'peran->value =='] as $pola) {
                if (str_contains($isi, $pola)) {
                    $pelanggar[] = str_replace(base_path().'/', '', $jalur).' memakai '.$pola;
                }
            }
        }

        $this->assertSame([], $pelanggar,
            "aturan proyek 10: otorisasi hanya diputuskan di App\\Support\\Izin.\n".implode("\n", $pelanggar));
    }

    #[Test]
    public function tidak_ada_gate_before(): void
    {
        $pelanggar = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iter as $f) {
            if ($f->getExtension() === 'php' && str_contains($this->kodeTanpaKomentar($f->getPathname()), 'Gate::before')) {
                $pelanggar[] = str_replace(base_path().'/', '', $f->getPathname());
            }
        }

        $this->assertSame([], $pelanggar, 'Tidak boleh ada Gate::before — tidak ada peran super.');
    }

    #[Test]
    public function penulisan_ke_periode_terkunci_ditolak_untuk_semua_peran(): void
    {
        $this->seed(IzinSeeder::class);

        $prodi = Prodi::create([
            'kode' => 'X', 'nama' => 'X', 'jenjang' => 'ppg',
            'upps' => 'X', 'perguruan_tinggi' => 'X', 'aktif' => true,
        ]);
        $terkunci = Periode::create([
            'prodi_id' => $prodi->id, 'nama' => 'Terkunci', 'ts_tahun' => 2027,
            'versi_instrumen' => 'IAPSK 3.0', 'status' => StatusPeriode::Dikunci,
        ]);

        $aksiTulis = ['tagihan.buat', 'tagihan.setujui', 'bukti.unggah', 'narasi.tulis', 'dkps.isi'];

        foreach (PeranPengguna::cases() as $peran) {
            $u = User::create([
                'name' => $peran->value, 'nama_lengkap' => $peran->value,
                'email' => $peran->value.'@kunci.test', 'password' => 'rahasia123',
                'peran' => $peran, 'prodi_id' => $prodi->id, 'aktif' => true,
            ]);

            foreach ($aksiTulis as $aksi) {
                $this->assertFalse(
                    Izin::boleh($u, $aksi, $terkunci),
                    "Periode terkunci: {$peran->value} masih boleh {$aksi}."
                );
            }
        }
    }

    #[Test]
    public function admin_tetap_bisa_membuka_periode_yang_terkunci(): void
    {
        $this->seed(IzinSeeder::class);

        $prodi = Prodi::create([
            'kode' => 'X', 'nama' => 'X', 'jenjang' => 'ppg',
            'upps' => 'X', 'perguruan_tinggi' => 'X', 'aktif' => true,
        ]);
        $terkunci = Periode::create([
            'prodi_id' => $prodi->id, 'nama' => 'Terkunci', 'ts_tahun' => 2027,
            'versi_instrumen' => 'IAPSK 3.0', 'status' => StatusPeriode::Dikunci,
        ]);

        $admin = User::create([
            'name' => 'admin', 'nama_lengkap' => 'Admin', 'email' => 'a@kunci.test',
            'password' => 'rahasia123', 'peran' => PeranPengguna::Admin,
            'prodi_id' => $prodi->id, 'aktif' => true,
        ]);

        // Tanpa jalan keluar ini, periode yang keliru dikunci tidak bisa dibuka
        // oleh siapa pun — sistem mengunci dirinya sendiri.
        $this->assertTrue(Izin::boleh($admin, 'periode.kelola', $terkunci));
        $this->assertTrue(Izin::boleh($admin, 'periode.kunci', $terkunci));
    }

    #[Test]
    public function pengguna_nonaktif_tidak_bisa_masuk(): void
    {
        $this->seed(IzinSeeder::class);

        $prodi = Prodi::create([
            'kode' => 'X', 'nama' => 'X', 'jenjang' => 'ppg',
            'upps' => 'X', 'perguruan_tinggi' => 'X', 'aktif' => true,
        ]);

        $nonaktif = User::create([
            'name' => 'n', 'nama_lengkap' => 'Nonaktif', 'email' => 'n@uji.test',
            'password' => 'rahasia123', 'peran' => PeranPengguna::Ketua,
            'prodi_id' => $prodi->id, 'aktif' => false,
        ]);

        $this->assertFalse($nonaktif->canAccessPanel(filament()->getPanel('panel')));

        // Nonaktif juga kehilangan seluruh wewenang, bukan sekadar akses panel.
        $this->assertFalse(Izin::boleh($nonaktif, 'tagihan.setujui'));
        $this->assertFalse(Izin::boleh($nonaktif, 'dasbor.lihat'));
    }

    #[Test]
    public function tidak_ada_rute_pendaftaran_verifikasi_atau_lupa_sandi(): void
    {
        $terlarang = ['register', 'verify-email', 'forgot-password', 'reset-password', 'confirm-password'];

        foreach (app('router')->getRoutes() as $rute) {
            foreach ($terlarang as $pola) {
                $this->assertStringNotContainsString(
                    $pola, $rute->uri(),
                    "Rute terlarang masih terpasang: {$rute->uri()} — lihat vibecoding/docs/08-auth-dan-izin.md."
                );
            }
        }
    }

    #[Test]
    public function seeder_idempoten_lewat_kunci_alami(): void
    {
        $this->seed(DatabaseSeeder::class);

        $idProdi = Prodi::first()->id;
        $cacah = [
            'izin' => \App\Models\Izin::count(),
            'prodi' => Prodi::count(),
            'periode' => Periode::count(),
            'pokja' => Pokja::count(),
            'user' => User::count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($cacah, [
            'izin' => \App\Models\Izin::count(),
            'prodi' => Prodi::count(),
            'periode' => Periode::count(),
            'pokja' => Pokja::count(),
            'user' => User::count(),
        ], 'Menjalankan seeder dua kali tidak boleh menggandakan baris.');

        $this->assertSame($idProdi, Prodi::first()->id, 'Baris yang sama harus dipakai ulang, bukan dibuat baru.');
    }

    #[Test]
    public function seed_awal_menghasilkan_organisasi_yang_diharapkan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Prodi::count());
        $this->assertSame(6, Pokja::count(), 'Enam pokja dari data/pokja.json.');
        $this->assertSame(6, User::count(), 'Satu pengguna contoh per peran.');

        $periode = Periode::first();
        $this->assertSame('PPG 2027', $periode->nama);
        $this->assertSame(2027, $periode->ts_tahun);
        $this->assertSame(StatusPeriode::Berjalan, $periode->status);

        foreach (PeranPengguna::cases() as $peran) {
            $this->assertTrue(
                User::where('peran', $peran)->exists(),
                "Tidak ada pengguna contoh berperan {$peran->value}."
            );
        }

        $this->assertTrue(
            Pokja::where('kode', 'POKJA-DATA')->exists(),
            'POKJA-DATA harus ada meski tidak memegang elemen.'
        );
    }

    #[Test]
    public function tidak_ada_tahun_yang_ditulis_mati(): void
    {
        $pelanggar = [];

        foreach ([app_path(), database_path('migrations')] as $akar) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar));

            foreach ($iter as $f) {
                if ($f->getExtension() !== 'php') {
                    continue;
                }

                if (preg_match('/\b(2026|2027)\b/', $this->kodeTanpaKomentar($f->getPathname()))) {
                    $pelanggar[] = str_replace(base_path().'/', '', $f->getPathname());
                }
            }
        }

        $this->assertSame([], $pelanggar,
            "aturan proyek 3: tahun selalu diturunkan dari periode.ts_tahun.\n".implode("\n", $pelanggar));
    }
}
