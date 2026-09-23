<?php

namespace App\Support\Na;

use App\Enums\LevelSyaratPerlu;

/**
 * Pengandaian yang ditumpangkan di atas penilaian sungguhan.
 *
 * "Bagaimana jika E58 naik dari 3 ke 4?" dijawab dengan menghitung ulang NA
 * memakai skor pengganti, TANPA menulis apa pun ke tabel `penilaian`. Itu
 * syarat yang tidak bisa ditawar: begitu simulasi boleh menyentuh penilaian,
 * tidak ada lagi cara membedakan skor yang benar-benar dinilai dari skor yang
 * diandaikan, dan nilai akreditasi berhenti bisa dipertanggungjawabkan.
 *
 * Skenario kosong menghasilkan NA yang persis sama dengan perhitungan biasa —
 * itu yang membuatnya aman dipakai sebagai jalur tunggal.
 */
readonly class Skenario
{
    /**
     * @param  array<string, int>  $skor  elemen_id => skor pengganti (1..4)
     * @param  array<string, LevelSyaratPerlu>  $syaratPerlu  elemen_id => level pengganti
     */
    public function __construct(
        public array $skor = [],
        public array $syaratPerlu = [],
    ) {}

    public function kosong(): bool
    {
        return $this->skor === [] && $this->syaratPerlu === [];
    }

    public function jumlahPengandaian(): int
    {
        return count($this->skor) + count($this->syaratPerlu);
    }

    /** Membangun dari larik JSON yang tersimpan di kolom `simulasi.parameter`. */
    public static function dariParameter(?array $parameter): self
    {
        $parameter ??= [];

        return new self(
            skor: array_map(intval(...), $parameter['skor'] ?? []),
            syaratPerlu: array_map(
                fn (string $l) => LevelSyaratPerlu::from($l),
                $parameter['syarat_perlu'] ?? [],
            ),
        );
    }

    /** Bentuk yang disimpan ke kolom JSON. */
    public function keParameter(): array
    {
        return [
            'skor' => $this->skor,
            'syarat_perlu' => array_map(fn (LevelSyaratPerlu $l) => $l->value, $this->syaratPerlu),
        ];
    }
}
