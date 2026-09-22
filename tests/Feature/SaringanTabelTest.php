<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Resources\Buktis\Pages\ListBuktis;
use App\Filament\Resources\DkpsBaris\Pages\ListDkpsBaris;
use App\Filament\Resources\Elemens\Pages\ListElemens;
use App\Filament\Resources\Narasis\Pages\ListNarasis;
use App\Filament\Resources\NilaiRumuses\Pages\ListNilaiRumuses;
use App\Filament\Resources\Penilaians\Pages\ListPenilaians;
use App\Filament\Resources\Tagihans\Pages\ListTagihans;
use App\Models\Kriteria;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Setiap saringan tabel benar-benar dijalankan.
 *
 * Alasannya konkret: Filament menyuntik argumen closure berdasarkan NAMA
 * parameter, bukan tipe. Closure `->query(fn (Builder $q) => ...)` tidak
 * pernah menerima kueri tabelnya — Filament mencoba membuat Builder lewat
 * container dan halamannya pecah dengan galat 500.
 *
 * Saringan yang tidak `->default()` hanya berjalan saat dinyalakan pengguna,
 * jadi kesalahan semacam itu bisa bertahan lama tanpa ketahuan. Uji ini
 * menyalakan semuanya satu per satu.
 */
class SaringanTabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DemoSeeder dipakai supaya setiap saringan punya baris untuk disaring;
        // saringan yang dijalankan di atas tabel kosong tidak membuktikan apa pun.
        $this->seed(DemoSeeder::class);
    }

    private function ketua(): User
    {
        return User::create([
            'name' => 'k', 'nama_lengkap' => 'Ketua uji',
            'email' => 'k-'.uniqid().'@saringan.test', 'password' => 'rahasia123',
            'peran' => PeranPengguna::Ketua, 'prodi_id' => Prodi::firstOrFail()->id,
            'aktif' => true, 'wajib_ganti_sandi' => false,
        ]);
    }

    /** @return array<string, array{class-string, string}> */
    public static function saringan(): array
    {
        return [
            'tagihan: terlambat' => [ListTagihans::class, 'terlambat'],
            'tagihan: syarat perlu' => [ListTagihans::class, 'syarat_perlu'],
            'tagihan: tanpa pj' => [ListTagihans::class, 'tanpa_pj'],
            'bukti: bermasalah' => [ListBuktis::class, 'bermasalah'],
            'bukti: perlu ditinjau' => [ListBuktis::class, 'perlu_ditinjau'],
            'elemen: syarat perlu' => [ListElemens::class, 'syarat_perlu'],
            'narasi: kurang kata' => [ListNarasis::class, 'kurang_kata'],
            'narasi: tanpa bukti' => [ListNarasis::class, 'tanpa_bukti'],
            'narasi: syarat perlu' => [ListNarasis::class, 'syarat_perlu'],
            'dkps: belum diverifikasi' => [ListDkpsBaris::class, 'belum_diverifikasi'],
            'dkps: calon selisih' => [ListDkpsBaris::class, 'calon_selisih'],
            'dkps: tanpa bukti' => [ListDkpsBaris::class, 'tanpa_bukti'],
            'nilai rumus: syarat perlu' => [ListNilaiRumuses::class, 'syarat_perlu'],
            'penilaian: syarat perlu' => [ListPenilaians::class, 'syarat_perlu'],
            'penilaian: diperselisihkan' => [ListPenilaians::class, 'diperselisihkan'],
        ];
    }

    #[Test]
    #[DataProvider('saringan')]
    public function saringan_berjalan_tanpa_galat(string $halaman, string $nama): void
    {
        // assertOk() mengembalikan respons, bukan komponen, jadi ia dipanggil
        // terakhir. Yang dibuktikan di sini sederhana tetapi cukup: saringannya
        // berjalan tanpa melempar — dan closure yang parameternya salah nama
        // akan melempar di sini.
        Livewire::actingAs($this->ketua())
            ->test($halaman)
            ->filterTable($nama)
            ->assertOk();
    }

    #[Test]
    public function saringan_bawaan_nilai_rumus_berjalan_saat_halaman_dibuka(): void
    {
        // Saringan ini `->default()`, jadi ia berjalan tanpa disentuh siapa
        // pun — dan dulu itulah yang membuat halamannya pecah dengan 500.
        $this->actingAs($this->ketua())
            ->get('/panel/nilai-rumuses')
            ->assertSuccessful();
    }

    #[Test]
    public function saringan_rentang_tanggal_bukti_berjalan(): void
    {
        Livewire::actingAs($this->ketua())
            ->test(ListBuktis::class)
            ->filterTable('tanggal_kejadian', [
                'dari' => now()->subYears(3)->toDateString(),
                'sampai' => now()->toDateString(),
            ])
            ->assertOk();
    }

    #[Test]
    public function saringan_kriteria_pada_tagihan_berjalan(): void
    {
        $kriteria = Kriteria::where('kode', 'K6')->firstOrFail();

        Livewire::actingAs($this->ketua())
            ->test(ListTagihans::class)
            ->filterTable('kriteria', $kriteria->id)
            ->assertOk();
    }
}
