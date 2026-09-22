<?php

namespace Tests\Feature;

use App\Enums\JenisTagihan;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\Tagihan;
use App\Services\PembangkitTagihan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pembangkit tagihan dan pembagian bobotnya.
 *
 * SUM(bobot_terkait) = 100,000 termasuk uji yang tidak boleh dihapus
 * selamanya (vibecoding/docs/06-kriteria-terima.md). Progres dihitung dari
 * bobot, jadi selisih di sini muncul sebagai persentase yang salah di dasbor
 * ketua — dan tidak ada yang akan curiga pada angka yang terlihat wajar.
 */
class PembangkitTagihanTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();

        app(PembangkitTagihan::class)->untuk($this->periode);
    }

    #[Test]
    public function jumlah_bobot_terkait_satu_periode_tepat_100(): void
    {
        $this->assertSame(
            '100.000',
            (string) Tagihan::where('periode_id', $this->periode->id)->sum('bobot_terkait'),
            'Jumlah bobot_terkait seluruh tagihan satu periode harus persis 100,000.'
        );
    }

    #[Test]
    public function setiap_elemen_punya_tepat_satu_tagihan_narasi(): void
    {
        $this->assertSame(59, Tagihan::where('jenis', JenisTagihan::Narasi)->count());

        foreach (Elemen::all() as $e) {
            $this->assertSame(
                1,
                Tagihan::where('elemen_id', $e->id)->where('jenis', JenisTagihan::Narasi)->count(),
                "Elemen {$e->no} harus punya tepat satu tagihan narasi."
            );
        }
    }

    #[Test]
    public function tagihan_bukti_hanya_untuk_elemen_yang_memintanya(): void
    {
        $dengan = Elemen::whereNotNull('bukti_pendukung')->count();

        $this->assertSame($dengan, Tagihan::where('jenis', JenisTagihan::Bukti)->count());

        // Sembilan elemen refleksi tidak meminta bukti pendukung, jadi tidak
        // punya tagihan bukti.
        foreach (Elemen::whereNull('bukti_pendukung')->get() as $e) {
            $this->assertSame(
                0,
                Tagihan::where('elemen_id', $e->id)->where('jenis', JenisTagihan::Bukti)->count(),
                "Elemen {$e->no} tidak meminta bukti, jadi tidak boleh punya tagihan bukti."
            );
        }
    }

    #[Test]
    public function setiap_butir_dkps_punya_satu_tagihan_di_pokja_data(): void
    {
        $this->assertSame(28, Tagihan::where('jenis', JenisTagihan::DataDkps)->count());
        $this->assertSame(28, DkpsButir::count());

        $pokja = Tagihan::where('jenis', JenisTagihan::DataDkps)
            ->with('pokja')->get()->pluck('pokja.kode')->unique();

        $this->assertSame(['POKJA-DATA'], $pokja->values()->all());
    }

    #[Test]
    public function tagihan_dkps_berbobot_nol(): void
    {
        // Pekerjaannya nyata, tetapi bobotnya sudah terhitung lewat elemen yang
        // dilayaninya. Menghitungnya dua kali membuat totalnya melebihi 100.
        $this->assertSame(
            '0.000',
            (string) Tagihan::where('jenis', JenisTagihan::DataDkps)->sum('bobot_terkait')
        );
    }

    #[Test]
    public function bobot_elemen_dibagi_rata_ke_tagihan_berbobot_miliknya(): void
    {
        foreach (Elemen::all() as $e) {
            $jumlah = (float) Tagihan::where('elemen_id', $e->id)
                ->whereIn('jenis', [JenisTagihan::Narasi, JenisTagihan::Bukti])
                ->sum('bobot_terkait');

            $this->assertEqualsWithDelta(
                (float) $e->bobot, $jumlah, 0.0005,
                "Jumlah bobot tagihan elemen {$e->no} tidak sama dengan bobot elemennya."
            );
        }
    }

    #[Test]
    public function pembagian_yang_tidak_bulat_tetap_utuh(): void
    {
        // Setengah dari 1,25 adalah 0,625 — tiga desimal. Dengan dua desimal
        // angkanya dibulatkan dan jumlah periode meleset dari 100,000.
        $e = Elemen::where('bobot', 1.25)->whereNotNull('bukti_pendukung')->first();

        if ($e === null) {
            $this->markTestSkipped('Tidak ada elemen berbobot 1,25 yang meminta bukti.');
        }

        $bobot = Tagihan::where('elemen_id', $e->id)->pluck('bobot_terkait')->map(fn ($b) => (string) $b);

        $this->assertSame(['0.625', '0.625'], $bobot->sort()->values()->all());
    }

    #[Test]
    public function elemen_syarat_perlu_diberi_prioritas_kritis(): void
    {
        // Lima elemen ini menentukan status Unggul; ia tidak boleh tenggelam
        // di antara 137 tagihan lain.
        $kritis = Tagihan::where('prioritas', 'kritis')->with('elemen')->get()
            ->pluck('elemen.no')->unique()->sort()->values()->all();

        $this->assertSame([17, 34, 45, 51, 58], $kritis);
    }

    #[Test]
    public function pembangkitan_ulang_tidak_menggandakan(): void
    {
        $sebelum = Tagihan::count();
        $bobotSebelum = (string) Tagihan::sum('bobot_terkait');

        $hasil = app(PembangkitTagihan::class)->untuk($this->periode);

        $this->assertSame($sebelum, Tagihan::count(), 'Menjalankan ulang tidak boleh menggandakan tagihan.');
        $this->assertSame($bobotSebelum, (string) Tagihan::sum('bobot_terkait'));
        $this->assertSame($sebelum, $hasil['dilewati']);
        $this->assertSame(0, $hasil['narasi'] + $hasil['bukti'] + $hasil['data_dkps']);
    }

    #[Test]
    public function tagihan_mewarisi_pokja_dari_elemennya(): void
    {
        foreach (Tagihan::whereNotNull('elemen_id')->with(['elemen', 'pokja'])->get() as $t) {
            $this->assertSame(
                $t->elemen->pokja_kode, $t->pokja->kode,
                "Tagihan elemen {$t->elemen->no} berada di pokja yang salah."
            );
        }
    }

    #[Test]
    public function perintah_artisan_membangkitkan_dan_melaporkan(): void
    {
        Tagihan::query()->forceDelete();

        $this->artisan('tagihan:bangkitkan', ['periode' => $this->periode->nama])
            ->assertSuccessful();

        $this->assertSame(137, Tagihan::count());
        $this->assertSame('100.000', (string) Tagihan::sum('bobot_terkait'));
    }

    #[Test]
    public function perintah_artisan_gagal_rapi_bila_periode_tidak_ada(): void
    {
        $this->artisan('tagihan:bangkitkan', ['periode' => 'Periode Yang Tidak Ada'])
            ->assertFailed();
    }
}
