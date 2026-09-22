<?php

namespace App\Enums;

/**
 * Enam status tagihan beserta transisi yang sah di antaranya.
 *
 * Nilai, label, warna, dan daftar transisi harus sama persis dengan
 * data/status-tagihan.json — data/verifikasi.py menguji jumlahnya, dan
 * StatusTagihanTest menguji kecocokannya satu per satu.
 *
 * Graf transisinya tujuh sisi:
 *   belum        -> dikerjakan
 *   dikerjakan   -> diajukan
 *   diajukan     -> direviu, dikembalikan
 *   direviu      -> disetujui, dikembalikan
 *   dikembalikan -> dikerjakan
 *   disetujui    -> (buntu)
 *
 * Tidak ada lompatan, dan `disetujui` tidak bisa dibatalkan. Untuk mengubah
 * tagihan yang sudah disetujui, ketua mengembalikannya lebih dulu — dan itu
 * tercatat di tagihan_riwayat.
 */
enum StatusTagihan: string
{
    case Belum = 'belum';
    case Dikerjakan = 'dikerjakan';
    case Diajukan = 'diajukan';
    case Direviu = 'direviu';
    case Dikembalikan = 'dikembalikan';
    case Disetujui = 'disetujui';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum dikerjakan',
            self::Dikerjakan => 'Sedang dikerjakan',
            self::Diajukan => 'Diajukan untuk reviu',
            self::Direviu => 'Lolos reviu koordinator',
            self::Dikembalikan => 'Dikembalikan untuk perbaikan',
            self::Disetujui => 'Disetujui ketua',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Belum => 'gray',
            self::Dikerjakan => 'info',
            self::Diajukan => 'warning',
            self::Direviu => 'primary',
            self::Dikembalikan => 'danger',
            self::Disetujui => 'success',
        };
    }

    public function urutan(): int
    {
        return match ($this) {
            self::Belum => 1,
            self::Dikerjakan => 2,
            self::Diajukan => 3,
            self::Direviu => 4,
            self::Dikembalikan => 5,
            self::Disetujui => 6,
        };
    }

    /**
     * Status berikutnya yang sah dari status ini.
     *
     * @return array<int, self>
     */
    public function berikutnya(): array
    {
        return match ($this) {
            self::Belum => [self::Dikerjakan],
            self::Dikerjakan => [self::Diajukan],
            self::Diajukan => [self::Direviu, self::Dikembalikan],
            self::Direviu => [self::Disetujui, self::Dikembalikan],
            self::Dikembalikan => [self::Dikerjakan],
            self::Disetujui => [],
        };
    }

    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->berikutnya(), true);
    }

    /**
     * Kode aksi izin yang dibutuhkan untuk berpindah ke status ini.
     * Dipakai AlurTagihan; tidak ada perbandingan peran di sana.
     */
    public function aksiIzin(): string
    {
        return match ($this) {
            self::Dikerjakan => 'tagihan.ajukan',
            self::Diajukan => 'tagihan.ajukan',
            self::Direviu => 'tagihan.reviu',
            self::Disetujui => 'tagihan.setujui',
            self::Dikembalikan => 'tagihan.kembalikan',
            self::Belum => 'tagihan.buat',
        };
    }

    /** Hanya satu status yang dihitung selesai untuk progres. */
    public function hitungSelesai(): bool
    {
        return $this === self::Disetujui;
    }

    /** Mengembalikan tagihan tanpa alasan membuat orang menebak-nebak. */
    public function butuhCatatan(): bool
    {
        return $this === self::Dikembalikan;
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
