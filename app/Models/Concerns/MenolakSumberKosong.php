<?php

namespace App\Models\Concerns;

use RuntimeException;

/**
 * Menolak baris yang kolom `sumber`-nya tidak diisi.
 *
 * MariaDB memperlakukan kolom ENUM NOT NULL tanpa default dengan memakai
 * NILAI PERTAMA enum secara diam-diam, bahkan pada sql_mode ketat. Untuk kolom
 * ini nilai pertamanya `siakad` — sumber yang paling dipercaya asesor.
 *
 * Akibatnya, bukti yang lupa diberi sumber akan tercatat berasal dari SIAKAD
 * padahal sumbernya tidak diketahui. Itu kebalikan dari yang diminta:
 * vibecoding/docs/07 menuntut penandaan sumber yang jujur justru supaya baris
 * manual yang seharusnya dari SIAKAD bisa ditemukan sebelum asesor menemukannya.
 *
 * aturan proyek 6 juga menuntut penolakan di tingkat validasi, bukan
 * sekadar peringatan. Karena basis data tidak bisa menegakkannya, model yang
 * menegakkannya.
 */
trait MenolakSumberKosong
{
    public static function bootMenolakSumberKosong(): void
    {
        static::creating(function ($model): void {
            if (blank($model->getAttributes()['sumber'] ?? null)) {
                throw new RuntimeException(
                    'Kolom `sumber` wajib diisi. Dibiarkan kosong, MariaDB akan '
                    .'mengisinya `siakad` secara diam-diam — dan bukti yang sumbernya '
                    .'tidak diketahui akan tercatat berasal dari sumber yang paling dipercaya.'
                );
            }
        });
    }
}
