<?php

namespace App\Support;

use App\Models\Periode;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Batas periode yang boleh dilihat seorang pengguna.
 *
 * Ada dua jenis pengguna dan dua jawaban:
 *
 *   biasa  melihat seluruh periode SUNGGUHAN, tidak pernah periode simulasi.
 *   demo   melihat HANYA satu periode demo miliknya, tidak pernah yang lain.
 *
 * Sebelum kelas ini ada, `periode.simulasi` hanya dikecualikan di
 * `Periode::scopeAktif()` — yang menjaga "periode mana yang sedang berjalan",
 * bukan "baris mana yang muncul di daftar". Akibatnya satu periode latihan
 * membuat ketua melihat 274 tagihan alih-alih 137, bercampur tanpa bisa
 * dibedakan. Fitur yang dimaksudkan sebagai tempat berlatih justru merusak
 * layar kerja sungguhan.
 */
class LingkupPeriode
{
    /** Periode yang dipaksa sementara oleh LingkupPeriode::paksa(). */
    private static ?array $dipaksa = null;

    /**
     * Id periode yang boleh dilihat pengguna yang sedang masuk.
     *
     * `null` berarti tidak ada pembatasan — dipakai saat tidak ada pengguna
     * sama sekali: seeder, perintah artisan, penjadwal. Membatasi di sana
     * akan membuat `tagihan:bangkitkan` gagal mengisi periode yang baru dibuat.
     *
     * @return array<int, string>|null
     */
    public static function idYangBolehDilihat(?User $u = null): ?array
    {
        if (self::$dipaksa !== null) {
            return self::$dipaksa;
        }

        $u ??= Auth::user();

        if ($u === null) {
            return null;
        }

        // Akun demo TIDAK pernah jatuh kembali ke periode sungguhan. Akun demo
        // yang periodenya sudah dibuang adalah akun yatim; ia harus melihat
        // kosong, bukan diam-diam berubah menjadi pengguna biasa.
        if ($u->demo) {
            return $u->periode_demo_id === null ? [] : [$u->periode_demo_id];
        }

        return self::idPeriodeSungguhan();
    }

    /**
     * Menjalankan sepotong kerja pada SATU periode tertentu, apa pun lingkup
     * pengguna yang sedang masuk.
     *
     * Dipakai layanan yang sudah menerima periodenya sebagai parameter dan
     * karena itu tidak perlu dibatasi lagi — membangkitkan tagihan untuk
     * periode latihan yang baru lahir, misalnya. Tanpa ini,
     * `Simulator::buatSandbox()` gagal dengan "periode belum punya pokja",
     * karena pokja yang baru saja ia buat justru tidak terlihat olehnya
     * sendiri.
     *
     * Sengaja berbentuk pembungkus, bukan `withoutGlobalScope` yang ditaburkan
     * di banyak kueri: satu tempat yang bisa dicari, dan batasnya kembali
     * dengan sendirinya walau kerjanya melempar.
     *
     * @template T
     *
     * @param  callable(): T  $kerja
     * @return T
     */
    public static function paksa(Periode|string $periode, callable $kerja): mixed
    {
        $sebelumnya = self::$dipaksa;
        self::$dipaksa = [$periode instanceof Periode ? $periode->getKey() : $periode];

        try {
            return $kerja();
        } finally {
            self::$dipaksa = $sebelumnya;
        }
    }

    /**
     * Id seluruh periode sungguhan, diingat selama satu permintaan.
     *
     * Jumlah periode selalu kecil — satu siklus akreditasi per beberapa tahun —
     * jadi daftar id jauh lebih murah daripada `whereHas` bersarang di setiap
     * kueri daftar.
     *
     * @return array<int, string>
     */
    public static function idPeriodeSungguhan(): array
    {
        return Cache::store('array')->rememberForever(
            'sigap.periode.sungguhan',
            fn () => Periode::withTrashed()->where('simulasi', false)->pluck('id')->all(),
        );
    }

    /** Dipanggil setelah periode dibuat atau dibuang. */
    public static function lupakan(): void
    {
        Cache::store('array')->forget('sigap.periode.sungguhan');
    }
}
