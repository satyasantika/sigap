<?php

namespace Database\Factories;

use App\Models\Prodi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prodi>
 */
class ProdiFactory extends Factory
{
    protected $model = Prodi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => 'PPG-'.fake()->unique()->bothify('??##'),
            'nama' => 'Pendidikan Profesi Guru',
            'jenjang' => 'ppg',
            'upps' => 'Fakultas Keguruan dan Ilmu Pendidikan',
            'perguruan_tinggi' => 'Universitas Siliwangi',
            'aktif' => true,
        ];
    }
}
