<?php

namespace App\Models;

use App\Enums\LevelSyaratPerlu;
use App\Models\Concerns\MenolakDihapus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusSyaratPerlu extends Model
{
    use HasUuids, MenolakDihapus;

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

    /**
     * Soft delete sengaja TIDAK dipakai di sini. Tabelnya punya kendala unik
     * (periode_id, elemen_id), jadi satu baris terhapus akan menghalangi pembuatan
     * baris baru untuk pasangan yang sama — dan `updateOrCreate` di layar Syarat
     * Perlu akan gagal dengan galat SQL mentah, bukan pesan yang bisa dibaca.
     */
    public function alasanTidakBolehDihapus(): string
    {
        return 'Lima baris ini yang menentukan apakah Nilai Akreditasi tertinggi sekalipun '
            .'berujung Unggul atau berhenti di Terakreditasi. Untuk mencabut pemenuhan, '
            .'setel levelnya kembali ke belum.';
    }
}
