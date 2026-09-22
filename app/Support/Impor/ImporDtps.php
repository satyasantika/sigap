<?php

namespace App\Support\Impor;

/**
 * Daftar dosen tetap program studi, mendarat di butir DKPS 6
 * (Dosen Bidang Studi dan Dosen Pengampu).
 *
 * Kunci duplikasinya NIDN. Dua baris dengan NIDN sama adalah orang yang sama,
 * betapa pun berbeda ejaan namanya — dan ejaan nama dosen memang berbeda-beda
 * antar sumber ("Dr. Ahmad, M.Pd." vs "Ahmad").
 *
 * Angka dari sinilah yang dibaca PDS3 dan PGBLKL: cacah doktor, cacah lektor
 * kepala, dan NDTPS.
 */
class ImporDtps extends ProfilDkpsDasar
{
    public function nama(): string
    {
        return 'Daftar DTPS';
    }

    protected function nomorButir(): int
    {
        return 6;
    }

    public function medan(): array
    {
        return $this->medanBersama() + [
            'nidn' => ['label' => 'NIDN', 'wajib' => true, 'tipe' => 'teks', 'contoh' => '0412088001'],
            'nama_dosen' => ['label' => 'Nama dosen', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'Ahmad Fauzi'],
            'pendidikan' => ['label' => 'Pendidikan tertinggi', 'wajib' => false, 'tipe' => 'pilihan',
                'contoh' => 'S3'],
            'jabatan_akademik' => ['label' => 'Jabatan akademik', 'wajib' => false, 'tipe' => 'pilihan',
                'contoh' => 'Lektor Kepala'],
            'bidang_keahlian' => ['label' => 'Bidang keahlian', 'wajib' => false, 'tipe' => 'teks',
                'contoh' => 'Pendidikan Matematika'],
        ];
    }

    public function normalkan(array $baris): array
    {
        $baris['tahun_acuan'] = strtoupper(trim($baris['tahun_acuan'] ?? ''));
        $baris['sumber'] = mb_strtolower(trim($baris['sumber'] ?? 'manual'));
        // NIDN kadang ditempel dengan spasi atau tanda hubung.
        $baris['nidn'] = preg_replace('/\D/', '', (string) ($baris['nidn'] ?? ''));
        $baris['_kunci'] = NormalisasiKunci::dari('dtps', $baris['nidn']);

        return $baris;
    }

    public function validasi(array $baris): array
    {
        $galat = parent::validasi($baris);

        if (filled($baris['nidn'] ?? null) && strlen($baris['nidn']) !== 10) {
            $galat[] = "NIDN `{$baris['nidn']}` bukan sepuluh digit.";
        }

        return $galat;
    }

    protected function kolomData(array $baris): array
    {
        return [
            'nidn' => $baris['nidn'],
            'nama_dosen' => $baris['nama_dosen'],
            'pendidikan' => $baris['pendidikan'] ?? null,
            'jabatan_akademik' => $baris['jabatan_akademik'] ?? null,
            'bidang_keahlian' => $baris['bidang_keahlian'] ?? null,
        ];
    }
}
