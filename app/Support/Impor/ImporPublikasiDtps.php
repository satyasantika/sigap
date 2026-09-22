<?php

namespace App\Support\Impor;

/**
 * Publikasi ilmiah DTPS — butir DKPS 25 dan 26.
 *
 * Kunci duplikasinya DOI lebih dulu; bila DOI kosong, nama dosen ditambah
 * judul ternormalisasi. Urutannya penting: DOI unik secara global, sementara
 * judul yang sama bisa muncul dua kali dengan ejaan berbeda antar penulis
 * yang menempelkan daftarnya masing-masing.
 *
 * PERHATIAN untuk PPDTPS: yang dihitung adalah JUMLAH DOSEN yang punya
 * sekurangnya satu publikasi memenuhi syarat, bukan jumlah baris di sini.
 * Seorang dosen dengan sepuluh artikel menghasilkan sepuluh baris tetapi
 * tetap dihitung satu.
 */
class ImporPublikasiDtps extends ProfilDkpsDasar
{
    public function __construct(
        string $periodeId,
        string $prodiId,
        string $penggunaId,
        private readonly int $butir = 25,
    ) {
        parent::__construct($periodeId, $prodiId, $penggunaId);
    }

    public function nama(): string
    {
        return "Publikasi DTPS (butir {$this->butir})";
    }

    protected function nomorButir(): int
    {
        return $this->butir;
    }

    public function medan(): array
    {
        return $this->medanBersama() + [
            'nama_dosen' => ['label' => 'Nama dosen', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'Ahmad Fauzi'],
            'nidn' => ['label' => 'NIDN', 'wajib' => false, 'tipe' => 'teks', 'contoh' => '0412088001'],
            'judul' => ['label' => 'Judul publikasi', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'Pengembangan model pembelajaran ...'],
            'doi' => ['label' => 'DOI', 'wajib' => false, 'tipe' => 'teks',
                'contoh' => '10.1234/abcd.2020.001'],
            'nama_jurnal' => ['label' => 'Nama jurnal', 'wajib' => false, 'tipe' => 'teks', 'contoh' => ''],
            'peringkat' => ['label' => 'Peringkat', 'wajib' => false, 'tipe' => 'pilihan',
                'contoh' => 'Sinta 2'],
            'peran_penulis' => ['label' => 'Peran penulis', 'wajib' => false, 'tipe' => 'pilihan',
                'contoh' => 'penulis pertama'],
        ];
    }

    public function normalkan(array $baris): array
    {
        $baris['tahun_acuan'] = strtoupper(trim($baris['tahun_acuan'] ?? ''));
        $baris['sumber'] = mb_strtolower(trim($baris['sumber'] ?? 'manual'));
        // DOI sering ditempel lengkap dengan awalan URL.
        $baris['doi'] = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', trim((string) ($baris['doi'] ?? '')));

        $baris['_kunci'] = filled($baris['doi'])
            ? NormalisasiKunci::dari('doi', $baris['doi'])
            : NormalisasiKunci::dari('judul', (string) ($baris['nama_dosen'] ?? ''), (string) ($baris['judul'] ?? ''));

        return $baris;
    }

    protected function kolomData(array $baris): array
    {
        return [
            'nama_dosen' => $baris['nama_dosen'],
            'nidn' => $baris['nidn'] ?? null,
            'judul' => $baris['judul'],
            'doi' => $baris['doi'] ?: null,
            'nama_jurnal' => $baris['nama_jurnal'] ?? null,
            'peringkat' => $baris['peringkat'] ?? null,
            'peran_penulis' => $baris['peran_penulis'] ?? null,
        ];
    }
}
