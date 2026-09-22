<?php

namespace App\Services;

use App\Enums\JenisTagihan;
use App\Enums\StatusTagihan;
use App\Exceptions\TransisiTidakSah;
use App\Models\Tagihan;
use App\Models\TagihanRiwayat;
use App\Models\User;
use App\Notifications\TagihanDikembalikan;
use App\Support\Izin;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA pintu perpindahan status tagihan.
 *
 * Tidak boleh ada `$tagihan->status = ...` di tempat lain mana pun — kolomnya
 * bahkan tidak fillable. Alasannya: setiap perpindahan wajib memeriksa jalur,
 * memeriksa wewenang, dan menulis satu baris riwayat. Perpindahan yang lolos
 * tanpa ketiganya membuat jejak auditnya bolong, dan tidak ada cara mengetahui
 * di mana bolongnya.
 *
 * Wewenang tidak diperiksa dengan membandingkan peran di sini, melainkan
 * dengan menanyakan App\Support\Izin — lihat AGENTS.md aturan 10.
 */
class AlurTagihan
{
    public function __construct(private readonly PengelolaNarasi $narasi = new PengelolaNarasi) {}

    public function pindah(Tagihan $t, StatusTagihan $ke, User $oleh, ?string $catatan = null): void
    {
        $dari = $t->status;

        // 1. Jalurnya ada di tabel alur?
        if (! $dari->bolehKe($ke)) {
            throw TransisiTidakSah::tidakAdaJalur($dari, $ke);
        }

        // 2. Orang ini berwenang?
        if (! Izin::boleh($oleh, $ke->aksiIzin(), $t)) {
            throw TransisiTidakSah::tidakBerwenang($ke);
        }

        // 3. Syarat tambahan per jalur.
        $this->periksaSyarat($t, $dari, $ke, $oleh, $catatan);

        DB::transaction(function () use ($t, $dari, $ke, $oleh, $catatan) {
            $isian = ['status' => $ke];

            if ($ke === StatusTagihan::Disetujui) {
                $isian['disetujui_oleh'] = $oleh->getKey();
                $isian['disetujui_pada'] = now();
            }

            // Menyetujui lalu mengembalikan harus menghapus jejak persetujuan
            // lama, kalau tidak dasbor akan menghitungnya sebagai selesai.
            if ($dari === StatusTagihan::Disetujui || $ke === StatusTagihan::Dikembalikan) {
                $isian['disetujui_oleh'] = null;
                $isian['disetujui_pada'] = null;
            }

            $t->forceFill($isian)->save();

            // Selalu, tanpa kecuali.
            TagihanRiwayat::create([
                'tagihan_id' => $t->getKey(),
                'user_id' => $oleh->getKey(),
                'status_dari' => $dari,
                'status_ke' => $ke,
                'catatan' => $catatan,
            ]);
        });

        if ($ke === StatusTagihan::Dikembalikan && $t->penanggungJawab !== null) {
            $t->penanggungJawab->notify(new TagihanDikembalikan($t, $oleh, $catatan));
        }
    }

    private function periksaSyarat(
        Tagihan $t,
        StatusTagihan $dari,
        StatusTagihan $ke,
        User $oleh,
        ?string $catatan,
    ): void {
        // Mengembalikan tanpa alasan membuat penanggung jawab menebak-nebak.
        if ($ke->butuhCatatan() && blank($catatan)) {
            throw TransisiTidakSah::catatanWajib();
        }

        // Tidak bisa mulai dikerjakan kalau belum ada yang bertanggung jawab.
        if ($ke === StatusTagihan::Dikerjakan && $t->penanggung_jawab_id === null) {
            throw TransisiTidakSah::belumPunyaPenanggungJawab();
        }

        // Narasi harus layak sebelum diajukan: 200-600 kata dan elemennya
        // punya bukti tertaut. Pemeriksaan bukti baru aktif setelah tahap 4;
        // sampai saat itu PengelolaNarasi hanya memeriksa cacah kata.
        if ($ke === StatusTagihan::Diajukan && $t->jenis === JenisTagihan::Narasi) {
            $alasan = $this->narasi->alasanBelumLayak($t);

            if ($alasan !== []) {
                throw TransisiTidakSah::narasiBelumLayak($alasan);
            }
        }
    }

    /**
     * Status yang boleh dituju pengguna ini dari posisi sekarang.
     * Dipakai antarmuka untuk menampilkan tombol; Policy tetap memeriksa ulang.
     *
     * @return array<int, StatusTagihan>
     */
    public function tujuanTersedia(Tagihan $t, User $oleh): array
    {
        return array_values(array_filter(
            $t->status->berikutnya(),
            fn (StatusTagihan $ke) => Izin::boleh($oleh, $ke->aksiIzin(), $t),
        ));
    }
}
