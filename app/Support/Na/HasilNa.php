<?php

namespace App\Support\Na;

/**
 * Hasil perhitungan Nilai Akreditasi.
 *
 * `jumlahElemenBelumDinilai` BUKAN pelengkap: ia wajib ditampilkan di layar.
 * Elemen tanpa penilaian diperlakukan berskor 3, jadi NA proyeksi atas 40
 * elemen yang belum dinilai lebih merupakan tebakan daripada perkiraan.
 * Menyembunyikan angka itu membuat proyeksi dibaca sebagai kepastian, dan
 * itulah cara paling halus membuat orang berhenti memeriksa.
 */
readonly class HasilNa
{
    public function __construct(
        public float $na,
        public float $surplus,
        public float $bobotSkor4,
        public int $jumlahElemenBelumDinilai,
        public string $status,
        public int $masaBerlaku,
        public bool $syarat3,
        public bool $syarat5,
    ) {}

    /** Kalimat status yang dibaca manusia, misalnya "Unggul — masa berlaku 3 tahun". */
    public function kalimatStatus(): string
    {
        return $this->masaBerlaku === 0
            ? $this->status
            : "{$this->status} — masa berlaku {$this->masaBerlaku} tahun";
    }

    /**
     * Kalimat kejujuran yang menyertai NA proyeksi.
     *
     * Selalu ada, bahkan ketika seluruh elemen sudah dinilai — saat itu ia
     * justru menyatakan bahwa angkanya utuh.
     */
    public function kalimatKeyakinan(): string
    {
        return $this->jumlahElemenBelumDinilai === 0
            ? 'Seluruh 59 elemen sudah dinilai.'
            : "{$this->jumlahElemenBelumDinilai} elemen belum dinilai dan diandaikan berskor 3.";
    }

    public function warna(): string
    {
        return match (true) {
            $this->status === 'Unggul' && $this->masaBerlaku === 5 => 'success',
            $this->status === 'Unggul' => 'warning',
            $this->status === 'Terakreditasi' => 'info',
            default => 'danger',
        };
    }
}
