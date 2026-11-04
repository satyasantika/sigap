<?php

namespace App\Models;

use App\Support\Dkps\SkemaLembar;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class DkpsButir extends Model
{
    use HasUuids;

    protected $table = 'dkps_butir';

    protected $fillable = [
        'no', 'kode', 'nama', 'label_tabel', 'jendela_data', 'keterangan',
        'pokja_kode', 'elemen', 'lembar',
    ];

    protected function casts(): array
    {
        return ['no' => 'integer', 'elemen' => 'array', 'lembar' => 'array'];
    }

    /** Butir menurut kode tabel DKPS 3.0 ("1".."29"); `no` bisa bergeser, kode tidak. */
    public static function dariKode(string $kode): static
    {
        return static::where('kode', $kode)->firstOrFail();
    }

    /** @return array<int, string> Kode lembar, urut sesuai berkas json. */
    public function kodeLembar(): array
    {
        return array_map(fn (array $l) => $l['kode'], $this->lembar ?? []);
    }

    /** Definisi mentah satu lembar dari data/dkps-tabel.json. */
    public function lembarMentah(string $kodeLembar): array
    {
        foreach ($this->lembar ?? [] as $l) {
            if ($l['kode'] === $kodeLembar) {
                return $l;
            }
        }

        throw new InvalidArgumentException("Tabel {$this->kode} tidak punya lembar {$kodeLembar}.");
    }

    public function skema(string $kodeLembar): SkemaLembar
    {
        return SkemaLembar::dari($this, $kodeLembar);
    }
}
