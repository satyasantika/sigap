<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DkpsButir extends Model
{
    use HasUuids;

    protected $table = 'dkps_butir';

    protected $fillable = ['no', 'nama', 'label_tabel', 'jendela_data', 'keterangan'];

    protected function casts(): array
    {
        return ['no' => 'integer'];
    }
}
