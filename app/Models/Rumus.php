<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rumus extends Model
{
    use HasUuids;

    protected $table = 'rumus';

    protected $fillable = [
        'kode', 'nama', 'elemen_id', 'ekspresi', 'variabel',
        'aturan_skor', 'jendela', 'catatan',
    ];

    protected function casts(): array
    {
        return ['variabel' => 'array'];
    }

    public function elemen(): BelongsTo
    {
        return $this->belongsTo(Elemen::class);
    }
}
