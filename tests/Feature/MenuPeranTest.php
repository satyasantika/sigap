<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Pages\Dasbor;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Support\Izin;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Menu yang muncul harus sesuai peran — seluruh 120 sel ditelusuri.
 *
 * Sebelum uji ini ada, `viewAny` pada enam Policy dipetakan ke `dasbor.lihat`
 * yang bernilai `ya` untuk semua peran. Akibatnya anggota pokja melihat menu
 * Periode, Pokja, Prodi, dan Pengguna. Tidak ada yang gagal karenanya —
 * Policy tetap menolak saat tombolnya ditekan — tetapi menu yang penuh
 * halaman yang tidak boleh disentuh adalah cara paling cepat membuat orang
 * berhenti percaya bahwa sistem tahu siapa mereka.
 *
 * Yang dijaga di sini HANYA tampilan menu. Penolakan tetap milik Policy dan
 * diuji MatriksIzinTest; menu yang hilang tidak boleh menjadi satu-satunya
 * pagar (aturan 7).
 */
class MenuPeranTest extends TestCase
{
    use RefreshDatabase;

    /** Peta kode menu ke kelas Filament yang mendaftarkannya. */
    private const KELAS = [
        'dasbor' => Dasbor::class,
        'tagihan_saya' => \App\Filament\Pages\TagihanSaya::class,
        'tagihan' => \App\Filament\Resources\Tagihans\TagihanResource::class,
        'bukti' => \App\Filament\Resources\Buktis\BuktiResource::class,
        'narasi' => \App\Filament\Resources\Narasis\NarasiResource::class,
        'penilaian' => \App\Filament\Resources\Penilaians\PenilaianResource::class,
        'syarat_perlu' => \App\Filament\Pages\SyaratPerluLayar::class,
        'simulasi' => \App\Filament\Pages\SimulasiLayar::class,
        'dkps_baris' => \App\Filament\Resources\DkpsBaris\DkpsBarisResource::class,
        'nilai_rumus' => \App\Filament\Resources\NilaiRumuses\NilaiRumusResource::class,
        'elemen' => \App\Filament\Resources\Elemens\ElemenResource::class,
        'kriteria' => \App\Filament\Resources\Kriterias\KriteriaResource::class,
        'rumus' => \App\Filament\Resources\Rumuses\RumusResource::class,
        'dkps_butir' => \App\Filament\Resources\DkpsButirs\DkpsButirResource::class,
        'periode' => \App\Filament\Resources\Periodes\PeriodeResource::class,
        'pokja' => \App\Filament\Resources\Pokjas\PokjaResource::class,
        'pengguna' => \App\Filament\Resources\Users\UserResource::class,
        'prodi' => \App\Filament\Resources\Prodis\ProdiResource::class,
        'matriks_izin' => \App\Filament\Pages\MatriksIzin::class,
        'log_aktivitas' => \App\Filament\Pages\LogAktivitasLayar::class,
    ];

    /**
     * Seluruh sel data/menu.json sebagai kasus uji.
     *
     * Dibangkitkan dari berkasnya, bukan ditulis ulang: menambah menu baru di
     * JSON otomatis menambah enam kasus uji di sini.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function selMenu(): array
    {
        $isi = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/data/menu.json'),
            true, flags: JSON_THROW_ON_ERROR
        );

        $kasus = [];

        foreach ($isi['menu'] as $menu) {
            foreach ($isi['peran'] as $peran) {
                $kasus["{$menu['kode']} / {$peran}"] = [$menu['kode'], $peran, $menu[$peran]];
            }
        }

        return $kasus;
    }

    private function dunia(): Prodi
    {
        $this->seed(\Database\Seeders\IzinSeeder::class);

        return Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
    }

    #[Test]
    #[DataProvider('selMenu')]
    public function setiap_sel_matriks_menu_ditelusuri(string $menu, string $peran, string $harapan): void
    {
        $prodi = $this->dunia();
        $u = User::factory()->peran(PeranPengguna::from($peran))->create(['prodi_id' => $prodi->id]);

        // `pokja_data` hanya berlaku bila penggunanya memang terdaftar di sana.
        // Diuji dua arah supaya lingkupnya tidak diam-diam berubah jadi `ya`.
        if ($harapan === 'pokja_data') {
            $this->assertFalse(Izin::bolehMenu($u, $menu),
                "Sel {$menu}.{$peran} berlingkup pokja_data, jadi tanpa keanggotaan harus tersembunyi.");

            $periode = \App\Models\Periode::create([
                'prodi_id' => $prodi->id, 'nama' => 'PPG Uji', 'ts_tahun' => 2027,
                'versi_instrumen' => 'IAPSK 3.0', 'status' => 'berjalan',
            ]);
            $pd = Pokja::create(['periode_id' => $periode->id, 'kode' => 'POKJA-DATA', 'nama' => 'Data']);
            $u->pokja()->attach($pd->id);
            $u->refresh();

            $this->assertTrue(Izin::bolehMenu($u, $menu),
                "Sel {$menu}.{$peran} harus muncul begitu penggunanya masuk POKJA-DATA.");

            return;
        }

        $this->assertSame($harapan === 'ya', Izin::bolehMenu($u, $menu),
            "Sel {$menu}.{$peran} seharusnya {$harapan}.");
    }

    #[Test]
    #[DataProvider('selMenu')]
    public function kelas_filament_mendaftarkan_menu_sesuai_matriks(string $menu, string $peran, string $harapan): void
    {
        $prodi = $this->dunia();
        $u = User::factory()->peran(PeranPengguna::from($peran))->create(['prodi_id' => $prodi->id]);

        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('panel'));

        $kelas = self::KELAS[$menu];

        $this->assertSame(
            $harapan === 'ya',
            $kelas::shouldRegisterNavigation(),
            "{$kelas}::shouldRegisterNavigation() tidak sesuai sel {$menu}.{$peran} = {$harapan}."
        );
    }

    #[Test]
    public function pengguna_nonaktif_tidak_melihat_menu_apa_pun(): void
    {
        $prodi = $this->dunia();
        $u = User::factory()->peran(PeranPengguna::Admin)->create(['prodi_id' => $prodi->id]);
        $u->update(['aktif' => false]);

        foreach (array_keys(self::KELAS) as $menu) {
            $this->assertFalse(Izin::bolehMenu($u->fresh(), $menu), "Menu {$menu} bocor ke pengguna nonaktif.");
        }
    }

    #[Test]
    public function matriks_menu_memuat_setiap_kelas_yang_terdaftar_di_panel(): void
    {
        $matriks = Izin::matriksMenu();

        $this->assertCount(count(self::KELAS), $matriks,
            'Ada menu di data/menu.json yang tidak punya kelas, atau sebaliknya.');

        foreach (array_keys(self::KELAS) as $kode) {
            $this->assertArrayHasKey($kode, $matriks, "Menu {$kode} belum ada di data/menu.json.");
        }
    }

    /** @return array<string, array{string, array<int, string>, array<int, string>}> */
    public static function harapanSidebar(): array
    {
        $isi = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/data/menu.json'),
            true, flags: JSON_THROW_ON_ERROR
        );

        $kasus = [];

        foreach ($isi['peran'] as $peran) {
            $muncul = [];
            $hilang = [];

            foreach ($isi['menu'] as $menu) {
                // `pokja_data` dilewati: pengguna contoh DemoSeeder tidak semuanya
                // terdaftar di POKJA-DATA, jadi harapannya bergantung pada seeder,
                // bukan pada matriks. Sudah ditelusuri uji di atas.
                if ($menu[$peran] === 'pokja_data') {
                    continue;
                }

                if ($menu[$peran] === 'ya') {
                    $muncul[] = $menu['label'];
                } else {
                    $hilang[] = $menu['label'];
                }
            }

            $kasus[$peran] = [$peran, $muncul, $hilang];
        }

        return $kasus;
    }

    #[Test]
    #[DataProvider('harapanSidebar')]
    public function sidebar_yang_benar_benar_dirender_sesuai_matriks(string $peran, array $muncul, array $hilang): void
    {
        // Uji ini memeriksa HTML yang sungguh dikirim, bukan hanya nilai
        // shouldRegisterNavigation(): Filament bisa saja menyaring ulang lewat
        // canAccess, dan yang dilihat pengguna adalah hasil akhirnya.
        $this->seed(DemoSeeder::class);

        $u = User::where('email', "{$peran}@sigap.test")->firstOrFail();
        $u->forceFill(['wajib_ganti_sandi' => false])->save();

        $html = $this->actingAs($u)->get(Dasbor::getUrl())->assertOk()->getContent();

        // Atribut Alpine muncul sebelum class="fi-sidebar-item-label", jadi
        // polanya harus longgar di depan. Mencocokkan span secara utuh pernah
        // membuat uji ini lulus tanpa menemukan satu pun label.
        preg_match_all('#class="fi-sidebar-item-label"\s*>\s*([^<]+?)\s*<#s', $html, $label);
        $terlihat = array_map('trim', $label[1]);

        $this->assertNotEmpty($terlihat, "Sidebar peran {$peran} tidak terbaca sama sekali — polanya yang salah, bukan menunya.");

        foreach ($muncul as $m) {
            $this->assertContains($m, $terlihat, "Peran {$peran} seharusnya melihat menu \"{$m}\".");
        }

        foreach ($hilang as $h) {
            $this->assertNotContains($h, $terlihat, "Peran {$peran} TIDAK boleh melihat menu \"{$h}\".");
        }
    }
}
