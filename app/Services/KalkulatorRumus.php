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
     * Singgahan teks `aturan_skor` per kode rumus, hanya untuk ditampilkan
     * lewat aturanSkor().
     *
     * Teks ini tidak diurai dan tidak dipakai menghitung: ambang yang
     * menentukan skor ditulis di metode masing-masing dan dikunci uji
     * (lihat kepala kelas). Kalau keduanya berbeda, ujilah yang salah.
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
     * Ambang skor dan syarat perlu dibandingkan pada persen MENTAH; `nilai`
     * dibulatkan hanya untuk tampilan (19,997 tampil 20,0 tetapi belum 20).
     *
     * @param  int  $nds3  DTPS berpendidikan doktor
     * @param  int  $ndtps  seluruh DTPS
     * @param  int  $ndlk  DTPS berjabatan lektor kepala
     * @param  int  $ndgb  DTPS berjabatan guru besar
     */
    public function pds3(int $nds3, int $ndtps, int $ndlk = 0, int $ndgb = 0): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $persen = ($nds3 * 100) / $ndtps;
        $nilai = round($persen, 2);

        // skor(a) = PDS3 >= 40 ? 4 : 2 + (5 * PDS3/100)
        $skorA = $persen >= 40.0 ? 4.0 : 2 + (5 * $persen / 100);

        // Syarat perlu 5 tahun: PDS3 >= 50 DAN NDLK + NDGB >= 4.
        // Syarat perlu 3 tahun: PDS3 >= 20 DAN NDLK + NDGB >= 3.
        $lektorKeAtas = $ndlk + $ndgb;

        return new HasilRumus(
            kode: 'PDS3',
            nilai: $nilai,
            skor: (int) floor(self::potong2($skorA)),
            skorTepat: self::potong2($skorA),
            memenuhiSyarat3Tahun: $persen >= 20.0 && $lektorKeAtas >= 3,
            memenuhiSyarat5Tahun: $persen >= 50.0 && $lektorKeAtas >= 4,
            komponen: compact('nds3', 'ndtps', 'ndlk', 'ndgb') + [
                'skor_a_tepat' => round($skorA, 4),
                'lektor_kepala_ke_atas' => $lektorKeAtas,
            ],
            catatan: $persen >= 40.0 && $persen < 50.0
                ? 'Skor penuh tercapai, tetapi syarat perlu lima tahun (PDS3 ≥ 50) BELUM terpenuhi.'
                : null,
        );
    }

    /**
     * Persentase DTPS berjabatan akademik guru besar, lektor kepala, atau
     * lektor. Ambang 70 dibandingkan pada persen mentah, seperti PDS3.
     */
    public function pgblkl(int $ndgb, int $ndlk, int $ndl, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $persen = (($ndgb + $ndlk + $ndl) * 100) / $ndtps;
        $nilai = round($persen, 2);

        // skor(b) = PGBLKL >= 70 ? 4 : 2 + ((20 * PGBLKL/100) / 7)
        $skorB = $persen >= 70.0 ? 4.0 : 2 + ((20 * $persen / 100) / 7);

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
     * Ambang dibandingkan pada persen MENTAH: 1504/7521 = 19,997 % tampil
     * 20,0 setelah dibulatkan, tetapi belum mencapai 20, jadi skornya 3.
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

        // Kalikan 100 sebelum membagi supaya 3/20 tepat 15, bukan 15,000…1.
        $persen = ($dosenBerpublikasi * 100) / $ndtps;
        $nilai = round($persen, 2);

        // Batasnya setengah terbuka: 20 masuk ke skor 4, 15 masuk ke skor 3.
        $skor = self::tangga($persen, 20.0, 15.0, 10.0);

        return new HasilRumus(
            kode: 'PPDTPS',
            nilai: $nilai,
            skor: $skor,
            skorTepat: (float) $skor,
            // Ambang syarat perlu lima tahun DUA KALI LIPAT ambang skor 4.
            memenuhiSyarat3Tahun: $persen >= 20.0,
            memenuhiSyarat5Tahun: $persen >= 40.0,
            komponen: compact('dosenBerpublikasi', 'ndtps', 'jumlahArtikel'),
            catatan: $persen >= 20.0 && $persen < 40.0
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
     * Skor A = (2·A.1 + A.2)/3. A.1 diturunkan dari rasio mentah — ambang
     * ≥ 4 dibandingkan sebelum pembulatan, sama seperti rumus lain (3,996
     * tampil 4,00 tetapi belum 4); `nilai` dibulatkan hanya untuk layar.
     * A.1, A.2, dan Skor A dipotong dua desimal (potong2).
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

        $rasio = ((3 * $n1) + (2 * $n2) + (1 * $n3)) / $ndtps;
        $nilai = round($rasio, 2);

        $a1 = self::potong2($rasio >= 4.0 ? 4.0 : max(1.0, $rasio));
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

    /**
     * Rasio artikel DTPS yang disitasi per DTPS: NAS (cacah judul artikel
     * Tabel 28, bukan jumlah sitasinya) ÷ NDTPS. Ambang dibandingkan pada
     * rasio mentah (8,996 tampil 9,0 tetapi belum 9).
     */
    public function rsa(int $nas, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $rasio = $nas / $ndtps;
        $skor = self::tangga($rasio, 9.0, 6.0, 3.0);

        return new HasilRumus(
            kode: 'RSA',
            nilai: round($rasio, 2),
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
     * di sini dalam persen (75 / 50 / 25), dibandingkan pada rata MENTAH
     * yang belum dibulatkan.
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
        $persen = $rataMentah / 4;
        $skor = self::tangga($persen, 75.0, 50.0, 25.0);

        return new HasilRumus(
            kode: 'TKM',
            nilai: round($persen, 2),
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
     *
     * Ambang dibandingkan pada rasio MENTAH, bukan pada `nilai` yang sudah
     * dibulatkan: 1504/7521 = 0,19997 tampil 0,2, tetapi belum mencapai 0,2
     * sehingga skornya 3.
     */
    public function pkim(int $paten, int $bukuIsbn, int $karyaSeni, int $publikasi, int $nm): HasilRumus
    {
        $this->pastikanPembagiSah($nm, 'NM');

        $jumlah = $paten + $bukuIsbn + $karyaSeni + $publikasi;
        $rasio = min(1.0, $jumlah / $nm);
        $skor = self::tangga($rasio, 0.2, 0.15, 0.1);

        return new HasilRumus(
            kode: 'PKIM',
            nilai: round($rasio, 4),
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
     *   3. BS = 2, MD ≥ a                   → 3
     *   4. BS = 2, MD < a                   → 2 + MD/a
     *   5. selain itu                       → 2
     *
     * Penyimpangan dari kertas kerja pada pita 3: E348 menuntut juga
     * MU < b dan GP < c, sehingga BS = 2 dengan MD ≥ a tetapi MU ≥ b atau
     * GP ≥ c jatuh ke "selain itu" → 2 — rasio yang LEBIH BAIK justru
     * menurunkan skor. Buku 4 tidak punya pita untuk keadaan itu, dan skor
     * tidak boleh turun karena mutu guru pamong naik; maka SIGAP memberi 3
     * untuk BS = 2 dan MD ≥ a, berapa pun MU dan GP. Cacat kertas kerja ini
     * dicatat sebagai pertanyaan ke LAMDIK.
     *
     * Perangkapnya: BS = 2 adalah perbandingan rasio pecahan, jadi diuji
     * dengan toleransi, bukan `==`. Ketiga sub-skor pita 2 dipotong dua
     * desimal sebelum dirata-rata menurut aturan potong SIGAP sendiri
     * (potong2), BUKAN mengikuti kertas kerja: E348 hanya membulatkan
     * (ROUND) skor akhir. Selisihnya terhadap kertas kerja paling besar
     * 0,02 (tiga pemotongan sub-skor ditambah pemotongan akhir).
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
            $sepadanDua && $md >= $a => 3.0,
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
     * Tangga dibandingkan pada rasio mentah; pembulatan ke rupiah hanya untuk
     * tampilan, supaya 4.999.999,996 tidak terbaca sebagai 5 jt.
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

        $ropMentah = $biayaOperasionalPendidikan / $nm;
        $rpdMentah = $danaPenelitian / $ndtps;
        $rpkmdMentah = $danaPkm / $ndtps;

        $skorOp = self::tangga($ropMentah, 18e6, 10e6, 5e6);
        $skorPd = self::tangga($rpdMentah, 10e6, 7e6, 4e6);
        $skorPkm = self::tangga($rpkmdMentah, 5e6, 3e6, 1e6);

        $rop = round($ropMentah, 2);
        $rpd = round($rpdMentah, 2);
        $rpkmd = round($rpkmdMentah, 2);

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

    // ---- RRD, NDTPSPK, NTENDIKPK — elemen 19, 20, 21 --------------------

    /**
     * Rasio pengakuan (rekognisi) kepakaran per DTPS.
     *
     * RRD ≥ 1 → 4; selain itu 2 + 2·RRD. Rasio TIDAK dibatasi 1 (lebih dari
     * satu rekognisi per dosen tetap 4) dan tidak punya lantai di bawah 2:
     * tanpa rekognisi sama sekali skornya 2, bukan 1.
     *
     * @param  int  $rekognisi  cacah pengakuan kepakaran DTPS
     * @param  int  $ndtps  seluruh DTPS
     */
    public function rrd(int $rekognisi, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $rasio = $rekognisi / $ndtps;
        $skorTepat = self::potong2($rasio >= 1.0 ? 4.0 : 2 + (2 * $rasio));

        return new HasilRumus(
            kode: 'RRD',
            nilai: round($rasio, 2),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: compact('rekognisi', 'ndtps'),
        );
    }

    /**
     * Persentase DTPS yang mengikuti pengembangan kompetensi, dibatasi 100.
     *
     * Pembilang bisa melebihi NDTPS bila pemanggil menyerahkan cacah
     * KEGIATAN, bukan cacah dosen; batas 100 mencegah persen yang mustahil.
     * Ambang 80 / 70 / 60 dibandingkan pada persen mentah.
     */
    public function ndtpspk(int $dtpsPk, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $persen = min(100.0, ($dtpsPk * 100) / $ndtps);
        $skor = self::tangga($persen, 80.0, 70.0, 60.0);

        return new HasilRumus(
            kode: 'NDTPSPK',
            nilai: round($persen, 2),
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: compact('dtpsPk', 'ndtps'),
        );
    }

    /**
     * Persentase tendik yang mengikuti pengembangan kompetensi, dibatasi 100.
     *
     * Ambangnya JAUH lebih rendah daripada NDTPSPK (40 / 25 / 10, bukan
     * 80 / 70 / 60); menyalin tangga dosen ke tendik adalah perangkapnya.
     */
    public function ntendikpk(int $tendikPk, int $tendik): HasilRumus
    {
        $this->pastikanPembagiSah($tendik, 'Jumlah tendik');

        $persen = min(100.0, ($tendikPk * 100) / $tendik);
        $skor = self::tangga($persen, 40.0, 25.0, 10.0);

        return new HasilRumus(
            kode: 'NTENDIKPK',
            nilai: round($persen, 2),
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: compact('tendikPk', 'tendik'),
        );
    }

    // ---- PDIPPKM, PMKI — elemen 32 --------------------------------------

    /**
     * Persentase DTPS yang mengintegrasikan penelitian/PkM ke pembelajaran.
     *
     * Yang dihitung adalah cacah DOSEN unik (tabel DKPS 19, kolom nama DTPS),
     * bukan cacah baris integrasi; persentasenya dibatasi 100. Ambang
     * dibandingkan pada rasio mentah.
     *
     * @param  int  $dosenIntegrasi  DTPS unik yang mengintegrasikan
     * @param  int  $ndtps  seluruh DTPS
     */
    public function pdippkm(int $dosenIntegrasi, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        // Kalikan 100 sebelum membagi: 0,29 × 100 di titik mengambang menjadi
        // 28,999…, dan perbandingan ambang memakai angka mentah.
        $persen = min(100.0, ($dosenIntegrasi * 100) / $ndtps);
        $skor = self::tangga($persen, 50.0, 30.0, 10.0);

        return new HasilRumus(
            kode: 'PDIPPKM',
            nilai: round($persen, 2),
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: compact('dosenIntegrasi', 'ndtps'),
        );
    }

    /**
     * Pecahan mata kuliah yang memuat integrasi penelitian/PkM, dibatasi 1.
     *
     * Tangga 4/3/2 TANPA skor 1: di bawah 0,15 skornya tetap 2. Buku 4
     * menulis pita 3 sebagai 3 + (x − 0,25)/0,10, yang untuk 0,15 ≤ x < 0,25
     * memberi 2–3 — jatuh di bawah 3 meski pitanya bernama "3". SIGAP
     * mengikuti kertas kerja (sel E496): ≥ 0,15 → 3 bulat (spec §2.1).
     *
     * @param  int  $mkIntegrasi  mata kuliah unik yang memuat integrasi
     * @param  int  $nmk  mata kuliah inti (kurikulum)
     */
    public function pmki(int $mkIntegrasi, int $nmk): HasilRumus
    {
        $this->pastikanPembagiSah($nmk, 'NMK');

        $rasio = min(1.0, $mkIntegrasi / $nmk);
        $skor = match (true) {
            $rasio >= 0.25 => 4,
            $rasio >= 0.15 => 3,
            default => 2,
        };

        return new HasilRumus(
            kode: 'PMKI',
            nilai: round($rasio, 4),
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: compact('mkIntegrasi', 'nmk'),
            catatan: 'Tangga 4/3/2 mengikuti kertas kerja E496; rumus Buku 4 untuk pita 3 jatuh di bawah 3 (pertanyaan ke LAMDIK)',
        );
    }

    // ---- RPPL — elemen 36 -----------------------------------------------

    /**
     * Rata-rata pertemuan pembimbingan PPL per dosen per tahun.
     *
     * Rerata dihitung pemanggil dari sel pertemuan yang TERISI saja (tabel
     * DKPS 20); sel kosong bukan nol. Metode ini hanya menerapkan tangga.
     */
    public function rppl(float $rataPertemuan): HasilRumus
    {
        $skor = self::tangga($rataPertemuan, 4.0, 3.0, 2.0);

        return new HasilRumus(
            kode: 'RPPL',
            nilai: round($rataPertemuan, 2),
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: ['rata_pertemuan' => $rataPertemuan],
        );
    }

    // ---- PMTK — elemen 40 -----------------------------------------------

    /**
     * Pecahan mahasiswa yang lulus tepat waktu.
     *
     * Pembilang dan pembagi masing-masing DIJUMLAHKAN atas TS-1 dan TS lebih
     * dulu — bukan rata-rata dua pecahan per angkatan. Di bawah 0,5 skornya
     * diinterpolasi 1 + 6·PMTK (dari rasio mentah, lalu dipotong).
     */
    public function pmtk(int $diterimaTs1, int $diterimaTs, int $lulusTs1, int $lulusTs): HasilRumus
    {
        $diterima = $diterimaTs1 + $diterimaTs;
        $this->pastikanPembagiSah($diterima, 'Σ mahasiswa diterima');

        $rasio = ($lulusTs1 + $lulusTs) / $diterima;
        $skorTepat = self::potong2($rasio >= 0.5 ? 4.0 : 1 + (6 * $rasio));

        return new HasilRumus(
            kode: 'PMTK',
            nilai: round($rasio, 4),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: compact('diterimaTs1', 'diterimaTs', 'lulusTs1', 'lulusTs'),
        );
    }

    // ---- RMS, PKMS — elemen 39, 41 --------------------------------------

    /**
     * Rata-rata masa studi lulusan, dalam tahun — TANPA skor.
     *
     * Rumus resmi (Buku 4, kertas kerja sel E586) memberi 4 hanya untuk
     * 3,5 < RMS ≤ 4 dan 1 di luar 3,5–5 tahun: rentang program sarjana empat
     * tahun. PPG yang masa studinya satu tahun akan selalu mendapat 1, justru
     * ketika lulusannya tepat waktu. docs/KEPUTUSAN.md §2 melarang meniru
     * cacat itu; maka `skor` dan `skorTepat` null sampai LAMDIK menjawab,
     * dan asesor melihat angka masa studinya, bukan skor 1 yang menyesatkan.
     */
    public function rms(float $rataTahun): HasilRumus
    {
        return new HasilRumus(
            kode: 'RMS',
            nilai: round($rataTahun, 2),
            skor: null,
            skorTepat: null,
            komponen: ['rata_tahun' => $rataTahun],
            catatan: 'Skor menunggu jawaban LAMDIK: rumus resmi (Buku 4, kertas kerja E586) memberi skor 1 untuk masa studi satu tahun; lihat docs/KEPUTUSAN.md §2.',
        );
    }

    /**
     * Persentase keberhasilan studi: pecahan lulusan terhadap mahasiswa yang
     * diterima pada angkatan yang sama, dibatasi 1.
     *
     * ≥ 0,85 → 4; 0,45 ≤ PKMS < 0,85 → (80·PKMS − 24)/11; di bawah → 1.
     * Rumus pita tengah memakai PKMS sebagai PECAHAN, bukan persen —
     * memasukkan 50 alih-alih 0,5 memberi ratusan.
     */
    public function pkms(int $lulusan, int $diterima): HasilRumus
    {
        $this->pastikanPembagiSah($diterima, 'Mahasiswa diterima');

        $rasio = min(1.0, $lulusan / $diterima);
        $skorTepat = self::potong2(match (true) {
            $rasio >= 0.85 => 4.0,
            $rasio >= 0.45 => ((80 * $rasio) - 24) / 11,
            default => 1.0,
        });

        return new HasilRumus(
            kode: 'PKMS',
            nilai: round($rasio, 4),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: compact('lulusan', 'diterima'),
        );
    }

    // ---- WTMP, TKi — elemen 43, 44 --------------------------------------

    /**
     * Waktu tunggu mendapatkan pekerjaan pertama, dalam bulan.
     *
     * Cacah per kelompok diubah ke bulan dengan TITIK TENGAH 3 (< 6 bulan),
     * 9 (6–12 bulan), dan 15 (> 12 bulan), lalu dirata-rata atas lulusan
     * TERLACAK (jumlah ketiga kelompok), bukan atas seluruh lulusan.
     * Skor awal: < 6 → 4; 6–12 → (18 − WTMP)/3; > 12 → 1.
     *
     * Aturan responden: bila lulusan terlacak (PJ = NJ/NL) di bawah
     * minimum Prmin, skor awal DIKALI PJ/Prmin — lihat penyesuaianResponden().
     * Tanpa aturan ini, melacak tiga lulusan yang kebetulan cepat bekerja
     * sudah memberi skor 4.
     *
     * @param  int  $lulusan  lulusan tiga tahun (NL)
     * @param  int  $terlacak  lulusan tiga tahun yang terlacak (NJ)
     */
    public function wtmp(int $kurang6Bulan, int $antara6Dan12Bulan, int $lebih12Bulan, int $lulusan, int $terlacak): HasilRumus
    {
        $responden = $this->penyesuaianResponden($lulusan, $terlacak);

        $jumlah = $kurang6Bulan + $antara6Dan12Bulan + $lebih12Bulan;
        $this->pastikanPembagiSah($jumlah, 'Σ lulusan per kelompok waktu tunggu');

        $bulan = ((3 * $kurang6Bulan) + (9 * $antara6Dan12Bulan) + (15 * $lebih12Bulan)) / $jumlah;
        $skorAwal = match (true) {
            $bulan < 6.0 => 4.0,
            $bulan <= 12.0 => (18 - $bulan) / 3,
            default => 1.0,
        };

        return $this->hasilBerResponden('WTMP', round($bulan, 2), $skorAwal, $responden, [
            'kurang_6_bulan' => $kurang6Bulan,
            'antara_6_dan_12_bulan' => $antara6Dan12Bulan,
            'lebih_12_bulan' => $lebih12Bulan,
        ]);
    }

    /**
     * Tingkat kepuasan pengguna lulusan atas sembilan aspek.
     *
     * TKi = 4a + 3b + 2c + d dengan a..d PECAHAN (persen/100), sehingga
     * skalanya 1–4 dan rata kesembilan aspek LANGSUNG menjadi skor awal.
     * Berbeda dengan TKM: di sana jumlah tertimbang dibagi 4 menjadi persen
     * lalu ditangga; membagi 4 di sini akan menjatuhkan skor penuh ke 1.
     *
     * Aturan responden sama dengan WTMP, dengan NJ = cacah tanggapan
     * pengguna lulusan.
     *
     * @param  array<int, array{a: float, b: float, c: float, d: float}>  $aspek  sembilan aspek, dalam persen
     * @param  int  $lulusan  lulusan tiga tahun (NL)
     * @param  int  $tanggapan  tanggapan pengguna lulusan (NJ)
     */
    public function tki(array $aspek, int $lulusan, int $tanggapan): HasilRumus
    {
        if (count($aspek) !== 9) {
            throw new InvalidArgumentException('TKi butuh tepat sembilan aspek; diterima '.count($aspek).'.');
        }

        foreach ($aspek as $i => $baris) {
            foreach (['a', 'b', 'c', 'd'] as $kunci) {
                if (! array_key_exists($kunci, $baris)) {
                    throw new InvalidArgumentException("TKi butuh kunci `{$kunci}` pada aspek ke-{$i}.");
                }
            }
        }

        $responden = $this->penyesuaianResponden($lulusan, $tanggapan);

        $perAspek = array_map(
            fn (array $x): float => ((4 * $x['a']) + (3 * $x['b']) + (2 * $x['c']) + $x['d']) / 100,
            array_values($aspek),
        );
        $rata = array_sum($perAspek) / 9;

        return $this->hasilBerResponden('TKi', round($rata, 4), $rata, $responden, [
            'tki_per_aspek' => array_map(fn (float $t): float => round($t, 4), $perAspek),
        ]);
    }

    /**
     * Aturan responden E43/E44: Prmin = 0,3 bila NL ≥ 150, selain itu
     * 0,5 − (NL/150)·0,2 (program kecil harus melacak porsi lebih besar);
     * PJ = min(1, NJ/NL); faktor = min(1, PJ/Prmin).
     *
     * @return array{prmin: float, pj: float, faktor: float}
     */
    private function penyesuaianResponden(int $lulusan, int $terlacak): array
    {
        if ($lulusan <= 0) {
            throw new InvalidArgumentException(
                'Jumlah lulusan tiga tahun nol; aturan responden tidak bisa diterapkan.'
            );
        }

        $prmin = $lulusan >= 150 ? 0.3 : 0.5 - (($lulusan / 150) * 0.2);
        $pj = min(1.0, $terlacak / $lulusan);

        return ['prmin' => $prmin, 'pj' => $pj, 'faktor' => min(1.0, $pj / $prmin)];
    }

    /**
     * Hasil WTMP/TKi: skorTepat = potong2(skor awal × faktor responden),
     * dengan rasio responden di komponen (4 desimal) dan catatan bila
     * faktornya memotong skor.
     *
     * Lantai 1 SETELAH faktor: kertas kerja menulis "PJ/Prmin × Skor"
     * apa adanya, yang bisa mencapai 0 (mis. tidak ada lulusan terlacak),
     * padahal Buku 4 hanya mengenal skor 1–4. SIGAP memberi lantai 1, seperti
     * BKD dan RIPK; `faktor` dan `skor_awal` di komponen tetap mentah supaya
     * efek faktornya terlihat. Dicatat sebagai pertanyaan ke LAMDIK.
     *
     * @param  array{prmin: float, pj: float, faktor: float}  $responden
     */
    private function hasilBerResponden(string $kode, float $nilai, float $skorAwal, array $responden, array $komponen): HasilRumus
    {
        ['prmin' => $prmin, 'pj' => $pj, 'faktor' => $faktor] = $responden;
        $skorTepat = self::potong2(max(1.0, $skorAwal * $faktor));

        $persen = fn (float $x): string => number_format($x * 100, 2, ',', '.');

        return new HasilRumus(
            kode: $kode,
            nilai: $nilai,
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: $komponen + [
                'skor_awal' => self::potong2($skorAwal),
                'prmin' => round($prmin, 4),
                'pj' => round($pj, 4),
                'faktor' => round($faktor, 4),
            ],
            catatan: $faktor < 1.0
                ? "Responden {$persen($pj)} % di bawah minimum {$persen($prmin)} %; skor dikali ".number_format($faktor, 4, ',', '.')
                : null,
        );
    }

    // ---- RMKCPL — elemen 45 ---------------------------------------------

    /**
     * Pecahan mata kuliah inti yang asesmennya tertaut ke CPL, dibatasi 1.
     *
     * Pita 2 bukan ambang biasa: SATU mata kuliah saja (> 0) sudah memberi 2;
     * skor 1 hanya bila tidak ada sama sekali. Karena itu tidak memakai
     * `tangga()`.
     */
    public function rmkcpl(int $mkCpl, int $nmk): HasilRumus
    {
        $this->pastikanPembagiSah($nmk, 'NMK');

        $rasio = min(1.0, $mkCpl / $nmk);
        $skor = match (true) {
            $rasio >= 0.2 => 4,
            $rasio >= 0.15 => 3,
            $rasio > 0 => 2,
            default => 1,
        };

        return new HasilRumus(
            kode: 'RMKCPL',
            nilai: round($rasio, 4),
            skor: $skor,
            skorTepat: (float) $skor,
            komponen: compact('mkCpl', 'nmk'),
        );
    }

    // ---- RPEN, RPUB, RPKM — elemen 49, 50, 54 ----------------------------

    /** Penelitian DTPS (elemen 49) per sumber pembiayaan. */
    public function rpen(int $luarNegeri, int $dalamNegeri, int $ptMandiri, int $ndtps): HasilRumus
    {
        return $this->produktivitasTridharma('RPEN', $luarNegeri, $dalamNegeri, $ptMandiri, $ndtps);
    }

    /** PkM DTPS (elemen 54) per sumber pembiayaan; rumusnya sama dengan RPEN. */
    public function rpkm(int $luarNegeri, int $dalamNegeri, int $ptMandiri, int $ndtps): HasilRumus
    {
        return $this->produktivitasTridharma('RPKM', $luarNegeri, $dalamNegeri, $ptMandiri, $ndtps);
    }

    /**
     * Publikasi DTPS (elemen 50), pola RI/RN/RW dengan a = 0,1, b = 1, c = 2.
     *
     * Tingkatnya TIDAK mengikuti huruf kode: RW = NA1 + NB1 + NC1,
     * RN = NA2 + NA3 + NB2 + NC2 + ND1 + ND2, RI = NA4 + NB3 + NC3, masing-
     * masing dibagi NDTPS (tanpa pembagi 3 tahun, berbeda dengan RPEN).
     * ND1 + ND2 masuk RN mengikuti kertas kerja (sel E815–E839); Buku 4
     * tidak menyebutnya (spec §9 jawaban 1).
     *
     * @param  array<string, int>  $publikasi  berkunci na1..na4, nb1..nb3, nc1..nc3, nd1, nd2
     */
    public function rpub(array $publikasi, int $ndtps): HasilRumus
    {
        foreach (['na1', 'na2', 'na3', 'na4', 'nb1', 'nb2', 'nb3', 'nc1', 'nc2', 'nc3', 'nd1', 'nd2'] as $kunci) {
            if (! array_key_exists($kunci, $publikasi)) {
                throw new InvalidArgumentException("RPUB butuh kunci `{$kunci}` pada data publikasi.");
            }
        }

        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $p = $publikasi;
        $rw = ($p['na1'] + $p['nb1'] + $p['nc1']) / $ndtps;
        $rn = ($p['na2'] + $p['na3'] + $p['nb2'] + $p['nc2'] + $p['nd1'] + $p['nd2']) / $ndtps;
        $ri = ($p['na4'] + $p['nb3'] + $p['nc3']) / $ndtps;

        $skorTepat = self::potong2($this->skorTigaTingkat($ri, $rn, $rw, a: 0.1, b: 1, c: 2));

        return new HasilRumus(
            kode: 'RPUB',
            nilai: round($ri, 4),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: [
                'ri' => round($ri, 4),
                'rn' => round($rn, 4),
                'rw' => round($rw, 4),
                'ndtps' => $ndtps,
            ] + $publikasi,
            catatan: 'RN menyertakan ND1 + ND2 sesuai kertas kerja (Buku 4 tidak menyebutnya)',
        );
    }

    /**
     * Rumus bersama RPEN dan RPKM: pola RI/RN/RW (a = 0,05, b = 0,3, c = 1)
     * atas cacah kegiatan per sumber dana.
     *
     * Perangkapnya pembagi 3: cacah adalah jumlah TIGA tahun, sedangkan
     * ambangnya per DTPS per TAHUN, jadi RI = NI/3/NDTPS (begitu pula RN dan
     * RL). Lupa membagi 3 melipatgandakan rasio dan mudah memberi skor 4.
     * "RL" (PT/mandiri) mengisi posisi RW pada pola.
     */
    private function produktivitasTridharma(string $kode, int $ni, int $nn, int $nl, int $ndtps): HasilRumus
    {
        $this->pastikanPembagiSah($ndtps, 'NDTPS');

        $ri = $ni / 3 / $ndtps;
        $rn = $nn / 3 / $ndtps;
        $rl = $nl / 3 / $ndtps;

        $skorTepat = self::potong2($this->skorTigaTingkat($ri, $rn, $rl, a: 0.05, b: 0.3, c: 1));

        return new HasilRumus(
            kode: $kode,
            nilai: round($ri, 4),
            skor: (int) floor($skorTepat),
            skorTepat: $skorTepat,
            komponen: [
                'ri' => round($ri, 4),
                'rn' => round($rn, 4),
                'rl' => round($rl, 4),
            ] + compact('ni', 'nn', 'nl', 'ndtps'),
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
     * Tangga 4/3/2/1 yang dipakai banyak rumus bertangga.
     *
     * `$x` harus rasio MENTAH, bukan `nilai` yang sudah dibulatkan untuk
     * tampilan: pembulatan bisa mengangkat nilai yang masih di bawah ambang
     * ke tepat di ambang (PKIM 0,19997 → 0,2).
     */
    private static function tangga(float $x, float $empat, float $tiga, float $dua): int
    {
        return match (true) {
            $x >= $empat => 4,
            $x >= $tiga => 3,
            $x >= $dua => 2,
            default => 1,
        };
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
