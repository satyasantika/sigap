<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pokja extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'pokja';

    protected $fillable = [
        'periode_id', 'kode', 'nama', 'koordinator_id', 'catatan',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function koordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_id');
    }

    public function anggota(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pokja_user')
            ->withPivot('peran_dalam_pokja');
    }
}
