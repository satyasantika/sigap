<?php

namespace App\Services;

use App\Enums\AksesTautan;
use App\Models\Bukti;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Memeriksa apakah sebuah tautan bisa dibuka oleh orang yang TIDAK punya akses
 * apa-apa — yaitu asesor.
 *
 * SYARAT MUTLAK: permintaan dikirim tanpa kredensial apa pun. Tanpa cookie,
 * tanpa token, tanpa akun layanan. Memakai kredensial membuat pemeriksaan
 * selalu lulus dan karena itu tidak berguna sama sekali — justru kegagalan
 * inilah yang paling sering terjadi di lapangan: pengunggah bisa membuka
 * tautannya sendiri, asesor tidak.
 */
class PemeriksaTautan
{
    public const BATAS_WAKTU = 10;

    public const BATAS_LOMPATAN = 5;

    /** Setelah tiga kegagalan mesin, serahkan ke manusia. */
    public const BATAS_PERCOBAAN = 3;

    public function periksa(Bukti $bukti): AksesTautan
    {
        if ($bukti->url_kanonik === null) {
            return $bukti->akses_status;
        }

        [$status, $pesan] = $this->tanya($bukti->url_kanonik);

        $bukti->forceFill([
            'akses_status' => $status,
            'akses_pesan' => $pesan,
            'akses_diperiksa_pada' => now(),
            // Cacah percobaan hanya naik untuk kegagalan MESIN; hasil yang
            // pasti (terbuka, perlu izin, tidak ditemukan) meresetnya, karena
            // ia jawaban, bukan kegagalan.
            'akses_percobaan' => $status === AksesTautan::GagalPeriksa
                ? $bukti->akses_percobaan + 1
                : 0,
        ])->save();

        return $status;
    }

    /**
     * @return array{AksesTautan, string} status dan pesan penelusuran
     */
    private function tanya(string $url): array
    {
        try {
            $respons = Http::withOptions([
                'allow_redirects' => ['max' => self::BATAS_LOMPATAN, 'track_redirects' => true],
                // Tanpa cookie jar, tanpa auth — disebut eksplisit supaya
                // tidak ada yang menambahkannya "supaya lolos".
                'cookies' => false,
            ])
                ->timeout(self::BATAS_WAKTU)
                ->withHeaders(['User-Agent' => 'SIGAP pemeriksa tautan (tanpa kredensial)'])
                ->get($url);

            $urlAkhir = $this->urlAkhir($respons, $url);
            $kode = $respons->status();
            $pesan = "HTTP {$kode} → {$urlAkhir}";

            // Dialihkan ke halaman masuk berarti asesor akan melihat hal yang
            // sama: minta izin.
            if ($this->halamanMasuk($urlAkhir)) {
                return [AksesTautan::PerluIzin, $pesan];
            }

            return [match (true) {
                $kode === 403 => AksesTautan::PerluIzin,
                in_array($kode, [404, 410], true) => AksesTautan::TidakDitemukan,
                $kode >= 500 => AksesTautan::GagalPeriksa,
                $respons->successful() => AksesTautan::Terbuka,
                default => AksesTautan::GagalPeriksa,
            }, $pesan];
        } catch (ConnectionException $e) {
            return [AksesTautan::GagalPeriksa, 'Gagal terhubung: '.$e->getMessage()];
        } catch (Throwable $e) {
            return [AksesTautan::GagalPeriksa, 'Galat: '.$e->getMessage()];
        }
    }

    private function urlAkhir($respons, string $awal): string
    {
        $jejak = $respons->handlerStats()['redirect_url'] ?? null;

        if (filled($jejak)) {
            return $jejak;
        }

        $riwayat = $respons->getHeader('X-Guzzle-Redirect-History');

        return $riwayat === [] ? $awal : end($riwayat);
    }

    private function halamanMasuk(string $url): bool
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        return str_contains($host, 'accounts.google.com')
            || str_contains($host, 'login.microsoftonline.com')
            || str_contains(strtolower($url), '/signin');
    }
}
