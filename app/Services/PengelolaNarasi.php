<?php

namespace App\Services;

use App\Models\Narasi;
use App\Models\NarasiVersi;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Naskah LED: penghitungan kata, pemversian, dan kelayakan pengajuan.
 *
 * Cacah kata memakai pemisahan spasi sederhana, dan pemisah YANG SAMA dipakai
 * di seluruh aplikasi — penghitung, penanda 200/600, dan uji. Angkanya hanya
 * berguna kalau semua orang melihat angka yang sama; penghitung yang lebih
 * pintar di satu tempat justru menimbulkan selisih yang membingungkan
 * ("di layar saya 201, kata sistem 199").
 */
class PengelolaNarasi
{
    public const MINIMAL_KATA = 200;

    public const MAKSIMAL_KATA = 600;

    public function jumlahKata(?string $teks): int
    {
        if (blank($teks)) {
            return 0;
        }

        return count(preg_split('/\s+/u', trim(strip_tags($teks)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Menyimpan naskah dan SELALU menulis satu baris versi.
     *
     * Tidak ada penyimpanan yang tidak meninggalkan jejak, termasuk penyimpanan
     * yang isinya sama — kapan seseorang membuka dan menyimpan ulang juga
     * informasi.
     */
    public function simpan(Narasi $narasi, ?string $isi, User $oleh): Narasi
    {
        return DB::transaction(function () use ($narasi, $isi, $oleh) {
            $kata = $this->jumlahKata($isi);

            $narasi->forceFill([
                'isi' => $isi,
                'jumlah_kata' => $kata,
                'penulis_id' => $oleh->getKey(),
            ])->save();

            NarasiVersi::create([
                'narasi_id' => $narasi->getKey(),
                'isi' => $isi,
                'jumlah_kata' => $kata,
                'user_id' => $oleh->getKey(),
            ]);

            // Nomor versi DITURUNKAN dari cacah baris versinya, bukan dinaikkan
            // sendiri. Baris narasi lahir lebih dulu lewat firstOrCreate saat
            // layar dibuka, jadi menaikkan penghitung terpisah membuat "versi 4"
            // padahal baru tiga kali disimpan.
            $narasi->forceFill([
                'versi' => NarasiVersi::where('narasi_id', $narasi->getKey())->count(),
            ])->save();

            return $narasi->refresh();
        });
    }

    /**
     * Daftar alasan naskah ini belum boleh diajukan; larik kosong berarti layak.
     *
     * Sengaja DAFTAR, bukan boolean: orang yang naskahnya ditolak harus tahu
     * semua yang kurang sekaligus, bukan menemukannya satu per satu lewat
     * percobaan berulang.
     *
     * @return array<int, string>
     */
    public function bolehDiajukan(Narasi $narasi): array
    {
        $alasan = [];
        $kata = $narasi->jumlah_kata;

        if ($kata === 0) {
            $alasan[] = 'Naskahnya masih kosong.';
        } elseif ($kata < self::MINIMAL_KATA) {
            $kurang = self::MINIMAL_KATA - $kata;
            $alasan[] = "Baru {$kata} kata, kurang {$kurang} kata dari minimal ".self::MINIMAL_KATA.'.';
        }

        // Lebih dari 600 kata hanya diperingatkan di layar, tidak menghalangi —
        // vibecoding/docs/01-domain-dan-aturan.md bagian "Aturan narasi LED".

        if ($narasi->elemen_id !== null && ! $this->elemenPunyaBukti($narasi)) {
            $alasan[] = 'Elemen ini belum punya bukti tertaut. Klaim tanpa bukti tidak bisa dinilai asesor.';
        }

        return $alasan;
    }

    /**
     * Alasan sebuah TAGIHAN narasi belum layak diajukan.
     *
     * @return array<int, string>
     */
    public function alasanBelumLayak(Tagihan $tagihan): array
    {
        if ($tagihan->elemen_id === null) {
            return [];
        }

        $narasi = Narasi::where('periode_id', $tagihan->periode_id)
            ->where('elemen_id', $tagihan->elemen_id)
            ->first();

        if ($narasi === null) {
            return ['Naskahnya belum pernah ditulis.'];
        }

        return $this->bolehDiajukan($narasi);
    }

    /** Naskah untuk satu elemen di satu periode, dibuat bila belum ada. */
    public function untukElemen(string $periodeId, string $elemenId, string $prodiId): Narasi
    {
        return Narasi::firstOrCreate(
            ['periode_id' => $periodeId, 'elemen_id' => $elemenId],
            ['prodi_id' => $prodiId],
        );
    }

    private function elemenPunyaBukti(Narasi $narasi): bool
    {
        return DB::table('bukti_elemen')
            ->join('bukti', 'bukti.id', '=', 'bukti_elemen.bukti_id')
            ->where('bukti_elemen.elemen_id', $narasi->elemen_id)
            ->where('bukti.periode_id', $narasi->periode_id)
            ->whereNull('bukti.deleted_at')
            ->exists();
    }
}
