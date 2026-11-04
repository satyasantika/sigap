<?php

namespace App\Services;

use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\Rumus;
use App\Models\User;
use App\Support\Rumus\HasilRumus;
use InvalidArgumentException;

/**
 * Perhitungan rumus instrumen: dua puluh tujuh metode untuk dua puluh delapan
 * rumus (NA dihitung KalkulatorNa).
 *
 * Satu metode per rumus, ditulis tangan. Ekspresi di `data/rumus.json` sengaja
 * TIDAK diurai mesin: notasinya bercampur matematika, kalimat Indonesia, dan
 * koma desimal gaya Indonesia, dan salah satunya bahkan belum punya rumus.
 * Membangun pengurai untuk itu akan menghasilkan penerjemah yang salah
 * diam-diam — jauh lebih berbahaya daripada metode-metode yang jelas.
 *
 * Ambang **ditampilkan** dari tabel `rumus` (`aturanSkor()`), **dihitung** di
 * metode dan dikunci uji.
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
            skor: (int) floor(self::potong2($skorA)),
            skorTepat: self::potong2($skorA),
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
            skor: (int) floor(self::potong2($skorB)),
            skorTepat: self::potong2($skorB),
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
            skorTepat: (float) $skor,
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

    /**
     * Kerja sama tridharma (elemen 7), dua bagian.
     *
     * A.1 = rasio tertimbang RK = (3·N1 + 2·N2 + N3)/NDTPS: RK ≥ 4 → 4,
     * selain itu max(1, RK). Buku 4 menulis "RK < 4 → A = RK" tanpa lantai;
     * kertas kerja memberi 1 bila RK ≤ 1 (spec §2.1).
     *
     * A.2 = pola RI/RN/RW atas CACAH MENTAH kerja sama internasional,
     * nasional, dan wilayah (a = 2, b = 6, c = 9) — bukan rasio per DTPS.
     * Membaginya dengan NDTPS lebih dulu adalah perangkap bagian ini.
     *
     * Skor A = (2·A.1 + A.2)/3. A.1 diturunkan dari `nilai` yang sudah
     * dibulatkan, dan A.1, A.2, serta Skor A dipotong dua desimal (potong2),
     * supaya angka yang terlihat di layar bisa dihitung ulang dengan tangan.
     *
     * @param  int  $n1  kerja sama tingkat 1 (bobot 3)
     * @param  int  $n2  kerja sama tingkat 2 (bobot 2)
     * @param  int  $n3  kerja sama tingkat 3 (bobot 1)
     * @param  int  $ni  kerja sama tingkat internasional
     * @param  int  $nn  kerja sama tingkat nasional
     * @param  int  $nw  kerja sama tingkat wilayah/lokal
     * @param  int  $ndtps  seluruh DTPS
     */
    public function rk(int $n1, int $n2, int $n3, int $ni, int $nn, int $nw, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $nilai = round(((3 * $n1) + (2 * $n2) + (1 * $n3)) / $ndtps, 2);

        $a1 = self::potong2($nilai >= 4.0 ? 4.0 : max(1.0, $nilai));
        $a2 = self::potong2($this->skorTigaTingkat($ni, $nn, $nw, a: 2, b: 6, c: 9));
        $skorTepat = self::potong2(((2 * $a1) + $a2) / 3);

        return new HasilRumus(
            kode: 'RK',
            nilai: $nilai,
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: compact('a1', 'a2', 'n1', 'n2', 'n3', 'ni', 'nn', 'nw', 'ndtps'),
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
            skorTepat: (float) $skor,
            komponen: compact('nas', 'ndtps'),
        );
    }

    /**
     * Tingkat kepuasan mahasiswa.
     *
     * TKMi = (4×ai) + (3×bi) + (2×ci) + di, dengan a..d dalam PERSEN, sehingga
     * hasil mentahnya berskala 100–400, sementara ambangnya dinyatakan sebagai
     * persentase. Nilai mentah DIBAGI 4 sebelum dibandingkan, sehingga 400
     * menjadi 100% dan 300 menjadi 75%.
     *
     * Normalisasi /4 sesuai kertas kerja AK IAPSK 3.0 (sel E207): kertas kerja
     * resmi menghitung `(4·SB/100 + 3·B/100 + 2·C/100 + K/100) / 4` per
     * dimensi. Tafsir ini tidak lagi menunggu konfirmasi LAMDIK — lihat
     * docs/KEPUTUSAN.md bagian 1.
     *
     * Ambang skor juga mengikuti kertas kerja (sel D229–D231, E232) dan
     * `data/rumus.json`: TKM ≥ 0,75 → 4; ≥ 0,5 → 3; ≥ 0,25 → 2; selain itu 1,
     * di sini dalam persen (75 / 50 / 25).
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
            $nilai >= 75.0 => 4,
            $nilai >= 50.0 => 3,
            $nilai >= 25.0 => 2,
            default => 1,
        };

        return new HasilRumus(
            kode: 'TKM',
            nilai: $nilai,
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: [
                'dimensi' => $dimensi,
                'tkm_mentah_per_dimensi' => $mentah->map(fn ($n) => round($n, 2))->all(),
                'rata_mentah' => round($rataMentah, 2),
                'pembagi_normalisasi' => 4,
            ],
            catatan: 'Normalisasi /4 sesuai kertas kerja AK IAPSK 3.0 (sel E207)',
        );
    }

    /**
     * Rata-rata IPK lulusan.
     *
     * Mengikuti Buku 4, kertas kerja (sel E569), dan `data/rumus.json`:
     * RIPK ≥ 3,25 → 4; 2,00 ≤ RIPK < 3,25 → (8·RIPK − 6)/5 dengan lantai 1;
     * di bawah 2,00 → 1. Tangga 3,25 / 3,00 / 2,75 yang dulu dipakai di sini
     * (dari dokumen 03, bukan Buku 4) membuang pecahannya: IPK 3,0 memberi 3,
     * padahal Buku 4 memberi 3,6, dan IPK 2,5 memberi 1, padahal 2,8.
     */
    public function ripk(float $rataIpk): HasilRumus
    {
        $skorTepat = match (true) {
            $rataIpk >= 3.25 => 4.0,
            $rataIpk >= 2.00 => max(1.0, ((8 * $rataIpk) - 6) / 5),
            default => 1.0,
        };
        $skorTepat = self::potong2($skorTepat);

        return new HasilRumus(
            kode: 'RIPK',
            nilai: round($rataIpk, 2),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: ['rata_ipk' => $rataIpk],
        );
    }

    // ---- PRESMA, PKIM, BKD, RGP, DANA — elemen 12, 13, 18, 22, 25 -------

    /**
     * Prestasi mahasiswa (elemen 12), pola RI/RN/RW dengan a = 0,005,
     * b = 0,05, c = 0,1.
     *
     * Berbeda dengan RK bagian A.2, di sini yang masuk pola adalah RASIO
     * cacah prestasi terhadap jumlah mahasiswa (NM), dibatasi paling tinggi 1.
     * Menyerahkan cacah mentah ke pola akan memberi skor 4 pada satu prestasi
     * internasional saja.
     *
     * @param  int  $ni  prestasi tingkat internasional
     * @param  int  $nn  prestasi tingkat nasional
     * @param  int  $nw  prestasi tingkat wilayah/lokal
     * @param  int  $nm  jumlah mahasiswa
     */
    public function presma(int $ni, int $nn, int $nw, int $nm): HasilRumus
    {
        $this->pastikanPembagiSah($nm, 'NM');

        $ri = min(1.0, $ni / $nm);
        $rn = min(1.0, $nn / $nm);
        $rw = min(1.0, $nw / $nm);

        $skorTepat = self::potong2($this->skorTigaTingkat($ri, $rn, $rw, a: 0.005, b: 0.05, c: 0.1));

        return new HasilRumus(
            kode: 'PRESMA',
            nilai: round($ri, 4),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: [
                'ri' => round($ri, 4),
                'rn' => round($rn, 4),
                'rw' => round($rw, 4),
            ] + compact('ni', 'nn', 'nw', 'nm'),
        );
    }

    /**
     * Produk/karya ilmiah mahasiswa (elemen 13).
     *
     * Keempat jenis karya DIJUMLAHKAN lebih dulu, baru dibagi NM; masing-masing
     * cacah sudah berupa mahasiswa unik per tabel DKPS (4.1–4.4). Rasionya
     * dibatasi 1. Batas setengah terbuka: 0,2 masuk skor 4.
     */
    public function pkim(int $paten, int $bukuIsbn, int $karyaSeni, int $publikasi, int $nm): HasilRumus
    {
        $this->pastikanPembagiSah($nm, 'NM');

        $jumlah = $paten + $bukuIsbn + $karyaSeni + $publikasi;
        $nilai = round(min(1.0, $jumlah / $nm), 4);

        $skor = match (true) {
            $nilai >= 0.2 => 4,
            $nilai >= 0.15 => 3,
            $nilai >= 0.1 => 2,
            default => 1,
        };

        return new HasilRumus(
            kode: 'PKIM',
            nilai: $nilai,
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: compact('paten', 'bukuIsbn', 'karyaSeni', 'publikasi', 'nm', 'jumlah'),
        );
    }

    /**
     * Beban kerja DTPS (elemen 18), rata-rata sks per semester.
     *
     * Mengikuti Buku 4 dengan lantai 1 (spec §5.3, catatan BKD): Buku 4
     * menulis 36 − 2x untuk 16 < x ≤ 18, yang memberi 0 tepat di x = 18 —
     * padahal skor 0 tidak ada. Kertas kerja (sel E283) memberi 1 untuk
     * 6 ≤ x ≤ 7 dan x = 18; lantai 1 pada kedua pita interpolasi menyamakan
     * keduanya. Perbedaan ini dicatat sebagai pertanyaan ke LAMDIK.
     */
    public function bkd(float $rataSksPerSemester): HasilRumus
    {
        $x = $rataSksPerSemester;

        $skorTepat = self::potong2(match (true) {
            $x >= 12.0 && $x <= 16.0 => 4.0,
            $x >= 6.0 && $x < 12.0 => max(1.0, ((2 * $x) - 12) / 3),
            $x > 16.0 && $x <= 18.0 => max(1.0, 36 - (2 * $x)),
            default => 1.0,
        });

        return new HasilRumus(
            kode: 'BKD',
            nilai: round($x, 2),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: ['rata_sks_per_semester' => $x],
        );
    }

    /**
     * Rasio guru pamong (elemen 22), rumus sel E348 kertas kerja.
     *
     * RGPBS = NGP/NBS; RGPMD, RGPMU, RGPGP = pecahan guru pamong bergelar
     * magister/doktor, berjabatan madya/utama, dan bersertifikat pendidik,
     * masing-masing dibatasi 1. Dengan a..d = 0,3 / 0,4 / 0,5 / 0,6:
     *
     *   1. BS > 2, MD ≥ d, MU ≥ b, GP ≥ c   → 4
     *   2. BS > 2                           → 3 + rata(⌊(MD−a)/(d−a)⌋₀¹, ⌊MU/b⌋₀¹, ⌊GP/c⌋₀¹)
     *   3. BS = 2, MD ≥ a, MU < b, GP < c   → 3
     *   4. BS = 2, MD < a                   → 2 + MD/a
     *   5. selain itu                       → 2
     *
     * Perangkapnya: BS = 2 adalah perbandingan rasio pecahan, jadi diuji
     * dengan toleransi, bukan `==`. Ketiga sub-skor pita 2 dipotong dua
     * desimal sebelum dirata-rata, mengikuti pembulatan kertas kerja.
     *
     * @param  int  $ngp  seluruh guru pamong
     * @param  int  $nbs  jumlah bidang studi
     * @param  int  $magisterDoktor  guru pamong bergelar magister atau doktor
     * @param  int  $madyaUtama  guru pamong berjabatan Guru Madya atau Guru Utama
     * @param  int  $serdik  guru pamong bersertifikat pendidik
     */
    public function rgp(int $ngp, int $nbs, int $magisterDoktor, int $madyaUtama, int $serdik): HasilRumus
    {
        $this->pastikanPembagiSah($nbs, 'NBS');
        $this->pastikanPembagiSah($ngp, 'NGP');

        [$a, $b, $c, $d] = [0.3, 0.4, 0.5, 0.6];

        $bs = $ngp / $nbs;
        $md = min(1.0, $magisterDoktor / $ngp);
        $mu = min(1.0, $madyaUtama / $ngp);
        $gp = min(1.0, $serdik / $ngp);

        $sepadanDua = abs($bs - 2.0) < 1e-9;
        $jepit = fn (float $v): float => self::potong2(max(0.0, min(1.0, $v)));

        $skor = match (true) {
            $bs > 2.0 && ! $sepadanDua && $md >= $d && $mu >= $b && $gp >= $c => 4.0,
            $bs > 2.0 && ! $sepadanDua => 3 + (($jepit(($md - $a) / ($d - $a)) + $jepit($mu / $b) + $jepit($gp / $c)) / 3),
            $sepadanDua && $md >= $a && $mu < $b && $gp < $c => 3.0,
            $sepadanDua && $md < $a => 2 + self::potong2($md / $a),
            default => 2.0,
        };
        $skorTepat = self::potong2($skor);

        return new HasilRumus(
            kode: 'RGP',
            nilai: round($bs, 2),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: [
                'rgpbs' => round($bs, 4),
                'rgpmd' => round($md, 4),
                'rgpmu' => round($mu, 4),
                'rgpgp' => round($gp, 4),
            ] + compact('ngp', 'nbs', 'magisterDoktor', 'madyaUtama', 'serdik'),
        );
    }

    /**
     * Pendanaan (elemen 25): Skor A = RATA-RATA tiga sub-skor bertangga.
     *
     * ROP dibagi jumlah MAHASISWA (NM), sedangkan RPD dan RPkMD dibagi
     * jumlah DTPS — pembagi yang tertukar adalah perangkap rumus ini.
     * Ambang dalam rupiah per tahun:
     *
     *   ROP   ≥ 18 jt → 4; ≥ 10 jt → 3; ≥ 5 jt → 2; selain itu 1
     *   RPD   ≥ 10 jt → 4; ≥  7 jt → 3; ≥ 4 jt → 2; selain itu 1
     *   RPkMD ≥  5 jt → 4; ≥  3 jt → 3; ≥ 1 jt → 2; selain itu 1
     *
     * @param  float  $biayaOperasionalPendidikan  rupiah per tahun
     * @param  float  $danaPenelitian  rupiah per tahun
     * @param  float  $danaPkm  rupiah per tahun
     * @param  int  $nm  jumlah mahasiswa
     * @param  int  $ndtps  seluruh DTPS
     */
    public function dana(float $biayaOperasionalPendidikan, float $danaPenelitian, float $danaPkm, int $nm, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($nm, 'NM');
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $rop = round($biayaOperasionalPendidikan / $nm, 2);
        $rpd = round($danaPenelitian / $ndtps, 2);
        $rpkmd = round($danaPkm / $ndtps, 2);

        $tangga = fn (float $x, float $empat, float $tiga, float $dua): int => match (true) {
            $x >= $empat => 4,
            $x >= $tiga => 3,
            $x >= $dua => 2,
            default => 1,
        };

        $skorOp = $tangga($rop, 18e6, 10e6, 5e6);
        $skorPd = $tangga($rpd, 10e6, 7e6, 4e6);
        $skorPkm = $tangga($rpkmd, 5e6, 3e6, 1e6);

        $skorTepat = self::potong2(($skorOp + $skorPd + $skorPkm) / 3);

        return new HasilRumus(
            kode: 'DANA',
            nilai: $rop,
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: compact('rop', 'rpd', 'rpkmd') + [
                'skor_op' => $skorOp,
                'skor_pd' => $skorPd,
                'skor_pkm' => $skorPkm,
                'biaya_operasional_pendidikan' => $biayaOperasionalPendidikan,
                'dana_penelitian' => $danaPenelitian,
                'dana_pkm' => $danaPkm,
                'nm' => $nm,
                'ndtps' => $ndtps,
            ],
        );
    }

    // ---- Pola tiga tingkat RI/RN/RW --------------------------------------

    /**
     * Skor pola RI/RN/RW (internasional, nasional, wilayah) yang dipakai lima
     * elemen: E7 bagian A.2, E12, E49, E50, E54. Urutan pemeriksaan mengikuti
     * kertas kerja (sel E834–E839):
     *
     *   1. RI ≥ a                         → 4
     *   2. RI < a dan RN ≥ b              → 3 + RI/a
     *   3. 0 < RI < a atau 0 < RN < b     → 2 + RI/a + RN/b − (RI·RN)/(a·b)
     *   4. RI = 0, RN = 0, RW ≥ c         → 2
     *   5. selain itu                     → max(1, 2·RW/c)
     *
     * Dua perbedaan dengan Buku 4 (spec §2.1), keduanya dicatat sebagai
     * pertanyaan ke LAMDIK:
     *
     * - Pita 3 memakai koefisien 1 pada RI/a, seperti kertas kerja. Buku 4
     *   menulis 2, yang membuat pita "2–3" bisa mencapai 3,89 (RI = 0,9a,
     *   RN = 0,9b) — tidak lagi di antara 2 dan 3.
     * - Pita 5 diinterpolasi, seperti kertas kerja. Buku 4 menulis "skor 1",
     *   tetapi menamai pita itu "1–2", yang menyiratkan rentang.
     *
     * Hasilnya TIDAK dibulatkan; pemanggil yang membulatkan.
     */
    private function skorTigaTingkat(float $ri, float $rn, float $rw, float $a, float $b, float $c): float
    {
        if ($ri >= $a) {
            return 4.0;
        }

        if ($rn >= $b) {
            return 3 + ($ri / $a);
        }

        if ($ri > 0 || $rn > 0) {
            return 2 + ($ri / $a) + ($rn / $b) - (($ri * $rn) / ($a * $b));
        }

        if ($rw >= $c) {
            return 2.0;
        }

        return max(1.0, 2 * $rw / $c);
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

    /**
     * Memotong skor ke dua desimal, ke arah nol — TIDAK membulatkan.
     *
     * Skor tepat dibulatkan bisa melompati ambang: PGBLKL 69,84 (< 70)
     * memberi skor(b) 3,9954, yang dibulatkan menjadi 4,00 padahal ambang
     * skor 4 belum tercapai. Dipotong, ia tetap 3,99. Aturan ini berlaku
     * untuk setiap `skorTepat`; `nilai` tetap dibulatkan seperti biasa.
     *
     * `round(…, 8)` sebelum `floor` hanya meredam derau titik mengambang
     * (2,8 × 100 bisa menjadi 279,9999…), bukan membulatkan skornya.
     */
    private static function potong2(float $x): float
    {
        return floor(round($x * 100, 8)) / 100;
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
