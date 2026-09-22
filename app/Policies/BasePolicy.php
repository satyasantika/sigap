<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Izin;
use Illuminate\Database\Eloquent\Model;

/**
 * Induk seluruh Policy SIGAP.
 *
 * Isinya sengaja tipis: Policy tidak memutuskan apa pun, ia hanya menerjemahkan
 * nama metode Laravel/Filament (viewAny, create, update, ...) menjadi kode aksi
 * di data/izin.json lalu meneruskannya ke App\Support\Izin.
 *
 * Penolakan karena periode `dikunci`/`selesai` juga diputuskan di sana, supaya
 * ada satu rantai keputusan, bukan dua.
 */
abstract class BasePolicy
{
    /** Awalan kode aksi, misalnya `periode` untuk `periode.kelola`. */
    abstract protected function awalan(): string;

    /**
     * Pemetaan metode Policy ke kode aksi. Metode yang tidak terdaftar di sini
     * ditolak — daftar putih, bukan daftar hitam.
     *
     * @return array<string, string>
     */
    abstract protected function peta(): array;

    protected function putuskan(User $u, string $metode, ?Model $obyek = null): bool
    {
        $aksi = $this->peta()[$metode] ?? null;

        return $aksi !== null && Izin::boleh($u, $aksi, $obyek);
    }

    public function viewAny(User $u): bool
    {
        return $this->putuskan($u, 'viewAny');
    }

    public function view(User $u, Model $m): bool
    {
        return $this->putuskan($u, 'view', $m);
    }

    public function create(User $u): bool
    {
        return $this->putuskan($u, 'create');
    }

    public function update(User $u, Model $m): bool
    {
        return $this->putuskan($u, 'update', $m);
    }

    public function delete(User $u, Model $m): bool
    {
        return $this->putuskan($u, 'delete', $m);
    }

    public function restore(User $u, Model $m): bool
    {
        return $this->putuskan($u, 'restore', $m);
    }

    public function forceDelete(User $u, Model $m): bool
    {
        return $this->putuskan($u, 'forceDelete', $m);
    }
}
