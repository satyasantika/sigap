<?php

namespace App\Services;

use App\Enums\LevelSyaratPerlu;
use App\Models\Elemen;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\StatusSyaratPerlu;
use App\Models\User;
use App\Support\Na\HasilNa;
use App\Support\Na\Skenario;

/**
 * Nilai Akreditasi: NA = Σ (skor × bobot), dengan Σbobot = 100.
 *
 * Elemen tanpa penilaian diandaikan berskor 3 — "memenuhi standar". Asumsi itu
 * masuk akal, tetapi HARUS terlihat: service ini selalu mengembalikan cacah
 * elemen yang belum dinilai, dan antarmuka wajib menampilkannya.
 *
 * Penentuan statusnya mengikuti PERSIS tabel di
 * vibecoding/docs/03-perhitungan.md, termasuk kasus yang paling mudah keliru:
 * NA 361 ke atas TANPA syarat perlu tetap berujung "Terakreditasi 5 tahun",
 * bukan "Unggul". Inilah sebabnya ubin progres tidak boleh dipisahkan dari
 * ubin gerbang — progres 90% tanpa syarat perlu tidak menghasilkan Unggul.
 */
class KalkulatorNa
{
    public const SKOR_BAWAAN = 3;

    /**
     * @param  Skenario|null  $skenario  pengandaian simulasi; null berarti apa adanya.
     */
    public function hitung(Periode $periode, ?User $penilai = null, ?Skenario $skenario = null): HasilNa
    {
        $elemen = Elemen::get(['id', 'bobot']);

        $skor = Penilaian::where('periode_id', $periode->id)
            ->when($penilai !== null, fn ($q) => $q->where('penilai_id', $penilai->getKey()))
            ->pluck('skor', 'elemen_id');

        // Skor pengandaian menimpa skor sungguhan HANYA di dalam perhitungan
        // ini. Tidak ada tulisan ke tabel `penilaian` di sepanjang jalur ini,
        // dan tidak boleh ada.
        foreach ($skenario?->skor ?? [] as $elemenId => $nilai) {
            $skor[$elemenId] = $nilai;
        }

        $na = 0.0;
        $bobotSkor4 = 0.0;
        $belumDinilai = 0;

        foreach ($elemen as $e) {
            $bobot = (float) $e->bobot;
            $nilai = $skor[$e->id] ?? null;

            if ($nilai !== null) {
                $nilai = (int) $nilai;
            }

            if ($nilai === null) {
                $belumDinilai++;
                $nilai = self::SKOR_BAWAAN;
            }

            $na += $nilai * $bobot;

            if ($nilai === 4) {
                $bobotSkor4 += $bobot;
            }
        }

        $na = round($na, 2);

        [$syarat3, $syarat5] = $this->statusSyaratPerlu($periode, $skenario);
        [$status, $masa] = $this->tentukanStatus($na, $syarat3, $syarat5);

        return new HasilNa(
            na: $na,
            surplus: round($na - 300, 2),
            bobotSkor4: round($bobotSkor4, 2),
            jumlahElemenBelumDinilai: $belumDinilai,
            status: $status,
            masaBerlaku: $masa,
            syarat3: $syarat3,
            syarat5: $syarat5,
        );
    }

    /**
     * Tabel penentuan status, disalin persis dari dokumen 03.
     *
     * Urutannya mengikat — memindahkan satu cabang akan mengubah hasil pada
     * kasus batas, dan kasus batas itulah yang menentukan status sesungguhnya.
     *
     * @return array{string, int}
     */
    private function tentukanStatus(float $na, bool $syarat3, bool $syarat5): array
    {
        if ($na < 200) {
            return ['Tidak Terakreditasi', 0];
        }

        if ($na < 321) {
            return ['Terakreditasi', 5];
        }

        if ($na < 361) {
            return $syarat3 ? ['Unggul', 3] : ['Terakreditasi', 5];
        }

        if ($syarat5) {
            return ['Unggul', 5];
        }

        if ($syarat3) {
            return ['Unggul', 3];
        }

        // NA tertinggi sekalipun tidak menghasilkan Unggul tanpa syarat perlu.
        return ['Terakreditasi', 5];
    }

    /**
     * syarat5 = kelima baris berlevel `lima`.
     * syarat3 = kelimanya berlevel `tiga` atau `lima`.
     *
     * Kelimanya, bukan sebagian. Empat dari lima terpenuhi tetap berarti
     * tidak terpenuhi.
     *
     * @return array{bool, bool}
     */
    private function statusSyaratPerlu(Periode $periode, ?Skenario $skenario = null): array
    {
        $wajib = Elemen::bersyaratPerlu()->pluck('id');

        $level = StatusSyaratPerlu::where('periode_id', $periode->id)
            ->whereIn('elemen_id', $wajib)
            ->pluck('level', 'elemen_id');

        foreach ($skenario?->syaratPerlu ?? [] as $elemenId => $l) {
            $level[$elemenId] = $l;
        }

        $tiga = 0;
        $lima = 0;

        foreach ($wajib as $id) {
            $l = $level[$id] ?? LevelSyaratPerlu::Belum;

            if ($l->memenuhiTiga()) {
                $tiga++;
            }

            if ($l->memenuhiLima()) {
                $lima++;
            }
        }

        $jumlah = $wajib->count();

        return [$tiga === $jumlah, $lima === $jumlah];
    }

    /**
     * Selisih antarpenilai: elemen yang skornya berbeda.
     *
     * Bukan sekadar catatan — elemen yang dinilai 4 oleh satu orang dan 2 oleh
     * yang lain hampir selalu elemen yang buktinya belum meyakinkan, dan itu
     * yang paling berguna diperiksa sebelum asesor datang.
     *
     * @return array<int, array{elemen: Elemen, skor: array<string, int>, rentang: int}>
     */
    public function selisihAntarPenilai(Periode $periode): array
    {
        $penilaian = Penilaian::where('periode_id', $periode->id)
            ->with(['elemen:id,no,nama,bobot', 'penilai:id,nama_lengkap'])
            ->get()
            ->groupBy('elemen_id');

        $selisih = [];

        foreach ($penilaian as $kumpulan) {
            if ($kumpulan->count() < 2) {
                continue;
            }

            $skor = $kumpulan->pluck('skor');

            if ($skor->unique()->count() === 1) {
                continue;
            }

            $selisih[] = [
                'elemen' => $kumpulan->first()->elemen,
                'skor' => $kumpulan->mapWithKeys(
                    fn (Penilaian $p) => [$p->penilai->nama_lengkap => $p->skor]
                )->all(),
                'rentang' => $skor->max() - $skor->min(),
            ];
        }

        // Selisih terbesar lebih dulu: itu yang paling perlu didiskusikan.
        usort($selisih, fn (array $a, array $b) => $b['rentang'] <=> $a['rentang']);

        return $selisih;
    }
}
