<?php

namespace Tests\Feature;

use App\Services\NormalisasiTautan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kesembilan bentuk URL Drive dari vibecoding/docs/07-bukti-dan-tautan-drive.md.
 *
 * Yang paling penting di sini bukan kerapian melainkan dua hal: segmen
 * `/u/0/` harus hilang, dan empat bentuk URL berkas yang berbeda harus menyatu
 * menjadi satu bentuk kanonik supaya duplikat terdeteksi.
 */
class NormalisasiTautanTest extends TestCase
{
    private const ID = '1AbC_dEf-123';

    /** @return array<string, array{string, string, string}> */
    public static function bentukDrive(): array
    {
        $id = self::ID;

        return [
            'berkas view?usp=sharing' => [
                "https://drive.google.com/file/d/{$id}/view?usp=sharing",
                'berkas', "https://drive.google.com/file/d/{$id}/view",
            ],
            'berkas edit' => [
                "https://drive.google.com/file/d/{$id}/edit",
                'berkas', "https://drive.google.com/file/d/{$id}/view",
            ],
            'berkas open?id=' => [
                "https://drive.google.com/open?id={$id}",
                'berkas', "https://drive.google.com/file/d/{$id}/view",
            ],
            'berkas uc?id=&export=download' => [
                "https://drive.google.com/uc?id={$id}&export=download",
                'berkas', "https://drive.google.com/file/d/{$id}/view",
            ],
            'folder' => [
                "https://drive.google.com/drive/folders/{$id}",
                'folder', "https://drive.google.com/drive/folders/{$id}",
            ],
            'folder dengan /u/0/' => [
                "https://drive.google.com/drive/u/0/folders/{$id}",
                'folder', "https://drive.google.com/drive/folders/{$id}",
            ],
            'dokumen' => [
                "https://docs.google.com/document/d/{$id}/edit",
                'dokumen', "https://docs.google.com/document/d/{$id}/view",
            ],
            'lembar dengan #gid' => [
                "https://docs.google.com/spreadsheets/d/{$id}/edit#gid=0",
                'lembar', "https://docs.google.com/spreadsheets/d/{$id}/view",
            ],
            'slide' => [
                "https://docs.google.com/presentation/d/{$id}/edit",
                'slide', "https://docs.google.com/presentation/d/{$id}/view",
            ],
        ];
    }

    #[Test]
    #[DataProvider('bentukDrive')]
    public function kesembilan_bentuk_url_drive_dikenali(string $url, string $bentuk, string $kanonik): void
    {
        $hasil = NormalisasiTautan::urai($url);

        $this->assertNotNull($hasil);
        $this->assertSame('drive', $hasil['penyedia']);
        $this->assertSame($bentuk, $hasil['tautan_bentuk'], "Bentuk tautan untuk {$url}.");
        $this->assertSame($kanonik, $hasil['url_kanonik'], "URL kanonik untuk {$url}.");
        $this->assertSame(self::ID, $hasil['tautan_id']);
    }

    #[Test]
    public function segmen_akun_dibuang_dari_mana_pun(): void
    {
        // Segmen ini menunjuk akun KE BERAPA yang sedang masuk di peramban
        // pengunggah. Orang lain dengan susunan akun berbeda akan diarahkan ke
        // tempat lain — atau ditolak.
        foreach (['u/0', 'u/1', 'u/3'] as $segmen) {
            $hasil = NormalisasiTautan::urai("https://drive.google.com/drive/{$segmen}/folders/XYZ");

            $this->assertStringNotContainsString('/u/', $hasil['url_kanonik']);
            $this->assertSame('https://drive.google.com/drive/folders/XYZ', $hasil['url_kanonik']);
        }
    }

    #[Test]
    public function empat_bentuk_berkas_menyatu_jadi_satu_kunci_duplikasi(): void
    {
        $id = self::ID;

        $kanonik = collect([
            "https://drive.google.com/file/d/{$id}/view?usp=sharing",
            "https://drive.google.com/file/d/{$id}/edit",
            "https://drive.google.com/open?id={$id}",
            "https://drive.google.com/uc?id={$id}&export=download",
        ])->map(fn (string $u) => NormalisasiTautan::urai($u)['url_kanonik'])->unique();

        // Tanpa ini, bukti yang sama diunggah empat kali tidak terdeteksi
        // sebagai duplikat oleh impor tempel-tabel.
        $this->assertCount(1, $kanonik);
    }

    #[Test]
    public function url_di_luar_drive_tetap_disimpan_dengan_penyedia_lainnya(): void
    {
        $hasil = NormalisasiTautan::urai('https://sipeg.unsil.ac.id/sk/2401.pdf');

        $this->assertSame('lainnya', $hasil['penyedia']);
        $this->assertNull($hasil['tautan_bentuk']);
        $this->assertSame('https://sipeg.unsil.ac.id/sk/2401.pdf', $hasil['url_kanonik']);
    }

    #[Test]
    public function penyedia_lain_dikenali_dari_host(): void
    {
        $this->assertSame('sharepoint',
            NormalisasiTautan::urai('https://unsil.sharepoint.com/sites/ppg/berkas.pdf')['penyedia']);
        $this->assertSame('onedrive',
            NormalisasiTautan::urai('https://onedrive.live.com/?id=ABC')['penyedia']);
    }

    #[Test]
    public function url_tidak_sah_ditolak(): void
    {
        foreach (['', '   ', 'bukan url', 'drive.google.com/file/d/X'] as $buruk) {
            $this->assertNull(NormalisasiTautan::urai($buruk), "Seharusnya ditolak: `{$buruk}`");
        }
    }
}
