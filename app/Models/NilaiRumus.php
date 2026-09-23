<?php

namespace App\Models;

use App\Models\Concerns\MenolakDihapus;
use App\Models\Concerns\TerikatPeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil satu perhitungan. Tidak pernah ditimpa — setiap perhitungan baru
 * menambah baris.
 */
class NilaiRumus extends Model
{
    use HasUuids, MenolakDihapus, TerikatPeriode;

    protected $table = 'nilai_rumus';

    public const UPDATED_AT = null;

    public const CREATED_AT = 'dihitung_pada';

    protected $fillable = [
        'periode_id', 'rumus_kode', 'nilai', 'skor',
        'memenuhi_syarat_3_tahun', 'memenuhi_syarat_5_tahun',
        'komponen', 'catatan', 'dihitung_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:4',
            'skor' => 'integer',
            'memenuhi_syarat_3_tahun' => 'boolean',
            'memenuhi_syarat_5_tahun' => 'boolean',
            'komponen' => 'array',
            'dihitung_pada' => 'datetime',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function penghitung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihitung_oleh');
    }

    public function rumus(): BelongsTo
    {
        return $this->belongsTo(Rumus::class, 'rumus_kode', 'kode');
    }

    /** Hasil terakhir tiap rumus di satu periode. */
    public function scopeTerakhir(Builder $q, string $periodeId): Builder
    {
        return $q->where('periode_id', $periodeId)
            ->whereIn('id', function ($sub) use ($periodeId) {
                $sub->selectRaw('MAX(id)')
                    ->from('nilai_rumus')
                    ->where('periode_id', $periodeId)
                    ->groupBy('rumus_kode');
            });
    }

    /**
     * Angka rumus disimpan sebagai deret, bukan satu nilai berjalan. Menghapus satu
     * baris memutus deretnya dan membuat riwayat perhitungan tidak bisa dibaca lagi.
     */
    public function alasanTidakBolehDihapus(): string
    {
        return 'Tabel ini append only: KalkulatorRumus menyimpan hasil baru tanpa menimpa yang lama, '
            .'supaya pertanyaan "kapan PDS3 kita turun di bawah 50?" masih bisa dijawab saat '
            .'ada sengketa.';
    }
}
