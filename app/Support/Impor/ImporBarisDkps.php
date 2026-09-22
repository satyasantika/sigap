<?php

namespace App\Support\Impor;

/**
 * Impor bebas untuk butir DKPS mana pun.
 *
 * Kunci duplikasinya butir + tahun acuan + kunci alami barisnya, sesuai
 * dokumen 09. Kunci alaminya diserahkan pengisi lewat kolom "Penanda baris",
 * karena bentuk tabel tiap butir berbeda dan tidak ada satu kolom yang selalu
 * ada di keduapuluh delapan butir.
 */
class ImporBarisDkps extends ProfilDkpsDasar
{
    public function __construct(
        string $periodeId,
        string $prodiId,
        string $penggunaId,
        private readonly int $butir,
    ) {
        parent::__construct($periodeId, $prodiId, $penggunaId);
    }

    public function nama(): string
    {
        return "Baris DKPS butir {$this->butir}";
    }

    protected function nomorButir(): int
    {
        return $this->butir;
    }

    public function medan(): array
    {
        return $this->medanBersama() + [
            'penanda' => ['label' => 'Penanda baris', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'nama, nomor, atau apa pun yang membedakan baris ini'],
            'isi' => ['label' => 'Isi', 'wajib' => false, 'tipe' => 'teks',
                'contoh' => 'nilai kolom utama'],
            'keterangan' => ['label' => 'Keterangan', 'wajib' => false, 'tipe' => 'teks',
                'contoh' => ''],
        ];
    }

    public function normalkan(array $baris): array
    {
        $baris['tahun_acuan'] = strtoupper(trim($baris['tahun_acuan'] ?? ''));
        $baris['sumber'] = mb_strtolower(trim($baris['sumber'] ?? 'manual'));
        $baris['_kunci'] = NormalisasiKunci::dari(
            (string) $this->butir,
            $baris['tahun_acuan'],
            (string) ($baris['penanda'] ?? ''),
        );

        return $baris;
    }

    protected function kolomData(array $baris): array
    {
        return [
            'penanda' => $baris['penanda'],
            'isi' => $baris['isi'] ?? null,
            'keterangan' => $baris['keterangan'] ?? null,
        ];
    }
}
