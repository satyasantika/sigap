<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\Impersonasi;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Menandai baris riwayat yang lahir selama penyamaran.
 *
 * Dipasang di tingkat model, bukan di tiap pemanggil, karena pemanggilnya
 * banyak dan akan bertambah: satu tempat yang lupa mengisi `impersonasi_oleh`
 * berarti satu persetujuan akreditasi yang tampak ditekan ketua padahal
 * ditekan admin. Peristiwa `creating` tidak bisa dilewati siapa pun yang
 * memakai Eloquent.
 *
 * Nilai yang sudah diisi pemanggil dibiarkan — `LogAktivitas` mengisinya
 * sendiri saat mencatat awal dan akhir sesi, ketika session() belum atau sudah
 * tidak lagi memuat id admin.
 */
trait MencatatImpersonasi
{
    public static function bootMencatatImpersonasi(): void
    {
        static::creating(function ($model) {
            if ($model->getAttribute('impersonasi_oleh') === null) {
                $model->setAttribute('impersonasi_oleh', app(Impersonasi::class)->adminDiBalikLayar());
            }
        });
    }

    /** Admin di balik layar, null bila baris ini bukan hasil penyamaran. */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonasi_oleh');
    }

    /** Baris ini lahir saat seseorang sedang menyamar. */
    public function lewatImpersonasi(): bool
    {
        return $this->impersonasi_oleh !== null;
    }
}
