<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Simulasi extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'simulasi';

    protected $fillable = [
        'prodi_id', 'periode_id', 'jenis', 'nama', 'keterangan',
        'parameter', 'hasil', 'periode_sandbox_id', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['parameter' => 'array', 'hasil' => 'array'];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function periodeSandbox(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_sandbox_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeSkor(Builder $q): Builder
    {
        return $q->where('jenis', 'skor');
    }

    public function scopeSandbox(Builder $q): Builder
    {
        return $q->where('jenis', 'periode');
    }
}
