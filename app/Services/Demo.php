<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Simulasi;
use App\Models\User;
use App\Support\Izin;
use App\Support\LingkupPeriode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demonstrasi hidup: periode latihan yang dibukakan pintunya dengan kode.
 *
 * Bukan jenis simulasi ketiga — ia periode latihan yang sudah ada, ditambah
 * kode akses, masa berlaku, dan akun demo yang lahir serta mati bersamanya.
 *
 * Tiga batas yang menjaganya, dan ketiganya sengaja:
 *
 * 1. **Kode akses, bukan pintu terbuka.** Halaman muka publik di domain
 *    fakultas. Tombol yang langsung membuat sesi berarti sesi sungguhan untuk
 *    siapa pun di internet, dan setiap celah otorisasi berubah menjadi celah
 *    yang bisa dicoba tanpa akun.
 * 2. **Tidak ada akun demo berperan admin.** Admin mengelola SISTEM: pengguna,
 *    prodi, periode. Tidak satu pun dari itu terikat periode, jadi
 *    `LingkupPeriode` tidak bisa mengurungnya — demo admin akan menyunting
 *    pengguna sungguhan. Peran admin dipelajari lewat tur, bukan lewat demo.
 * 3. **Masa berlaku wajib.** Demo yang dibuat sekali lalu dilupakan adalah
 *    pintu yang dibiarkan terbuka bertahun-tahun.
 */
class Demo
{
    /** Kunci sesi peramban tempat id simulasi demo disimpan. */
    public const KUNCI_SESI = 'demo.simulasi_id';

    public const MAKSIMAL_HARI = 90;

    /**
     * Peran yang bisa dicoba lewat demo.
     *
     * Jawabannya dibaca dari matriks `demo.coba` di `data/izin-sistem.json`,
     * bukan dari perbandingan peran di sini. Aturan 10: otorisasi diputuskan di
     * satu tempat, dan "peran mana yang boleh ditempati orang luar" adalah
     * pertanyaan otorisasi.
     *
     * @return array<int, PeranPengguna>
     */
    public static function peranYangBisaDicoba(): array
    {
        return array_values(array_filter(
            PeranPengguna::cases(),
            fn (PeranPengguna $p) => Izin::nilaiSistem('demo.coba', $p->value) === 'ya',
        ));
    }

    /**
     * Membuka demo atas satu periode latihan, dan mengembalikan kodenya.
     *
     * Kode dikembalikan sekali di sini dan juga tersimpan supaya admin bisa
     * membacanya lagi — ia bukan rahasia sekelas kata sandi, melainkan pagar
     * agar demo tidak ditemukan mesin perayap.
     */
    public function buka(User $admin, Simulasi $simulasi, int $hari = 14): string
    {
        $this->pastikanBoleh($admin);

        if ($simulasi->jenis !== 'periode' || $simulasi->periode_sandbox_id === null) {
            throw new RuntimeException(
                'Hanya simulasi berjenis periode latihan yang bisa dibukakan demo. '
                .'Pengandaian skor tidak punya layar untuk dicoba.'
            );
        }

        $hari = max(1, min(self::MAKSIMAL_HARI, $hari));

        return DB::transaction(function () use ($admin, $simulasi, $hari) {
            $kode = $simulasi->kode_demo ?? $this->kodeBaru();

            $simulasi->update([
                'kode_demo' => $kode,
                'demo_berlaku_sampai' => now()->addDays($hari),
            ]);

            $this->siapkanAkun($simulasi);

            app(Impersonasi::class)->catat(
                'demo.buka', $admin,
                keterangan: "Membuka demo \"{$simulasi->nama}\" selama {$hari} hari.",
                subjek: $simulasi,
            );

            return $kode;
        });
    }

    /** Menutup demo: kode dicabut dan seluruh akun demonya dihapus. */
    public function tutup(User $admin, Simulasi $simulasi): void
    {
        $this->pastikanBoleh($admin);

        DB::transaction(function () use ($admin, $simulasi) {
            $this->hapusAkun($simulasi);

            $simulasi->update(['kode_demo' => null, 'demo_berlaku_sampai' => null]);

            app(Impersonasi::class)->catat(
                'demo.tutup', $admin,
                keterangan: "Menutup demo \"{$simulasi->nama}\".",
                subjek: $simulasi,
            );
        });
    }

    /** Demo yang cocok dengan kode ini dan masih berlaku, atau null. */
    public function dariKode(?string $kode): ?Simulasi
    {
        $kode = strtoupper(trim((string) $kode));

        if ($kode === '') {
            return null;
        }

        $simulasi = Simulasi::where('kode_demo', $kode)->first();

        return $simulasi?->demoTerbuka() ? $simulasi : null;
    }

    /**
     * Masuk sebagai salah satu peran demo.
     *
     * Akun demo tidak pernah punya kata sandi yang bisa dipakai orang: ia
     * dibuat acak dan tidak pernah ditampilkan. Satu-satunya jalan masuk
     * adalah lewat kode, dan lewat sini.
     */
    public function masuk(Simulasi $simulasi, PeranPengguna $peran): User
    {
        if (! $simulasi->demoTerbuka()) {
            throw new RuntimeException('Demo ini sudah tidak berlaku.');
        }

        if (Izin::nilaiSistem('demo.coba', $peran->value) !== 'ya') {
            throw new RuntimeException(
                "Peran {$peran->label()} tidak tersedia di demo. Wewenangnya menyentuh data yang "
                .'tidak terikat periode, jadi tidak ada cara mengurungnya di dalam periode '
                .'latihan. Pelajari lewat tur terpandu.'
            );
        }

        $akun = $this->akun($simulasi, $peran);

        Auth::login($akun);
        session()->put(self::KUNCI_SESI, $simulasi->getKey());

        // AuthenticateSession membandingkan hash sandi pengguna yang masuk
        // dengan salinan di sesi. Sama seperti impersonasi, berganti pengguna
        // di tengah permintaan meninggalkan salinan milik pengguna lama.
        session()->put('password_hash_'.Auth::getDefaultDriver(), $akun->getAuthPassword());

        app(Impersonasi::class)->catat(
            'demo.masuk', $akun,
            keterangan: "Masuk demo \"{$simulasi->nama}\" sebagai {$peran->label()}.",
        );

        return $akun;
    }

    /** Sesi yang sedang berjalan ini sesi demo. */
    public function sedangBerjalan(): bool
    {
        return Auth::user()?->akunDemo() === true;
    }

    public function simulasiSesiIni(): ?Simulasi
    {
        $id = session(self::KUNCI_SESI);

        return $id === null ? null : Simulasi::find($id);
    }

    public function keluar(): void
    {
        session()->forget(self::KUNCI_SESI);
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    // --- akun demo -----------------------------------------------------------

    private function siapkanAkun(Simulasi $simulasi): void
    {
        foreach (self::peranYangBisaDicoba() as $peran) {
            $this->akun($simulasi, $peran);
        }
    }

    /** Akun demo untuk satu peran, dibuat bila belum ada. */
    private function akun(Simulasi $simulasi, PeranPengguna $peran): User
    {
        $periode = Periode::withTrashed()->findOrFail($simulasi->periode_sandbox_id);
        $surel = "{$peran->value}@demo-".strtolower($simulasi->kode_demo).'.sigap.invalid';

        $akun = User::withTrashed()->where('email', $surel)->first();

        if ($akun !== null) {
            $akun->restore();
        }

        $akun ??= new User(['email' => $surel]);

        $akun->forceFill([
            'email' => $surel,
            'name' => 'Demo '.$peran->label(),
            'nama_lengkap' => 'Demo '.$peran->label(),
            'peran' => $peran,
            'prodi_id' => $periode->prodi_id,
            'aktif' => true,
            'demo' => true,
            'periode_demo_id' => $periode->getKey(),
            'wajib_ganti_sandi' => false,
            // Sandi acak yang tidak pernah ditampilkan. Satu-satunya jalan
            // masuk adalah lewat kode demo.
            'password' => Hash::make(Str::random(48)),
        ])->save();

        $this->masukkanKePokja($akun, $periode, $peran);

        return $akun->refresh();
    }

    /**
     * Koordinator dan anggota didaftarkan ke pokja, supaya lingkup `pokjanya`
     * dan `miliknya` punya arti di demo. Tanpa ini keduanya melihat layar
     * kosong dan demonya justru menyesatkan.
     */
    private function masukkanKePokja(User $akun, Periode $periode, PeranPengguna $peran): void
    {
        if (! in_array($peran, [PeranPengguna::Koordinator, PeranPengguna::Anggota], true)) {
            return;
        }

        $pokja = LingkupPeriode::paksa(
            $periode,
            fn () => Pokja::where('periode_id', $periode->getKey())
                ->where('kode', 'POKJA-DIK')
                ->first(),
        );

        if ($pokja !== null) {
            $akun->pokja()->syncWithoutDetaching([$pokja->getKey() => ['peran_dalam_pokja' => $peran->value]]);
        }
    }

    private function hapusAkun(Simulasi $simulasi): void
    {
        if ($simulasi->periode_sandbox_id === null) {
            return;
        }

        // Dihapus LUNAK, bukan permanen. Akun demo yang pernah dipakai ditunjuk
        // `log_aktivitas`, dan menghapusnya permanen menabrak kendala kunci
        // asing — sekaligus memutus jejak siapa melakukan apa selama demo.
        // Hapus lunak sudah cukup: penyedia autentikasi Laravel tidak pernah
        // menemukan baris yang ter-soft-delete, jadi pintunya tertutup.
        User::where('demo', true)
            ->where('periode_demo_id', $simulasi->periode_sandbox_id)
            ->get()
            ->each(fn (User $u) => $u->delete());
    }

    private function kodeBaru(): string
    {
        // Tanpa huruf dan angka yang mudah tertukar saat didiktekan lewat
        // telepon: O/0, I/1, L. Kode ini akan dibacakan orang.
        $huruf = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $kode = 'DEMO-'.collect(range(1, 6))
                ->map(fn () => $huruf[random_int(0, strlen($huruf) - 1)])
                ->implode('');
        } while (Simulasi::where('kode_demo', $kode)->exists());

        return $kode;
    }

    private function pastikanBoleh(User $admin): void
    {
        if (! Izin::bolehSistem($admin, 'simulasi.kelola')) {
            throw new RuntimeException('Anda tidak berwenang mengelola demo.');
        }
    }
}
