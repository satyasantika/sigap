<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prodi extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'prodi';

    protected $fillable = [
        'kode', 'nama', 'jenjang', 'upps', 'perguruan_tinggi', 'aktif',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function periode(): HasMany
    {
        return $this->hasMany(Periode::class);
    }

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
