<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImporBatch extends Model
{
    use HasUuids;

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
}
