<?php

namespace App\Support\Rumus;

/**
 * Nilai satu variabel katalog DKPS untuk satu periode.
 *
 * `nilai` null berarti lembar sumbernya belum berisi baris sama sekali —
 * berbeda dari 0,0 yang berarti lembar terisi tetapi memang nol. Perbedaan
 * itu yang membuat "belum diisi" dan "memang nol" bisa dibedakan di layar.
 */
final readonly class NilaiVariabel
{
    /**
     * @param  array<int, string>  $lembar  kode lembar yang dibaca
     */
    public function __construct(
        public string $kode,
        public ?float $nilai,
        public array $lembar,
        public int $jumlahBaris,
        public int $belumTerverifikasi,
        public ?string $catatan = null,
    ) {}
}
