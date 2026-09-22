<?php

namespace Tests\Feature;

use App\Enums\StatusTagihan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Enum StatusTagihan harus sama persis dengan data/status-tagihan.json.
 *
 * Keduanya memang menyimpan hal yang sama, dan itu disengaja: enum dipakai PHP,
 * JSON dipakai data/verifikasi.py dan dokumen. Kalau keduanya berselisih,
 * salah satunya sudah diubah tanpa yang lain — dan uji ini yang menangkapnya.
 */
class StatusTagihanTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function dariJson(): array
    {
        return json_decode(
            file_get_contents(base_path('data/status-tagihan.json')),
            true, flags: JSON_THROW_ON_ERROR,
        );
    }

    #[Test]
    public function enam_status_dengan_kode_yang_sama_persis(): void
    {
        $json = collect($this->dariJson())->pluck('kode')->all();
        $enum = collect(StatusTagihan::cases())->map(fn (StatusTagihan $s) => $s->value)->all();

        $this->assertCount(6, $enum);
        $this->assertSame($json, $enum, 'Urutan dan kode status harus sama dengan data/status-tagihan.json.');
    }

    #[Test]
    public function daftar_transisi_sama_dengan_json(): void
    {
        foreach ($this->dariJson() as $baris) {
            $status = StatusTagihan::from($baris['kode']);

            $enum = collect($status->berikutnya())
                ->map(fn (StatusTagihan $s) => $s->value)->all();

            $this->assertSame(
                $baris['berikutnya'], $enum,
                "Transisi dari `{$baris['kode']}` tidak sama dengan data/status-tagihan.json."
            );
        }
    }

    #[Test]
    public function urutan_dan_penanda_selesai_sama_dengan_json(): void
    {
        foreach ($this->dariJson() as $baris) {
            $status = StatusTagihan::from($baris['kode']);

            $this->assertSame($baris['urutan'], $status->urutan(), "Urutan `{$baris['kode']}`.");
            $this->assertSame(
                $baris['hitung_selesai'], $status->hitungSelesai(),
                "Penanda selesai `{$baris['kode']}`."
            );
        }
    }

    #[Test]
    public function hanya_disetujui_yang_dihitung_selesai(): void
    {
        // Progres dihitung dari status ini; menambah status kedua yang
        // "dihitung selesai" akan menggandakan bobot yang dianggap tuntas.
        $selesai = collect(StatusTagihan::cases())
            ->filter(fn (StatusTagihan $s) => $s->hitungSelesai())
            ->map(fn (StatusTagihan $s) => $s->value)->values()->all();

        $this->assertSame(['disetujui'], $selesai);
    }

    #[Test]
    public function disetujui_adalah_status_buntu(): void
    {
        $this->assertSame([], StatusTagihan::Disetujui->berikutnya());
    }

    #[Test]
    public function graf_transisi_berisi_tujuh_sisi(): void
    {
        $sisi = collect(StatusTagihan::cases())
            ->sum(fn (StatusTagihan $s) => count($s->berikutnya()));

        // Tujuh, bukan delapan: `disetujui` adalah status buntu, dan
        // `dikembalikan` hanya punya satu jalan keluar.
        $this->assertSame(7, $sisi);
    }

    #[Test]
    public function hanya_dikembalikan_yang_menuntut_catatan(): void
    {
        $butuh = collect(StatusTagihan::cases())
            ->filter(fn (StatusTagihan $s) => $s->butuhCatatan())
            ->map(fn (StatusTagihan $s) => $s->value)->values()->all();

        $this->assertSame(['dikembalikan'], $butuh);
    }
}
