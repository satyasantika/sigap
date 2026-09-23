<?php

namespace App\Models;

use App\Exceptions\RiwayatTidakBolehDiubah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append only, sama seperti tagihan_riwayat.
 *
 * Log ini menjawab pertanyaan yang tidak bisa dijawab riwayat per modul:
 * "apa saja yang dilakukan admin selama menyamar menjadi ketua?" Riwayatnya
 * tersebar di sepuluh tabel; di sini ia berkumpul dalam satu urutan waktu.
 */
class LogAktivitas extends Model
{
    use HasUuids;

    protected $table = 'log_aktivitas';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'impersonasi_oleh', 'impersonasi_sesi_id',
        'aksi', 'subjek_tipe', 'subjek_id', 'keterangan', 'konteks', 'ip',
    ];

    protected function casts(): array
    {
        return ['konteks' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw RiwayatTidakBolehDiubah::untuk('log_aktivitas', 'update');
        });

        static::deleting(function (): never {
            throw RiwayatTidakBolehDiubah::untuk('log_aktivitas', 'delete');
        });
    }

    /**
     * Pelaku, termasuk yang akunnya sudah dihapus.
     *
     * `withTrashed()` penting: akun demo dihapus lunak begitu demonya ditutup,
     * dan tanpa ini seluruh baris log demo berubah menjadi "Pengguna terhapus"
     * — jejak yang masih ada tetapi tidak lagi bisa dibaca.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonasi_oleh')->withTrashed();
    }

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(ImpersonasiSesi::class, 'impersonasi_sesi_id');
    }

    public function scopeLewatImpersonasi(Builder $q): Builder
    {
        return $q->whereNotNull('impersonasi_oleh');
    }

    /** Kalimat yang dibaca manusia di layar riwayat. */
    public function kalimat(): string
    {
        $pelaku = $this->user?->nama_lengkap ?? 'Pengguna terhapus';

        return $this->impersonasi_oleh === null
            ? "{$pelaku} — {$this->aksi}"
            : "{$pelaku} — {$this->aksi} (melalui impersonasi oleh ".($this->admin?->nama_lengkap ?? '?').')';
    }
}
