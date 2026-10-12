<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Pages\TagihanSaya;
use App\Filament\Resources\Tagihans\Pages\ListTagihans;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\PembangkitTagihan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pembuktian ujung ke ujung: tagihan yang ditugaskan benar-benar tampil bagi
 * koordinator dan anggota pokja, termasuk koordinator yang hanya ditunjuk di
 * form Pokja (tanpa baris keanggotaan).
 */
class TagihanTerlihatTest extends TestCase
{
    use RefreshDatabase;

    private Pokja $dik;

    private Pokja $mutu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        User::query()->update(['wajib_ganti_sandi' => false]);
        app(PembangkitTagihan::class)->untuk(Periode::firstOrFail());

        $this->dik = Pokja::where('kode', 'POKJA-DIK')->firstOrFail();
        $this->mutu = Pokja::where('kode', 'POKJA-MUTU')->firstOrFail();
    }

    private function pengguna(PeranPengguna $peran): User
    {
        return User::factory()->peran($peran)->create(['wajib_ganti_sandi' => false]);
    }

    private function tagihanPokja(Pokja $pokja, int $n = 2)
    {
        return Tagihan::where('pokja_id', $pokja->id)->orderBy('urutan')->take($n)->get();
    }

    #[Test]
    public function koordinator_yang_hanya_ditunjuk_melihat_tagihan_pokjanya(): void
    {
        $koord = $this->pengguna(PeranPengguna::Koordinator);
        $this->mutu->update(['koordinator_id' => $koord->id]);
        $milik = $this->tagihanPokja($this->mutu);
        $lain = $this->tagihanPokja($this->dik);

        $this->actingAs($koord);

        Livewire::test(ListTagihans::class)
            ->assertCanSeeTableRecords($milik)
            ->assertCanNotSeeTableRecords($lain);
    }

    #[Test]
    public function menunjuk_koordinator_mendaftarkannya_sebagai_anggota(): void
    {
        $koord = $this->pengguna(PeranPengguna::Koordinator);

        $this->mutu->update(['koordinator_id' => $koord->id]);

        $this->assertDatabaseHas('pokja_user', [
            'pokja_id' => $this->mutu->id,
            'user_id' => $koord->id,
            'peran_dalam_pokja' => 'koordinator',
        ]);
    }

    #[Test]
    public function koordinator_melihat_tagihan_yang_ditugaskan_kepadanya(): void
    {
        $koord = $this->pengguna(PeranPengguna::Koordinator);
        $this->mutu->update(['koordinator_id' => $koord->id]);
        $t = $this->tagihanPokja($this->mutu, 1)->first();
        $t->update(['penanggung_jawab_id' => $koord->id]);

        $this->actingAs($koord);

        Livewire::test(TagihanSaya::class)->assertCanSeeTableRecords([$t]);
    }

    #[Test]
    public function koordinator_melihat_tagihan_pokja_yang_perlu_ditugaskan(): void
    {
        $koord = $this->pengguna(PeranPengguna::Koordinator);
        $this->mutu->update(['koordinator_id' => $koord->id]);
        $belum = $this->tagihanPokja($this->mutu);
        $lain = $this->tagihanPokja($this->dik);
        Tagihan::whereIn('id', $belum->pluck('id'))->update(['penanggung_jawab_id' => null]);

        $this->actingAs($koord);

        Livewire::test(TagihanSaya::class)
            ->assertCanNotSeeTableRecords($belum)
            ->callAction('perlu_ditugaskan')
            ->assertCanSeeTableRecords($belum)
            ->assertCanNotSeeTableRecords($lain);
    }

    #[Test]
    public function anggota_melihat_dan_membuka_tagihan_yang_ditugaskan_kepadanya(): void
    {
        $anggota = $this->pengguna(PeranPengguna::Anggota);
        $this->mutu->anggota()->attach($anggota->id);
        [$milik, $orangLain] = $this->tagihanPokja($this->mutu)->all();
        $milik->update(['penanggung_jawab_id' => $anggota->id]);
        $orangLain->update(['penanggung_jawab_id' => $this->pengguna(PeranPengguna::Anggota)->id]);

        $this->actingAs($anggota);

        Livewire::test(TagihanSaya::class)
            ->assertCanSeeTableRecords([$milik])
            ->assertCanNotSeeTableRecords([$orangLain]);

        $this->get("/panel/tagihans/{$milik->id}")->assertSuccessful();
        $this->get("/panel/tagihans/{$orangLain->id}")->assertNotFound();
    }

    #[Test]
    public function anggota_tidak_melihat_tab_perlu_ditugaskan(): void
    {
        $anggota = $this->pengguna(PeranPengguna::Anggota);
        $this->mutu->anggota()->attach($anggota->id);
        $this->actingAs($anggota);

        Livewire::test(TagihanSaya::class)->assertActionDoesNotExist('perlu_ditugaskan');
    }
}
