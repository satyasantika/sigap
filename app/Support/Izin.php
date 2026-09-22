<?php

namespace App\Support;

use App\Models\Izin as ModelIzin;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * SATU-SATUNYA tempat keputusan otorisasi diambil.
 *
 * Membaca tabel `izin` (144 baris, disemai dari data/izin.json). Policy
 * meneruskan ke sini; tidak ada perbandingan peran di controller, Resource,
 * atau Blade; tidak ada Gate::before; tidak ada peran super.
 * Lihat AGENTS.md aturan 10 dan vibecoding/docs/08-auth-dan-izin.md.
 *
 * Lima nilai lingkup, tidak lebih:
 *   ya          seluruh baris pada periode aktif
 *   tidak       ditolak
 *   pokjanya    baris yang pokjanya sama dengan pokja pengguna
 *   miliknya    baris yang penanggung jawabnya pengguna itu sendiri
 *   pokja_data  hanya bila pengguna terdaftar di POKJA-DATA
 *
 * Obyek null: `pokjanya` dan `miliknya` mengembalikan true — artinya "boleh
 * membuka halaman daftar", dan penyaringan dilakukan di kueri. Lihat
 * CLAUDE.md bagian 7. Konsekuensinya setiap kueri daftar wajib menyaring
 * sendiri; kelas ini bukan lagi satu-satunya pagar untuk halaman daftar.
 */
class Izin
{
    /** Aksi yang tetap berlaku bagi admin walau periode terkunci. */
    public const AKSI_SAAT_TERKUNCI = ['periode.kunci', 'periode.kelola'];

    /** Aksi yang tidak menulis apa pun, jadi tidak tertutup penguncian periode. */
    public const AKSI_BACA = ['dasbor.lihat', 'tagihan.lihat'];

    public static function boleh(User $u, string $aksi, ?Model $obyek = null): bool
    {
        if (! $u->aktif) {
            return false;
        }

        $nilai = self::nilai($aksi, $u->peran->value);

        if ($nilai === null || $nilai === 'tidak') {
            return false;
        }

        // Periode terkunci menutup seluruh penulisan untuk SEMUA peran.
        // Satu-satunya jalan keluar: admin lewat periode.kunci / periode.kelola,
        // supaya periode yang keliru dikunci masih bisa dibuka.
        if (self::periodeTerkunci($obyek) && ! in_array($aksi, self::AKSI_BACA, true)) {
            return $u->peran->value === 'admin'
                && in_array($aksi, self::AKSI_SAAT_TERKUNCI, true);
        }

        return match ($nilai) {
            'ya' => true,
            'pokja_data' => $u->anggotaPokjaData(),
            'pokjanya' => $obyek === null
                ? true
                : in_array($obyek->getAttribute('pokja_id'), $u->idPokjanya(), true),
            'miliknya' => $obyek === null
                ? true
                : $obyek->getAttribute('penanggung_jawab_id') === $u->getKey(),
            default => false,
        };
    }

    /** Nilai mentah satu sel, atau null bila aksinya tidak dikenal. */
    public static function nilai(string $aksi, string $peran): ?string
    {
        return self::matriks()[$aksi][$peran] ?? null;
    }

    /**
     * Seluruh matriks sebagai [aksi][peran] => lingkup.
     *
     * @return array<string, array<string, string>>
     */
    public static function matriks(): array
    {
        return Cache::store('array')->rememberForever('sigap.izin.matriks', function () {
            $keluar = [];
            foreach (ModelIzin::query()->get(['aksi', 'peran', 'nilai']) as $baris) {
                $keluar[$baris->aksi][$baris->peran] = $baris->nilai;
            }

            return $keluar;
        });
    }

    /** Dipanggil seeder dan uji setelah matriks berubah. */
    public static function lupakan(): void
    {
        Cache::store('array')->forget('sigap.izin.matriks');
    }

    /**
     * Periode yang menaungi obyek. Obyek null dianggap tidak terkunci: halaman
     * daftar tetap bisa dibuka, penguncian menggigit saat baris disentuh.
     */
    private static function periodeTerkunci(?Model $obyek): bool
    {
        if ($obyek === null) {
            return false;
        }

        if ($obyek instanceof Periode) {
            return $obyek->terkunci();
        }

        $periodeId = $obyek->getAttribute('periode_id');

        if ($periodeId === null) {
            return false;
        }

        return Periode::query()->whereKey($periodeId)->first()?->terkunci() ?? false;
    }
}
