<?php

namespace App\Policies;

class UserPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'pengguna';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'pengguna.kelola',
            'view' => 'pengguna.kelola',
            'create' => 'pengguna.kelola',
            'update' => 'pengguna.kelola',
            'delete' => 'pengguna.kelola',
            'restore' => 'pengguna.kelola',
            'forceDelete' => 'pengguna.kelola',
        ];
    }
}
