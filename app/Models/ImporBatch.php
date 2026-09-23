<?php

namespace App\Models;

use App\Models\Concerns\MenolakDihapus;
use App\Models\Concerns\TerikatPeriode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImporBatch extends Model
{
    use HasUuids, MenolakDihapus, TerikatPeriode;

    protected $table = 'impor_batch';

    protected $fillable = [
        'periode_id', 'profil', 'dijalankan_oleh', 'jumlah_baris',
        'jumlah_impor', 'jumlah_lewati', 'jumlah_perbarui', 'ringkasan',
        'dibatalkan_pada', 'dibatalkan_oleh',
    ];

    protected function casts(): array
    {
        return ['ringkasan' => 'array', 'dibatalkan_pada' => 'datetime'];
    }

    public function pelaksana(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dijalankan_oleh');
    }

    public function dibatalkan(): bool
    {
        return $this->dibatalkan_pada !== null;
    }

    /**
     * Menghapus baris ini menghapus satu-satunya jalan mengembalikan data ke keadaan
     * sebelum impor. Kolom `dibatalkan_pada` sudah menyediakan cara membatalkan
     * tanpa kehilangan riwayatnya.
     */
    public function alasanTidakBolehDihapus(): string
    {
        return 'Batch impor adalah catatan apa yang pernah masuk dan dari mana. Impor yang '
            .'keliru dibatalkan lewat pembatalan — yang justru MEMBUTUHKAN barisnya tetap ada '
            .'karena ringkasannya menyimpan nilai sebelum impor.';
    }
}
