<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberData;
use App\Filament\Resources\NilaiRumuses\Pages\ListNilaiRumuses;
use App\Filament\Resources\NilaiRumuses\Tables\NilaiRumusesTable;
use App\Models\DkpsButir;
use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengisiDkps;
use Database\Seeders\DatabaseSeeder;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tombol "Hitung dari DKPS" di layar Nilai Rumus dan kolom Skor A.
 * Fixture Tabel 1 + 6/7 ditulis lewat PengisiDkps. TS 2027.
 */
class NilaiRumusLayarTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->periode->forceFill(['ts_tahun' => 2027])->save();
        $this->prodi = Prodi::firstOrFail();
    }

    private function pengguna(PeranPengguna $peran, ?string $kodePokja = null): User
    {
        $u = User::factory()->peran($peran)->create([
            'prodi_id' => $this->prodi->id, 'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    private function isi(string $kodeLembar, array $data): void
    {
        $butir = DkpsButir::dariKode(explode('.', $kodeLembar)[0]);

        app(PengisiDkps::class)->simpan($butir, $kodeLembar, $this->periode, $data,
            SumberData::Manual, $this->pengguna(PeranPengguna::Koordinator, $butir->pokja_kode));
    }

    /** Tabel 1 ketiga lembar dan tiga DTPS dari Tabel 6/7 — cukup untuk RK. */
    private function fixture(): void
    {
        foreach ([['1.1', 'UniSZA', 'internasional'], ['1.2', 'BRIN', 'nasional'],
            ['1.3', 'SMAN 1 Tasikmalaya', 'wilayah_lokal']] as [$lembar, $mitra, $tingkat]) {
            $this->isi($lembar, [
                'lembaga_mitra' => $mitra, $tingkat => 'V', 'judul_kegiatan' => 'Kegiatan '.$mitra,
                'tanggal_awal' => '01/02/2026', 'tanggal_akhir' => '01/02/2028',
            ]);
        }

        $this->isi('6', ['nama_dtps' => 'Dr. Ani', 'nidn_nidk' => '0401010101', 'jabatan_akademik' => 'Lektor',
            'doktor' => 'Universitas Pendidikan Indonesia']);
        $this->isi('6', ['nama_dtps' => 'Dr. Budi', 'nidn_nidk' => '0402020202', 'jabatan_akademik' => 'Lektor Kepala',
            'doktor' => 'Universitas Pendidikan Indonesia']);
        $this->isi('7', ['nama_dosen' => 'Prof. Citra', 'nama_bidang_studi' => 'Matematika',
            'nidn_nidk' => '0403030303', 'jabatan_akademik' => 'Guru Besar', 'doktor' => null]);
    }

    #[Test]
    public function ketua_melihat_tombol_hitung_dan_menjalankannya(): void
    {
        $this->fixture();
        $this->actingAs($this->pengguna(PeranPengguna::Ketua));

        $this->assertSame(0, NilaiRumus::count());

        Livewire::test(ListNilaiRumuses::class)
            ->assertActionVisible('hitung')
            ->callAction('hitung');

        $this->assertGreaterThan(0, NilaiRumus::count());
        $this->assertTrue(NilaiRumus::where('periode_id', $this->periode->id)
            ->where('rumus_kode', 'RK')->exists(), 'RK harus tersimpan dari fixture Tabel 1 + 6/7.');
        $this->assertSame(auth()->id(), NilaiRumus::where('rumus_kode', 'RK')->value('dihitung_oleh'));

        // Fixture hanya mengisi Tabel 1, 6, dan 7: banyak rumus dilewati, jadi
        // notifikasinya peringatan berisi lima alasan pertama dan sisanya.
        $daftar = new Notifications;
        $daftar->mount();
        $notifikasi = $daftar->notifications->last();
        $this->assertSame('warning', $notifikasi->getStatus());
        $this->assertStringStartsWith('Disimpan ', $notifikasi->getTitle());
        $this->assertCount(6, explode('<br>', $notifikasi->getBody()), 'Lima alasan ditambah baris sisa.');
        $this->assertMatchesRegularExpression('/… dan \d+ lagi$/', $notifikasi->getBody());
    }

    #[Test]
    public function koordinator_pokja_data_melihat_tombol(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Koordinator, 'POKJA-DATA'));

        Livewire::test(ListNilaiRumuses::class)->assertActionVisible('hitung');
    }

    #[Test]
    public function anggota_tidak_melihat_tombol(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Anggota, 'POKJA-DIK'));

        Livewire::test(ListNilaiRumuses::class)->assertActionHidden('hitung');
    }

    #[Test]
    public function kolom_skor_a_tampil(): void
    {
        $this->fixture();
        $this->artisan('rumus:hitung')->assertSuccessful();
        $this->actingAs($this->pengguna(PeranPengguna::Ketua));

        $rk = NilaiRumus::where('rumus_kode', 'RK')->firstOrFail();
        $this->assertNotNull($rk->skor_tepat);

        Livewire::test(ListNilaiRumuses::class)
            ->assertTableColumnExists('skor_tepat')
            ->assertSee('Skor A')
            ->assertSee(number_format((float) $rk->skor_tepat, 2, ',', '.'))
            // Variabel sumber ditampilkan di kolom Variabel DKPS.
            ->assertTableColumnExists('variabel_dkps')
            ->assertSee('N_KERJASAMA_PENDIDIKAN');

        // Sanitizer HTML Filament menulis "=" sebagai entitas, jadi teks
        // barisnya diperiksa dari pembentuknya.
        $this->assertStringContainsString('N_KERJASAMA_PENDIDIKAN = 1 (1.1)',
            NilaiRumusesTable::daftarVariabel($rk->komponen));
    }

    #[Test]
    public function komponen_lama_tanpa_variabel_tampil_kosong(): void
    {
        $this->assertNull(NilaiRumusesTable::daftarVariabel(null));
        $this->assertNull(NilaiRumusesTable::daftarVariabel(['n1' => 2, 'ndtps' => 3]));
    }
}
