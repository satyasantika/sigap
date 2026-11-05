<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Livewire\ImporTempel;
use App\Models\DkpsBaris;
use App\Models\ImporBatch;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PelaksanaImpor;
use App\Support\Impor\ImporKerjaSama;
use App\Support\Impor\ImporLembarDkps;
use App\Support\Impor\NormalisasiKunci;
use App\Support\Impor\PenguraiTempelan;
use App\Support\Impor\PratinjauImpor;
use App\Support\Impor\ProfilImpor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImporLembarDkpsTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->prodi = Prodi::firstOrFail();
        $this->ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();
    }

    /**
     * Jalur yang sama dengan komponen ImporTempel: urai → tebak pemetaan →
     * (pemetaan disunting) → pratinjau → jalankan dengan keputusan bawaan.
     *
     * @param  array<string, int>  $ubahPeta  medan ⇒ indeks kolom, menimpa tebakan
     * @return array{0: array, 1: ImporBatch}
     */
    private function tempel(ProfilImpor $profil, string $tempelan, array $ubahPeta = []): array
    {
        $urai = PenguraiTempelan::urai($tempelan);
        $peta = array_replace(PenguraiTempelan::tebakPemetaan($urai['kepala'], $profil->medan()), $ubahPeta);

        $terpetakan = array_map(function (array $baris) use ($peta) {
            $hasil = [];

            foreach ($peta as $kunci => $indeks) {
                $hasil[$kunci] = $indeks === null ? null : ($baris[$indeks] ?? null);
            }

            return $hasil;
        }, $urai['baris']);

        $pratinjau = PratinjauImpor::susun($terpetakan, $profil, $this->periode->id);
        $batch = app(PelaksanaImpor::class)->jalankan($pratinjau, [], $profil, $this->periode, $this->ketua);

        return [$pratinjau, $batch];
    }

    private function profil(string $tabel, string $lembar): ImporLembarDkps
    {
        return new ImporLembarDkps($this->periode->id, $this->prodi->id, $this->ketua->id, $tabel, $lembar);
    }

    #[Test]
    public function medan_mengikuti_kolom_isian_lembar(): void
    {
        $p = $this->profil('28', '28');

        $this->assertSame(['sumber', 'nama_dtps', 'judul_artikel', 'jumlah_sitasi'], array_keys($p->medan()));
        $this->assertTrue($p->medan()['jumlah_sitasi']['wajib']);
    }

    #[Test]
    public function tempelan_tabel_28_masuk_dengan_deteksi_duplikat(): void
    {
        $tempel = "Nama DTPS\tJudul\tJumlah Sitasi\n"
            ."Prof. Firmansyah\tFactors affecting\t65\n"
            ."Prof. Firmansyah\tFactors affecting\t65\n"
            ."Prof. Firmansyah\tSEIR model\t52";

        // Kepala "Judul" tidak sama dengan label lengkapnya; pengguna
        // memetakannya sendiri di langkah 2.
        [$pratinjau, $batch] = $this->tempel($this->profil('28', '28'), $tempel, ['judul_artikel' => 1]);

        $this->assertSame(
            [PratinjauImpor::BARU, PratinjauImpor::DUPLIKAT_TEMPELAN, PratinjauImpor::BARU],
            array_column($pratinjau, 'status'),
        );
        $this->assertSame(2, $batch->jumlah_impor);

        $baris = DkpsBaris::where('lembar_kode', '28')->orderBy('created_at')->get();
        $this->assertCount(2, $baris);
        $this->assertSame(65, $baris->firstWhere(fn ($b) => $b->nilai('judul_artikel') === 'Factors affecting')->nilai('jumlah_sitasi'));

        // Baris hasil impor tetap bisa dibatalkan lewat batch-nya.
        $this->assertTrue(app(PelaksanaImpor::class)->bolehDibatalkan($batch));
        $this->assertSame(2, DkpsBaris::where('impor_batch_id', $batch->id)->count());
    }

    #[Test]
    public function lembar_baris_tetap_memetakan_label_baris(): void
    {
        $tempel = "Tahun Lulus\tJumlah Lulusan\tRata-rata\nTS-2\t104\t3,84";

        [$pratinjau] = $this->tempel($this->profil('21', '21'), $tempel);

        $this->assertSame([PratinjauImpor::BARU], array_column($pratinjau, 'status'));

        $baris = DkpsBaris::where('lembar_kode', '21')->sole();
        $this->assertSame(NormalisasiKunci::dari('21', '21', 'TS-2'), $baris->kunci_normal);
        $this->assertSame('TS-2', $baris->tahun_acuan);
        $this->assertSame(104, $baris->nilai('jumlah_lulusan'));
    }

    #[Test]
    public function baris_tetap_yang_asing_menjadi_galat_bukan_pengecualian(): void
    {
        $tempel = "Tahun Lulus\tJumlah Lulusan\nTS-9\t104";

        [$pratinjau] = $this->tempel($this->profil('21', '21'), $tempel);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $this->assertContains('Baris tetap tidak dikenal: TS-9.', $pratinjau[0]['galat']);
    }

    #[Test]
    public function kerja_sama_memilih_lembar_dari_bidang(): void
    {
        $profil = new ImporKerjaSama($this->periode->id, $this->prodi->id, $this->ketua->id);
        $tempel = "Tahun acuan\tLembaga mitra\tJudul kegiatan\tTahun\tBidang tridharma\n"
            ."TS\tDinas Pendidikan\tRiset bersama\t2024\tPenelitian";

        [$pratinjau] = $this->tempel($profil, $tempel);

        $this->assertSame([PratinjauImpor::BARU], array_column($pratinjau, 'status'));
        $this->assertSame('1.2', DkpsBaris::where('kunci_normal', $pratinjau[0]['data']['_kunci'])->sole()->lembar_kode);
    }

    #[Test]
    public function kerja_sama_menolak_bidang_asing(): void
    {
        $profil = new ImporKerjaSama($this->periode->id, $this->prodi->id, $this->ketua->id);

        $pratinjau = PratinjauImpor::susun([
            ['tahun_acuan' => 'TS', 'sumber' => 'manual', 'lembaga_mitra' => 'Dinas',
                'judul' => 'Riset', 'tahun' => '2024', 'bidang' => 'olahraga'],
        ], $profil, $this->periode->id);

        $this->assertSame(PratinjauImpor::GALAT, $pratinjau[0]['status']);
        $this->assertContains('Bidang harus pendidikan, penelitian, atau pkm.', $pratinjau[0]['galat']);
    }

    private function koordinator(string $kodePokja): User
    {
        $u = User::create([
            'name' => 'k', 'nama_lengkap' => 'Koordinator '.$kodePokja,
            'email' => 'koord-'.uniqid().'@dkps.test', 'password' => 'rahasia123',
            'peran' => PeranPengguna::Koordinator, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);
        $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);

        return $u;
    }

    /** @return array<string, mixed> */
    private function argumenKomponen(string $penggunaId): array
    {
        return [
            'profilKelas' => ImporLembarDkps::class,
            'profilArgs' => [
                'periodeId' => $this->periode->id, 'prodiId' => $this->prodi->id,
                'penggunaId' => $penggunaId, 'kodeTabel' => '24', 'kodeLembar' => '24.1',
            ],
            'periodeId' => $this->periode->id,
        ];
    }

    #[Test]
    public function argumen_profil_impor_tempel_terkunci_dari_klien(): void
    {
        // Tanpa #[Locked], koordinator Tabel 24 bisa menukar id pengguna
        // dan kode tabel lewat $wire.set lalu mengimpor ke tabel pokja lain.
        $koordinator = $this->koordinator('POKJA-DIK');
        $this->actingAs($koordinator);

        foreach ([
            'profilArgs.penggunaId' => $this->ketua->id,
            'profilArgs.kodeTabel' => '28',
            'profilArgs' => [],
            'profilKelas' => ImporKerjaSama::class,
            'periodeId' => null,
        ] as $properti => $nilai) {
            try {
                Livewire::test(ImporTempel::class, $this->argumenKomponen($koordinator->id))
                    ->set($properti, $nilai);
                $this->fail("{$properti} seharusnya terkunci.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function impor_mengotorisasi_pengguna_sesi_bukan_argumen_profil(): void
    {
        // Profil membawa id ketua, tetapi yang sedang masuk koordinator
        // POKJA-DIK; Tabel 28 milik POKJA-SDM, jadi impor harus ditolak.
        $this->actingAs($this->koordinator('POKJA-DIK'));

        try {
            $this->tempel($this->profil('28', '28'),
                "Nama DTPS\tJudul Artikel\tJumlah Sitasi\nDr. Ani\tJurnal X\t5", ['judul_artikel' => 1]);
            $this->fail('Impor ke tabel pokja lain seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertSame(0, DkpsBaris::where('lembar_kode', '28')->count());
        }
    }

    #[Test]
    public function impor_tempel_mengabari_grid_setelah_berhasil(): void
    {
        $koordinator = $this->koordinator('POKJA-DIK');
        $this->actingAs($koordinator);

        Livewire::test(ImporTempel::class, $this->argumenKomponen($koordinator->id))
            ->set('tempelan', "Sumber data\tJenis Kemampuan\tSangat Baik\tBaik\tCukup\tKurang\nmanual\tEtika\t40\t40\t15\t5")
            ->call('uraikan')
            ->call('pratinjaukan')
            ->call('jalankan')
            ->assertSet('galat', null)
            ->assertSet('langkah', 4)
            ->assertDispatched('dkps-diimpor');

        $this->assertSame(1, DkpsBaris::where('lembar_kode', '24.1')->count());
    }
}
