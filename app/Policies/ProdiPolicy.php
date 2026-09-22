<?php

namespace App\Policies;

class ProdiPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'prodi';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'dasbor.lihat',
            'view' => 'dasbor.lihat',
            'create' => 'prodi.kelola',
            'update' => 'prodi.kelola',
            'delete' => 'prodi.kelola',
            'restore' => 'prodi.kelola',
            'forceDelete' => 'prodi.kelola',
        ];
    }
}
