<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\Izin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeranGandaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function wewenang_adalah_gabungan_seluruh_peran(): void
    {
        $anggota = User::factory()->peran(PeranPengguna::Anggota)->create();
        $ganda = User::factory()->peran(PeranPengguna::Anggota)->create([
            'peran_tambahan' => [PeranPengguna::Admin->value, PeranPengguna::Auditor->value],
        ]);

        $this->assertFalse(Izin::bolehSistem($anggota, 'impersonasi.mulai'));
        $this->assertTrue(Izin::bolehSistem($ganda, 'impersonasi.mulai'));
        $this->assertSame(
            [PeranPengguna::Anggota, PeranPengguna::Admin, PeranPengguna::Auditor],
            $ganda->semuaPeran(),
        );
    }

    #[Test]
    public function peran_tambahan_yang_kembar_atau_tak_dikenal_diabaikan(): void
    {
        $u = User::factory()->peran(PeranPengguna::Ketua)->create([
            'peran_tambahan' => ['ketua', 'tidak-ada', 'auditor'],
        ]);

        $this->assertSame([PeranPengguna::Ketua, PeranPengguna::Auditor], $u->semuaPeran());
    }

    #[Test]
    public function scope_berperan_mencakup_peran_tambahan(): void
    {
        $u = User::factory()->peran(PeranPengguna::Anggota)->create([
            'peran_tambahan' => [PeranPengguna::Auditor->value],
        ]);

        $this->assertTrue(User::berperan(PeranPengguna::Auditor)->whereKey($u->id)->exists());
        $this->assertFalse(User::berperan(PeranPengguna::Pimpinan)->whereKey($u->id)->exists());
    }
}
