<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Seluruh antarmuka berbahasa Indonesia — termasuk pesan galat, yaitu justru
 * saat kata-kata paling perlu dimengerti.
 */
class BahasaIndonesiaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pesan_validasi_diterjemahkan_bukan_kunci_mentah(): void
    {
        // Laravel 11 ke atas tidak menyertakan berkas terjemahan apa pun;
        // tanpa lang/id/validation.php pesannya muncul sebagai
        // "validation.required" di layar pengguna.
        foreach (['required', 'email', 'numeric', 'unique', 'url', 'date'] as $aturan) {
            $pesan = __("validation.{$aturan}", ['attribute' => 'kolom']);

            $this->assertStringNotContainsString('validation.', $pesan, "Aturan `{$aturan}` belum diterjemahkan.");
            $this->assertStringContainsString('kolom', $pesan);
        }
    }

    #[Test]
    public function nama_kolom_diterjemahkan_ke_istilah_yang_dilihat_pengguna(): void
    {
        // Tanpa ini pesannya berbunyi "Kolom tanggal_kejadian wajib diisi" —
        // benar, tetapi menuntut pembaca menerjemahkan nama kolom basis data.
        $this->assertSame('tanggal kejadian', __('validation.attributes.tanggal_kejadian'));
        $this->assertSame('penanggung jawab', __('validation.attributes.penanggung_jawab_id'));
        $this->assertSame('sumber data', __('validation.attributes.sumber'));

        $pesan = __('validation.required', ['attribute' => __('validation.attributes.tanggal_kejadian')]);
        $this->assertSame('Kolom tanggal kejadian wajib diisi.', $pesan);
    }

    #[Test]
    public function pesan_gagal_masuk_berbahasa_indonesia(): void
    {
        $this->assertStringNotContainsString('auth.', __('auth.failed'));
        $this->assertStringContainsString('Kata sandi', __('auth.password'));
    }

    #[Test]
    public function locale_aplikasi_adalah_indonesia(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('id', config('app.fallback_locale'));
    }

    #[Test]
    public function tombol_filament_diterjemahkan(): void
    {
        foreach ([
            'filament-actions::edit.single.label' => 'Ubah',
            'filament-actions::delete.single.label' => 'Hapus',
        ] as $kunci => $harapan) {
            $this->assertSame($harapan, __($kunci), "Kunci {$kunci} belum diterjemahkan.");
        }

        // Pesan tabel kosong ikut diterjemahkan — ia yang pertama dilihat
        // orang pada layar yang belum berisi apa-apa.
        $this->assertStringNotContainsString(
            'filament-tables::', __('filament-tables::table.empty.heading'),
        );
    }

    /** @return array<string, array{string}> */
    public static function halamanPanel(): array
    {
        return [
            'dasbor' => ['/panel/dasbor'],
            'semua tagihan' => ['/panel/tagihans'],
            'bukti' => ['/panel/buktis'],
            'naskah LED' => ['/panel/narasis'],
            'isian DKPS' => ['/panel/dkps-baris'],
            'nilai rumus' => ['/panel/nilai-rumuses'],
            'asesmen mandiri' => ['/panel/penilaians'],
            'syarat perlu' => ['/panel/syarat-perlu'],
            'matriks izin' => ['/panel/matriks-izin'],
            'elemen' => ['/panel/elemens'],
        ];
    }

    #[Test]
    #[DataProvider('halamanPanel')]
    public function halaman_tidak_memuat_teks_inggris_bawaan(string $jalur): void
    {
        $this->seed(DatabaseSeeder::class);

        $prodi = Prodi::firstOrFail();
        Periode::firstOrFail();

        $ketua = User::create([
            'name' => 'k', 'nama_lengkap' => 'Ketua', 'email' => 'k-'.uniqid().'@bahasa.test',
            'password' => 'rahasia123', 'peran' => PeranPengguna::Ketua,
            'prodi_id' => $prodi->id, 'aktif' => true, 'wajib_ganti_sandi' => false,
        ]);

        $isi = $this->actingAs($ketua)->get($jalur)->assertSuccessful()->getContent();

        // Teks yang paling sering tertinggal dari Filament. Dicari sebagai
        // teks di antara tag, bukan di mana saja: "Create" juga muncul di
        // dalam nama kelas CSS dan atribut Alpine.
        foreach ([
            '>Create<', '>Edit<', '>Delete<', '>Save<', '>Cancel<',
            '>Search<', '>Submit<', '>Actions<', '>No records found<',
            '>Bulk actions<', '>Reset<', '>Columns<',
        ] as $inggris) {
            $this->assertStringNotContainsString(
                $inggris, $isi,
                "Halaman {$jalur} masih memuat teks Inggris {$inggris}."
            );
        }
    }
}
