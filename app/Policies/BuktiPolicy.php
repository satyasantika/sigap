<?php

namespace App\Policies;

use App\Models\Bukti;
use App\Models\User;
use App\Support\Izin;

/**
 * Perhatikan `bukti.hapus`: hanya ketua. Menghapus bukti yang sudah menopang
 * elemen memutus klaim tanpa jejak, jadi wewenangnya sesempit mungkin.
 */
class BuktiPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'bukti';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'tagihan.lihat',
            'view' => 'tagihan.lihat',
            'create' => 'bukti.unggah',
            'update' => 'bukti.unggah',
            'delete' => 'bukti.hapus',
            'restore' => 'bukti.hapus',
            'forceDelete' => 'bukti.hapus',
        ];
    }

    public function validasi(User $u, Bukti $b): bool
    {
        return Izin::boleh($u, 'bukti.validasi', $b);
    }

    public function komentari(User $u, Bukti $b): bool
    {
        return Izin::boleh($u, 'komentar.tulis', $b);
    }
}
