<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Naskah LED satu elemen.
 *
 * Nama kelasnya `Narasi`, bukan `NarasiLed` — lihat CLAUDE.md bagian 7 butir 4.
 */
class Narasi extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'narasi';

    protected $fillable = [
        'prodi_id', 'periode_id', 'elemen_id', 'isi', 'jumlah_kata', 'penulis_id', 'versi',
    ];

    protected $attributes = ['jumlah_kata' => 0, 'versi' => 1];

    protected function casts(): array
    {
        return ['jumlah_kata' => 'integer', 'versi' => 'integer'];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function elemen(): BelongsTo
    {
        return $this->belongsTo(Elemen::class);
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    public function versiLama(): HasMany
    {
        return $this->hasMany(NarasiVersi::class)->latest('created_at');
    }
}
