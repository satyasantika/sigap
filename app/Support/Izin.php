<?php

namespace App\Support;

use App\Models\Izin as ModelIzin;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

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

    /**
     * Wewenang PENGELOLAAN SISTEM: impersonasi dan simulasi.
     *
     * Matriks terpisah (`data/izin-sistem.json`) karena jumlah sel di
     * `izin.json` dipatok 24 x 6 = 144 dan dijaga `data/verifikasi.py`;
     * menambah aksi di sana akan memecah angka itu. Yang penting tetap
     * terjaga: keputusannya lahir di kelas ini, bukan dari perbandingan peran
     * yang berserakan di Resource atau Blade (AGENTS.md aturan 10).
     *
     * Tidak mengenal lingkup `pokjanya`/`miliknya` dan tidak tunduk penguncian
     * periode — mengelola sistem justru yang dibutuhkan saat periode terkunci.
     */
    public static function bolehSistem(User $u, string $aksi): bool
    {
        if (! $u->aktif) {
            return false;
        }

        return (self::matriksSistem()[$aksi][$u->peran->value] ?? 'tidak') === 'ya';
    }

    /**
     * Matriks sistem sebagai [aksi][peran] => ya|tidak.
     *
     * Dibaca langsung dari berkas, bukan dari tabel: isinya hanya menyangkut
     * wewenang admin atas sistem, tidak pernah disunting lewat antarmuka, dan
     * menyemainya ke tabel `izin` akan merusak hitungan 144 baris.
     *
     * @return array<string, array<string, string>>
     */
    public static function matriksSistem(): array
    {
        return Cache::store('array')->rememberForever('sigap.izin.matriks_sistem', function () {
            $isi = self::bacaBerkas('data/izin-sistem.json');
            $keluar = [];

            foreach ($isi['aksi'] ?? [] as $aksi) {
                foreach ($isi['peran'] ?? [] as $peran) {
                    $nilai = $aksi[$peran] ?? null;

                    // Sel kosong ditolak keras. Diam-diam menganggapnya 'tidak'
                    // menyembunyikan berkas yang rusak sampai ada yang mengeluh
                    // tombolnya hilang.
                    if (! in_array($nilai, ['ya', 'tidak'], true)) {
                        throw new RuntimeException(
                            "Nilai izin sistem tidak sah pada {$aksi['kode']}.{$peran}: ".var_export($nilai, true)
                        );
                    }

                    $keluar[$aksi['kode']][$peran] = $nilai;
                }
            }

            return $keluar;
        });
    }

    /**
     * Apakah sebuah menu MUNCUL bagi pengguna ini.
     *
     * Terpisah dari `boleh()`, dan pemisahan itu penting: menu menyembunyikan,
     * ia tidak menolak. Admin memegang `tagihan.lihat` = ya sehingga berhak
     * membuka layar tagihan lewat tautan langsung, tetapi menu "Semua Tagihan"
     * tidak muncul baginya karena admin tidak mengurus isi akreditasi.
     *
     * Kalau keduanya disatukan, ada dua akibat buruk sekaligus: menyembunyikan
     * menu berubah menjadi pagar otorisasi (aturan 7 melarangnya — pagar ada di
     * Policy, bukan di tombol yang disembunyikan), dan mengubah tampilan menu
     * ikut mengubah siapa yang boleh berbuat apa.
     *
     * Tabelnya `data/menu.json`, diturunkan dari
     * vibecoding/docs/05-layar-dan-widget.md.
     */
    public static function bolehMenu(User $u, string $menu): bool
    {
        if (! $u->aktif) {
            return false;
        }

        return match (self::matriksMenu()[$menu][$u->peran->value] ?? 'tidak') {
            'ya' => true,
            'pokja_data' => $u->anggotaPokjaData(),
            default => false,
        };
    }

    /**
     * Matriks menu sebagai [menu][peran] => ya|tidak|pokja_data.
     *
     * @return array<string, array<string, string>>
     */
    public static function matriksMenu(): array
    {
        return Cache::store('array')->rememberForever('sigap.izin.matriks_menu', function () {
            $isi = self::bacaBerkas('data/menu.json');
            $keluar = [];

            foreach ($isi['menu'] ?? [] as $menu) {
                foreach ($isi['peran'] ?? [] as $peran) {
                    $nilai = $menu[$peran] ?? null;

                    if (! in_array($nilai, ['ya', 'tidak', 'pokja_data'], true)) {
                        throw new RuntimeException(
                            "Nilai menu tidak sah pada {$menu['kode']}.{$peran}: ".var_export($nilai, true)
                        );
                    }

                    $keluar[$menu['kode']][$peran] = $nilai;
                }
            }

            return $keluar;
        });
    }

    /** Label dan grup tiap menu, untuk halaman matriks. */
    public static function daftarMenu(): array
    {
        return collect(self::bacaBerkas('data/menu.json')['menu'] ?? [])
            ->mapWithKeys(fn (array $m) => [$m['kode'] => ['label' => $m['label'], 'grup' => $m['grup']]])
            ->all();
    }

    /** Label manusiawi tiap aksi sistem, untuk halaman matriks. */
    public static function labelSistem(): array
    {
        return collect(self::bacaBerkas('data/izin-sistem.json')['aksi'] ?? [])
            ->pluck('label', 'kode')
            ->all();
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
        Cache::store('array')->forget('sigap.izin.matriks_sistem');
        Cache::store('array')->forget('sigap.izin.matriks_menu');
    }

    /**
     * Membaca berkas matriks di `data/`, gagal keras bila hilang.
     *
     * Diam-diam menganggapnya kosong berarti seluruh menu lenyap dan seluruh
     * wewenang sistem ditolak, tanpa satu pun pesan yang menjelaskan mengapa.
     */
    private static function bacaBerkas(string $relatif): array
    {
        $berkas = base_path($relatif);

        if (! is_file($berkas)) {
            throw new RuntimeException("{$relatif} tidak ditemukan di {$berkas}.");
        }

        return json_decode(file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);
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
