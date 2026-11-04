<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Isian baris DKPS yang ditolak skema lembarnya.
 *
 * Galat dibawa per kolom (`_baris` untuk aturan antarkolom), bukan hanya
 * sebagai satu kalimat: formulir perlu menempelkan tiap pesan di sel yang
 * salah, sedangkan impor dan log cukup membaca pesan gabungannya.
 */
class IsianDkpsTidakSah extends RuntimeException
{
    /** @param  array<string, string>  $galat  kunci kolom (atau `_baris`) ⇒ pesan */
    public function __construct(public readonly array $galat)
    {
        parent::__construct('Isian belum sah: '.implode(' ', $galat));
    }
}
