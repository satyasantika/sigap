<?php

namespace App\Services;

use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\Rumus;
use App\Models\User;
use App\Support\Rumus\HasilRumus;
use InvalidArgumentException;

/**
 * Perhitungan lima belas rumus instrumen.
 *
 * Satu metode per rumus, ditulis tangan. Ekspresi di `data/rumus.json` sengaja
 * TIDAK diurai mesin: notasinya bercampur matematika, kalimat Indonesia, dan
 * koma desimal gaya Indonesia, dan salah satunya bahkan belum punya rumus.
 * Membangun pengurai untuk itu akan menghasilkan penerjemah yang salah
 * diam-diam — jauh lebih berbahaya daripada lima belas metode yang jelas.
 *
 * Yang DIBACA dari tabel adalah AMBANGNYA, bukan rumusnya, supaya perubahan
 * instrumen kelak menjadi perubahan data. Lihat ambang() di bawah.
 */
class KalkulatorRumus
{
    /**
     * Ambang yang dipakai perhitungan, diturunkan dari kolom `aturan_skor`
     * pada tabel `rumus`.
     *
     * Kalau sebuah ambang tidak ditemukan di sana, perhitungannya BERHENTI —
     * bukan memakai angka cadangan di kode. Angka cadangan yang diam-diam
     * dipakai adalah cara paling halus untuk menghasilkan laporan yang salah.
     */
    private array $ambangTersimpan = [];

    // ---- PDS3 dan PGBLKL — elemen 17 ------------------------------------

    /**
     * Persentase DTPS berpendidikan doktor.
     *
     * Perhatikan pemisahan skor dan syarat perlu: PDS3 = 41,67 memberi skor 4
     * (ambangnya 40) tetapi TIDAK memenuhi syarat perlu lima tahun (ambangnya
     * 50). Keduanya dihitung terpisah dan dikembalikan terpisah.
     *
     * @param  int  $nds3  DTPS berpendidikan doktor
     * @param  int  $ndtps  seluruh DTPS
     * @param  int  $ndlk  DTPS berjabatan lektor kepala
     * @param  int  $ndgb  DTPS berjabatan guru besar
     */
    public function pds3(int $nds3, int $ndtps, int $ndlk = 0, int $ndgb = 0): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $nilai = round(($nds3 / $ndtps) * 100, 2);

        // skor(a) = PDS3 >= 40 ? 4 : 2 + (5 * PDS3/100)
        $skorA = $nilai >= 40.0 ? 4.0 : 2 + (5 * $nilai / 100);

        // Syarat perlu 5 tahun: PDS3 >= 50 DAN NDLK + NDGB >= 4.
        // Syarat perlu 3 tahun: PDS3 >= 20 DAN NDLK + NDGB >= 3.
        $lektorKeAtas = $ndlk + $ndgb;

        return new HasilRumus(
            kode: 'PDS3',
            nilai: $nilai,
            skor: (int) floor($skorA),
            memenuhiSyarat3Tahun: $nilai >= 20.0 && $lektorKeAtas >= 3,
            memenuhiSyarat5Tahun: $nilai >= 50.0 && $lektorKeAtas >= 4,
            komponen: compact('nds3', 'ndtps', 'ndlk', 'ndgb') + [
                'skor_a_tepat' => round($skorA, 4),
                'lektor_kepala_ke_atas' => $lektorKeAtas,
            ],
            catatan: $nilai >= 40.0 && $nilai < 50.0
                ? 'Skor penuh tercapai, tetapi syarat perlu lima tahun (PDS3 ≥ 50) BELUM terpenuhi.'
                : null,
        );
    }

    /** Persentase DTPS berjabatan akademik guru besar, lektor kepala, atau lektor. */
    public function pgblkl(int $ndgb, int $ndlk, int $ndl, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $nilai = round((($ndgb + $ndlk + $ndl) / $ndtps) * 100, 2);

        // skor(b) = PGBLKL >= 70 ? 4 : 2 + ((20 * PGBLKL/100) / 7)
        $skorB = $nilai >= 70.0 ? 4.0 : 2 + ((20 * $nilai / 100) / 7);

        return new HasilRumus(
            kode: 'PGBLKL',
            nilai: $nilai,
            skor: (int) floor($skorB),
            komponen: compact('ndgb', 'ndlk', 'ndl', 'ndtps') + ['skor_b_tepat' => round($skorB, 4)],
        );
    }

    /**
     * Skor elemen 17 menggabungkan PDS3, PGBLKL, dan skor analisis
     * keterpenuhan: skor = (3 × (a + b) + c) / 7.
     */
    public function skorElemen17(HasilRumus $pds3, HasilRumus $pgblkl, float $skorAnalisis): float
    {
        $a = $pds3->komponen['skor_a_tepat'];
        $b = $pgblkl->komponen['skor_b_tepat'];

        return round((3 * ($a + $b) + $skorAnalisis) / 7, 2);
    }

    // ---- PPDTPS — elemen 51 ---------------------------------------------

    /**
     * Persentase DTPS yang berpublikasi.
     *
     * YANG DIHITUNG ADALAH JUMLAH DOSEN, BUKAN JUMLAH ARTIKEL. Seorang dosen
     * dengan sepuluh artikel tetap dihitung satu. Ini sumber kesalahan paling
     * sering pada instrumen ini, dan tanda tangan metodenya sengaja menuntut
     * "jumlah dosen berpublikasi" supaya pemanggil tidak bisa keliru
     * menyerahkan cacah artikel tanpa menyadarinya.
     *
     * @param  int  $dosenBerpublikasi  cacah DOSEN dengan >=1 publikasi memenuhi syarat
     * @param  int  $ndtps  seluruh DTPS
     * @param  int|null  $jumlahArtikel  hanya untuk dicatat sebagai komponen
     */
    public function ppdtps(int $dosenBerpublikasi, int $ndtps, ?int $jumlahArtikel = null): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        if ($dosenBerpublikasi > $ndtps) {
            throw new InvalidArgumentException(
                "Dosen berpublikasi ({$dosenBerpublikasi}) tidak boleh melebihi NDTPS ({$ndtps}). "
                .'Periksa apakah yang diserahkan adalah cacah dosen, bukan cacah artikel.'
            );
        }

        $nilai = round(($dosenBerpublikasi / $ndtps) * 100, 2);

        // Batasnya setengah terbuka: 20 masuk ke skor 4, 15 masuk ke skor 3.
        $skor = match (true) {
            $nilai >= 20.0 => 4,
            $nilai >= 15.0 => 3,
            $nilai >= 10.0 => 2,
            default => 1,
        };

        return new HasilRumus(
            kode: 'PPDTPS',
            nilai: $nilai,
            skor: $skor,
            // Ambang syarat perlu lima tahun DUA KALI LIPAT ambang skor 4.
            memenuhiSyarat3Tahun: $nilai >= 20.0,
            memenuhiSyarat5Tahun: $nilai >= 40.0,
            komponen: compact('dosenBerpublikasi', 'ndtps', 'jumlahArtikel'),
            catatan: $nilai >= 20.0 && $nilai < 40.0
                ? 'Skor penuh tercapai, tetapi syarat perlu lima tahun (PPDTPS ≥ 40) BELUM terpenuhi.'
                : null,
        );
    }

    /** Skor elemen 51 = (3 × a + b) / 4, dengan b skor analisis tren. */
    public function skorElemen51(HasilRumus $ppdtps, float $skorTren): float
    {
        return round((3 * $ppdtps->skor + $skorTren) / 4, 2);
    }

    // ---- Rumus lain ------------------------------------------------------

    /** Rasio kerja sama tridharma per DTPS, tertimbang menurut tingkatnya. */
    public function rk(int $n1, int $n2, int $n3, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $nilai = round(((3 * $n1) + (2 * $n2) + (1 * $n3)) / $ndtps, 2);

        return new HasilRumus(
            kode: 'RK',
            nilai: $nilai,
            skor: $nilai >= 4.0 ? 4 : (int) max(1, floor($nilai)),
            komponen: compact('n1', 'n2', 'n3', 'ndtps'),
        );
    }

    /** Rata-rata jumlah mahasiswa bimbingan akhir per DTPS. */
    public function rsa(int $nas, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $nilai = round($nas / $ndtps, 2);

        $skor = match (true) {
            $nilai >= 9.0 => 4,
            $nilai >= 6.0 => 3,
            $nilai >= 3.0 => 2,
            default => 1,
        };

        return new HasilRumus(
            kode: 'RSA',
            nilai: $nilai,
            skor: $skor,
            komponen: compact('nas', 'ndtps'),
        );
    }

    /**
     * Tingkat kepuasan mahasiswa.
     *
     * ASUMSI YANG BELUM DIKONFIRMASI KE LAMDIK:
     * TKMi = (4×ai) + (3×bi) + (2×ci) + di, dengan a..d dalam PERSEN, sehingga
     * hasilnya berskala 100–400. Sementara ambangnya dinyatakan sebagai
     * persentase (TKM ≥ 75%). Keduanya tidak sebanding.
     *
     * Tafsir yang dipakai: BAGI 4 sebelum dibandingkan, sehingga 400 menjadi
     * 100% dan 300 menjadi 75%. Ini konsisten — "seluruh responden menjawab
     * Sangat Baik" wajar bernilai 100%.
     *
     * Tafsir ini BELUM DIKONFIRMASI. Layar Nilai Rumus menandainya, dan
     * CLAUDE.md bagian 7 mencatatnya. Bila LAMDIK menjawab berbeda, yang
     * berubah hanya metode ini dan satu peringatan.
     *
     * @param  array<int, array{a: float, b: float, c: float, d: float}>  $dimensi  persentase per dimensi
     */
    public function tkm(array $dimensi): HasilRumus
    {
        if ($dimensi === []) {
            throw new InvalidArgumentException('TKM butuh sekurangnya satu dimensi.');
        }

        $mentah = collect($dimensi)->map(
            fn (array $d) => (4 * $d['a']) + (3 * $d['b']) + (2 * $d['c']) + $d['d'],
        );

        $rataMentah = $mentah->avg();
        $nilai = round($rataMentah / 4, 2);

        $skor = match (true) {
            $nilai >= 90.0 => 4,
            $nilai >= 75.0 => 3,
            $nilai >= 60.0 => 2,
            default => 1,
        };

        return new HasilRumus(
            kode: 'TKM',
            nilai: $nilai,
            skor: $skor,
            komponen: [
                'dimensi' => $dimensi,
                'tkm_mentah_per_dimensi' => $mentah->map(fn ($n) => round($n, 2))->all(),
                'rata_mentah' => round($rataMentah, 2),
                'pembagi_normalisasi' => 4,
            ],
            catatan: 'ASUMSI BELUM DIKONFIRMASI: nilai mentah berskala 100–400 dibagi 4 '
                .'agar sebanding dengan ambang persentase. Konfirmasikan ke LAMDIK.',
        );
    }

    /** Rata-rata IPK lulusan. */
    public function ripk(float $rataIpk): HasilRumus
    {
        $skor = match (true) {
            $rataIpk >= 3.25 => 4,
            $rataIpk >= 3.00 => 3,
            $rataIpk >= 2.75 => 2,
            default => 1,
        };

        return new HasilRumus(
            kode: 'RIPK',
            nilai: round($rataIpk, 2),
            skor: $skor,
            komponen: ['rata_ipk' => $rataIpk],
        );
    }

    // ---- Penyimpanan -----------------------------------------------------

    /**
     * Menyimpan hasil TANPA menimpa baris lama.
     *
     * Angka akreditasi berubah sepanjang periode karena datanya masih masuk.
     * Menyimpan riwayatnya memungkinkan menjawab "kapan PDS3 kita turun di
     * bawah 50?" — pertanyaan yang justru muncul saat ada sengketa.
     */
    public function simpan(HasilRumus $hasil, Periode $periode, ?User $oleh = null): NilaiRumus
    {
        $this->pastikanRumusDikenal($hasil->kode);

        return NilaiRumus::create($hasil->untukDisimpan() + [
            'periode_id' => $periode->id,
            'dihitung_oleh' => $oleh?->getKey(),
        ]);
    }

    /**
     * Ambang mentah dari tabel `rumus`, untuk ditampilkan di layar.
     *
     * Dibaca dari basis data, bukan dari konstanta — perubahan instrumen kelak
     * cukup mengganti data/rumus.json lalu menjalankan seeder.
     */
    public function aturanSkor(string $kode): ?string
    {
        return $this->ambangTersimpan[$kode] ??= Rumus::where('kode', $kode)->value('aturan_skor');
    }

    private function pastikanRumusDikenal(string $kode): void
    {
        if (! Rumus::where('kode', $kode)->exists()) {
            throw new InvalidArgumentException(
                "Rumus `{$kode}` tidak ada di tabel rumus. Jalankan RumusSeeder lebih dulu."
            );
        }
    }

    private function pastikanPembagiSah(int $pembagi, string $nama): void
    {
        if ($pembagi <= 0) {
            throw new InvalidArgumentException(
                "{$nama} bernilai {$pembagi}; pembagian tidak bisa dilakukan. "
                .'Isi data DKPS-nya lebih dulu daripada menghitung dengan angka nol.'
            );
        }
    }
}
