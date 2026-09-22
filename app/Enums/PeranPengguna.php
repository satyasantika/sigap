<?php

namespace App\Enums;

/**
 * Enam peran pengguna SIGAP. Nilainya harus sama persis dengan `kode` di
 * data/peran.json dan dengan larik `peran` di data/izin.json — data/verifikasi.py
 * menguji kecocokan itu.
 *
 * Enum ini hanya menyediakan label dan warna untuk antarmuka. Ia TIDAK
 * memutuskan wewenang apa pun: seluruh otorisasi lewat App\Support\Izin.
 */
enum PeranPengguna: string
{
    case Admin = 'admin';
    case Ketua = 'ketua';
    case Pimpinan = 'pimpinan';
    case Koordinator = 'koordinator';
    case Anggota = 'anggota';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator Sistem',
            self::Ketua => 'Ketua Task Force',
            self::Pimpinan => 'Pimpinan',
            self::Koordinator => 'Koordinator Pokja',
            self::Anggota => 'Anggota Pokja',
            self::Auditor => 'Auditor Mutu Internal',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Admin => 'gray',
            self::Ketua => 'success',
            self::Pimpinan => 'info',
            self::Koordinator => 'warning',
            self::Anggota => 'primary',
            self::Auditor => 'danger',
        };
    }

    /** Keterangan singkat batas wewenang, untuk halaman matriks izin. */
    public function ringkas(): string
    {
        return match ($this) {
            self::Admin => 'Kelola pengguna, prodi, dan periode. Bukan isi akreditasi.',
            self::Ketua => 'Pemilik isi akreditasi: menugaskan, mereviu, menyetujui.',
            self::Pimpinan => 'Hanya memantau. Tidak mengubah apa pun.',
            self::Koordinator => 'Mengelola dan mereviu pekerjaan di dalam pokjanya sendiri.',
            self::Anggota => 'Mengerjakan tagihan yang ditugaskan kepadanya.',
            self::Auditor => 'Membaca dan memberi catatan mutu tanpa mengubah isi.',
        };
    }

    /**
     * Ubin dasbor yang relevan bagi peran ini, sesuai tabel di
     * vibecoding/docs/10-kpi-dan-bento.md.
     *
     * Ini pemetaan TAMPILAN, bukan otorisasi: seluruh peran boleh membuka
     * dasbor (`dasbor.lihat` bernilai `ya` untuk keenamnya), dan pimpinan yang
     * melihat K9 bukan lubang keamanan melainkan kebisingan. Karena itu
     * tempatnya di sini bersama label dan warna, bukan di App\Support\Izin —
     * kelas itu khusus untuk keputusan wewenang.
     *
     * Pimpinan tidak melihat K5 dan K9 karena keduanya operasional. Anggota
     * cukup daftar kerjanya sendiri ditambah K2 sebagai konteks.
     *
     * @return array<int, string>
     */
    public function ubinDasbor(): array
    {
        return match ($this) {
            self::Pimpinan => ['K1', 'K2', 'K3', 'K4', 'K6', 'K7', 'K8'],
            self::Auditor => ['K1', 'K2', 'K3', 'K4', 'K6', 'K7', 'K8', 'K9'],
            self::Anggota => ['K2', 'K9'],
            self::Admin, self::Ketua, self::Koordinator => [
                'K1', 'K2', 'K3', 'K4', 'K5', 'K6', 'K7', 'K8', 'K9',
            ],
        };
    }

    /** @return array<string, string> nilai => label, untuk pilihan di formulir. */
    public static function pilihan(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $p) => [$p->value => $p->label()])
            ->all();
    }
}
