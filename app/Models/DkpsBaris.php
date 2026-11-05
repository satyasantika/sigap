<?php

namespace App\Models;

use App\Enums\SumberData;
use App\Models\Concerns\MenolakSumberKosong;
use App\Models\Concerns\TerikatPeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DkpsBaris extends Model
{
    use HasUuids, MenolakSumberKosong, SoftDeletes, TerikatPeriode;

    protected $table = 'dkps_baris';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'prodi_id', 'periode_id', 'dkps_butir_id', 'tahun_acuan',
        'data', 'sumber', 'kunci_normal', 'impor_batch_id', 'lembar_kode', 'pokja_id',
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

    protected static function booted(): void
    {
        static::creating(function (self $baris): void {
            $butir = $baris->butir;

            if (blank($baris->lembar_kode)) {
                $baris->lembar_kode = $butir?->kodeLembar()[0] ?? null;
            }

            if (blank($baris->pokja_id) && $butir?->pokja_kode !== null) {
                // Tanpa lingkup periode: pokja dicari menurut periode baris,
                // bukan menurut periode yang sedang dilihat pengguna.
                $baris->pokja_id = Pokja::withoutGlobalScope('lingkup_periode')
                    ->where('periode_id', $baris->periode_id)
                    ->where('kode', $butir->pokja_kode)
                    ->value('id');
            }
        });
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function butir(): BelongsTo
    {
        return $this->belongsTo(DkpsButir::class, 'dkps_butir_id');
    }

    public function pokja(): BelongsTo
    {
        return $this->belongsTo(Pokja::class);
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
            && in_array($this->butir?->kode, self::KODE_DARI_SIAKAD, true);
    }

    /**
     * Tabel DKPS 3.0 yang datanya ada di SIAKAD: 2 Mahasiswa, 3 Prestasi,
     * 6 DPP, 7 DBS, 8 BKD, 11 Tendik, 18 Kurikulum, 20 PPL, 21 IPK,
     * 22 Masa studi.
     */
    public const KODE_DARI_SIAKAD = ['2', '3', '6', '7', '8', '11', '18', '20', '21', '22'];

    /** Nilai satu kolom di dalam json, tanpa whereJsonContains. */
    public function nilai(string $kunci, mixed $bawaan = null): mixed
    {
        return data_get($this->data, $kunci, $bawaan);
    }
}
