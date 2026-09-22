<?php

namespace App\Policies;

class NarasiPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'narasi';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'tagihan.lihat',
            'view' => 'tagihan.lihat',
            'create' => 'narasi.tulis',
            'update' => 'narasi.tulis',
            // Naskah LED tidak pernah dihapus, hanya ditulis ulang — dan tiap
            // penulisan ulang meninggalkan baris versi.
            'delete' => 'tidak-ada-aksi-ini',
            'restore' => 'tidak-ada-aksi-ini',
            'forceDelete' => 'tidak-ada-aksi-ini',
        ];
    }
}
