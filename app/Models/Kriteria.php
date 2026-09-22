<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kriteria extends Model
{
    use HasUuids;

    protected $table = 'kriteria';

    protected $fillable = ['kode', 'nama', 'urutan', 'bobot'];

    protected function casts(): array
    {
        return ['urutan' => 'integer', 'bobot' => 'decimal:2'];
    }

    public function elemen(): HasMany
    {
        return $this->hasMany(Elemen::class)->orderBy('no');
    }
}
