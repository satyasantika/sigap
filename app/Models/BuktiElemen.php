<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Bukan pivot sederhana: ia punya kunci utama sendiri karena membawa
 * `keterangan` — penjelasan mengapa bukti ini menopang elemen TERTENTU.
 *
 * Satu SK bisa menopang tiga elemen dengan alasan berbeda-beda, dan alasan
 * itulah yang dibaca ketua saat memvalidasi. Pivot tanpa id tidak bisa
 * menyimpannya per pasangan.
 */
class BuktiElemen extends Pivot
{
    use HasUuids;

    protected $table = 'bukti_elemen';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = ['bukti_id', 'elemen_id', 'keterangan'];
}
