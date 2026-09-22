<?php

namespace App\Services;

use App\Enums\JenisTagihan;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Tagihan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membangkitkan seluruh tagihan untuk satu periode dari instrumen.
 *
 * Pembagian bobot adalah bagian yang paling mudah salah. Bobot satu elemen
 * dibagi rata ke tagihan `narasi` dan `bukti` miliknya:
 *
 *   elemen tanpa bukti_pendukung  -> 1 tagihan narasi, memikul bobot penuh
 *   elemen dengan bukti_pendukung -> 2 tagihan, masing-masing setengah bobot
 *
 * Setengah dari 1,25 adalah 0,625 — itu sebabnya kolomnya decimal(5,3), bukan
 * decimal(5,2). Dengan dua desimal, 0,625 dibulatkan menjadi 0,63 dan jumlah
 * seluruh periode meleset dari 100,000.
 *
 * Sisa pembagian dititipkan ke tagihan terakhir elemen itu supaya jumlah per
 * elemen tetap persis sama dengan bobot elemennya. Tanpa itu, 59 pembulatan
 * kecil menumpuk menjadi selisih yang terlihat di dasbor.
 */
class PembangkitTagihan
{
    /**
     * @return array{narasi: int, bukti: int, data_dkps: int, dilewati: int}
     */
    public function untuk(Periode $periode): array
    {
        $pokja = Pokja::where('periode_id', $periode->id)->pluck('id', 'kode');

        if ($pokja->isEmpty()) {
            throw new RuntimeException("Periode {$periode->nama} belum punya pokja.");
        }

        $pokjaData = $pokja['POKJA-DATA'] ?? null;

        if ($pokjaData === null) {
            throw new RuntimeException('POKJA-DATA tidak ditemukan; tagihan DKPS tidak punya pemilik.');
        }

        $hasil = ['narasi' => 0, 'bukti' => 0, 'data_dkps' => 0, 'dilewati' => 0];

        DB::transaction(function () use ($periode, $pokja, $pokjaData, &$hasil) {
            foreach (Elemen::orderBy('no')->get() as $elemen) {
                $pokjaId = $pokja[$elemen->pokja_kode] ?? null;

                if ($pokjaId === null) {
                    throw new RuntimeException(
                        "Elemen {$elemen->no} menunjuk pokja `{$elemen->pokja_kode}` yang tidak ada di periode ini."
                    );
                }

                $hasil = $this->tagihanElemen($periode, $elemen, $pokjaId, $hasil);
            }

            foreach (DkpsButir::orderBy('no')->get() as $butir) {
                $dibuat = $this->buat($periode, [
                    'pokja_id' => $pokjaData,
                    'dkps_butir_id' => $butir->id,
                    'jenis' => JenisTagihan::DataDkps,
                    'judul' => "DKPS {$butir->label_tabel}: {$butir->nama}",
                    'deskripsi' => $butir->keterangan,
                    // Berbobot nol: pekerjaannya nyata, tetapi bobotnya sudah
                    // terhitung lewat elemen yang dilayaninya.
                    'bobot_terkait' => 0,
                    'urutan' => $butir->no,
                ]);

                $dibuat ? $hasil['data_dkps']++ : $hasil['dilewati']++;
            }
        });

        $this->pastikanBobotUtuh($periode);

        return $hasil;
    }

    /**
     * @param  array{narasi: int, bukti: int, data_dkps: int, dilewati: int}  $hasil
     * @return array{narasi: int, bukti: int, data_dkps: int, dilewati: int}
     */
    private function tagihanElemen(Periode $periode, Elemen $elemen, string $pokjaId, array $hasil): array
    {
        $jenis = [JenisTagihan::Narasi];

        if (filled($elemen->bukti_pendukung)) {
            $jenis[] = JenisTagihan::Bukti;
        }

        $bobotElemen = (float) $elemen->bobot;
        $porsi = round($bobotElemen / count($jenis), 3);

        foreach ($jenis as $i => $j) {
            $terakhir = $i === count($jenis) - 1;

            // Tagihan terakhir memikul sisanya, supaya jumlah per elemen persis
            // sama dengan bobot elemen meski pembagiannya tidak bulat.
            $bobot = $terakhir
                ? round($bobotElemen - ($porsi * $i), 3)
                : $porsi;

            $dibuat = $this->buat($periode, [
                'pokja_id' => $pokjaId,
                'elemen_id' => $elemen->id,
                'jenis' => $j,
                'judul' => $j === JenisTagihan::Narasi
                    ? "Naskah LED E{$elemen->no}: {$elemen->nama}"
                    : "Bukti E{$elemen->no}: {$elemen->nama}",
                'deskripsi' => $j === JenisTagihan::Narasi
                    ? $elemen->panduan
                    : $elemen->bukti_pendukung,
                'bobot_terkait' => $bobot,
                'urutan' => $elemen->no,
                // Elemen syarat perlu tidak boleh tenggelam di antara 146
                // tagihan lain: kelimanya menentukan status Unggul.
                'prioritas' => $elemen->syarat_perlu ? 'kritis' : 'biasa',
            ]);

            $dibuat ? $hasil[$j->value]++ : $hasil['dilewati']++;
        }

        return $hasil;
    }

    /** @return bool true bila baris baru dibuat, false bila sudah ada. */
    private function buat(Periode $periode, array $atribut): bool
    {
        $kunci = [
            'periode_id' => $periode->id,
            'jenis' => $atribut['jenis'],
            'elemen_id' => $atribut['elemen_id'] ?? null,
            'dkps_butir_id' => $atribut['dkps_butir_id'] ?? null,
        ];

        if (Tagihan::where($kunci)->exists()) {
            return false;
        }

        Tagihan::create($kunci + $atribut + ['prodi_id' => $periode->prodi_id]);

        return true;
    }

    /**
     * Pagar terakhir: jumlah bobot seluruh tagihan periode ini harus 100,000.
     *
     * Diperiksa di sini, bukan hanya di uji, supaya periode yang bobotnya
     * meleset tidak sempat dipakai orang. Progres dihitung dari bobot, jadi
     * selisih di sini muncul sebagai persentase yang salah di dasbor ketua.
     */
    private function pastikanBobotUtuh(Periode $periode): void
    {
        $total = (float) Tagihan::where('periode_id', $periode->id)->sum('bobot_terkait');

        if (abs($total - 100.0) > 0.0005) {
            throw new RuntimeException(
                "Jumlah bobot_terkait periode {$periode->nama} adalah {$total}, seharusnya 100,000."
            );
        }
    }
}
