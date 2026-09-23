<?php

namespace App\Models;

use App\Models\Concerns\MencatatImpersonasi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Komentar extends Model
{
    use HasUuids, MencatatImpersonasi, SoftDeletes;

    protected $table = 'komentar';

    public const UPDATED_AT = null;

    protected $fillable = ['commentable_type', 'commentable_id', 'user_id', 'isi'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
