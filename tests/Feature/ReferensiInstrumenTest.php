<?php

namespace Tests\Feature;

use App\Enums\JenisElemen;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Kriteria;
use App\Models\Rumus;
use App\Models\SyaratPerlu;
use Database\Seeders\Concerns\MembacaBerkasData;
use Database\Seeders\DkpsButirSeeder;
use Database\Seeders\ElemenSeeder;
use Database\Seeders\KriteriaSeeder;
use Database\Seeders\RumusSeeder;
use Database\Seeders\SyaratPerluSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Penjaga kebenaran data instrumen LAMDIK.
 *
 * Angka-angka di berkas ini berasal dari dokumen resmi dan SUDAH PASTI.
 * Bila salah satunya merah, yang salah adalah kode atau data — bukan ujinya.
 * Jangan pernah menyesuaikan angka di sini agar cocok dengan hasil kode.
 *
 * Empat di antaranya termasuk uji yang tidak boleh dihapus selamanya
 * (vibecoding/docs/06-kriteria-terima.md), dan dua ada di sini:
 * SUM(elemen.bobot) = 100,00 dan bobot per jenis.
 */
class ReferensiInstrumenTest extends TestCase
{
    use RefreshDatabase;

    private function semaiReferensi(): void
    {
        $this->seed(KriteriaSeeder::class);
        $this->seed(ElemenSeeder::class);
        $this->seed(SyaratPerluSeeder::class);
        $this->seed(RumusSeeder::class);
        $this->seed(DkpsButirSeeder::class);
    }

    #[Test]
    public function elemen_berjumlah_59_dengan_total_bobot_tepat_100(): void
    {
        $this->semaiReferensi();

        $this->assertSame(59, Elemen::count());

        // Dibandingkan sebagai string agar pembulatan titik-mengambang tidak
        // menyembunyikan selisih sekecil 0,01 — yang cukup untuk menggeser NA.
        $this->assertSame('100.00', (string) Elemen::sum('bobot'));
    }

    #[Test]
    public function nomor_elemen_berurutan_1_sampai_59(): void
    {
        $this->semaiReferensi();

        $this->assertSame(range(1, 59), Elemen::orderBy('no')->pluck('no')->all());
    }

    /** @return array<string, array{string, int, string}> */
    public static function bobotPerJenis(): array
    {
        return [
            'data' => ['data', 20, '36.75'],
            'rubrik' => ['rubrik', 30, '49.75'],
            'refleksi' => ['refleksi', 9, '13.50'],
        ];
    }

    #[Test]
    #[DataProvider('bobotPerJenis')]
    public function cacah_dan_bobot_per_jenis_sesuai_instrumen(string $jenis, int $cacah, string $bobot): void
    {
        $this->semaiReferensi();

        $q = Elemen::where('jenis', $jenis);

        $this->assertSame($cacah, $q->count(), "Jumlah elemen berjenis {$jenis}.");
        $this->assertSame($bobot, (string) $q->sum('bobot'), "Total bobot elemen berjenis {$jenis}.");
    }

    #[Test]
    public function syarat_perlu_tepat_pada_lima_elemen(): void
    {
        $this->semaiReferensi();

        $this->assertSame(
            [17, 34, 45, 51, 58],
            Elemen::bersyaratPerlu()->orderBy('no')->pluck('no')->all(),
            'Syarat perlu harus persis pada elemen 17, 34, 45, 51, 58 — tidak kurang, tidak lebih.'
        );

        $this->assertSame(5, SyaratPerlu::count());
    }

    /** @return array<string, array{string, string}> */
    public static function bobotKriteria(): array
    {
        return [
            'K1' => ['K1', '5.50'],
            'K2' => ['K2', '6.00'],
            'K3' => ['K3', '11.75'],
            'K4' => ['K4', '12.75'],
            'K5' => ['K5', '8.00'],
            'K6' => ['K6', '30.50'],
            'K7' => ['K7', '13.00'],
            'K8' => ['K8', '4.00'],
            'K9' => ['K9', '8.50'],
        ];
    }

    #[Test]
    #[DataProvider('bobotKriteria')]
    public function bobot_tiap_kriteria_sesuai_instrumen(string $kode, string $bobot): void
    {
        $this->semaiReferensi();

        $k = Kriteria::where('kode', $kode)->firstOrFail();

        $this->assertSame($bobot, (string) $k->bobot, "Bobot tercatat pada kriteria {$kode}.");

        // Bobot kriteria harus sama dengan jumlah bobot elemen di dalamnya.
        // Kalau keduanya berselisih, salah satu sumbernya rusak.
        $this->assertSame(
            $bobot,
            (string) Elemen::where('kriteria_id', $k->id)->sum('bobot'),
            "Jumlah bobot elemen di dalam {$kode} tidak sama dengan bobot kriterianya."
        );
    }

    #[Test]
    public function dkps_berisi_28_butir_bernomor_1_sampai_28(): void
    {
        $this->semaiReferensi();

        $this->assertSame(28, DkpsButir::count());
        $this->assertSame(range(1, 28), DkpsButir::orderBy('no')->pluck('no')->all());
    }

    #[Test]
    public function label_tabel_dkps_tidak_diturunkan_dari_nomor(): void
    {
        $this->semaiReferensi();

        // Buku 3 melompati "Tabel 13". Kalau label diturunkan dari `no`,
        // seluruh rujukan ke Buku 3 dari butir 13 ke atas meleset satu nomor.
        $this->assertSame('Tabel 14', DkpsButir::where('no', 13)->value('label_tabel'));
        $this->assertSame('Tabel 29', DkpsButir::where('no', 28)->value('label_tabel'));
    }

    #[Test]
    public function rumus_berjumlah_15_dan_memuat_rumus_inti(): void
    {
        $this->semaiReferensi();

        $this->assertSame(15, Rumus::count());

        foreach (['PDS3', 'PGBLKL', 'PPDTPS', 'RSA', 'RK', 'NA'] as $kode) {
            $this->assertTrue(Rumus::where('kode', $kode)->exists(), "Rumus {$kode} tidak tersemai.");
        }

        // NA adalah penjumlah global, tidak terikat elemen mana pun.
        $this->assertNull(Rumus::where('kode', 'NA')->value('elemen_id'));
    }

    #[Test]
    public function nomor_tabel_1_3_dipertahankan_apa_adanya(): void
    {
        $this->semaiReferensi();

        // Selisih antara nomor elemen dan nomor di Tabel 1.3 Buku 4 memang ada
        // pada tiga dari lima butir. Itu bukan salah ketik yang perlu dibetulkan.
        $pasangan = SyaratPerlu::with('elemen')->get()
            ->mapWithKeys(fn (SyaratPerlu $s) => [$s->elemen->no => $s->nomor_di_tabel_1_3])
            ->all();

        $this->assertSame(
            [17 => 17, 34 => 35, 45 => 46, 51 => 52, 58 => 59],
            $pasangan,
            'Penomoran Tabel 1.3 Buku 4 tidak boleh "diperbaiki" agar sama dengan nomor elemen.'
        );
    }

    #[Test]
    public function patokan_na_tertinggi_adalah_363_25(): void
    {
        $this->semaiReferensi();

        // Bila seluruh rubrik dan refleksi berskor 4 sementara data berskor 3,
        // NA-nya 363,25 — hanya 2,25 di atas ambang Unggul. Patokan ini dipakai
        // ulang oleh KalkulatorNa di tahap 6.
        $rubrikRefleksi = (float) Elemen::whereIn('jenis', ['rubrik', 'refleksi'])->sum('bobot');
        $data = (float) Elemen::where('jenis', 'data')->sum('bobot');

        $this->assertSame(363.25, round(4 * $rubrikRefleksi + 3 * $data, 2));
    }

    #[Test]
    public function elemen_refleksi_punya_evaluasi_dan_bukan_parameter(): void
    {
        $this->semaiReferensi();

        foreach (Elemen::where('jenis', JenisElemen::Refleksi)->get() as $e) {
            $this->assertNotNull($e->evaluasi_refleksi, "Elemen {$e->no} berjenis refleksi tanpa evaluasi.");
            $this->assertNull($e->parameter, "Elemen {$e->no} berjenis refleksi seharusnya tanpa parameter.");
        }

        foreach (Elemen::whereIn('jenis', ['data', 'rubrik'])->get() as $e) {
            $this->assertNotNull($e->parameter, "Elemen {$e->no} tanpa parameter.");
            $this->assertNotNull($e->bukti_pendukung, "Elemen {$e->no} tanpa daftar bukti pendukung.");
        }
    }

    #[Test]
    public function setiap_elemen_punya_panduan(): void
    {
        $this->semaiReferensi();

        $this->assertSame(0, Elemen::whereNull('panduan')->orWhere('panduan', '')->count());
    }

    #[Test]
    public function pokja_membagi_habis_59_elemen(): void
    {
        $this->semaiReferensi();

        $perPokja = Elemen::selectRaw('pokja_kode, COUNT(*) c, SUM(bobot) b')
            ->groupBy('pokja_kode')->pluck('b', 'pokja_kode');

        $this->assertSame(59, Elemen::count());

        // Dijumlahkan di PHP dari lima nilai per grup, jadi hasilnya float —
        // diformat dua desimal agar selisih 0,01 tetap terlihat.
        $this->assertSame('100.00', number_format($perPokja->sum(), 2, '.', ''));

        // POKJA-DATA sengaja tidak memegang elemen: ia memegang 28 butir DKPS
        // dan seluruh perhitungan rumus. Uji "tiap pokja punya minimal satu
        // elemen" akan salah.
        $this->assertArrayNotHasKey('POKJA-DATA', $perPokja->all());
    }

    #[Test]
    public function seeder_referensi_idempoten(): void
    {
        $this->semaiReferensi();

        $sebelum = [Kriteria::count(), Elemen::count(), SyaratPerlu::count(), Rumus::count(), DkpsButir::count()];
        $idElemen17 = Elemen::where('no', 17)->value('id');

        $this->semaiReferensi();

        $this->assertSame($sebelum, [Kriteria::count(), Elemen::count(), SyaratPerlu::count(), Rumus::count(), DkpsButir::count()]);
        $this->assertSame($idElemen17, Elemen::where('no', 17)->value('id'), 'Baris harus dipakai ulang lewat kunci alami.');
        $this->assertSame('100.00', (string) Elemen::sum('bobot'));
    }

    #[Test]
    public function seeder_gagal_keras_bila_berkas_data_hilang(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak ditemukan/');

        $seeder = new class extends Seeder
        {
            use MembacaBerkasData;

            public function run(): void
            {
                $this->bacaJson('berkas-yang-tidak-ada.json', 1);
            }
        };

        $seeder->run();
    }
}
