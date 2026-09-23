<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Simulasi extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'simulasi';

    protected $fillable = [
        'prodi_id', 'periode_id', 'jenis', 'nama', 'keterangan',
        'parameter', 'hasil', 'periode_sandbox_id', 'dibuat_oleh',
        'kode_demo', 'demo_berlaku_sampai',
    ];

    protected function casts(): array
    {
        return [
            'parameter' => 'array',
            'hasil' => 'array',
            'demo_berlaku_sampai' => 'datetime',
        ];
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

    /** Demo terbuka: punya kode dan masa berlakunya belum lewat. */
    public function demoTerbuka(): bool
    {
        return $this->kode_demo !== null
            && $this->demo_berlaku_sampai !== null
            && $this->demo_berlaku_sampai->isFuture();
    }

    /** Punya kode tetapi masa berlakunya sudah lewat. */
    public function demoKedaluwarsa(): bool
    {
        return $this->kode_demo !== null && ! $this->demoTerbuka();
    }

    /** Akun demo yang lahir bersama demo ini. */
    public function penggunaDemo(): HasMany
    {
        return $this->hasMany(User::class, 'periode_demo_id', 'periode_sandbox_id');
    }

    public function scopeDemoBerjalan(Builder $q): Builder
    {
        return $q->whereNotNull('kode_demo')->where('demo_berlaku_sampai', '>', now());
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
