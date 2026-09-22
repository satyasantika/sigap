<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\TagihanRiwayat;
use App\Models\User;
use App\Services\PembangkitTagihan;
use App\Services\RingkasanProgres;
use Carbon\CarbonInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RingkasanProgresTest extends TestCase
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

        app(PembangkitTagihan::class)->untuk($this->periode);
    }

    private function ringkasan(): RingkasanProgres
    {
        return app(RingkasanProgres::class);
    }

    /** Menyetujui seluruh tagihan sebuah elemen, beserta riwayatnya. */
    private function selesaikanElemen(Elemen $elemen, ?CarbonInterface $kapan = null): void
    {
        foreach (Tagihan::where('elemen_id', $elemen->id)->get() as $t) {
            $t->forceFill([
                'status' => StatusTagihan::Disetujui,
                'disetujui_oleh' => $this->ketua->id,
                'disetujui_pada' => $kapan ?? now(),
            ])->save();

            // created_at tidak fillable pada TagihanRiwayat — ia jejak audit,
            // jadi waktunya tidak boleh diisi lewat mass assignment. Untuk uji
            // laju, waktunya dipasang lewat forceFill.
            TagihanRiwayat::create([
                'tagihan_id' => $t->id, 'user_id' => $this->ketua->id,
                'status_dari' => StatusTagihan::Direviu, 'status_ke' => StatusTagihan::Disetujui,
            ])->forceFill(['created_at' => $kapan ?? now()])->saveQuietly();
        }
    }

    // ---- UJI WAJIB: penimbangan benar-benar terjadi ---------------------

    #[Test]
    public function sepuluh_tagihan_kecil_kalah_dari_satu_tagihan_besar(): void
    {
        // UJI WAJIB dari prompt: membuktikan progres ditimbang bobot, bukan
        // dihitung cacah. Dasbor yang menghitung cacah akan membuat orang
        // mengerjakan yang mudah lebih dulu dan meninggalkan elemen berbobot
        // besar sampai tenggat.
        //
        // Angkanya disesuaikan dengan instrumen yang sebenarnya: bobot elemen
        // terkecil 1,00 dan terbesar 3,00, jadi SEPULUH elemen kecil (11,25)
        // memang melampaui satu elemen besar (3,00). Yang membuktikan
        // penimbangan adalah perbandingan di mana cacah dan bobot BERLAWANAN —
        // dua elemen kecil versus satu elemen besar.
        $kecil = Elemen::where('bobot', 1.00)->limit(2)->get();

        foreach ($kecil as $e) {
            $this->selesaikanElemen($e);
        }

        $bobotDuaKecil = $this->ringkasan()->bobotSelesai($this->periode);

        Tagihan::query()->update([
            'status' => StatusTagihan::Belum, 'disetujui_oleh' => null, 'disetujui_pada' => null,
        ]);

        $besar = Elemen::orderByDesc('bobot')->first();
        $this->selesaikanElemen($besar);

        $bobotSatuBesar = $this->ringkasan()->bobotSelesai($this->periode);

        // Cacah mengatakan dua lebih maju daripada satu; bobot mengatakan
        // sebaliknya. Bila ujinya merah, progresnya dihitung dari cacah.
        $this->assertSame(2, $kecil->count());
        $this->assertSame(2.0, $bobotDuaKecil);
        $this->assertSame(3.0, $bobotSatuBesar);
        $this->assertGreaterThan(
            $bobotDuaKecil, $bobotSatuBesar,
            'Satu elemen berbobot 3,00 harus melampaui dua elemen berbobot 1,00 '
            .'meski cacahnya lebih sedikit.'
        );
    }

    #[Test]
    public function elemen_dihitung_selesai_hanya_bila_seluruh_tagihannya_disetujui(): void
    {
        // Elemen 1 punya tagihan narasi DAN bukti. Menyetujui satu saja tidak
        // membuatnya selesai — naskah tanpa bukti tidak bisa dinilai.
        $e = Elemen::where('no', 1)->firstOrFail();
        $tagihan = Tagihan::where('elemen_id', $e->id)->get();

        $this->assertGreaterThan(1, $tagihan->count());

        $tagihan->first()->forceFill(['status' => StatusTagihan::Disetujui])->save();

        $this->assertSame(0.0, $this->ringkasan()->bobotSelesai($this->periode));

        $this->selesaikanElemen($e);

        $this->assertSame((float) $e->bobot, $this->ringkasan()->bobotSelesai($this->periode));
    }

    #[Test]
    public function progres_keseluruhan_adalah_bobot_dibagi_seratus(): void
    {
        $e = Elemen::where('no', 58)->firstOrFail();  // bobot 3,00
        $this->selesaikanElemen($e);

        $this->assertSame(3.0, $this->ringkasan()->bobotSelesai($this->periode));
        $this->assertSame(0.03, $this->ringkasan()->progresKeseluruhan($this->periode));
    }

    // ---- Progres per pokja dan kriteria ---------------------------------

    #[Test]
    public function pokja_data_diukur_dengan_dkps_bukan_bobot(): void
    {
        $pokja = collect($this->ringkasan()->progresPokja($this->periode));
        $data = $pokja->firstWhere('kode', 'POKJA-DATA');

        // Menampilkannya nol akan dibaca sebagai "belum mengerjakan apa-apa",
        // padahal ia memegang 28 butir DKPS.
        $this->assertTrue($data['pakai_dkps']);
        $this->assertSame(28.0, $data['total']);
    }

    #[Test]
    public function pokja_terurut_menurut_bobot_yang_dipegang(): void
    {
        $pokja = collect($this->ringkasan()->progresPokja($this->periode));
        $berbobot = $pokja->where('pakai_dkps', false);

        $this->assertSame(
            $berbobot->pluck('total')->sortDesc()->values()->all(),
            $berbobot->pluck('total')->values()->all(),
        );

        // POKJA-DATA di akhir: ukurannya berbeda dan tidak sebanding.
        $this->assertTrue($pokja->last()['pakai_dkps']);
    }

    #[Test]
    public function pokja_pemegang_syarat_perlu_ditandai(): void
    {
        $pokja = collect($this->ringkasan()->progresPokja($this->periode));

        // POKJA-SDM memegang E17 dan E51; POKJA-DIK memegang E34 dan E45;
        // POKJA-MUTU memegang E58.
        foreach (['POKJA-SDM', 'POKJA-DIK', 'POKJA-MUTU'] as $kode) {
            $this->assertTrue($pokja->firstWhere('kode', $kode)['syarat_perlu'], $kode);
        }

        $this->assertFalse($pokja->firstWhere('kode', 'POKJA-MAWA')['syarat_perlu']);
    }

    #[Test]
    public function total_bobot_seluruh_pokja_tetap_seratus(): void
    {
        $total = collect($this->ringkasan()->progresPokja($this->periode))
            ->where('pakai_dkps', false)->sum('total');

        $this->assertSame('100.00', number_format($total, 2, '.', ''));
    }

    #[Test]
    public function progres_kriteria_memakai_bobot_kriteria_sebagai_pembagi(): void
    {
        $e = Elemen::where('no', 58)->firstOrFail();  // K9, bobot 3,00
        $this->selesaikanElemen($e);

        $k9 = collect($this->ringkasan()->progresKriteria($this->periode))->firstWhere('kode', 'K9');

        $this->assertSame(3.0, $k9['selesai']);
        $this->assertSame(8.5, $k9['total']);
        $this->assertSame(round(3.0 / 8.5, 4), $k9['rasio']);
    }

    #[Test]
    public function progres_elemen_dihitung_dari_bobot_terkait_tagihannya(): void
    {
        // Berbeda dari progres keseluruhan: di sini separuh tagihan yang
        // selesai memang berarti separuh, karena yang ditanyakan kemajuan
        // DI DALAM elemen itu.
        $e = Elemen::where('no', 1)->firstOrFail();
        Tagihan::where('elemen_id', $e->id)->first()
            ->forceFill(['status' => StatusTagihan::Disetujui])->save();

        $this->assertSame(0.5, $this->ringkasan()->progresElemen($this->periode, $e));
    }

    // ---- DKPS terpisah dari bobot ---------------------------------------

    #[Test]
    public function dkps_tidak_ikut_dihitung_ke_dalam_progres_bobot(): void
    {
        $sebelum = $this->ringkasan()->bobotSelesai($this->periode);

        // Tagihan DKPS berbobot nol; menyetujui seluruhnya tidak menggeser
        // progres bobot sedikit pun.
        Tagihan::where('jenis', 'data_dkps')
            ->update(['status' => StatusTagihan::Disetujui]);

        $this->assertSame($sebelum, $this->ringkasan()->bobotSelesai($this->periode));
    }

    #[Test]
    public function kesiapan_dkps_dihitung_dari_butir_terverifikasi(): void
    {
        $d = $this->ringkasan()->progresDkps($this->periode);

        $this->assertSame(28, $d['total']);
        $this->assertSame(0, $d['terverifikasi']);
        $this->assertSame(0.0, $d['rasio']);
    }

    // ---- Daftar kerja ----------------------------------------------------

    #[Test]
    public function tagihan_terlambat_disertai_bobot_yang_tertahan(): void
    {
        $e = Elemen::where('no', 58)->firstOrFail();
        Tagihan::where('elemen_id', $e->id)->update(['tenggat' => now()->subWeek()]);

        $this->assertGreaterThan(0, $this->ringkasan()->tagihanTerlambat($this->periode)->count());

        // Dua angka, karena sepuluh tagihan kecil terlambat tidak sama
        // gawatnya dengan satu tagihan berbobot 3,00 yang terlambat.
        $this->assertSame(3.0, $this->ringkasan()->bobotTertahan($this->periode));
    }

    #[Test]
    public function tagihan_tanpa_pj_terurut_dari_bobot_terbesar(): void
    {
        $daftar = $this->ringkasan()->tagihanTanpaPj($this->periode);

        $this->assertSame(137, $daftar->count(), 'Seluruh tagihan belum ditugaskan.');

        $bobot = $daftar->pluck('bobot_terkait')->map(fn ($b) => (float) $b);
        $this->assertSame($bobot->sortDesc()->values()->all(), $bobot->values()->all());
    }

    #[Test]
    public function elemen_tanpa_bukti_terurut_dari_bobot_terbesar(): void
    {
        $daftar = $this->ringkasan()->elemenTanpaBukti($this->periode);

        $this->assertSame(59, $daftar->count());
        $this->assertSame(58, $daftar->first()->no, 'E58 berbobot 3,00, yang terbesar.');
    }

    // ---- Laju dan perkiraan ---------------------------------------------

    #[Test]
    public function laju_nol_tidak_menghasilkan_tak_hingga(): void
    {
        $l = $this->ringkasan()->lajuDanPerkiraan($this->periode);

        $this->assertFalse($l['bisa_diperkirakan']);
        $this->assertNull($l['perkiraan_minggu']);
        $this->assertSame(0.0, $l['laju_mingguan']);

        // Ubinnya tidak disembunyikan; "belum bisa diperkirakan" adalah
        // informasi, dan justru yang paling perlu terlihat.
        $this->assertStringContainsString(
            'belum ada tagihan yang disetujui',
            $this->ringkasan()->kalimatLaju($this->periode)['kalimat'],
        );
    }

    #[Test]
    public function laju_dihitung_dari_riwayat_bukan_dari_status_sekarang(): void
    {
        $e = Elemen::where('no', 58)->firstOrFail();

        // Disetujui setahun lalu: tidak menyumbang laju hari ini.
        $this->selesaikanElemen($e, now()->subYear());

        $this->assertFalse($this->ringkasan()->lajuDanPerkiraan($this->periode)['bisa_diperkirakan']);

        // Elemen lain disetujui minggu ini.
        $this->selesaikanElemen(Elemen::where('no', 51)->firstOrFail(), now()->subDays(3));

        $this->assertTrue($this->ringkasan()->lajuDanPerkiraan($this->periode)['bisa_diperkirakan']);
    }

    #[Test]
    public function kalimat_laju_menyebut_selisih_terhadap_tanggal_target(): void
    {
        $this->periode->update(['tanggal_target_unggah' => now()->addMonths(2)]);
        $this->selesaikanElemen(Elemen::where('no', 58)->firstOrFail(), now()->subDays(3));

        $k = $this->ringkasan()->kalimatLaju($this->periode);

        // Kalimat berdiri sendiri; mengandalkan warna saja menyingkirkan
        // pembaca yang tidak membedakan merah dan hijau.
        $this->assertStringContainsString('Dengan laju sekarang', $k['kalimat']);
        $this->assertStringContainsString('hari', $k['kalimat']);
        $this->assertContains($k['warna'], ['success', 'warning', 'danger', 'info']);
    }
}
