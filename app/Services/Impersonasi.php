<?php

namespace App\Services;

use App\Models\ImpersonasiSesi;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Support\Izin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Personifikasi pengguna lain.
 *
 * `vibecoding/docs/08-auth-dan-izin.md` melarang fitur ini; larangan itu
 * dibatalkan keputusan manusia, dan syaratnya pencatatan. Lihat CLAUDE.md
 * bagian 7 butir 8.
 *
 * Selama impersonasi admin memegang wewenang PENUH peran yang ditirunya —
 * termasuk menyetujui tagihan dan memvalidasi bukti. Yang menahan agar
 * keterlacakan tidak hilang bukanlah pagar wewenang, melainkan jejak: id admin
 * asli ikut disimpan pada setiap baris riwayat yang lahir selama sesi ini.
 *
 * Kalau pencatatan ini dilepas, `disetujui_oleh` akan menunjuk ketua padahal
 * admin yang menekan — dan persetujuan akreditasi berhenti bisa
 * dipertanggungjawabkan. Jangan lepaskan.
 */
class Impersonasi
{
    /** Kunci sesi peramban tempat id admin asli disimpan. */
    public const KUNCI_ADMIN = 'impersonasi.admin_id';

    public const KUNCI_SESI = 'impersonasi.sesi_id';

    /** Mulai menyamar sebagai pengguna lain. */
    public function mulai(User $admin, User $target, ?string $alasan = null): ImpersonasiSesi
    {
        $this->pastikanBoleh($admin, $target);

        return DB::transaction(function () use ($admin, $target, $alasan) {
            // Sesi lama yang menggantung ditutup lebih dulu; dua sesi berjalan
            // sekaligus membuat jejaknya ambigu.
            ImpersonasiSesi::masihBerjalan()->where('admin_id', $admin->getKey())
                ->update(['diakhiri_pada' => now()]);

            $sesi = ImpersonasiSesi::create([
                'admin_id' => $admin->getKey(),
                'target_id' => $target->getKey(),
                'alasan' => $alasan,
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);

            session([self::KUNCI_ADMIN => $admin->getKey(), self::KUNCI_SESI => $sesi->getKey()]);

            $this->catat('impersonasi.mulai', $target, $admin, $sesi->getKey(),
                "{$admin->nama_lengkap} mulai menyamar sebagai {$target->nama_lengkap}"
                .($alasan ? ": {$alasan}" : '.'));

            Auth::login($target);
            $this->segarkanHashSandi($target);

            return $sesi;
        });
    }

    /**
     * Keluar dari penyamaran dan kembali menjadi admin.
     *
     * Dipanggil dari tombol khusus DAN dari alur keluar: orang yang menekan
     * "Keluar" saat sedang menyamar hampir selalu bermaksud berhenti menyamar,
     * bukan mengakhiri sesinya sendiri.
     */
    public function akhiri(): ?User
    {
        $adminId = session(self::KUNCI_ADMIN);

        if ($adminId === null) {
            return null;
        }

        $admin = User::find($adminId);
        $target = Auth::user();
        $sesiId = session(self::KUNCI_SESI);

        if ($admin === null) {
            $this->bersihkanSesi();

            return null;
        }

        DB::transaction(function () use ($admin, $target, $sesiId) {
            ImpersonasiSesi::whereKey($sesiId)->update(['diakhiri_pada' => now()]);

            if ($target !== null) {
                $this->catat('impersonasi.akhiri', $target, $admin, $sesiId,
                    "{$admin->nama_lengkap} berhenti menyamar sebagai {$target->nama_lengkap}.");
            }
        });

        $this->bersihkanSesi();
        Auth::login($admin);
        $this->segarkanHashSandi($admin);

        return $admin;
    }

    public function sedangBerlangsung(): bool
    {
        return session()->has(self::KUNCI_ADMIN);
    }

    public function adminAsli(): ?User
    {
        $id = session(self::KUNCI_ADMIN);

        return $id === null ? null : User::find($id);
    }

    public function sesiBerjalan(): ?ImpersonasiSesi
    {
        $id = session(self::KUNCI_SESI);

        return $id === null ? null : ImpersonasiSesi::find($id);
    }

    /**
     * Id admin di balik layar, atau null bila tindakan ini bukan impersonasi.
     *
     * Inilah yang disimpan ke kolom `impersonasi_oleh` pada setiap tabel
     * riwayat. Dipanggil dari AlurTagihan, PengelolaNarasi, PengelolaBukti,
     * dan mana pun yang menulis jejak.
     */
    public function adminDiBalikLayar(): ?string
    {
        return session(self::KUNCI_ADMIN);
    }

    /** Mencatat satu tindakan ke log aktivitas. */
    public function catat(
        string $aksi,
        ?User $pelaku = null,
        ?User $admin = null,
        ?string $sesiId = null,
        ?string $keterangan = null,
        ?object $subjek = null,
        array $konteks = [],
    ): ?LogAktivitas {
        $pelaku ??= Auth::user();

        if ($pelaku === null) {
            return null;
        }

        return LogAktivitas::create([
            'user_id' => $pelaku->getKey(),
            'impersonasi_oleh' => $admin?->getKey() ?? $this->adminDiBalikLayar(),
            'impersonasi_sesi_id' => $sesiId ?? session(self::KUNCI_SESI),
            'aksi' => $aksi,
            'subjek_tipe' => $subjek?->getMorphClass(),
            'subjek_id' => $subjek?->getKey(),
            'keterangan' => $keterangan,
            'konteks' => $konteks ?: null,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Siapa saja yang boleh ditiru.
     *
     * Yang dikecualikan bukan "peran admin" melainkan siapa pun yang sendirinya
     * memegang `impersonasi.mulai`. Rumusannya sengaja begitu: aturan 10
     * melarang perbandingan peran di luar kelas Izin, dan bentuk ini tetap
     * benar bila suatu hari wewenang itu diberikan ke peran lain.
     *
     * @return Collection<int, User>
     */
    public function targetTersedia(User $admin): Collection
    {
        return User::bisaDitugaskan()
            ->whereKeyNot($admin->getKey())
            ->orderBy('nama_lengkap')
            ->get()
            ->reject(fn (User $u) => Izin::bolehSistem($u, 'impersonasi.mulai'))
            ->values();
    }

    private function pastikanBoleh(User $admin, User $target): void
    {
        if (! Izin::bolehSistem($admin, 'impersonasi.mulai')) {
            throw new RuntimeException('Anda tidak berwenang menyamar sebagai pengguna lain.');
        }

        if ($target->getKey() === $admin->getKey()) {
            throw new RuntimeException('Tidak bisa menyamar sebagai diri sendiri.');
        }

        if (Izin::bolehSistem($target, 'impersonasi.mulai')) {
            throw new RuntimeException(
                'Tidak bisa menyamar sebagai pengguna yang juga berwenang menyamar. '
                .'Dua wewenang pengelolaan sistem yang saling menyamar membuat jejaknya '
                .'berputar tanpa menambah kemampuan apa pun.'
            );
        }

        if (! $target->aktif) {
            throw new RuntimeException('Pengguna ini dinonaktifkan dan tidak bisa ditiru.');
        }

        // Akun demo hanya melihat periode demonya. Menyamarinya tidak
        // memperlihatkan apa pun tentang sistem sungguhan, dan jejaknya
        // menggantung begitu demonya dibuang.
        if ($target->akunDemo()) {
            throw new RuntimeException('Akun demo tidak bisa ditiru. Masuklah ke demonya langsung.');
        }

        if ($this->sedangBerlangsung()) {
            throw new RuntimeException('Sudah ada penyamaran yang berjalan. Akhiri dulu yang sekarang.');
        }
    }

    /**
     * Menyelaraskan sidik sandi yang disimpan `AuthenticateSession`.
     *
     * Middleware itu membandingkan hash sandi pengguna yang sedang masuk dengan
     * salinan di sesi, dan mengeluarkan siapa pun yang tidak cocok. Berganti
     * pengguna di tengah permintaan meninggalkan salinan milik pengguna lama,
     * jadi permintaan BERIKUTNYA akan menendang keluar orang yang baru saja
     * mulai menyamar. Menyegarkannya di sini membuat impersonasi tidak
     * bergantung pada middleware mana yang kebetulan berjalan — termasuk pada
     * permintaan Livewire, yang tidak memakai tumpukan middleware panel.
     */
    private function segarkanHashSandi(User $u): void
    {
        session()->put('password_hash_'.Auth::getDefaultDriver(), $u->getAuthPassword());
    }

    private function bersihkanSesi(): void
    {
        session()->forget([self::KUNCI_ADMIN, self::KUNCI_SESI]);
    }
}
