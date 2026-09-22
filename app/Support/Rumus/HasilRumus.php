<?php

namespace App\Support\Rumus;

/**
 * Hasil satu perhitungan rumus.
 *
 * `skor` dan `memenuhiSyarat*` adalah MEDAN TERPISAH, dan itu inti seluruh
 * kelas ini. Pada PDS3 dan PPDTPS, ambang skor 4 justru LEBIH RENDAH daripada
 * ambang syarat perlu lima tahun — PDS3 40% sudah memberi skor penuh sementara
 * syarat perlunya menuntut 50%. Menyimpulkan "skor 4, berarti aman" adalah
 * kesalahan yang paling mahal di seluruh instrumen ini, dan struktur data
 * inilah yang mencegahnya.
 */
readonly class HasilRumus
{
    public function __construct(
        public string $kode,
        public ?float $nilai,
        public ?int $skor = null,
        public ?bool $memenuhiSyarat3Tahun = null,
        public ?bool $memenuhiSyarat5Tahun = null,
        public array $komponen = [],
        public ?string $catatan = null,
    ) {}

    public function terkaitSyaratPerlu(): bool
    {
        return $this->memenuhiSyarat3Tahun !== null || $this->memenuhiSyarat5Tahun !== null;
    }

    /** @return array<string, mixed> */
    public function untukDisimpan(): array
    {
        return [
            'rumus_kode' => $this->kode,
            'nilai' => $this->nilai,
            'skor' => $this->skor,
            'memenuhi_syarat_3_tahun' => $this->memenuhiSyarat3Tahun,
            'memenuhi_syarat_5_tahun' => $this->memenuhiSyarat5Tahun,
            'komponen' => $this->komponen,
            'catatan' => $this->catatan,
        ];
    }
}
