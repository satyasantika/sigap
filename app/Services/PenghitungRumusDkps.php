<?php

namespace App\Services;

use App\Models\NilaiRumus;
use App\Models\Periode;
use App\Models\Rumus;
use App\Models\User;
use App\Support\Rumus\HasilRumus;
use App\Support\Rumus\LaporanPerhitungan;
use App\Support\Rumus\NilaiVariabel;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Jembatan katalog variabel DKPS → KalkulatorRumus → `nilai_rumus`.
 *
 * Pemetaan kode rumus ke pemanggilan metode ditulis eksplisit di satu
 * `match` (panggil()), bukan lewat refleksi: tanda tangan tiap metode
 * berbeda, dan di situlah salah petik variabel paling mudah terbaca.
 *
 * Variabel diambil lewat dua pengakses (`$int`, `$float`) yang sekaligus
 * MENCATAT variabel apa saja yang dipakai rumus itu. Satu sumber itu yang
 * mengisi `komponen['variabel']` dan aturan lewat, sehingga daftar
 * variabel tidak bisa berselisih dengan argumen yang benar-benar dikirim.
 * Variabel kosong dibaca 0 sementara; hasil perhitungannya dibuang dan
 * rumus dilewati dengan alasan yang menyebut lembar kosongnya.
 *
 * Append-only dan tanpa duplikat: hasil yang sama dengan baris terakhir
 * (nilai, skor_tepat, dan variabel sumbernya) tidak disimpan lagi.
 */
final class PenghitungRumusDkps
{
    private const TIDAK_BERUBAH = 'sama dengan hasil terakhir';

    /** Lima dimensi TKM pada Tabel 5, sesuai urutan katalog. */
    private const DIMENSI_TKM = ['RELIABILITY', 'RESPONSIVENESS', 'ASSURANCE', 'EMPATHY', 'TANGIBLE'];

    /** Sembilan aspek kepuasan pengguna lulusan pada Tabel 24.1. */
    private const ASPEK_TKI = ['ETIKA', 'KU', 'BHS', 'TI', 'KOM', 'TIM', 'PD', 'BKRITIS', 'KREATIVITAS'];

    public function __construct(
        private PembacaVariabelDkps $pembaca,
        private KalkulatorRumus $kalkulator,
    ) {}

    /**
     * Menghitung semua rumus (kecuali NA, milik KalkulatorNa) yang
     * variabelnya tersedia pada periode itu.
     *
     * @throws RuntimeException periode terkunci
     */
    public function hitungSemua(Periode $periode, ?User $oleh = null): LaporanPerhitungan
    {
        if ($periode->terkunci()) {
            throw new RuntimeException('Periode terkunci; rumus tidak dihitung ulang.');
        }

        $variabel = $this->pembaca->semua($periode);
        $disimpan = $dilewati = $tidakBerubah = [];

        foreach (Rumus::where('kode', '!=', 'NA')->orderBy('created_at')->orderBy('kode')->pluck('kode') as $kode) {
            $dipakai = [];
            $kosong = [];

            $ambil = function (string $kodeVariabel) use ($variabel, &$dipakai, &$kosong): float {
                $nv = $variabel[$kodeVariabel]
                    ?? throw new LogicException("Variabel DKPS `{$kodeVariabel}` tidak ada di katalog.");
                $dipakai[$kodeVariabel] = $nv;

                if ($nv->nilai === null) {
                    $kosong[$kodeVariabel] = "Variabel {$kodeVariabel} kosong: ".($nv->catatan ?? 'nilai tidak terdefinisi');

                    return 0.0;
                }

                return $nv->nilai;
            };
            $int = fn (string $k): int => (int) round($ambil($k));
            $float = fn (string $k): float => $ambil($k);

            try {
                $hasil = $this->panggil($kode, $int, $float);
                $galat = null;
            } catch (InvalidArgumentException $e) {
                $hasil = null;
                $galat = $e->getMessage();
            }

            if ($kosong !== []) {
                $dilewati[$kode] = implode('; ', $kosong);

                continue;
            }

            if ($hasil === null) {
                $dilewati[$kode] = $galat;

                continue;
            }

            $hasil = $this->denganVariabel($hasil, $dipakai);

            if ($this->samaDenganTerakhir($hasil, $periode)) {
                $tidakBerubah[$kode] = self::TIDAK_BERUBAH;

                continue;
            }

            $disimpan[$kode] = $this->kalkulator->simpan($hasil, $periode, $oleh);
        }

        return new LaporanPerhitungan($disimpan, $dilewati, $tidakBerubah);
    }

    /**
     * Satu-satunya tempat kode rumus dipetakan ke metode KalkulatorRumus.
     *
     * @param  callable(string): int  $int
     * @param  callable(string): float  $float
     */
    private function panggil(string $kode, callable $int, callable $float): HasilRumus
    {
        $k = $this->kalkulator;

        return match ($kode) {
            'PDS3' => $k->pds3(
                nds3: $int('N_DTPS_DOKTOR'), ndtps: $int('N_DTPS'),
                ndlk: $int('N_DTPS_LK'), ndgb: $int('N_DTPS_GB'),
            ),
            'PGBLKL' => $k->pgblkl(
                ndgb: $int('N_DTPS_GB'), ndlk: $int('N_DTPS_LK'),
                ndl: $int('N_DTPS_LEKTOR'), ndtps: $int('N_DTPS'),
            ),
            'RK' => $k->rk(
                n1: $int('N_KERJASAMA_PENDIDIKAN'), n2: $int('N_KERJASAMA_PENELITIAN'),
                n3: $int('N_KERJASAMA_PKM'), ni: $int('N_KERJASAMA_INTERNASIONAL'),
                nn: $int('N_KERJASAMA_NASIONAL'), nw: $int('N_KERJASAMA_WILAYAH'),
                ndtps: $int('N_DTPS'),
            ),
            'PPDTPS' => $k->ppdtps(
                dosenBerpublikasi: $int('N_DTPS_PUBLIKASI'), ndtps: $int('N_DTPS'),
                jumlahArtikel: $int('N_ARTIKEL_PUBLIKASI'),
            ),
            'RSA' => $k->rsa(nas: $int('NAS'), ndtps: $int('N_DTPS')),
            'RIPK' => $k->ripk($this->rataIpkTertimbang($int, $float)),
            'RMS' => $k->rms($float('RT_MS_LULUSAN')),
            'PKMS' => $k->pkms(lulusan: $int('N_LULUSAN_ANGKATAN'), diterima: $int('N_DITERIMA_ANGKATAN')),
            'WTMP' => $k->wtmp(
                kurang6Bulan: $int('N_LULUSAN_WT_6BLN'), antara6Dan12Bulan: $int('N_LULUSAN_WT_612BLN'),
                lebih12Bulan: $int('N_LULUSAN_WT_12BLN'), lulusan: $int('N_LULUSAN_3TH'),
                terlacak: $int('N_LULUSAN_TERLACAK_3TH'),
            ),
            'TKM' => $k->tkm(array_map(fn (string $d): array => [
                'a' => $float("{$d}_SB"), 'b' => $float("{$d}_B"),
                'c' => $float("{$d}_C"), 'd' => $float("{$d}_KB"),
            ], self::DIMENSI_TKM)),
            'TKi' => $k->tki(
                aspek: array_map(fn (string $a): array => [
                    'a' => $float("{$a}_SB"), 'b' => $float("{$a}_B"),
                    'c' => $float("{$a}_C"), 'd' => $float("{$a}_K"),
                ], self::ASPEK_TKI),
                lulusan: $int('N_LULUSAN_TKP_3TH'), tanggapan: $int('N_TKP_TERLACAK_3TH'),
            ),
            'NDTPSPK' => $k->ndtpspk(dtpsPk: $int('N_DTPS_PK'), ndtps: $int('N_DTPS')),
            'NTENDIKPK' => $k->ntendikpk(tendikPk: $int('N_TENDIK_PK'), tendik: $int('N_TENDIK')),
            'RRD' => $k->rrd(rekognisi: $int('N_REKOGNISI_DTPS'), ndtps: $int('N_DTPS')),
            'PRESMA' => $k->presma(
                ni: $int('N_PRESTASI_INTERNASIONAL'), nn: $int('N_PRESTASI_NASIONAL'),
                nw: $int('N_PRESTASI_LOKAL'), nm: $int('N_MHS_PPG'),
            ),
            'PKIM' => $k->pkim(
                paten: $int('N_MHS_PATEN'), bukuIsbn: $int('N_MHS_BUKU_ISBN'),
                karyaSeni: $int('N_MHS_SENI'), publikasi: $int('N_MHS_PUBLIKASI_MIN_SINTA5'),
                nm: $int('N_MHS_PPG'),
            ),
            'BKD' => $k->bkd($float('RBK_DTPS')),
            'RGP' => $k->rgp(
                ngp: $int('N_GP'), nbs: $int('N_BIDANG_STUDI'),
                magisterDoktor: $int('N_GP_MAGISTER_DOKTOR'), madyaUtama: $int('N_GP_MADYA_UTAMA'),
                serdik: $int('N_GP_SERDIK'),
            ),
            'DANA' => $k->dana(
                biayaOperasionalPendidikan: $float('B_OP'), danaPenelitian: $float('B_PENELITIAN'),
                danaPkm: $float('B_PKM'), nm: $int('N_MHS_PPG'), ndtps: $int('N_DTPS'),
            ),
            'PDIPPKM' => $k->pdippkm(dosenIntegrasi: $int('N_INTEGRASI'), ndtps: $int('N_DTPS')),
            'PMKI' => $k->pmki(mkIntegrasi: $int('N_INTEGRASI_MK'), nmk: $int('N_MK_KU')),
            'RPPL' => $k->rppl($float('RT_PEMBIMBINGAN_PPL')),
            'PMTK' => $k->pmtk(
                diterimaTs1: $int('N_MHS_DITERIMA_TS1'), diterimaTs: $int('N_MHS_DITERIMA_TS'),
                lulusTs1: $int('N_LULUS_PADA_TS1'), lulusTs: $int('N_LULUS_PADA_TS'),
            ),
            'RMKCPL' => $k->rmkcpl(mkCpl: $int('N_MK_CPL'), nmk: $int('N_MK_KU')),
            'RPEN' => $k->rpen(
                luarNegeri: $int('N_PENELITIAN_SB_LN'), dalamNegeri: $int('N_PENELITIAN_SB_DN'),
                ptMandiri: $int('N_PENELITIAN_SB_PT'), ndtps: $int('N_DTPS'),
            ),
            'RPKM' => $k->rpkm(
                luarNegeri: $int('N_PKM_SB_LN'), dalamNegeri: $int('N_PKM_SB_DN'),
                ptMandiri: $int('N_PKM_SB_PT'), ndtps: $int('N_DTPS'),
            ),
            'RPUB' => $k->rpub([
                'na1' => $int('NA1'), 'na2' => $int('NA2'), 'na3' => $int('NA3'), 'na4' => $int('NA4'),
                'nb1' => $int('NB1'), 'nb2' => $int('NB2'), 'nb3' => $int('NB3'),
                'nc1' => $int('NC1'), 'nc2' => $int('NC2'), 'nc3' => $int('NC3'),
                'nd1' => $int('ND1'), 'nd2' => $int('ND2'),
            ], ndtps: $int('N_DTPS')),
            default => throw new LogicException("Rumus `{$kode}` belum dipetakan di PenghitungRumusDkps."),
        };
    }

    /**
     * Rata IPK Tabel 21 ditimbang jumlah lulusan TS-2, TS-1, dan TS.
     *
     * Semua variabel dibaca lebih dulu supaya pengakses mencatat keenamnya
     * sebelum pembagi diperiksa.
     *
     * @param  callable(string): int  $int
     * @param  callable(string): float  $float
     */
    private function rataIpkTertimbang(callable $int, callable $float): float
    {
        $tahun = ['TS2', 'TS1', 'TS'];
        $lulusan = array_map(fn (string $t): int => $int("N_LULUSAN_{$t}"), $tahun);
        $ipk = array_map(fn (string $t): float => $float("RT_IPK_{$t}"), $tahun);
        $total = array_sum($lulusan);

        if ($total <= 0) {
            throw new InvalidArgumentException('Σ lulusan Tabel 21 nol; rata IPK tertimbang tidak terdefinisi.');
        }

        return array_sum(array_map(fn (int $n, float $r): float => $n * $r, $lulusan, $ipk)) / $total;
    }

    /**
     * HasilRumus readonly: buat salinan dengan `komponen['variabel']`.
     *
     * @param  array<string, NilaiVariabel>  $dipakai
     */
    private function denganVariabel(HasilRumus $hasil, array $dipakai): HasilRumus
    {
        $daftar = array_values(array_map(fn (NilaiVariabel $v): array => [
            'kode' => $v->kode,
            'nilai' => $v->nilai,
            'lembar' => $v->lembar,
            'jumlah_baris' => $v->jumlahBaris,
            'belum_terverifikasi' => $v->belumTerverifikasi,
        ], $dipakai));

        return new HasilRumus(
            kode: $hasil->kode,
            nilai: $hasil->nilai,
            skor: $hasil->skor,
            memenuhiSyarat3Tahun: $hasil->memenuhiSyarat3Tahun,
            memenuhiSyarat5Tahun: $hasil->memenuhiSyarat5Tahun,
            komponen: ['variabel' => $daftar] + $hasil->komponen,
            catatan: $hasil->catatan,
            skorTepat: $hasil->skorTepat,
        );
    }

    /**
     * Sama dengan baris terakhir kode itu pada periode itu?
     *
     * `nilai` dan `skor_tepat` dibulatkan ke presisi kolomnya (4 dan 2
     * desimal) sebelum dibandingkan, karena yang tersimpan sudah dibulatkan
     * basis data. Variabel dinormalkan (nilai float, kunci terurut) supaya
     * 2 dan 2.0 dari kolom JSON tidak dianggap berbeda.
     */
    private function samaDenganTerakhir(HasilRumus $hasil, Periode $periode): bool
    {
        $terakhir = NilaiRumus::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $periode->getKey())
            ->where('rumus_kode', $hasil->kode)
            ->orderByDesc('dihitung_pada')
            ->orderByDesc('id')
            ->first();

        return $terakhir !== null
            && self::angkaSama($hasil->nilai, $terakhir->nilai, 4)
            && self::angkaSama($hasil->skorTepat, $terakhir->skor_tepat, 2)
            && self::sidikVariabel($hasil->komponen['variabel'] ?? [])
                === self::sidikVariabel($terakhir->komponen['variabel'] ?? []);
    }

    private static function angkaSama(int|float|string|null $a, int|float|string|null $b, int $presisi): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        return abs(round((float) $a, $presisi) - round((float) $b, $presisi)) < 1e-9;
    }

    /** @param  array<int, array<string, mixed>>  $variabel */
    private static function sidikVariabel(array $variabel): string
    {
        $normal = array_map(function (array $v): array {
            $v['nilai'] = $v['nilai'] === null ? null : (float) $v['nilai'];

            return $v;
        }, $variabel);

        return json_encode(self::urutkanKunci($normal), JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function urutkanKunci(mixed $nilai): mixed
    {
        if (! is_array($nilai)) {
            return $nilai;
        }

        if (! array_is_list($nilai)) {
            ksort($nilai);
        }

        return array_map(self::urutkanKunci(...), $nilai);
    }
}
