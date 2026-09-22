<?php

namespace App\Models;

use App\Enums\StatusPeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Periode extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'periode';

    protected $fillable = [
        'prodi_id', 'nama', 'ts_tahun', 'tanggal_target_unggah',
        'versi_instrumen', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPeriode::class,
            'ts_tahun' => 'integer',
            'tanggal_target_unggah' => 'date',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function pokja(): HasMany
    {
        return $this->hasMany(Pokja::class);
    }

    /** Periode yang sedang dikerjakan. */
    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status', StatusPeriode::Berjalan);
    }

    /**
     * Terkunci berarti seluruh penulisan isi akreditasi ditolak untuk semua
     * peran. Lihat App\Policies\BasePolicy.
     */
    public function terkunci(): bool
    {
        return $this->status->terkunci();
    }
}
