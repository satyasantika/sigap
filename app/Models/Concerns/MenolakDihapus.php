<?php

namespace App\Models\Concerns;

use App\Exceptions\BarisTidakBolehDihapus;

/**
 * Menolak penghapusan pada tingkat model, bukan pada tingkat tombol.
 *
 * Dipakai tabel yang boleh disunting tetapi tidak boleh hilang. Dipasang lewat
 * peristiwa `deleting` supaya tidak ada pemanggil yang bisa melewatinya —
 * termasuk `Model::destroy()`, aksi massal Filament, dan kode yang belum
 * ditulis.
 *
 * Yang TIDAK tertahan: penghapusan berantai di tingkat basis data
 * (`cascadeOnDelete`). Itu disengaja — satu-satunya yang memicunya adalah
 * `forceDelete()` atas periode sandbox, dan membuang periode latihan beserta
 * seluruh isinya memang gunanya.
 *
 * Kelas yang memakainya WAJIB menyediakan alasannya, supaya orang yang
 * terhalang tahu apa yang sebenarnya dijaga.
 */
trait MenolakDihapus
{
    public static function bootMenolakDihapus(): void
    {
        static::deleting(function ($model): never {
            throw BarisTidakBolehDihapus::untuk($model->getTable(), $model->alasanTidakBolehDihapus());
        });
    }

    abstract public function alasanTidakBolehDihapus(): string;
}
