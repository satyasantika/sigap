<?php

namespace App\Exceptions;

use Illuminate\Support\Str;

/**
 * Kode rujukan satu permintaan — jembatan antara layar galat dan berkas log.
 *
 * Tanpa ini, laporan galat yang masuk berbunyi "tadi pagi error waktu buka
 * bukti", dan menemukan barisnya di `storage/logs` berarti menebak jam. Dengan
 * kode ini, layar galat menampilkan `SIGAP-7KQ3M2XA` dan `grep 7KQ3M2XA`
 * langsung menunjuk satu baris berikut jejak tumpukannya.
 *
 * Dibangkitkan sekali per permintaan lalu diingat, supaya kode di layar dan
 * kode di log PASTI sama. Kalau keduanya berbeda, fitur ini justru lebih buruk
 * daripada tidak ada.
 */
class KodeRujukan
{
    private static ?string $kode = null;

    public static function kode(): string
    {
        return self::$kode ??= 'SIGAP-'.strtoupper(Str::random(8));
    }

    /** Dipakai uji agar tiap kasus mulai bersih. */
    public static function lupakan(): void
    {
        self::$kode = null;
    }
}
