<?php

namespace App\Policies;

use App\Models\Tagihan;
use App\Models\User;
use App\Support\Izin;

/**
 * Seluruh keputusan diteruskan ke App\Support\Izin; tidak ada perbandingan
 * peran di sini.
 *
 * Lingkup yang berlaku, langsung dari data/izin.json:
 *   koordinator  `pokjanya`  — hanya tagihan di pokjanya sendiri
 *   anggota      `miliknya`  — hanya tagihan yang ditugaskan kepadanya
 *   pimpinan     membaca saja
 *   auditor      membaca saja
 *
 * Perhatikan koordinator BISA mereviu tetapi TIDAK bisa menyetujui:
 * `tagihan.setujui` bernilai `tidak` untuknya. Persetujuan akhir hanya ketua.
 */
class TagihanPolicy extends BasePolicy
{
    protected function awalan(): string
    {
        return 'tagihan';
    }

    protected function peta(): array
    {
        return [
            'viewAny' => 'tagihan.lihat',
            'view' => 'tagihan.lihat',
            'create' => 'tagihan.buat',
            'update' => 'tagihan.tugaskan',
            'delete' => 'tagihan.buat',
            'restore' => 'tagihan.buat',
            'forceDelete' => 'tagihan.buat',
        ];
    }

    public function tugaskan(User $u, Tagihan $t): bool
    {
        return Izin::boleh($u, 'tagihan.tugaskan', $t);
    }

    public function ubahTenggat(User $u, Tagihan $t): bool
    {
        return Izin::boleh($u, 'tagihan.ubah_tenggat', $t);
    }

    public function ajukan(User $u, Tagihan $t): bool
    {
        return Izin::boleh($u, 'tagihan.ajukan', $t);
    }

    public function reviu(User $u, Tagihan $t): bool
    {
        return Izin::boleh($u, 'tagihan.reviu', $t);
    }

    public function setujui(User $u, Tagihan $t): bool
    {
        return Izin::boleh($u, 'tagihan.setujui', $t);
    }

    public function kembalikan(User $u, Tagihan $t): bool
    {
        return Izin::boleh($u, 'tagihan.kembalikan', $t);
    }

    public function komentari(User $u, Tagihan $t): bool
    {
        return Izin::boleh($u, 'komentar.tulis', $t);
    }
}
