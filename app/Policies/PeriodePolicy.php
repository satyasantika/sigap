<?php

namespace App\Policies;

use App\Models\Periode;
use App\Models\User;
use App\Support\Izin;

class PeriodePolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'periode';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'dasbor.lihat',
            'view' => 'dasbor.lihat',
            'create' => 'periode.kelola',
            'update' => 'periode.kelola',
            'delete' => 'periode.kelola',
            'restore' => 'periode.kelola',
            'forceDelete' => 'periode.kelola',
        ];
    }

    /** Mengunci dan membuka periode — aksi tersendiri, bukan `update`. */
    public function kunci(User $u, Periode $p): bool
    {
        return Izin::boleh($u, 'periode.kunci', $p);
    }
}
