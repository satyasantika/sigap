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
        'versi_instrumen', 'status', 'simulasi',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPeriode::class,
            'ts_tahun' => 'integer',
            'tanggal_target_unggah' => 'date',
            'simulasi' => 'boolean',
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

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    /**
     * Periode yang sedang dikerjakan.
     *
     * Periode simulasi SELALU dikecualikan. Ia berstatus `berjalan` supaya
     * bisa dikerjakan seperti periode sungguhan, jadi satu-satunya yang
     * memisahkannya dari periode asli adalah kolom `simulasi` — dan pemisahan
     * itu harus terjadi di sini, bukan diingat satu per satu di setiap kueri.
     */
    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status', StatusPeriode::Berjalan)->where('simulasi', false);
    }

    /** Periode sungguhan saja, apa pun statusnya. */
    public function scopeSungguhan(Builder $q): Builder
    {
        return $q->where('simulasi', false);
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
