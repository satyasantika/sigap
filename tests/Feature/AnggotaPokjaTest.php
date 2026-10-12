<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Resources\Pokjas\Pages\EditPokja;
use App\Filament\Resources\Pokjas\RelationManagers\AnggotaRelationManager;
use App\Models\Pokja;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnggotaPokjaTest extends TestCase
{
    use RefreshDatabase;

    private Pokja $dik;

    private Pokja $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        // Pengguna contoh dipaksa ganti sandi; itu urusan uji lain.
        User::query()->update(['wajib_ganti_sandi' => false]);
        $this->dik = Pokja::where('kode', 'POKJA-DIK')->firstOrFail();
        $this->data = Pokja::where('kode', 'POKJA-DATA')->firstOrFail();
    }

    private function koordinator(): User
    {
        return User::where('email', 'koordinator@sigap.test')->firstOrFail();
    }

    #[Test]
    public function koordinator_membuka_pokjanya_sendiri(): void
    {
        $this->actingAs($this->koordinator())
            ->get("/panel/pokjas/{$this->dik->id}/edit")
            ->assertSuccessful();
    }

    #[Test]
    public function koordinator_tidak_bisa_membuka_pokja_lain(): void
    {
        $this->actingAs($this->koordinator())
            ->get("/panel/pokjas/{$this->data->id}/edit")
            ->assertNotFound();
    }

    #[Test]
    public function koordinator_tidak_bisa_membuat_pokja(): void
    {
        $this->actingAs($this->koordinator())
            ->get('/panel/pokjas/create')
            ->assertForbidden();
    }

    #[Test]
    public function koordinator_menambah_dan_melepas_anggota_pokjanya(): void
    {
        $baru = User::factory()->peran(PeranPengguna::Anggota)->create();
        $this->actingAs($this->koordinator());

        Livewire::test(AnggotaRelationManager::class, [
            'ownerRecord' => $this->dik,
            'pageClass' => EditPokja::class,
        ])
            ->callTableAction('attach', data: [
                'recordId' => $baru->id,
                'peran_dalam_pokja' => 'anggota',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($this->dik->anggota()->whereKey($baru->id)->exists());

        Livewire::test(AnggotaRelationManager::class, [
            'ownerRecord' => $this->dik,
            'pageClass' => EditPokja::class,
        ])->callTableAction('detach', $baru);

        $this->assertFalse($this->dik->anggota()->whereKey($baru->id)->exists());
    }

    #[Test]
    public function ketua_mengelola_anggota_pokja_mana_pun(): void
    {
        $ketua = User::where('email', 'ketua@sigap.test')->firstOrFail();

        $this->actingAs($ketua)
            ->get("/panel/pokjas/{$this->data->id}/edit")
            ->assertSuccessful();
    }

    #[Test]
    public function anggota_biasa_tidak_bisa_mengelola_anggota(): void
    {
        $this->actingAs(User::where('email', 'anggota@sigap.test')->firstOrFail())
            ->get("/panel/pokjas/{$this->dik->id}/edit")
            ->assertForbidden();
    }
}
