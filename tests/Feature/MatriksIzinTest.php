<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Support\Izin;
use Database\Seeders\IzinSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menelusuri SELURUH 144 sel matriks izin (24 aksi x 6 peran).
 *
 * Data provider DIBANGKITKAN dari data/izin.json, bukan ditulis satu per satu.
 * Akibatnya: menambah aksi baru di JSON otomatis menambah kasus uji di sini,
 * dan tidak ada sel yang bisa lolos tanpa diperiksa.
 *
 * Tahap 1 menguji keputusan `Izin::boleh` secara langsung. Sembilan aksi
 * `tagihan.*` dan beberapa lainnya menyasar obyek yang baru lahir di tahap 3-4,
 * jadi assertion 403 lewat HTTP ditambahkan per tahap begitu Resource-nya ada.
 */
class MatriksIzinTest extends TestCase
{
    use RefreshDatabase;

    /** Obyek tiruan seperlunya, dibuat sekali per kasus. */
    private function dunia(): array
    {
        $prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);

        $periode = Periode::create([
            'prodi_id' => $prodi->id, 'nama' => 'PPG Uji 2027',
            'ts_tahun' => 2027, 'versi_instrumen' => 'IAPSK 3.0', 'status' => 'berjalan',
        ]);

        $pokjaDik = Pokja::create([
            'periode_id' => $periode->id, 'kode' => 'POKJA-DIK', 'nama' => 'Pendidikan',
        ]);
        $pokjaLain = Pokja::create([
            'periode_id' => $periode->id, 'kode' => 'POKJA-SDM', 'nama' => 'SDM',
        ]);
        $pokjaData = Pokja::create([
            'periode_id' => $periode->id, 'kode' => 'POKJA-DATA', 'nama' => 'Data dan DKPS',
        ]);

        return compact('prodi', 'periode', 'pokjaDik', 'pokjaLain', 'pokjaData');
    }

    private function pengguna(string $peran, array $d, array $pokja = []): User
    {
        $u = User::create([
            'name' => $peran, 'nama_lengkap' => "Uji {$peran}",
            'email' => $peran.'-'.uniqid().'@uji.test', 'password' => 'rahasia123',
            'peran' => PeranPengguna::from($peran), 'prodi_id' => $d['prodi']->id,
            'aktif' => true,
        ]);

        foreach ($pokja as $p) {
            $u->pokja()->attach($p->id);
        }

        return $u;
    }

    /**
     * Baris berisi (kode aksi, kode peran, nilai lingkup) untuk 144 sel.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function selMatriks(): array
    {
        // Data provider berjalan SEBELUM aplikasi boot, jadi base_path() belum
        // tersedia. Path diresolusi relatif terhadap berkas ini.
        $berkas = dirname(__DIR__, 2).'/data/izin.json';

        $isi = json_decode(file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);

        $baris = [];

        foreach ($isi['aksi'] as $aksi) {
            foreach ($isi['peran'] as $peran) {
                $baris["{$aksi['kode']} / {$peran}"] = [$aksi['kode'], $peran, $aksi[$peran]];
            }
        }

        return $baris;
    }

    #[Test]
    #[DataProvider('selMatriks')]
    public function setiap_sel_diputuskan_sesuai_izin_json(string $aksi, string $peran, string $lingkup): void
    {
        $this->seed(IzinSeeder::class);
        $d = $this->dunia();

        match ($lingkup) {
            'ya' => $this->pastikanYa($aksi, $peran, $d),
            'tidak' => $this->pastikanTidak($aksi, $peran, $d),
            'pokjanya' => $this->pastikanPokjanya($aksi, $peran, $d),
            'miliknya' => $this->pastikanMiliknya($aksi, $peran, $d),
            'pokja_data' => $this->pastikanPokjaData($aksi, $peran, $d),
        };
    }

    private function pastikanYa(string $aksi, string $peran, array $d): void
    {
        $u = $this->pengguna($peran, $d);

        $this->assertTrue(
            Izin::boleh($u, $aksi),
            "[{$aksi} / {$peran}] bernilai `ya` tetapi ditolak."
        );
    }

    private function pastikanTidak(string $aksi, string $peran, array $d): void
    {
        // Diuji dengan keanggotaan paling longgar yang mungkin: kalau toh
        // ditolak walau ia anggota semua pokja, penolakannya benar-benar mutlak.
        $u = $this->pengguna($peran, $d, [$d['pokjaDik'], $d['pokjaData']]);

        $this->assertFalse(
            Izin::boleh($u, $aksi),
            "[{$aksi} / {$peran}] bernilai `tidak` tetapi diizinkan."
        );
        $this->assertFalse(
            Izin::boleh($u, $aksi, $this->barisPokja($d['pokjaDik'], $u)),
            "[{$aksi} / {$peran}] bernilai `tidak` tetapi diizinkan atas baris pokjanya sendiri."
        );
    }

    private function pastikanPokjanya(string $aksi, string $peran, array $d): void
    {
        $u = $this->pengguna($peran, $d, [$d['pokjaDik']]);

        $this->assertTrue(
            Izin::boleh($u, $aksi, $this->barisPokja($d['pokjaDik'], $u)),
            "[{$aksi} / {$peran}] `pokjanya` ditolak di pokjanya sendiri."
        );
        $this->assertFalse(
            Izin::boleh($u, $aksi, $this->barisPokja($d['pokjaLain'], $u)),
            "[{$aksi} / {$peran}] `pokjanya` diizinkan di pokja orang lain."
        );
        $this->assertTrue(
            Izin::boleh($u, $aksi),
            "[{$aksi} / {$peran}] `pokjanya` menolak halaman daftar (obyek null)."
        );
    }

    private function pastikanMiliknya(string $aksi, string $peran, array $d): void
    {
        $u = $this->pengguna($peran, $d, [$d['pokjaDik']]);
        $lain = $this->pengguna($peran, $d, [$d['pokjaDik']]);

        $this->assertTrue(
            Izin::boleh($u, $aksi, $this->barisPokja($d['pokjaDik'], $u)),
            "[{$aksi} / {$peran}] `miliknya` ditolak atas barisnya sendiri."
        );
        $this->assertFalse(
            Izin::boleh($u, $aksi, $this->barisPokja($d['pokjaDik'], $lain)),
            "[{$aksi} / {$peran}] `miliknya` diizinkan atas baris orang lain."
        );
    }

    private function pastikanPokjaData(string $aksi, string $peran, array $d): void
    {
        $anggota = $this->pengguna($peran, $d, [$d['pokjaData']]);
        $bukan = $this->pengguna($peran, $d, [$d['pokjaDik']]);

        $this->assertTrue(
            Izin::boleh($anggota, $aksi),
            "[{$aksi} / {$peran}] `pokja_data` ditolak untuk anggota POKJA-DATA."
        );
        $this->assertFalse(
            Izin::boleh($bukan, $aksi),
            "[{$aksi} / {$peran}] `pokja_data` diizinkan untuk yang bukan anggota POKJA-DATA."
        );
    }

    /**
     * Baris tiruan yang membawa `pokja_id` dan `penanggung_jawab_id` —
     * bentuk yang dipakai `tagihan` dan `bukti` di tahap 3-4.
     */
    private function barisPokja(Pokja $pokja, User $pj): Model
    {
        return new class($pokja, $pj) extends Model
        {
            protected $table = 'baris_tiruan';

            public function __construct(?Pokja $pokja = null, ?User $pj = null)
            {
                parent::__construct([
                    'pokja_id' => $pokja?->id,
                    'penanggung_jawab_id' => $pj?->getKey(),
                    'periode_id' => $pokja?->periode_id,
                ]);
                $this->exists = true;
            }

            protected $guarded = [];
        };
    }

    #[Test]
    public function matriks_berisi_tepat_144_sel(): void
    {
        $this->seed(IzinSeeder::class);

        $this->assertSame(144, \App\Models\Izin::count(), 'Tabel izin harus berisi 144 baris.');
        $this->assertCount(144, self::selMatriks(), 'Data provider harus membangkitkan 144 kasus.');
    }

    #[Test]
    public function tidak_ada_peran_yang_boleh_semua_aksi(): void
    {
        $this->seed(IzinSeeder::class);
        $d = $this->dunia();

        foreach (PeranPengguna::cases() as $peran) {
            $u = $this->pengguna($peran->value, $d, [$d['pokjaDik'], $d['pokjaData']]);

            $ditolak = collect(array_keys(Izin::matriks()))
                ->reject(fn (string $aksi) => Izin::boleh($u, $aksi));

            $this->assertTrue(
                $ditolak->isNotEmpty(),
                "Peran {$peran->value} boleh melakukan semua aksi — tidak boleh ada peran super."
            );
        }
    }
}
