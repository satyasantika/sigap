<?php

namespace App\Models;

use App\Enums\JenisElemen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Elemen extends Model
{
    use HasUuids;

    protected $table = 'elemen';

    protected $fillable = [
        'kriteria_id', 'no', 'nama', 'bobot', 'jenis', 'syarat_perlu',
        'pokja_kode', 'panduan', 'pertanyaan_pemandu', 'parameter',
        'bukti_pendukung', 'evaluasi_refleksi', 'tindak_lanjut',
    ];

    protected function casts(): array
    {
        return [
            'no' => 'integer',
            'bobot' => 'decimal:2',
            'jenis' => JenisElemen::class,
            'syarat_perlu' => 'boolean',
        ];
    }

    public function kriteria(): BelongsTo
    {
        return $this->belongsTo(Kriteria::class);
    }

    public function syaratPerlu(): HasOne
    {
        return $this->hasOne(SyaratPerlu::class);
    }

    public function rumus(): HasMany
    {
        return $this->hasMany(Rumus::class);
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    /**
     * Sengaja TIDAK dinamai scopeSyaratPerlu: namanya akan bertabrakan dengan
     * relasi syaratPerlu() di atas, dan Eloquent memilih relasinya lebih dulu.
     */
    public function scopeBersyaratPerlu(Builder $q): Builder
    {
        return $q->where('syarat_perlu', true);
    }

    /** Label pendek untuk dirujuk di layar dan pesan, misalnya "E17". */
    public function kode(): string
    {
        return 'E'.$this->no;
    }
}
