<?php

namespace App\Services;

use App\Models\DkpsBaris;
use App\Models\User;
use RuntimeException;

/**
 * Verifikasi baris DKPS.
 *
 * Aturan pokok 3 di vibecoding/docs/07-bukti-dan-tautan-drive.md: baris DKPS
 * tidak bisa diverifikasi tanpa sekurangnya satu bukti yang TERBUKA sekaligus
 * SAH. Keduanya, bukan salah satu — bukti yang sah tetapi tidak bisa dibuka
 * asesor sama tidak bergunanya dengan bukti yang terbuka tetapi palsu.
 */
class VerifikatorDkps
{
    public function verifikasi(DkpsBaris $baris, User $oleh): DkpsBaris
    {
        $alasan = $this->alasanBelumBisaDiverifikasi($baris);

        if ($alasan !== []) {
            throw new RuntimeException(implode(' ', $alasan));
        }

        $baris->forceFill([
            'diverifikasi_oleh' => $oleh->getKey(),
            'diverifikasi_pada' => now(),
        ])->save();

        return $baris->refresh();
    }

    /** @return array<int, string> */
    public function alasanBelumBisaDiverifikasi(DkpsBaris $baris): array
    {
        $bukti = $baris->bukti;

        if ($bukti->isEmpty()) {
            return ['Baris ini belum punya bukti tertaut. Angka tanpa bukti tidak bisa dinilai asesor.'];
        }

        // Cukup SATU bukti yang kedua sumbunya hijau.
        if ($bukti->contains(fn ($b) => $b->layakDipakai())) {
            return [];
        }

        // Tidak ada yang layak: sebutkan alasannya satu per satu, jangan
        // digabung menjadi "buktinya bermasalah".
        return $bukti->flatMap(fn ($b) => $b->alasanBelumLayak())->all();
    }

    /** Membatalkan verifikasi — dicatat, bukan dihapus diam-diam. */
    public function batalkan(DkpsBaris $baris): DkpsBaris
    {
        $baris->forceFill(['diverifikasi_oleh' => null, 'diverifikasi_pada' => null])->save();

        return $baris->refresh();
    }
}
