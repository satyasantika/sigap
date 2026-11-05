<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
use App\Enums\SumberData;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\User;
use App\Services\PengisiDkps;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Perintah rumus:hitung membungkus PenghitungRumusDkps. Fixture lewat PengisiDkps, TS 2027.
 */
class HitungRumusCommandTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Prodi $prodi;

    private PengisiDkps $pengisi;

    /** @var array<string, User> koordinator per kode pokja */
    private array $koordinator = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->periode = Periode::firstOrFail();
        $this->periode->forceFill(['ts_tahun' => 2027])->save();
        $this->prodi = Prodi::firstOrFail();
        $this->pengisi = app(PengisiDkps::class);
    }

    private function pengguna(PeranPengguna $peran, ?string $kodePokja = null): User
    {
        $u = User::create([
            'name' => 'u', 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@dkps.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
            'wajib_ganti_sandi' => false,
        ]);

        if ($kodePokja !== null) {
            $u->pokja()->attach(Pokja::where('kode', $kodePokja)->firstOrFail()->id);
        }

        return $u;
    }

    /** Menulis satu baris lewat PengisiDkps oleh koordinator pokja pemilik tabel. */
    private function isi(string $kodeLembar, array $data, ?string $kunciBaris = null): DkpsBaris
    {
        $butir = DkpsButir::dariKode(explode('.', $kodeLembar)[0]);
        $pokja = $butir->pokja_kode;
        $this->koordinator[$pokja] ??= $this->pengguna(PeranPengguna::Koordinator, $pokja);

        return $this->pengisi->simpan($butir, $kodeLembar, $this->periode, $data,
            SumberData::Manual, $this->koordinator[$pokja], kunciBaris: $kunciBaris);
    }

    private function kerjaSama(string $lembar, string $mitra, string $tingkat): void
    {
        $this->isi($lembar, [
            'lembaga_mitra' => $mitra, $tingkat => 'V', 'judul_kegiatan' => 'Kegiatan '.$mitra,
            'tanggal_awal' => '01/02/2026', 'tanggal_akhir' => '01/02/2028',
        ]);
    }

    private function dosen(string $lembar, string $nama, string $nidn, string $jabatan, ?string $doktor = null): void
    {
        $data = ['nidn_nidk' => $nidn, 'jabatan_akademik' => $jabatan, 'doktor' => $doktor];

        $this->isi($lembar, $lembar === '6'
            ? $data + ['nama_dtps' => $nama]
            : $data + ['nama_dosen' => $nama, 'nama_bidang_studi' => 'Matematika']);
    }

    /** Tabel 1 ketiga lembar terisi. */
    private function tabel1(): void
    {
        $this->kerjaSama('1.1', 'UniSZA', 'internasional');
        $this->kerjaSama('1.1', 'Dinas Pendidikan Jabar', 'nasional');
        $this->kerjaSama('1.2', 'BRIN', 'nasional');
        $this->kerjaSama('1.3', 'SMAN 1 Tasikmalaya', 'wilayah_lokal');
    }

    /** Tiga DTPS unik dari Tabel 6 dan 7. */
    private function tigaDosen(): void
    {
        $this->dosen('6', 'Dr. Ani', '0401010101', 'Lektor', 'Universitas Pendidikan Indonesia');
        $this->dosen('6', 'Dr. Budi', '0402020202', 'Lektor Kepala', 'Universitas Pendidikan Indonesia');
        $this->dosen('7', 'Prof. Citra', '0403030303', 'Guru Besar');
    }

    #[Test]
    public function menghitung_periode_aktif_dan_melaporkan_tiga_blok(): void
    {
        $this->tabel1();
        $this->tigaDosen();

        $this->artisan('rumus:hitung')
            ->expectsOutputToContain('Periode '.$this->periode->nama.' (TS 2027)')
            ->expectsOutputToContain('RK')
            ->expectsOutputToContain('Tidak berubah')
            ->expectsOutputToContain('Dilewati')
            ->expectsOutputToContain('Disimpan ')
            ->assertExitCode(0);

        $this->assertTrue(NilaiRumus::where('rumus_kode', 'RK')->exists());
    }

    #[Test]
    public function periode_tak_dikenal_gagal(): void
    {
        $this->artisan('rumus:hitung', ['--periode' => 'bukan-uuid'])
            ->expectsOutputToContain('Periode bukan-uuid tidak ditemukan')
            ->assertExitCode(1);
    }

    #[Test]
    public function periode_terkunci_dilaporkan_tanpa_menulis(): void
    {
        $this->tabel1();
        $this->tigaDosen();
        $this->periode->forceFill(['status' => StatusPeriode::Dikunci])->save();

        $this->artisan('rumus:hitung', ['--periode' => $this->periode->id])
            ->expectsOutputToContain('Periode terkunci; rumus tidak dihitung ulang.')
            ->assertExitCode(0);

        $this->assertSame(0, NilaiRumus::count());
    }

    #[Test]
    public function tanpa_periode_aktif_dilaporkan(): void
    {
        $this->periode->forceFill(['status' => StatusPeriode::Dikunci])->save();

        $this->artisan('rumus:hitung')
            ->expectsOutputToContain('Tidak ada periode aktif.')
            ->assertExitCode(0);
    }
}
