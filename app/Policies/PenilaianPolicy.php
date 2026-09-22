<?php

namespace App\Policies;

class PenilaianPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'penilaian';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'dasbor.lihat',
            'view' => 'dasbor.lihat',
            'create' => 'penilaian.isi',
            'update' => 'penilaian.isi',
            'delete' => 'penilaian.isi',
            'restore' => 'penilaian.isi',
            'forceDelete' => 'penilaian.isi',
        ];
    }
}
