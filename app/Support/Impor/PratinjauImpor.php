<?php

namespace App\Support\Impor;

/**
 * Menyusun pratinjau baris beserta status duplikasinya.
 *
 * Deteksi berjalan DUA ARAH: terhadap basis data dan terhadap baris lain di
 * tempelan yang sama. Memeriksa satu arah saja adalah kesalahan yang paling
 * sering terjadi — orang menempel daftar yang di dalamnya sendiri sudah ada
 * kembaran, dan keduanya lolos karena belum ada di basis data.
 */
class PratinjauImpor
{
    public const BARU = 'baru';

    public const DUPLIKAT_TEMPELAN = 'duplikat_tempelan';

    public const DUPLIKAT_BASIS_DATA = 'duplikat_basis_data';

    public const GALAT = 'galat';

    /**
     * @param  array<int, array<string, mixed>>  $barisTerpetakan
     * @return array<int, array{no: int, data: array<string, mixed>, status: string, galat: array<int, string>, id_lama: ?string}>
     */
    public static function susun(array $barisTerpetakan, ProfilImpor $profil, string $periodeId): array
    {
        $hasil = [];
        $kunciTerlihat = [];

        foreach ($barisTerpetakan as $i => $mentah) {
            $data = $profil->normalkan($mentah);
            $galat = $profil->validasi($data);

            if ($galat !== []) {
                $hasil[] = [
                    'no' => $i + 1, 'data' => $data, 'status' => self::GALAT,
                    'galat' => $galat, 'id_lama' => null,
                ];

                continue;
            }

            $kunci = $profil->kunciDuplikat($data);

            // Arah pertama: kembaran di dalam tempelan yang sama.
            if ($kunci !== null && isset($kunciTerlihat[$kunci])) {
                $hasil[] = [
                    'no' => $i + 1, 'data' => $data, 'status' => self::DUPLIKAT_TEMPELAN,
                    'galat' => ['Kembar dengan baris '.$kunciTerlihat[$kunci].' di tempelan ini.'],
                    'id_lama' => null,
                ];

                continue;
            }

            if ($kunci !== null) {
                $kunciTerlihat[$kunci] = $i + 1;
            }

            // Arah kedua: sudah ada di basis data.
            $lama = $kunci === null ? null : $profil->cariYangAda($kunci, $periodeId);

            $hasil[] = [
                'no' => $i + 1,
                'data' => $data,
                'status' => $lama === null ? self::BARU : self::DUPLIKAT_BASIS_DATA,
                'galat' => [],
                'id_lama' => $lama?->getKey(),
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<int, array{status: string}>  $pratinjau
     * @return array{baru: int, duplikat_tempelan: int, duplikat_basis_data: int, galat: int, total: int}
     */
    public static function ringkas(array $pratinjau): array
    {
        $cacah = collect($pratinjau)->countBy('status');

        return [
            'baru' => $cacah[self::BARU] ?? 0,
            'duplikat_tempelan' => $cacah[self::DUPLIKAT_TEMPELAN] ?? 0,
            'duplikat_basis_data' => $cacah[self::DUPLIKAT_BASIS_DATA] ?? 0,
            'galat' => $cacah[self::GALAT] ?? 0,
            'total' => count($pratinjau),
        ];
    }

    /** Kalimat ringkasan yang dibaca manusia sebelum menekan Jalankan. */
    public static function kalimat(array $ringkas): string
    {
        return "{$ringkas['total']} baris — {$ringkas['baru']} baru, "
            ."{$ringkas['duplikat_basis_data']} duplikat di basis data, "
            ."{$ringkas['duplikat_tempelan']} kembar di tempelan, "
            ."{$ringkas['galat']} galat.";
    }
}
