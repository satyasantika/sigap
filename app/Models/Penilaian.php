<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penilaian extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'penilaian';

    protected $fillable = [
        'prodi_id', 'periode_id', 'elemen_id', 'skor', 'catatan', 'penilai_id', 'tanggal',
    ];

    protected function casts(): array
    {
        return ['skor' => 'integer', 'tanggal' => 'date'];
    }

    public function elemen(): BelongsTo
    {
        return $this->belongsTo(Elemen::class);
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penilai_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }
}
