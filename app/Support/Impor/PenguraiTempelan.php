<?php

namespace App\Support\Impor;

use RuntimeException;

/**
 * Menguraikan tempelan dari Excel, Google Sheets, atau Word.
 *
 * Pemisah dideteksi TAB lebih dulu, baru titik koma, baru koma. Urutannya
 * penting: tempelan dari spreadsheet selalu bertab, sementara judul bukti
 * sering mengandung koma ("SK Dekan, 2401") — menebak koma lebih dulu akan
 * memecah judul menjadi dua kolom.
 */
class PenguraiTempelan
{
    /** Lebih dari ini, tempelannya ditolak — bukan dipotong diam-diam. */
    public const BATAS_BARIS = 1000;

    /**
     * @return array{pemisah: string, kepala: array<int, string>, baris: array<int, array<int, string>>}
     */
    public static function urai(string $tempelan, bool $barisPertamaKepala = true): array
    {
        $teks = str_replace(["\r\n", "\r"], "\n", trim($tempelan));

        if ($teks === '') {
            throw new RuntimeException('Tempelannya kosong.');
        }

        $pemisah = self::tebakPemisah($teks);
        $barisMentah = self::pecahBaris($teks, $pemisah);

        if (count($barisMentah) > self::BATAS_BARIS) {
            throw new RuntimeException(
                'Tempelan berisi '.count($barisMentah).' baris, melebihi batas '.self::BATAS_BARIS.'. '
                .'Bagi menjadi beberapa tempelan — memotongnya diam-diam akan membuat sebagian data hilang tanpa disadari.'
            );
        }

        $kepala = [];

        if ($barisPertamaKepala && $barisMentah !== []) {
            $kepala = array_shift($barisMentah);
        }

        return ['pemisah' => $pemisah, 'kepala' => $kepala, 'baris' => array_values($barisMentah)];
    }

    /** Baris pertama dianggap kepala bila tidak mengandung angka sama sekali. */
    public static function barisPertamaTampakKepala(string $tempelan): bool
    {
        $baris = strtok(trim($tempelan), "\n") ?: '';

        return preg_match('/\d/', $baris) !== 1;
    }

    private static function tebakPemisah(string $teks): string
    {
        $barisPertama = strtok($teks, "\n") ?: '';

        foreach (["\t", ';', ','] as $kandidat) {
            if (str_contains($barisPertama, $kandidat)) {
                return $kandidat;
            }
        }

        return "\t";
    }

    /**
     * Memecah dengan menghormati tanda kutip: sel bertanda kutip boleh
     * mengandung pemisah maupun baris baru, dan itu sering terjadi pada kolom
     * keterangan yang disalin dari spreadsheet.
     *
     * @return array<int, array<int, string>>
     */
    private static function pecahBaris(string $teks, string $pemisah): array
    {
        $baris = [];
        $sel = [];
        $buffer = '';
        $dalamKutip = false;
        $panjang = mb_strlen($teks);

        for ($i = 0; $i < $panjang; $i++) {
            $huruf = mb_substr($teks, $i, 1);

            if ($huruf === '"') {
                // Dua kutip berturut-turut di dalam kutip berarti satu kutip harfiah.
                if ($dalamKutip && mb_substr($teks, $i + 1, 1) === '"') {
                    $buffer .= '"';
                    $i++;

                    continue;
                }

                $dalamKutip = ! $dalamKutip;

                continue;
            }

            if (! $dalamKutip && $huruf === $pemisah) {
                $sel[] = trim($buffer);
                $buffer = '';

                continue;
            }

            if (! $dalamKutip && $huruf === "\n") {
                $sel[] = trim($buffer);
                $baris[] = $sel;
                $sel = [];
                $buffer = '';

                continue;
            }

            $buffer .= $huruf;
        }

        if ($buffer !== '' || $sel !== []) {
            $sel[] = trim($buffer);
            $baris[] = $sel;
        }

        return array_values(array_filter(
            $baris,
            fn (array $b) => collect($b)->filter(fn ($s) => $s !== '')->isNotEmpty(),
        ));
    }

    /**
     * Menebak pemetaan kolom dari kepala tabel.
     *
     * Tidak peka huruf besar-kecil, spasi, maupun garis bawah: "Tanggal
     * Kejadian", "tanggal_kejadian", dan "TanggalKejadian" semuanya cocok.
     *
     * @param  array<int, string>  $kepala
     * @param  array<string, array{label: string, wajib: bool, tipe: string, contoh: string}>  $medan
     * @return array<string, int|null> kunci medan => indeks kolom
     */
    public static function tebakPemetaan(array $kepala, array $medan): array
    {
        $rapikan = fn (string $s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower($s)) ?? '';

        $kepalaRapi = collect($kepala)->map($rapikan);
        $peta = [];

        foreach ($medan as $kunci => $def) {
            $calon = [$rapikan($kunci), $rapikan($def['label'])];
            $indeks = null;

            foreach ($calon as $c) {
                $ketemu = $kepalaRapi->search($c);

                if ($ketemu !== false) {
                    $indeks = $ketemu;
                    break;
                }
            }

            $peta[$kunci] = $indeks;
        }

        return $peta;
    }
}
