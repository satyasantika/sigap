<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Hasil perhitungan hanya-baca. Ia lahir dari KalkulatorRumus, bukan diketik
 * orang — angka yang bisa diketik tangan berhenti menjadi hasil perhitungan.
 */
class NilaiRumusPolicy extends BasePolicy
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
        ];
    }

    public function create(User $u): bool
    {
        return false;
    }

    public function update(User $u, Model $m): bool
    {
        return false;
    }

    public function delete(User $u, Model $m): bool
    {
        return false;
    }
}
