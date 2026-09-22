<?php

namespace App\Models;

use App\Enums\LevelSyaratPerlu;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusSyaratPerlu extends Model
{
    use HasUuids;

    protected $table = 'status_syarat_perlu';

    protected $fillable = [
        'prodi_id', 'periode_id', 'elemen_id', 'level',
        'nilai_terukur', 'catatan', 'diperbarui_oleh',
    ];

    protected $attributes = ['level' => 'belum'];

    protected function casts(): array
    {
        return ['level' => LevelSyaratPerlu::class];
    }

    public function elemen(): BelongsTo
    {
        return $this->belongsTo(Elemen::class);
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diperbarui_oleh');
    }
}
