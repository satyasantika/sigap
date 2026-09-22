<?php

namespace Database\Factories;

use App\Enums\PeranPengguna;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nama = fake()->name();

        return [
            'name' => $nama,
            'nama_lengkap' => $nama,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Peran paling sempit sebagai bawaan: uji yang butuh wewenang
            // lebih harus memintanya terang-terangan lewat state di bawah,
            // supaya tidak ada uji yang lulus karena kebetulan berperan ketua.
            'peran' => PeranPengguna::Anggota,
            'prodi_id' => Prodi::factory(),
            'aktif' => true,
            'wajib_ganti_sandi' => false,
        ];
    }

    public function peran(PeranPengguna $peran): static
    {
        return $this->state(fn () => ['peran' => $peran]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
