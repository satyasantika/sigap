<?php

namespace App\Policies;

use App\Models\DkpsBaris;
use App\Models\User;
use App\Support\Izin;

/**
 * Lingkup `pokjanya` bergantung pada pokja pemilik tabel (`pokja_id` baris),
 * bukan pada peran saja: koordinator POKJA-DIK boleh mengisi Tabel 18–24,
 * tetapi ditolak mengisi Tabel 7 milik POKJA-SDM.
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
