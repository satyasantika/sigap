<?php

namespace App\Policies;

use App\Models\DkpsBaris;
use App\Models\User;
use App\Support\Izin;

/**
 * Lingkup `pokja_data` bergantung keanggotaan di POKJA-DATA, bukan pada peran.
 * Seorang koordinator POKJA-DIK ditolak mengisi DKPS, sementara anggota biasa
 * POKJA-DATA justru boleh.
 */
class DkpsBarisPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'dkps';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'tagihan.lihat',
            'view' => 'tagihan.lihat',
            'create' => 'dkps.isi',
            'update' => 'dkps.isi',
            'delete' => 'dkps.isi',
            'restore' => 'dkps.isi',
            'forceDelete' => 'dkps.isi',
        ];
    }

    public function verifikasi(User $u, DkpsBaris $b): bool
    {
        return Izin::boleh($u, 'dkps.verifikasi', $b);
    }
}
