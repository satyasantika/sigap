<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImpersonasiSesi extends Model
{
    use HasUuids;

    protected $table = 'impersonasi_sesi';

    public $timestamps = false;

    protected $fillable = [
        'admin_id', 'target_id', 'alasan', 'ip', 'user_agent', 'dimulai_pada', 'diakhiri_pada',
    ];

    protected function casts(): array
    {
        return ['dimulai_pada' => 'datetime', 'diakhiri_pada' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(LogAktivitas::class, 'impersonasi_sesi_id')->latest('created_at');
    }

    /**
     * Sesi yang belum ditutup.
     *
     * Dinamai `scopeMasihBerjalan`, bukan `scopeBerjalan`, supaya tidak
     * bertabrakan dengan metode instans `berjalan()` di bawahnya — Eloquent
     * memanggil scope lewat nama statis dan tabrakan seperti itu baru
     * ketahuan saat dijalankan. Persoalan yang sama pernah muncul pada
     * `scopeSyaratPerlu`.
     */
    public function scopeMasihBerjalan(Builder $q): Builder
    {
        return $q->whereNull('diakhiri_pada');
    }

    public function berjalan(): bool
    {
        return $this->diakhiri_pada === null;
    }

    public function durasi(): ?string
    {
        if ($this->diakhiri_pada === null) {
            return null;
        }

        return $this->dimulai_pada->diffForHumans($this->diakhiri_pada, short: true, syntax: true);
    }
}
