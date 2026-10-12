<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PokjaPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'pokja';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'dasbor.lihat',
            'view' => 'dasbor.lihat',
            'create' => 'pokja.kelola',
            'update' => 'pokja.kelola',
            'delete' => 'pokja.kelola',
            'restore' => 'pokja.kelola',
            'forceDelete' => 'pokja.kelola',
            'anggota' => 'pokja.anggota',
        ];
    }

    /**
     * Membuka halaman sunting pokja cukup dengan salah satu dari dua wewenang:
     * mengelola pokja, atau mengelola anggotanya. Koordinator hanya memegang
     * yang kedua, dan formulirnya dikunci untuknya (lihat PokjaForm).
     */
    public function update(User $u, Model $m): bool
    {
        return $this->putuskan($u, 'update', $m) || $this->putuskan($u, 'anggota', $m);
    }

    public function kelolaAnggota(User $u, Model $m): bool
    {
        return $this->putuskan($u, 'anggota', $m);
    }
}
