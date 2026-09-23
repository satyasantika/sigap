<?php

namespace App\Models;

use App\Enums\JenisTagihan;
use App\Enums\StatusTagihan;
use App\Models\Concerns\TerikatPeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu satuan pekerjaan pengumpulan.
 *
 * Status TIDAK pernah diubah lewat model ini. Satu-satunya pintu perpindahan
 * adalah App\Services\AlurTagihan::pindah(), karena setiap perpindahan wajib
 * menulis baris riwayat dan memeriksa wewenang. Kalau status bisa diubah
 * langsung, riwayatnya bolong dan tidak ada yang tahu kapan bolongnya.
 */
class Tagihan extends Model
{
    use HasUuids, SoftDeletes, TerikatPeriode;

    protected $table = 'tagihan';

    protected $fillable = [
        'prodi_id', 'periode_id', 'pokja_id', 'elemen_id', 'dkps_butir_id',
        'jenis', 'judul', 'deskripsi', 'penanggung_jawab_id', 'tenggat',
        'bobot_terkait', 'prioritas', 'urutan',
    ];

    /**
     * status, disetujui_oleh, dan disetujui_pada sengaja TIDAK ada di
     * $fillable: ketiganya hanya boleh disentuh AlurTagihan lewat forceFill.
     * $guarded tidak dipakai — bila keduanya diisi, Laravel mengabaikan
     * $guarded, jadi mencantumkannya hanya menyesatkan pembaca.
     *
     * Nilai bawaan dicantumkan di model, bukan hanya di migrasi: baris hasil
     * create() belum membaca ulang dari basis data, sehingga tanpa ini
     * $tagihan->status bernilai null sampai baris itu di-refresh.
     */
    protected $attributes = [
        'status' => 'belum',
        'prioritas' => 'biasa',
        'bobot_terkait' => 0,
        'urutan' => 0,
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisTagihan::class,
            'status' => StatusTagihan::class,
            'tenggat' => 'date',
            'bobot_terkait' => 'decimal:3',
            'urutan' => 'integer',
            'disetujui_pada' => 'datetime',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function pokja(): BelongsTo
    {
        return $this->belongsTo(Pokja::class);
    }

    public function elemen(): BelongsTo
    {
        return $this->belongsTo(Elemen::class);
    }

    public function dkpsButir(): BelongsTo
    {
        return $this->belongsTo(DkpsButir::class);
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_id');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function bukti(): BelongsToMany
    {
        return $this->belongsToMany(Bukti::class, 'bukti_tagihan');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(TagihanRiwayat::class)->latest('created_at');
    }

    public function komentar(): MorphMany
    {
        return $this->morphMany(Komentar::class, 'commentable')->latest('created_at');
    }

    public function scopeBelumSelesai(Builder $q): Builder
    {
        return $q->where('status', '!=', StatusTagihan::Disetujui);
    }

    public function scopeTerlambat(Builder $q): Builder
    {
        return $q->whereNotNull('tenggat')
            ->whereDate('tenggat', '<', now())
            ->where('status', '!=', StatusTagihan::Disetujui);
    }

    public function scopeMilik(Builder $q, User $u): Builder
    {
        return $q->where('penanggung_jawab_id', $u->getKey());
    }

    public function terlambat(): bool
    {
        return $this->tenggat !== null
            && $this->tenggat->isPast()
            && $this->status !== StatusTagihan::Disetujui;
    }

    /** Sisa hari sampai tenggat; negatif berarti sudah lewat. */
    public function sisaHari(): ?int
    {
        return $this->tenggat?->diffInDays(now()->startOfDay(), false) !== null
            ? (int) now()->startOfDay()->diffInDays($this->tenggat, false)
            : null;
    }
}
