<?php

namespace App\Services;

use App\Models\Bukti;
use App\Models\ImporBatch;
use App\Models\Periode;
use App\Models\User;
use App\Support\Impor\PratinjauImpor;
use App\Support\Impor\ProfilImpor;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan impor dalam SATU transaksi.
 *
 * Satu baris gagal berarti tidak ada yang tersimpan, dan pesannya menyebut
 * nomor barisnya. Impor separuh jalan jauh lebih buruk daripada impor yang
 * gagal seluruhnya: orang tidak tahu sampai mana yang masuk, lalu menempel
 * ulang seluruhnya dan menghasilkan duplikat.
 */
class PelaksanaImpor
{
    /**
     * @param  array<int, array{no: int, data: array<string, mixed>, status: string, id_lama: ?string}>  $pratinjau
     * @param  array<int, string>  $keputusan  nomor baris => impor|lewati|perbarui
     */
    public function jalankan(
        array $pratinjau,
        array $keputusan,
        ProfilImpor $profil,
        Periode $periode,
        User $oleh,
    ): ImporBatch {
        return DB::transaction(function () use ($pratinjau, $keputusan, $profil, $periode, $oleh) {
            $batch = ImporBatch::create([
                'periode_id' => $periode->id,
                'profil' => $profil->nama(),
                'dijalankan_oleh' => $oleh->getKey(),
                'jumlah_baris' => count($pratinjau),
            ]);

            $impor = $lewati = $perbarui = 0;
            $sebelum = [];
            $dibuat = [];

            foreach ($pratinjau as $baris) {
                $pilihan = $keputusan[$baris['no']] ?? $this->bawaan($baris['status']);

                if ($pilihan === 'lewati' || $baris['status'] === PratinjauImpor::GALAT) {
                    $lewati++;

                    continue;
                }

                if ($pilihan === 'perbarui' && $baris['id_lama'] !== null) {
                    $lama = $profil->cariYangAda($profil->kunciDuplikat($baris['data']), $periode->id);

                    if ($lama !== null) {
                        // Nilai lama disimpan supaya "Batalkan impor" bisa
                        // mengembalikannya. Dokumen 09 menuntut pembatalan itu
                        // tetapi tidak menyebut di mana nilai lamanya disimpan.
                        // getRawOriginal, BUKAN getOriginal: yang kedua
                        // menerapkan cast, sehingga tanggal keluar sebagai
                        // Carbon lalu tersimpan di json sebagai ISO-8601
                        // ("2026-06-22T00:00:00.000000Z") — dan MariaDB
                        // menolaknya saat dikembalikan.
                        $sebelum[] = ['id' => $lama->getKey(), 'nilai' => $lama->getRawOriginal()];
                        $profil->perbarui($lama, $baris['data'], $batch);
                        $perbarui++;

                        continue;
                    }
                }

                $baru = $profil->simpan($baris['data'], $batch);
                // Id baris yang DIBUAT dicatat terpisah dari yang diperbarui:
                // keduanya sama-sama membawa impor_batch_id, dan pembatalan
                // yang menghapus berdasarkan batch saja akan ikut menghapus
                // baris lama yang hanya diperbarui.
                $dibuat[] = [
                    'id' => $baru->getKey(),
                    'disentuh' => $baru->updated_at?->format('Y-m-d H:i:s.v'),
                ];
                $impor++;
            }

            $batch->update([
                'jumlah_impor' => $impor,
                'jumlah_lewati' => $lewati,
                'jumlah_perbarui' => $perbarui,
                'ringkasan' => ['dibuat' => $dibuat, 'sebelum' => $sebelum],
            ]);

            return $batch->refresh();
        });
    }

    /**
     * Membatalkan impor: baris yang dibuat di-soft-delete, baris yang
     * diperbarui dikembalikan ke nilai sebelumnya.
     */
    public function batalkan(ImporBatch $batch, ProfilImpor $profil, User $oleh): void
    {
        if ($batch->dibatalkan()) {
            return;
        }

        DB::transaction(function () use ($batch, $oleh) {
            // HANYA baris yang dibuat impor ini yang dihapus. Baris yang cuma
            // diperbarui juga membawa impor_batch_id, jadi menghapus
            // berdasarkan batch akan ikut menyingkirkan data yang sudah ada
            // sebelum impor berjalan.
            $idDibuat = collect($batch->ringkasan['dibuat'] ?? [])->pluck('id')->all();

            if ($idDibuat !== []) {
                Bukti::whereIn('id', $idDibuat)->delete();
            }

            // withTrashed() penting di sini: tanpa itu, baris yang terlanjur
            // terhapus pada versi sebelumnya tidak akan pernah dikembalikan
            // karena kuerinya menyaringnya lebih dulu.
            foreach ($batch->ringkasan['sebelum'] ?? [] as $catatan) {
                Bukti::withTrashed()->where('id', $catatan['id'])->update(
                    collect($catatan['nilai'])
                        ->only(['judul', 'keterangan', 'tanggal_kejadian', 'sumber'])
                        ->all()
                );
            }

            $batch->update(['dibatalkan_pada' => now(), 'dibatalkan_oleh' => $oleh->getKey()]);
        });
    }

    /**
     * Pembatalan hanya boleh selama belum ada baris hasil impor yang disunting
     * orang lain — membatalkan setelah itu akan menghapus pekerjaan mereka.
     */
    public function bolehDibatalkan(ImporBatch $batch): bool
    {
        if ($batch->dibatalkan()) {
            return false;
        }

        // Dibandingkan terhadap cap waktu yang DICATAT saat impor, bukan
        // terhadap created_at: keduanya sama persis sampai ke detik pada baris
        // yang baru dibuat, sehingga `updated_at > created_at` tidak pernah
        // benar dan pembatalan akan tetap terbuka selamanya.
        foreach ($batch->ringkasan['dibuat'] ?? [] as $catatan) {
            $baris = Bukti::withTrashed()->find($catatan['id']);

            if ($baris === null) {
                continue;
            }

            if ($baris->updated_at?->format('Y-m-d H:i:s.v') !== $catatan['disentuh']) {
                return false;
            }
        }

        return true;
    }

    private function bawaan(string $status): string
    {
        // Duplikat DILEWATI secara bawaan, bukan diperbarui: memperbarui
        // diam-diam menimpa pekerjaan orang lain, dan pilihan antara melewati
        // dan memperbarui selalu keputusan manusia.
        return $status === PratinjauImpor::BARU ? 'impor' : 'lewati';
    }
}
