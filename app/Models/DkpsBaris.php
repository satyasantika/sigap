<?php

namespace App\Models;

use App\Enums\SumberData;
use App\Models\Concerns\MenolakSumberKosong;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DkpsBaris extends Model
{
    use HasUuids, MenolakSumberKosong, SoftDeletes;

    protected $table = 'dkps_baris';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'prodi_id', 'periode_id', 'dkps_butir_id', 'tahun_acuan',
        'data', 'sumber', 'kunci_normal', 'impor_batch_id',
    ];

    /** Verifikasi hanya lewat VerifikatorDkps, tidak lewat pembaruan biasa. */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'sumber' => SumberData::class,
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function butir(): BelongsTo
    {
        return $this->belongsTo(DkpsButir::class, 'dkps_butir_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function bukti(): BelongsToMany
    {
        return $this->belongsToMany(Bukti::class, 'bukti_dkps_baris');
    }

    public function scopeTerverifikasi(Builder $q): Builder
    {
        return $q->whereNotNull('diverifikasi_pada');
    }

    public function terverifikasi(): bool
    {
        return $this->diverifikasi_pada !== null;
    }

    /**
     * Baris bersumber `manual` yang sebenarnya bisa ditarik dari SIAKAD.
     *
     * Baris semacam ini adalah calon selisih pada asesmen lapangan: asesor
     * membandingkannya dengan SIAKAD, dan angka yang diketik tangan hampir
     * selalu berbeda dari angka sistem.
     */
    public function calonSelisih(): bool
    {
        return $this->sumber === SumberData::Manual
            && in_array($this->butir?->no, self::BUTIR_DARI_SIAKAD, true);
    }

    /**
     * Butir DKPS yang datanya ada di SIAKAD: mahasiswa, dosen, beban kerja,
     * kurikulum, IPK, masa studi.
     */
    public const BUTIR_DARI_SIAKAD = [2, 3, 5, 6, 7, 10, 17, 19, 20, 21];

    /** Nilai satu kolom di dalam json, tanpa whereJsonContains. */
    public function nilai(string $kunci, mixed $bawaan = null): mixed
    {
        return data_get($this->data, $kunci, $bawaan);
    }
}
