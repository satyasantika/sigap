<?php

namespace App\Services;

use App\Models\Tagihan;

/**
 * Kelayakan naskah LED sebelum diajukan.
 *
 * Tahap 3 baru memeriksa cacah kata; tabel `narasi` dan tautan bukti lahir di
 * tahap 4. Kerangkanya disiapkan sekarang supaya AlurTagihan tidak perlu
 * diubah lagi nanti — cukup metode di kelas ini yang diisi.
 *
 * Cacah kata memakai pemisahan spasi sederhana, dan pemisah yang sama dipakai
 * di seluruh aplikasi. Angkanya hanya berguna kalau semua orang melihat angka
 * yang sama; penghitung yang lebih pintar di satu tempat justru menimbulkan
 * selisih yang membingungkan.
 */
class PengelolaNarasi
{
    public const MINIMAL_KATA = 200;

    public const MAKSIMAL_KATA = 600;

    public function jumlahKata(?string $teks): int
    {
        if (blank($teks)) {
            return 0;
        }

        return count(preg_split('/\s+/u', trim($teks), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Daftar alasan sebuah tagihan narasi belum boleh diajukan.
     * Larik kosong berarti layak.
     *
     * Sengaja mengembalikan DAFTAR, bukan boolean: orang yang naskahnya ditolak
     * harus tahu semua yang kurang sekaligus, bukan menemukannya satu per satu
     * lewat percobaan berulang.
     *
     * @return array<int, string>
     */
    public function alasanBelumLayak(Tagihan $tagihan): array
    {
        $alasan = [];

        $narasi = $this->narasiUntuk($tagihan);
        $kata = $this->jumlahKata($narasi);

        if ($kata === 0) {
            $alasan[] = 'Naskahnya masih kosong.';
        } elseif ($kata < self::MINIMAL_KATA) {
            $alasan[] = "Baru {$kata} kata, minimal ".self::MINIMAL_KATA.' kata.';
        }

        // Lebih dari 600 kata hanya diperingatkan, tidak menghalangi —
        // vibecoding/docs/01-domain-dan-aturan.md bagian "Aturan narasi LED".

        return $alasan;
    }

    /**
     * Naskah yang tertaut pada tagihan ini.
     *
     * Tahap 3 belum punya tabel `narasi`, jadi sementara membaca `deskripsi`
     * tidak masuk akal — yang benar adalah mengembalikan null dan membiarkan
     * pemeriksaan cacah kata menolak pengajuan sampai tahap 4 memasang
     * sumbernya. Diganti di tahap 4, bukan ditambal di sini.
     */
    private function narasiUntuk(Tagihan $tagihan): ?string
    {
        return null;
    }
}
