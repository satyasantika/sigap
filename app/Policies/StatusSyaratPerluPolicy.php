<?php

namespace App\Policies;

/**
 * Hanya ketua yang boleh mengubah level syarat perlu — `syarat_perlu.ubah`
 * bernilai `ya` hanya untuknya. Kelima baris ini menentukan hasil akhir
 * akreditasi, jadi wewenangnya sesempit mungkin.
 */
class StatusSyaratPerluPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'syarat_perlu';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'dasbor.lihat',
            'view' => 'dasbor.lihat',
            'create' => 'syarat_perlu.ubah',
            'update' => 'syarat_perlu.ubah',
            'delete' => 'syarat_perlu.ubah',
            'restore' => 'syarat_perlu.ubah',
            'forceDelete' => 'syarat_perlu.ubah',
        ];
    }
}
