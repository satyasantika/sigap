<?php

namespace Tests\Feature;

use App\Enums\JenisTagihan;
use App\Enums\PeranPengguna;
use App\Enums\StatusPeriode;
use App\Enums\StatusTagihan;
use App\Exceptions\RiwayatTidakBolehDiubah;
use App\Exceptions\TransisiTidakSah;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Prodi;
use App\Models\Tagihan;
use App\Models\TagihanRiwayat;
use App\Models\User;
use App\Services\AlurTagihan;
use Database\Seeders\IzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gerbang tunggal perpindahan status.
 *
 * Yang dijaga di sini: jalur yang sah, wewenang, catatan wajib, dan yang
 * paling penting — bahwa SETIAP perpindahan meninggalkan tepat satu baris
 * riwayat yang tidak bisa disunting.
 */
class AlurTagihanTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    private Periode $periode;

    private Pokja $pokjaDik;

    private Pokja $pokjaSdm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(IzinSeeder::class);

        $this->prodi = Prodi::create([
            'kode' => 'PPG-UJI', 'nama' => 'PPG', 'jenjang' => 'ppg',
            'upps' => 'FKIP', 'perguruan_tinggi' => 'Unsil', 'aktif' => true,
        ]);

        $this->periode = Periode::create([
            'prodi_id' => $this->prodi->id, 'nama' => 'PPG Uji', 'ts_tahun' => 2027,
            'versi_instrumen' => 'IAPSK 3.0', 'status' => StatusPeriode::Berjalan,
        ]);

        $this->pokjaDik = Pokja::create([
            'periode_id' => $this->periode->id, 'kode' => 'POKJA-DIK', 'nama' => 'Pendidikan',
        ]);
        $this->pokjaSdm = Pokja::create([
            'periode_id' => $this->periode->id, 'kode' => 'POKJA-SDM', 'nama' => 'SDM',
        ]);
    }

    private function pengguna(PeranPengguna $peran, ?Pokja $pokja = null): User
    {
        $u = User::create([
            'name' => $peran->value, 'nama_lengkap' => $peran->label(),
            'email' => $peran->value.'-'.uniqid().'@alur.test', 'password' => 'rahasia123',
            'peran' => $peran, 'prodi_id' => $this->prodi->id, 'aktif' => true,
        ]);

        if ($pokja !== null) {
            $u->pokja()->attach($pokja->id);
        }

        return $u;
    }

    private function tagihan(array $ubah = []): Tagihan
    {
        return Tagihan::create(array_merge([
            'prodi_id' => $this->prodi->id,
            'periode_id' => $this->periode->id,
            'pokja_id' => $this->pokjaDik->id,
            'jenis' => JenisTagihan::Bukti,
            'judul' => 'Tagihan uji',
            'bobot_terkait' => 1.000,
        ], $ubah));
    }

    private function alur(): AlurTagihan
    {
        return app(AlurTagihan::class);
    }

    // ---- Jalur transisi -------------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function transisiTidakSah(): array
    {
        return [
            'belum -> diajukan (melompati dikerjakan)' => ['belum', 'diajukan'],
            'belum -> disetujui (melompati semuanya)' => ['belum', 'disetujui'],
            'dikerjakan -> disetujui (tanpa reviu)' => ['dikerjakan', 'disetujui'],
            'diajukan -> disetujui (tanpa reviu)' => ['diajukan', 'disetujui'],
            'disetujui -> dikerjakan (membatalkan persetujuan)' => ['disetujui', 'dikerjakan'],
            'disetujui -> dikembalikan (jalur buntu)' => ['disetujui', 'dikembalikan'],
            'dikembalikan -> diajukan (melompati dikerjakan)' => ['dikembalikan', 'diajukan'],
        ];
    }

    #[Test]
    #[DataProvider('transisiTidakSah')]
    public function transisi_di_luar_tabel_alur_ditolak(string $dari, string $ke): void
    {
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $t = $this->tagihan(['penanggung_jawab_id' => $pj->id]);
        $t->forceFill(['status' => StatusTagihan::from($dari)])->save();

        $this->expectException(TransisiTidakSah::class);
        $this->expectExceptionMessageMatches('/Tidak ada jalur/');

        $this->alur()->pindah($t, StatusTagihan::from($ke), $this->pengguna(PeranPengguna::Ketua));
    }

    #[Test]
    public function alur_penuh_dari_belum_sampai_disetujui(): void
    {
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, $this->pokjaDik);
        $ketua = $this->pengguna(PeranPengguna::Ketua);

        $t = $this->tagihan(['penanggung_jawab_id' => $pj->id]);

        $this->alur()->pindah($t, StatusTagihan::Dikerjakan, $pj);
        $this->alur()->pindah($t->fresh(), StatusTagihan::Diajukan, $pj);
        $this->alur()->pindah($t->fresh(), StatusTagihan::Direviu, $koordinator);
        $this->alur()->pindah($t->fresh(), StatusTagihan::Disetujui, $ketua);

        $t = $t->fresh();

        $this->assertSame(StatusTagihan::Disetujui, $t->status);
        $this->assertSame($ketua->id, $t->disetujui_oleh);
        $this->assertNotNull($t->disetujui_pada);
        $this->assertSame(4, $t->riwayat()->count(), 'Empat perpindahan, empat baris riwayat.');
    }

    // ---- Wewenang -------------------------------------------------------

    #[Test]
    public function koordinator_bisa_mereviu_tetapi_tidak_bisa_menyetujui(): void
    {
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, $this->pokjaDik);
        $t = $this->tagihan(['penanggung_jawab_id' => $koordinator->id]);
        $t->forceFill(['status' => StatusTagihan::Diajukan])->save();

        // Mereviu: boleh.
        $this->alur()->pindah($t, StatusTagihan::Direviu, $koordinator);
        $this->assertSame(StatusTagihan::Direviu, $t->fresh()->status);

        // Menyetujui: tidak. Persetujuan akhir hanya ketua.
        $this->expectException(TransisiTidakSah::class);
        $this->expectExceptionMessageMatches('/tidak berwenang/');

        $this->alur()->pindah($t->fresh(), StatusTagihan::Disetujui, $koordinator);
    }

    #[Test]
    public function koordinator_tidak_berkuasa_atas_pokja_lain(): void
    {
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, $this->pokjaDik);
        $t = $this->tagihan(['pokja_id' => $this->pokjaSdm->id]);
        $t->forceFill(['status' => StatusTagihan::Diajukan])->save();

        $this->expectException(TransisiTidakSah::class);

        $this->alur()->pindah($t, StatusTagihan::Direviu, $koordinator);
    }

    #[Test]
    public function pimpinan_tidak_bisa_memindahkan_status_apa_pun(): void
    {
        $pimpinan = $this->pengguna(PeranPengguna::Pimpinan);
        $t = $this->tagihan(['penanggung_jawab_id' => $pimpinan->id]);
        $t->forceFill(['status' => StatusTagihan::Diajukan])->save();

        foreach ([StatusTagihan::Direviu, StatusTagihan::Dikembalikan] as $ke) {
            try {
                $this->alur()->pindah($t->fresh(), $ke, $pimpinan, 'catatan');
                $this->fail("Pimpinan seharusnya tidak bisa memindahkan ke {$ke->value}.");
            } catch (TransisiTidakSah $e) {
                $this->assertStringContainsString('tidak berwenang', $e->getMessage());
            }
        }
    }

    // ---- Syarat tambahan ------------------------------------------------

    #[Test]
    public function mengembalikan_tanpa_catatan_ditolak(): void
    {
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $t = $this->tagihan();
        $t->forceFill(['status' => StatusTagihan::Diajukan])->save();

        $this->expectException(TransisiTidakSah::class);
        $this->expectExceptionMessageMatches('/wajib disertai catatan/');

        $this->alur()->pindah($t, StatusTagihan::Dikembalikan, $ketua);
    }

    #[Test]
    public function mengembalikan_dengan_catatan_diterima_dan_catatannya_tersimpan(): void
    {
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $t = $this->tagihan(['penanggung_jawab_id' => $pj->id]);
        $t->forceFill(['status' => StatusTagihan::Diajukan])->save();

        $this->alur()->pindah($t, StatusTagihan::Dikembalikan, $ketua, 'Bukti SK belum dilampirkan.');

        $riwayat = $t->fresh()->riwayat()->first();

        $this->assertSame(StatusTagihan::Dikembalikan, $t->fresh()->status);
        $this->assertSame('Bukti SK belum dilampirkan.', $riwayat->catatan);
    }

    #[Test]
    public function tagihan_tanpa_penanggung_jawab_tidak_bisa_dikerjakan(): void
    {
        // Aktornya koordinator pokja ini, bukan ketua: `tagihan.ajukan`
        // bernilai `tidak` untuk ketua, jadi pemakaian ketua akan gagal karena
        // wewenang dan menyembunyikan apa yang sebenarnya diuji di sini.
        $koordinator = $this->pengguna(PeranPengguna::Koordinator, $this->pokjaDik);
        $t = $this->tagihan(['penanggung_jawab_id' => null]);

        $this->expectException(TransisiTidakSah::class);
        $this->expectExceptionMessageMatches('/belum punya penanggung jawab/');

        $this->alur()->pindah($t, StatusTagihan::Dikerjakan, $koordinator);
    }

    #[Test]
    public function narasi_tanpa_naskah_tidak_bisa_diajukan(): void
    {
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $t = $this->tagihan(['jenis' => JenisTagihan::Narasi, 'penanggung_jawab_id' => $pj->id]);
        $t->forceFill(['status' => StatusTagihan::Dikerjakan])->save();

        $this->expectException(TransisiTidakSah::class);
        $this->expectExceptionMessageMatches('/Narasi belum bisa diajukan/');

        $this->alur()->pindah($t, StatusTagihan::Diajukan, $pj);
    }

    #[Test]
    public function persetujuan_dicabut_saat_tagihan_dikembalikan(): void
    {
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $ketua = $this->pengguna(PeranPengguna::Ketua);
        $t = $this->tagihan(['penanggung_jawab_id' => $pj->id]);
        $t->forceFill(['status' => StatusTagihan::Direviu])->save();

        $this->alur()->pindah($t, StatusTagihan::Dikembalikan, $ketua, 'Perlu diperbaiki.');

        // Kalau jejak persetujuan tertinggal, dasbor akan menghitungnya selesai.
        $this->assertNull($t->fresh()->disetujui_oleh);
        $this->assertNull($t->fresh()->disetujui_pada);
    }

    // ---- Riwayat append only --------------------------------------------

    #[Test]
    public function setiap_perpindahan_menulis_tepat_satu_baris_riwayat(): void
    {
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $t = $this->tagihan(['penanggung_jawab_id' => $pj->id]);

        $this->assertSame(0, TagihanRiwayat::count());

        $this->alur()->pindah($t, StatusTagihan::Dikerjakan, $pj);

        $this->assertSame(1, TagihanRiwayat::count());

        $r = TagihanRiwayat::first();
        $this->assertSame(StatusTagihan::Belum, $r->status_dari);
        $this->assertSame(StatusTagihan::Dikerjakan, $r->status_ke);
        $this->assertSame($pj->id, $r->user_id);
    }

    #[Test]
    public function riwayat_menolak_disunting(): void
    {
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $t = $this->tagihan(['penanggung_jawab_id' => $pj->id]);
        $this->alur()->pindah($t, StatusTagihan::Dikerjakan, $pj);

        $this->expectException(RiwayatTidakBolehDiubah::class);
        $this->expectExceptionMessageMatches('/append only/');

        TagihanRiwayat::first()->update(['catatan' => 'disisipkan diam-diam']);
    }

    #[Test]
    public function riwayat_menolak_dihapus(): void
    {
        $pj = $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik);
        $t = $this->tagihan(['penanggung_jawab_id' => $pj->id]);
        $this->alur()->pindah($t, StatusTagihan::Dikerjakan, $pj);

        $this->expectException(RiwayatTidakBolehDiubah::class);

        TagihanRiwayat::first()->delete();
    }

    #[Test]
    public function riwayat_tidak_punya_kolom_updated_at(): void
    {
        $this->assertNull(TagihanRiwayat::UPDATED_AT);
        $this->assertFalse(
            Schema::hasColumn('tagihan_riwayat', 'updated_at'),
            'tagihan_riwayat bersifat append only; updated_at tidak boleh ada.'
        );
    }

    // ---- Periode terkunci -----------------------------------------------

    #[Test]
    public function periode_terkunci_menolak_perpindahan_untuk_semua_peran(): void
    {
        $this->periode->forceFill(['status' => StatusPeriode::Dikunci])->save();

        $t = $this->tagihan(['penanggung_jawab_id' => $this->pengguna(PeranPengguna::Anggota, $this->pokjaDik)->id]);
        $t->forceFill(['status' => StatusTagihan::Diajukan])->save();

        foreach (PeranPengguna::cases() as $peran) {
            $u = $this->pengguna($peran, $this->pokjaDik);

            try {
                $this->alur()->pindah($t->fresh(), StatusTagihan::Direviu, $u);
                $this->fail("Periode terkunci: {$peran->value} seharusnya ditolak.");
            } catch (TransisiTidakSah $e) {
                $this->assertStringContainsString('tidak berwenang', $e->getMessage());
            }
        }
    }

    // ---- Status tidak bisa diubah di luar AlurTagihan --------------------

    #[Test]
    public function status_tidak_fillable_lewat_mass_assignment(): void
    {
        $t = $this->tagihan();

        // Kalau status bisa diisi lewat create/update biasa, riwayatnya bolong
        // dan tidak ada cara mengetahui di mana bolongnya.
        $t->update(['status' => StatusTagihan::Disetujui, 'judul' => 'Judul baru']);

        $this->assertSame('Judul baru', $t->fresh()->judul);
        $this->assertSame(StatusTagihan::Belum, $t->fresh()->status, 'status tidak boleh bisa diisi massal.');
    }
}
