<?php

namespace App\Services;

use App\Enums\PenyediaTautan;

/**
 * Menguraikan URL penyimpanan awan menjadi bentuk kanonik.
 *
 * Gunanya bukan kerapian, melainkan dua hal yang mahal kalau diabaikan:
 *
 * 1. Segmen `/u/0/`, `/u/1/` dan seterusnya menunjuk akun KE BERAPA yang
 *    sedang masuk di peramban pengunggah. Orang lain yang membukanya dengan
 *    susunan akun berbeda akan diarahkan ke dokumen lain — atau ditolak.
 *    Ini sumber kebingungan yang sangat sering terjadi dan sepenuhnya bisa
 *    dihilangkan di sini.
 *
 * 2. Satu berkas Drive punya banyak bentuk URL (`/view`, `/edit`, `open?id=`,
 *    `uc?id=`). Tanpa dinormalkan, bukti yang sama diunggah dua kali tidak
 *    terdeteksi sebagai duplikat.
 */
class NormalisasiTautan
{
    /** Id Drive: huruf, angka, garis bawah, tanda hubung. */
    private const ID = '[A-Za-z0-9_-]+';

    /**
     * @return array{penyedia: string, tautan_id: ?string, tautan_bentuk: ?string, url_kanonik: string}|null
     *                                                                                                       null bila URL-nya tidak sah sama sekali
     */
    public static function urai(string $url): ?array
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        // Dibuang lebih dulu, sebelum pencocokan pola apa pun.
        $bersih = self::buangSegmenAkun($url);

        foreach (self::pola() as [$pola, $bentuk, $templat]) {
            if (preg_match($pola, $bersih, $cocok) === 1) {
                $id = $cocok['id'];

                return [
                    'penyedia' => PenyediaTautan::Drive->value,
                    'tautan_id' => $id,
                    'tautan_bentuk' => $bentuk,
                    'url_kanonik' => str_replace('{ID}', $id, $templat),
                ];
            }
        }

        // URL yang tidak dikenali tetap disimpan dan tetap diperiksa
        // keterbacaannya — hanya tanpa penanganan khusus Drive.
        return [
            'penyedia' => self::tebakPenyedia($bersih),
            'tautan_id' => null,
            'tautan_bentuk' => null,
            'url_kanonik' => $bersih,
        ];
    }

    /**
     * Kesembilan bentuk yang wajib dikenali, beserta bentuk kanoniknya.
     *
     * @return array<int, array{string, string, string}>
     */
    private static function pola(): array
    {
        $id = self::ID;

        return [
            // Berkas
            ["#^https?://drive\.google\.com/file/d/(?<id>{$id})(/|$)#i",
                'berkas', 'https://drive.google.com/file/d/{ID}/view'],
            ["#^https?://drive\.google\.com/open\?(?:[^\#]*&)?id=(?<id>{$id})#i",
                'berkas', 'https://drive.google.com/file/d/{ID}/view'],
            ["#^https?://drive\.google\.com/uc\?(?:[^\#]*&)?id=(?<id>{$id})#i",
                'berkas', 'https://drive.google.com/file/d/{ID}/view'],
            // Folder
            ["#^https?://drive\.google\.com/drive/folders/(?<id>{$id})#i",
                'folder', 'https://drive.google.com/drive/folders/{ID}'],
            // Dokumen, lembar, slide
            ["#^https?://docs\.google\.com/document/d/(?<id>{$id})#i",
                'dokumen', 'https://docs.google.com/document/d/{ID}/view'],
            ["#^https?://docs\.google\.com/spreadsheets/d/(?<id>{$id})#i",
                'lembar', 'https://docs.google.com/spreadsheets/d/{ID}/view'],
            ["#^https?://docs\.google\.com/presentation/d/(?<id>{$id})#i",
                'slide', 'https://docs.google.com/presentation/d/{ID}/view'],
        ];
    }

    /**
     * Membuang `/u/0/`, `/u/1/` dan seterusnya di mana pun ia muncul.
     *
     * Drive menyisipkannya tepat setelah nama host atau setelah `/drive`,
     * jadi keduanya ditangani.
     */
    private static function buangSegmenAkun(string $url): string
    {
        return preg_replace('#/u/\d+(?=/)#', '', $url) ?? $url;
    }

    private static function tebakPenyedia(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        return match (true) {
            str_contains($host, 'google.com') => PenyediaTautan::Drive->value,
            str_contains($host, 'sharepoint.com') => PenyediaTautan::SharePoint->value,
            str_contains($host, 'onedrive.live.com'),
            str_contains($host, '1drv.ms') => PenyediaTautan::OneDrive->value,
            default => PenyediaTautan::Lainnya->value,
        };
    }
}
