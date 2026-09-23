<?php

namespace App\Models;

use App\Exceptions\RiwayatTidakBolehDiubah;
use App\Models\Concerns\MencatatImpersonasi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append only, sama seperti tagihan_riwayat.
 *
 * Naskah akreditasi ditulis berbulan-bulan oleh banyak orang. Tanpa jejak
 * versi, kalimat yang hilang tidak bisa dikembalikan dan tidak ada yang tahu
 * siapa menghapusnya.
 */
class NarasiVersi extends Model
{
    use HasUuids, MencatatImpersonasi;

    protected $table = 'narasi_versi';

    public const UPDATED_AT = null;

    protected $fillable = ['narasi_id', 'isi', 'jumlah_kata', 'user_id'];

    protected function casts(): array
    {
        return ['jumlah_kata' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw RiwayatTidakBolehDiubah::untuk('narasi_versi', 'update');
        });

        static::deleting(function (): never {
            throw RiwayatTidakBolehDiubah::untuk('narasi_versi', 'delete');
        });
    }

    public function narasi(): BelongsTo
    {
        return $this->belongsTo(Narasi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
