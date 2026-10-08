<?php

namespace App\Models\Concerns;

use App\Support\LingkupPeriode;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menyaring baris menurut periode yang boleh dilihat pengguna.
 *
 * Dipasang sebagai global scope, bukan diingat di tiap Resource. Alasannya
 * konkret: `TagihanResource::getEloquentQuery()` sudah menyaring menurut peran
 * dengan rapi dan tetap melewatkan periode simulasi — bukan karena ceroboh,
 * melainkan karena penyaringan periode belum terpikir saat itu ditulis. Delapan
 * tempat yang harus diingat adalah delapan tempat yang bisa terlewat.
 *
 * Dua jalan keluar yang sengaja disediakan:
 *
 *   `withoutGlobalScope('lingkup_periode')`  untuk layar yang memang lintas
 *       periode, misalnya daftar Simulasi milik admin.
 *   tanpa pengguna yang masuk  scope-nya diam sama sekali, supaya seeder,
 *       perintah artisan, dan penjadwal tetap bisa menyentuh periode mana pun.
 */
trait TerikatPeriode
{
    public static function bootTerikatPeriode(): void
    {
        static::addGlobalScope('lingkup_periode', function (Builder $kueri): void {
            $id = LingkupPeriode::idYangBolehDilihat();

            if ($id === null) {
                return;
            }

            $kolom = $kueri->getModel()->getTable().'.periode_id';

            // whereNull ikut disertakan: tanpanya baris dengan periode_id
            // NULL (impor_batch milik ImporPengguna, yang sengaja tidak
            // terikat periode) lenyap dari setiap pengguna yang masuk --
            // SQL `NULL IN (...)` tidak pernah benar.
            $kueri->where(function (Builder $q) use ($kolom, $id): void {
                $q->whereIn($kolom, $id)->orWhereNull($kolom);
            });
        });
    }
}
