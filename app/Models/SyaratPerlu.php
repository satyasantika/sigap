<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyaratPerlu extends Model
{
    use HasUuids;

    protected $table = 'syarat_perlu';

    protected $fillable = [
        'elemen_id', 'jenis_ambang', 'ambang_3_tahun', 'ambang_5_tahun',
        'catatan', 'nomor_di_tabel_1_3',
    ];

    protected function casts(): array
    {
        return ['nomor_di_tabel_1_3' => 'integer'];
    }

    public function elemen(): BelongsTo
    {
        return $this->belongsTo(Elemen::class);
    }
}
