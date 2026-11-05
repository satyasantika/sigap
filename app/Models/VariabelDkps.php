<?php

namespace App\Models;

use App\Enums\CaraBacaVariabel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu variabel bernama yang dibaca dari isian DKPS (cermin lembar "5. DKPS"
 * kertas kerja AK). Disemai dari data/variabel-dkps.json; tabel ini rujukan,
 * bukan isian pengguna.
 */
class VariabelDkps extends Model
{
    use HasUuids;

    protected $table = 'variabel_dkps';

    protected $fillable = [
        'kode', 'label', 'lembar', 'cara', 'kolom', 'parameter', 'rumus', 'urutan',
    ];

    protected function casts(): array
    {
        return [
            'lembar' => 'array',
            'cara' => CaraBacaVariabel::class,
            'parameter' => 'array',
            'rumus' => 'array',
            'urutan' => 'integer',
        ];
    }

    public static function dariKode(string $kode): static
    {
        return static::where('kode', $kode)->firstOrFail();
    }
}
