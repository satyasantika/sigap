<?php

namespace App\Models;

use App\Models\Concerns\TerikatPeriode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pokja extends Model
{
    use HasUuids, SoftDeletes, TerikatPeriode;

    protected $table = 'pokja';

    protected $fillable = [
        'periode_id', 'kode', 'nama', 'koordinator_id', 'catatan',
    ];

    protected static function booted(): void
    {
        // Menunjuk koordinator sekaligus mendaftarkannya sebagai anggota,
        // supaya ia tampil di tab Anggota dan lingkup `pokjanya` tak bergantung
        // pada langkah kedua yang mudah terlupa.
        static::saved(function (self $pokja) {
            if ($pokja->koordinator_id !== null) {
                $pokja->anggota()->syncWithoutDetaching([
                    $pokja->koordinator_id => ['peran_dalam_pokja' => 'koordinator'],
                ]);
            }
        });
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function koordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_id');
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    public function anggota(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pokja_user')
            ->withPivot('peran_dalam_pokja');
    }
}
