<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu sel matriks izin. Disemai dari data/izin.json, tidak pernah disunting
 * lewat antarmuka — halaman Matriks Izin hanya-baca.
 */
class Izin extends Model
{
    use HasUuids;

    protected $table = 'izin';

    protected $fillable = ['aksi', 'peran', 'nilai'];
}
