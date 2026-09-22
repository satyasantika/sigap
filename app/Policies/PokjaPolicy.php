<?php

namespace App\Policies;

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
        ];
    }
}
