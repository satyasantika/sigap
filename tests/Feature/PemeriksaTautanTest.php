<?php

namespace Tests\Feature;

use App\Enums\AksesTautan;
use App\Enums\JenisBukti;
use App\Enums\SumberData;
use App\Models\Bukti;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PemeriksaTautan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pemeriksa keterbacaan tautan.
 *
 * Seluruh uji memakai Http::fake(); tidak ada satu pun panggilan jaringan
 * sungguhan. Uji yang memanggil Drive asli akan merah setiap kali jaringan
 * lambat, dan orang akan belajar mengabaikannya.
 */
class PemeriksaTautanTest extends TestCase
{
    use RefreshDatabase;

    private function bukti(string $url = 'https://drive.google.com/file/d/ABC/view'): Bukti
    {
        $this->seed(DatabaseSeeder::class);

        $prodi = Prodi::firstOrFail();
        $periode = Periode::firstOrFail();
        $user = User::firstOrFail();

        return Bukti::create([
            'prodi_id' => $prodi->id,
            'periode_id' => $periode->id,
            'judul' => 'Tautan uji',
            'jenis' => JenisBukti::Tautan,
            'url' => $url,
            'url_kanonik' => $url,
            'tanggal_kejadian' => now()->subMonth(),
            'sumber' => SumberData::Manual,
            'diunggah_oleh' => $user->id,
        ]);
    }

    #[Test]
    public function permintaan_dikirim_tanpa_kredensial_apa_pun(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        app(PemeriksaTautan::class)->periksa($this->bukti());

        // Ini syarat mutlak: memakai kredensial membuat pemeriksaan selalu
        // lulus dan karena itu tidak berguna. Justru inilah kegagalan yang
        // paling sering terjadi — pengunggah bisa membuka tautannya sendiri,
        // asesor tidak.
        Http::assertSent(function (Request $r) {
            $header = collect($r->headers())->keys()->map(fn ($h) => strtolower($h));

            $this->assertFalse($header->contains('authorization'), 'Tidak boleh mengirim Authorization.');
            $this->assertFalse($header->contains('cookie'), 'Tidak boleh mengirim Cookie.');

            return true;
        });
    }

    #[Test]
    public function respons_200_menjadi_terbuka(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $status = app(PemeriksaTautan::class)->periksa($b = $this->bukti());

        $this->assertSame(AksesTautan::Terbuka, $status);
        $this->assertSame(AksesTautan::Terbuka, $b->fresh()->akses_status);
        $this->assertNotNull($b->fresh()->akses_diperiksa_pada);
    }

    #[Test]
    public function dialihkan_ke_halaman_masuk_google_menjadi_perlu_izin(): void
    {
        // Inilah kegagalan nomor satu di lapangan: tautan yang hanya terbuka
        // bagi orang yang sudah masuk dengan akun kampus.
        Http::fake([
            'drive.google.com/*' => Http::response('', 302, ['Location' => 'https://accounts.google.com/signin']),
            'accounts.google.com/*' => Http::response('halaman masuk', 200),
        ]);

        $status = app(PemeriksaTautan::class)->periksa($this->bukti());

        $this->assertSame(AksesTautan::PerluIzin, $status);
    }

    #[Test]
    public function respons_403_menjadi_perlu_izin(): void
    {
        Http::fake(['*' => Http::response('terlarang', 403)]);

        $this->assertSame(AksesTautan::PerluIzin, app(PemeriksaTautan::class)->periksa($this->bukti()));
    }

    #[Test]
    public function respons_404_dan_410_menjadi_tidak_ditemukan(): void
    {
        foreach ([404, 410] as $kode) {
            Http::fake(['*' => Http::response('', $kode)]);

            $this->assertSame(
                AksesTautan::TidakDitemukan,
                app(PemeriksaTautan::class)->periksa($this->bukti()),
                "HTTP {$kode} seharusnya tidak_ditemukan."
            );
        }
    }

    #[Test]
    public function respons_5xx_menjadi_gagal_periksa(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        // Kegagalan MESIN, bukan kesalahan pengunggah — tetapi tetap
        // menghalangi persetujuan, karena "tidak terbukti terbuka" sama
        // berbahayanya dengan "terbukti tertutup".
        $this->assertSame(AksesTautan::GagalPeriksa, app(PemeriksaTautan::class)->periksa($this->bukti()));
    }

    #[Test]
    public function galat_jaringan_menjadi_gagal_periksa(): void
    {
        Http::fake(fn () => throw new ConnectionException('habis waktu'));

        $b = $this->bukti();
        $this->assertSame(AksesTautan::GagalPeriksa, app(PemeriksaTautan::class)->periksa($b));
        $this->assertStringContainsString('habis waktu', $b->fresh()->akses_pesan);
    }

    #[Test]
    public function cacah_percobaan_naik_saat_gagal_dan_direset_saat_ada_jawaban(): void
    {
        $b = $this->bukti();

        // Urutan respons, bukan dua panggilan Http::fake terpisah: pemanggilan
        // fake kedua MENGGABUNG stub, tidak menggantikannya, sehingga stub 503
        // yang pertama tetap menang dan ujinya menyesatkan.
        Http::fake(['*' => Http::sequence()
            ->push('', 503)
            ->push('', 503)
            ->push('ok', 200),
        ]);

        app(PemeriksaTautan::class)->periksa($b);
        app(PemeriksaTautan::class)->periksa($b->fresh());

        $this->assertSame(2, $b->fresh()->akses_percobaan);

        // Hasil yang pasti adalah jawaban, bukan kegagalan — cacahnya direset.
        app(PemeriksaTautan::class)->periksa($b->fresh());

        $this->assertSame(0, $b->fresh()->akses_percobaan);
    }

    #[Test]
    public function pesan_penelusuran_disimpan_untuk_sengketa(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $b = $this->bukti();
        app(PemeriksaTautan::class)->periksa($b);

        // Saat ada sengketa "tautan saya bisa dibuka kok", inilah yang dilihat.
        $this->assertStringContainsString('HTTP 200', $b->fresh()->akses_pesan);
    }

    #[Test]
    public function perintah_artisan_memeriksa_tautan_yang_perlu(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $b = $this->bukti();

        $this->artisan('bukti:periksa-tautan')->assertSuccessful();

        $this->assertSame(AksesTautan::Terbuka, $b->fresh()->akses_status);
    }

    #[Test]
    public function yang_sudah_gagal_tiga_kali_tidak_diperiksa_lagi_otomatis(): void
    {
        $b = $this->bukti();
        $b->forceFill([
            'akses_status' => AksesTautan::GagalPeriksa,
            'akses_percobaan' => PemeriksaTautan::BATAS_PERCOBAAN,
        ])->save();

        Http::fake(['*' => Http::response('ok', 200)]);

        $this->artisan('bukti:periksa-tautan')->assertSuccessful();

        // Sudah menjadi urusan manusia; mengulanginya tiap hari hanya menambah
        // beban tanpa menambah informasi.
        Http::assertNothingSent();
        $this->assertSame(AksesTautan::GagalPeriksa, $b->fresh()->akses_status);
    }

    #[Test]
    public function opsi_paksa_memeriksa_ulang_semuanya(): void
    {
        $b = $this->bukti();
        $b->forceFill([
            'akses_status' => AksesTautan::GagalPeriksa,
            'akses_percobaan' => PemeriksaTautan::BATAS_PERCOBAAN,
        ])->save();

        Http::fake(['*' => Http::response('ok', 200)]);

        $this->artisan('bukti:periksa-tautan', ['--paksa' => true])->assertSuccessful();

        $this->assertSame(AksesTautan::Terbuka, $b->fresh()->akses_status);
    }
}
