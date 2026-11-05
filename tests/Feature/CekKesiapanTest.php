<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pemeriksa kesiapan harus benar-benar menangkap, bukan sekadar mencetak OK.
 *
 * Perintah semacam ini gampang menjadi hiasan: ia berjalan, hijau semua, dan
 * tidak pernah memeriksa apa pun. Uji di sini memasang keadaan yang MEMANG
 * salah lalu menuntut perintahnya menemukannya.
 */
class CekKesiapanTest extends TestCase
{
    use RefreshDatabase;

    private function prodi(): Prodi
    {
        return Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG Uji', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);
    }

    #[Test]
    public function menemukan_kata_sandi_contoh_yang_tertinggal(): void
    {
        $prodi = $this->prodi();
        User::factory()->peran(PeranPengguna::Admin)->create([
            'prodi_id' => $prodi->id,
            'email' => 'admin@contoh.test',
            'password' => Hash::make('password'),
        ]);

        $this->artisan('sigap:cek-kesiapan')
            ->expectsOutputToContain('admin@contoh.test')
            ->assertFailed();
    }

    #[Test]
    public function menemukan_tidak_adanya_administrator_aktif(): void
    {
        $prodi = $this->prodi();
        User::factory()->peran(PeranPengguna::Ketua)->create([
            'prodi_id' => $prodi->id,
            'password' => Hash::make('sandi-yang-panjang-dan-acak'),
        ]);

        $this->artisan('sigap:cek-kesiapan')
            ->expectsOutputToContain('Ada administrator sistem yang aktif')
            ->assertFailed();
    }

    #[Test]
    public function menemukan_data_instrumen_yang_belum_tersemai(): void
    {
        $this->artisan('sigap:cek-kesiapan')
            ->expectsOutputToContain('59 elemen tersemai')
            ->assertFailed();
    }

    #[Test]
    public function memeriksa_subfolder_ketika_diminta(): void
    {
        $this->artisan('sigap:cek-kesiapan', ['--subfolder' => '/sigap'])
            ->expectsOutputToContain('SESSION_PATH sesuai subfolder')
            ->assertFailed();
    }

    #[Test]
    public function lulus_ketika_lingkungannya_memang_siap(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Seeder memasang sandi contoh `password` pada enam pengguna contoh —
        // pantas di basis data demo, tidak pantas di server sungguhan. Di sini
        // diganti supaya sisa pemeriksaannya bisa dinilai.
        User::query()->get()->each(
            fn (User $u) => $u->forceFill(['password' => Hash::make('sandi-yang-panjang-dan-acak')])->save()
        );

        $this->artisan('sigap:cek-kesiapan')
            ->expectsOutputToContain('Total bobot elemen 100,00')
            ->expectsOutputToContain('156 sel izin tersemai')
            ->assertSuccessful();
    }

    #[Test]
    public function menemukan_baris_dkps_tanpa_pokja_lalu_lulus_setelah_diselaraskan(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::query()->get()->each(
            fn (User $u) => $u->forceFill(['password' => Hash::make('sandi-yang-panjang-dan-acak')])->save()
        );

        $periode = Periode::firstOrFail();
        $butir = DkpsButir::dariKode('28');
        $baris = DkpsBaris::create([
            'prodi_id' => Prodi::firstOrFail()->id, 'periode_id' => $periode->id,
            'dkps_butir_id' => $butir->id, 'tahun_acuan' => 'TS',
            'data' => ['penulis' => 'Dr. Lama'], 'sumber' => SumberData::Manual,
        ]);

        // Baris dari sebelum DKPS 3.0: belum punya lembar maupun pokja.
        DkpsBaris::withoutGlobalScopes()->whereKey($baris->id)
            ->update(['pokja_id' => null, 'lembar_kode' => null]);

        $this->artisan('sigap:cek-kesiapan')
            ->expectsOutputToContain('35 lembar DKPS')
            ->expectsOutputToContain('dkps:selaraskan')
            ->assertFailed();

        $this->artisan('dkps:selaraskan')
            ->expectsOutputToContain('Diselaraskan: 1')
            ->assertSuccessful();

        $baris->refresh();
        $this->assertSame('28', $baris->lembar_kode);
        $this->assertSame(
            Pokja::where('periode_id', $periode->id)->where('kode', $butir->pokja_kode)->value('id'),
            $baris->pokja_id,
        );

        // Idempoten: putaran kedua tidak menyentuh apa pun.
        $this->artisan('dkps:selaraskan')
            ->expectsOutputToContain('Diselaraskan: 0')
            ->assertSuccessful();

        $this->artisan('sigap:cek-kesiapan')
            ->expectsOutputToContain('Tidak ada baris DKPS tanpa pokja')
            ->assertSuccessful();
    }
}
