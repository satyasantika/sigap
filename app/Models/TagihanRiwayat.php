<?php

namespace App\Models;

use App\Enums\StatusTagihan;
use App\Exceptions\RiwayatTidakBolehDiubah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append only. Tidak ada update, tidak ada delete.
 *
 * Penolakannya dipasang di event model, bukan hanya diandalkan pada disiplin
 * pemanggil: satu `$riwayat->update()` yang lolos sudah cukup membuat jejak
 * auditnya tidak bisa dipercaya lagi, dan tidak ada cara mengetahuinya
 * belakangan.
 */
class TagihanRiwayat extends Model
{
    use HasUuids;

    protected $table = 'tagihan_riwayat';

    /** Tidak ada updated_at: baris ini tidak pernah berubah. */
    public const UPDATED_AT = null;

    protected $fillable = ['tagihan_id', 'user_id', 'status_dari', 'status_ke', 'catatan'];

    protected function casts(): array
    {
        return [
            'status_dari' => StatusTagihan::class,
            'status_ke' => StatusTagihan::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw RiwayatTidakBolehDiubah::untuk('tagihan_riwayat', 'update');
        });

        static::deleting(function (): never {
            throw RiwayatTidakBolehDiubah::untuk('tagihan_riwayat', 'delete');
        });
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
