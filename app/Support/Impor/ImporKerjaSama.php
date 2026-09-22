<?php

namespace App\Support\Impor;

/**
 * Kerja sama tridharma — butir DKPS 1.
 *
 * Kunci duplikasinya lembaga mitra + judul ternormalisasi + tahun. Ketiganya
 * diperlukan: satu lembaga bisa punya beberapa kerja sama, dan judul yang sama
 * bisa diperbarui tiap tahun.
 *
 * Angka dari sinilah yang dibaca RK, yang menimbang kerja sama menurut
 * tingkatnya: internasional, nasional, lokal.
 */
class ImporKerjaSama extends ProfilDkpsDasar
{
    public function nama(): string
    {
        return 'Kerja sama tridharma';
    }

    protected function nomorButir(): int
    {
        return 1;
    }

    public function medan(): array
    {
        return $this->medanBersama() + [
            'lembaga_mitra' => ['label' => 'Lembaga mitra', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'Dinas Pendidikan Kota Tasikmalaya'],
            'judul' => ['label' => 'Judul kegiatan', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'Pendampingan PPL mahasiswa PPG'],
            'tahun' => ['label' => 'Tahun', 'wajib' => true, 'tipe' => 'angka', 'contoh' => '2024'],
            'tingkat' => ['label' => 'Tingkat', 'wajib' => false, 'tipe' => 'pilihan',
                'contoh' => 'nasional'],
            'bidang' => ['label' => 'Bidang tridharma', 'wajib' => false, 'tipe' => 'pilihan',
                'contoh' => 'pendidikan'],
        ];
    }

    public function normalkan(array $baris): array
    {
        $baris['tahun_acuan'] = strtoupper(trim($baris['tahun_acuan'] ?? ''));
        $baris['sumber'] = mb_strtolower(trim($baris['sumber'] ?? 'manual'));
        $baris['tingkat'] = mb_strtolower(trim($baris['tingkat'] ?? ''));

        $baris['_kunci'] = NormalisasiKunci::dari(
            'kerjasama',
            (string) ($baris['lembaga_mitra'] ?? ''),
            (string) ($baris['judul'] ?? ''),
            (string) ($baris['tahun'] ?? ''),
        );

        return $baris;
    }

    public function validasi(array $baris): array
    {
        $galat = parent::validasi($baris);

        $tahun = $baris['tahun'] ?? null;

        if (filled($tahun) && ! preg_match('/^\d{4}$/', (string) $tahun)) {
            $galat[] = "Tahun `{$tahun}` bukan empat digit.";
        }

        if (filled($baris['tingkat']) && ! in_array($baris['tingkat'], ['internasional', 'nasional', 'lokal'], true)) {
            $galat[] = "Tingkat `{$baris['tingkat']}` harus internasional, nasional, atau lokal.";
        }

        return $galat;
    }

    protected function kolomData(array $baris): array
    {
        return [
            'lembaga_mitra' => $baris['lembaga_mitra'],
            'judul' => $baris['judul'],
            'tahun' => (int) $baris['tahun'],
            'tingkat' => $baris['tingkat'] ?: null,
            'bidang' => $baris['bidang'] ?? null,
        ];
    }
}
