<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabel referensi instrumen: boleh dibaca siapa pun yang bisa membuka dasbor,
 * tidak boleh disunting siapa pun — termasuk admin.
 *
 * Mengubah instrumen berarti menyunting data/*.json lalu menjalankan seeder,
 * dan perubahan itu wajib menjadi commit tersendiri berjenis `data:`. Kalau
 * angka bobot bisa diubah lewat layar, jejaknya hilang dan `verifikasi.py`
 * tidak lagi menjaga apa pun.
 */
class ReferensiPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'referensi';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'dasbor.lihat',
            'view' => 'dasbor.lihat',
        ];
    }

    public function create(User $u): bool
    {
        return false;
    }

    public function update(User $u, Model $m): bool
    {
        return false;
    }

    public function delete(User $u, Model $m): bool
    {
        return false;
    }

    public function restore(User $u, Model $m): bool
    {
        return false;
    }

    public function forceDelete(User $u, Model $m): bool
    {
        return false;
    }
}
