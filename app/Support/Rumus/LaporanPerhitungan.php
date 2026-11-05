<?php

namespace App\Support\Rumus;

use App\Models\NilaiRumus;

/**
 * Hasil satu putaran PenghitungRumusDkps::hitungSemua(): setiap rumus
 * (kecuali NA) masuk tepat satu dari tiga daftar.
 */
final readonly class LaporanPerhitungan
{
    /**
     * @param  array<string, NilaiRumus>  $disimpan  kode ⇒ baris baru
     * @param  array<string, string>  $dilewati  kode ⇒ alasan (variabel kosong, pembagi nol)
     * @param  array<string, string>  $tidakBerubah  kode ⇒ "sama dengan hasil terakhir"
     */
    public function __construct(
        public array $disimpan,
        public array $dilewati,
        public array $tidakBerubah,
    ) {}

    public function ringkasan(): string
    {
        return sprintf('Disimpan %d, tidak berubah %d, dilewati %d',
            count($this->disimpan), count($this->tidakBerubah), count($this->dilewati));
    }
}
