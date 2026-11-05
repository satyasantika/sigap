<?php

namespace App\Services;

use App\Enums\CaraBacaVariabel;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\VariabelDkps;
use App\Support\Dkps\SkemaLembar;
use App\Support\Rumus\NilaiVariabel;
use InvalidArgumentException;

/**
 * Membaca katalog variabel DKPS (tabel `variabel_dkps`) menjadi angka untuk
 * satu periode.
 *
 * Tidak ada nama kolom yang ditulis ulang di sini kecuali untuk variabel
 * `khusus`: lembar, kolom, baris tetap, dan pilihan semuanya datang dari
 * katalog, dan kolom bertipe `hitung` dibaca dari SkemaLembar::hitung()
 * supaya satu rumus kolom tidak ditulis dua kali.
 *
 * Baris yang dihitung adalah semua baris hidup pada periode itu,
 * terverifikasi maupun belum; yang belum terverifikasi dilaporkan, tidak
 * disaring. Lingkup periode dilepas karena perintah artisan berjalan tanpa
 * sesi pengguna.
 */
final class PembacaVariabelDkps
{
    /** Variabel `khusus` yang dihitung di kelas ini; kode lain ditolak. */
    private const KHUSUS = [
        'N_DTPS', 'N_DTPS_DOKTOR', 'N_DTPS_GB', 'N_DTPS_LK', 'N_DTPS_LEKTOR',
        'RT_PEMBIMBINGAN_PPL', 'RT_MS_LULUSAN', 'N_DTPS_PUBLIKASI',
    ];

    private const JABATAN = [
        'N_DTPS_GB' => 'Guru Besar',
        'N_DTPS_LK' => 'Lektor Kepala',
        'N_DTPS_LEKTOR' => 'Lektor',
    ];

    /** @var array<string, array<int, DkpsBaris>> "periode|lembar" ⇒ baris */
    private array $baris = [];

    /** @var array<string, array<string, int|float|null>> id baris ⇒ hasil kolom hitung */
    private array $hitungan = [];

    /** @var array<string, DkpsButir> */
    private array $butir = [];

    /** @var array<string, SkemaLembar> */
    private array $skema = [];

    /** @return array<string, NilaiVariabel> kode ⇒ nilai, urut `urutan` */
    public function semua(Periode $periode): array
    {
        $this->kosongkanSinggahan();
        $hasil = [];

        foreach (VariabelDkps::orderBy('urutan')->get() as $variabel) {
            $hasil[$variabel->kode] = $this->baca($periode, $variabel);
        }

        $this->kosongkanSinggahan();

        return $hasil;
    }

    /** @throws InvalidArgumentException kode tidak ada di katalog */
    public function satu(Periode $periode, string $kode): NilaiVariabel
    {
        $variabel = VariabelDkps::where('kode', $kode)->first()
            ?? throw new InvalidArgumentException("Variabel DKPS `{$kode}` tidak ada di katalog.");

        $this->kosongkanSinggahan();
        $hasil = $this->baca($periode, $variabel);
        $this->kosongkanSinggahan();

        return $hasil;
    }

    private function kosongkanSinggahan(): void
    {
        $this->baris = [];
        $this->hitungan = [];
    }

    private function baca(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        // Diperiksa sebelum lembar kosong, supaya kode khusus yang salah ketik
        // tidak bersembunyi di balik nilai null.
        if ($v->cara === CaraBacaVariabel::Khusus && ! in_array($v->kode, self::KHUSUS, true)) {
            throw new InvalidArgumentException("Variabel khusus `{$v->kode}` tidak dikenal pembaca.");
        }

        $kosong = array_values(array_filter($v->lembar, fn (string $l) => $this->barisLembar($periode, $l) === []));

        if (count($kosong) === count($v->lembar)) {
            return new NilaiVariabel($v->kode, null, $v->lembar, 0, 0,
                implode('; ', array_map(fn (string $l) => "Tabel {$l} belum berisi baris", $kosong)));
        }

        return match ($v->cara) {
            CaraBacaVariabel::CacahBaris => $this->cacahBaris($periode, $v),
            CaraBacaVariabel::CacahCentang => $this->cacahCentang($periode, $v),
            CaraBacaVariabel::CacahPilihan => $this->cacahPilihan($periode, $v),
            CaraBacaVariabel::CacahTerisi => $this->cacahTerisi($periode, $v),
            CaraBacaVariabel::CacahUnik => $this->cacahUnik($periode, $v),
            CaraBacaVariabel::JumlahKolom => $this->jumlahKolom($periode, $v),
            CaraBacaVariabel::RataKolom => $this->rataKolom($periode, $v),
            CaraBacaVariabel::NilaiSel => $this->nilaiSel($periode, $v),
            CaraBacaVariabel::JumlahSel => $this->jumlahSel($periode, $v),
            CaraBacaVariabel::Khusus => $this->khusus($periode, $v),
        };
    }

    // ── Satu metode per cara ────────────────────────────────────────────

    private function cacahBaris(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dibaca = $this->barisTersaring($periode, $v);

        return $this->hasil($v, count($dibaca), $dibaca);
    }

    private function cacahCentang(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        return $this->cacahYang($periode, $v, fn (mixed $n) => self::tercentang($n));
    }

    private function cacahPilihan(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $pilihan = $v->parameter['nilai'] ?? [];

        return $this->cacahYang($periode, $v, fn (mixed $n) => in_array($n, $pilihan, true));
    }

    private function cacahTerisi(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        return $this->cacahYang($periode, $v, fn (mixed $n) => filled($n));
    }

    private function cacahUnik(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dibaca = $this->barisTersaring($periode, $v);
        $nilai = array_map(fn (array $b) => $this->sel($b[0], $b[1], $v->kolom), $dibaca);

        return $this->hasil($v, self::cacahUnikDari($nilai), $dibaca);
    }

    private function jumlahKolom(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dibaca = $this->barisTersaring($periode, $v);
        $angka = $this->angkaKolom($v, $dibaca);

        if ($angka === []) {
            return $this->hasil($v, null, $dibaca, "Kolom {$v->kolom} pada ".self::namaLembar($v->lembar).' belum terisi');
        }

        return $this->hasil($v, array_sum($angka), $dibaca);
    }

    private function rataKolom(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dibaca = $this->barisTersaring($periode, $v);
        $angka = $this->angkaKolom($v, $dibaca);

        if ($angka === []) {
            return $this->hasil($v, null, $dibaca, "Kolom {$v->kolom} pada ".self::namaLembar($v->lembar).' belum terisi');
        }

        return $this->hasil($v, array_sum($angka) / count($angka), $dibaca);
    }

    /**
     * Angka kolom variabel pada baris-baris itu; baris yang selnya kosong
     * tidak ikut, bukan dibaca 0.
     *
     * @param  array<int, array{0: string, 1: DkpsBaris}>  $dibaca
     * @return array<int, float>
     */
    private function angkaKolom(VariabelDkps $v, array $dibaca): array
    {
        return array_values(array_filter(
            array_map(fn (array $b) => self::angka($this->sel($b[0], $b[1], $v->kolom)), $dibaca),
            fn (?float $n) => $n !== null,
        ));
    }

    private function nilaiSel(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        return $this->jumlahDariSel($periode, $v, [[$v->parameter['baris'], $v->parameter['kolom']]]);
    }

    private function jumlahSel(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        return $this->jumlahDariSel($periode, $v, $v->parameter['sel']);
    }

    private function khusus(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        return match ($v->kode) {
            'N_DTPS', 'N_DTPS_DOKTOR', 'N_DTPS_GB', 'N_DTPS_LK', 'N_DTPS_LEKTOR' => $this->dtps($periode, $v),
            'RT_PEMBIMBINGAN_PPL' => $this->rataPembimbingan($periode, $v),
            'RT_MS_LULUSAN' => $this->rataMasaStudi($periode, $v),
            'N_DTPS_PUBLIKASI' => $this->dtpsPublikasi($periode, $v),
        };
    }

    // ── Variabel khusus ─────────────────────────────────────────────────

    /**
     * Dosen unik menurut NIDN/NIDK gabungan Tabel 6 dan 7: orang yang sama
     * di dua lembar dihitung sekali. Baris tanpa NIDN tidak bisa dicocokkan,
     * jadi masing-masing dihitung satu orang. Lembar dibaca urut katalog
     * (6 lalu 7), sehingga jabatan Tabel 7 menimpa jabatan Tabel 6.
     */
    private function dtps(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dosen = [];
        $dibaca = $this->barisTersaring($periode, $v);

        foreach ($dibaca as [$lembar, $baris]) {
            $nidn = trim((string) $this->sel($lembar, $baris, 'nidn_nidk'));
            $kunci = $nidn === '' ? 'tanpa-nidn:'.$baris->getKey() : $nidn;
            $jabatan = $this->sel($lembar, $baris, 'jabatan_akademik');

            $dosen[$kunci] ??= ['doktor' => false, 'jabatan' => null];
            $dosen[$kunci]['doktor'] = $dosen[$kunci]['doktor'] || filled($this->sel($lembar, $baris, 'doktor'));

            if (filled($jabatan)) {
                $dosen[$kunci]['jabatan'] = $jabatan;
            }
        }

        $nilai = match ($v->kode) {
            'N_DTPS' => count($dosen),
            'N_DTPS_DOKTOR' => count(array_filter($dosen, fn (array $d) => $d['doktor'])),
            default => count(array_filter($dosen, fn (array $d) => $d['jabatan'] === self::JABATAN[$v->kode])),
        };

        return $this->hasil($v, $nilai, $dibaca);
    }

    /** Σ sel pertemuan ÷ banyak sel pertemuan yang terisi (per dosen per tahun). */
    private function rataPembimbingan(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dibaca = $this->barisTersaring($periode, $v);
        $sel = [];

        foreach ($dibaca as [$lembar, $baris]) {
            foreach (['pertemuan_ts2', 'pertemuan_ts1', 'pertemuan_ts'] as $kolom) {
                $sel[] = self::angka($this->sel($lembar, $baris, $kolom));
            }
        }

        $terisi = array_filter($sel, fn (?float $n) => $n !== null);

        if ($terisi === []) {
            return $this->hasil($v, null, $dibaca, self::namaLembar($v->lembar).' belum berisi jumlah pertemuan');
        }

        return $this->hasil($v, array_sum($terisi) / count($terisi), $dibaca);
    }

    /** Rata `rata_masa_studi` berbobot `jumlah_lulusan` atas angkatan TS-1 dan TS. */
    private function rataMasaStudi(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dibaca = array_values(array_filter(
            $this->barisTersaring($periode, $v),
            fn (array $b) => in_array($b[1]->nilai('_baris'), ['TS-1', 'TS'], true),
        ));
        $bobot = 0.0;
        $tertimbang = 0.0;

        foreach ($dibaca as [$lembar, $baris]) {
            $rata = self::angka($this->sel($lembar, $baris, 'rata_masa_studi'));
            $lulusan = self::angka($this->sel($lembar, $baris, 'jumlah_lulusan')) ?? 0.0;

            if ($rata === null) {
                continue;
            }

            $bobot += $lulusan;
            $tertimbang += $rata * $lulusan;
        }

        if ($bobot == 0) {
            return $this->hasil($v, null, $dibaca, self::namaLembar($v->lembar).' baris TS-1 dan TS belum berisi lulusan');
        }

        return $this->hasil($v, $tertimbang / $bobot, $dibaca);
    }

    private function dtpsPublikasi(Periode $periode, VariabelDkps $v): NilaiVariabel
    {
        $dibaca = $this->barisTersaring($periode, $v);
        $nama = array_map(fn (array $b) => $this->sel($b[0], $b[1], 'nama_dtps'), $dibaca);

        return $this->hasil($v, self::cacahUnikDari($nama), $dibaca);
    }

    // ── Pembantu ────────────────────────────────────────────────────────

    /** Cacah baris tersaring yang kolom variabelnya memenuhi `$syarat`. */
    private function cacahYang(Periode $periode, VariabelDkps $v, callable $syarat): NilaiVariabel
    {
        $dibaca = $this->barisTersaring($periode, $v);
        $cocok = array_filter($dibaca, fn (array $b) => $syarat($this->sel($b[0], $b[1], $v->kolom)));

        return $this->hasil($v, count($cocok), $dibaca);
    }

    /**
     * Jumlah sel baris tetap. Baris tetap yang belum ada dibaca 0 — lembarnya
     * sudah terisi (lembar kosong sudah menjadi null di baca()), jadi baris
     * yang tidak ada berarti memang tidak diisi. Pada baris yang ADA, sel
     * kosong bertipe `angka` (cacah: lulusan, terlacak, wt_*) berarti "tidak
     * ada" dan dibaca 0, seperti Excel; sel kosong bertipe `desimal` (ukuran:
     * IPK, persen kepuasan) belum terisi, jadi variabelnya null dengan
     * catatan. Kolom `hitung` mengikuti sel(): null bila semua masukannya
     * kosong.
     *
     * @param  array<int, array{0: string, 1: string}>  $sel  [kunci baris, kolom]
     */
    private function jumlahDariSel(Periode $periode, VariabelDkps $v, array $sel): NilaiVariabel
    {
        $kunciBaris = array_unique(array_column($sel, 0));
        $dibaca = array_values(array_filter(
            $this->barisTersaring($periode, $v),
            fn (array $b) => in_array($b[1]->nilai('_baris'), $kunciBaris, true),
        ));
        $jumlah = 0.0;
        $kosong = [];

        foreach ($sel as [$baris, $kolom]) {
            foreach ($dibaca as [$lembar, $b]) {
                if ($b->nilai('_baris') !== $baris) {
                    continue;
                }

                $isi = $this->sel($lembar, $b, $kolom);
                $angka = self::angka($isi) ?? (blank($isi) && $this->kolomCacah($lembar, $kolom) ? 0.0 : null);

                if ($angka === null) {
                    $kosong[] = $this->namaSel($lembar, $baris, $kolom);

                    continue;
                }

                $jumlah += $angka;
            }
        }

        if ($kosong !== []) {
            return $this->hasil($v, null, $dibaca, implode('; ', $kosong));
        }

        return $this->hasil($v, $jumlah, $dibaca);
    }

    /** Kolom isian bertipe `angka` (cacah), menurut skema. */
    private function kolomCacah(string $lembar, string $kunciKolom): bool
    {
        return (collect($this->skema($lembar)->kolom())->firstWhere('kunci', $kunciKolom)['tipe'] ?? null) === 'angka';
    }

    /**
     * "Tabel 21 baris TS: Indeks Prestasi Kumulatif (IPK) › Rata-rata belum
     * terisi" — label baris, kelompok kepala terdekat, dan label kolom dari
     * skema, supaya pengisi tahu sel mana yang dimaksud.
     */
    private function namaSel(string $lembar, string $kunciBaris, string $kunciKolom): string
    {
        $skema = $this->skema($lembar);
        $baris = collect($skema->barisTetap())->firstWhere('kunci', $kunciBaris)['label'] ?? $kunciBaris;
        $kolom = collect($skema->kolom())->firstWhere('kunci', $kunciKolom) ?? [];
        $kelompok = $kolom['kelompok'] ?? [];
        $label = implode(' › ', array_filter([end($kelompok) ?: null, $kolom['label'] ?? $kunciKolom]));

        return "Tabel {$lembar} baris {$baris}: {$label} belum terisi";
    }

    /**
     * Baris semua lembar variabel yang lolos `parameter.filter`, sebagai
     * pasangan [kode lembar, baris].
     *
     * @return array<int, array{0: string, 1: DkpsBaris}>
     */
    private function barisTersaring(Periode $periode, VariabelDkps $v): array
    {
        $filter = $v->parameter['filter'] ?? null;
        $hasil = [];

        foreach ($v->lembar as $lembar) {
            foreach ($this->barisLembar($periode, $lembar) as $baris) {
                if ($filter === null || $this->lolosFilter($filter, $lembar, $baris)) {
                    $hasil[] = [$lembar, $baris];
                }
            }
        }

        return $hasil;
    }

    private function lolosFilter(array $filter, string $lembar, DkpsBaris $baris): bool
    {
        $nilai = $this->sel($lembar, $baris, $filter['kolom']);

        return match (true) {
            isset($filter['nilai']) => in_array($nilai, $filter['nilai'], true),
            ($filter['terisi'] ?? false) === true => filled($nilai),
            ($filter['centang'] ?? false) === true => self::tercentang($nilai),
            default => throw new InvalidArgumentException("Filter variabel pada kolom `{$filter['kolom']}` tidak dikenal."),
        };
    }

    /** @param  array<int, array{0: string, 1: DkpsBaris}>  $dibaca */
    private function hasil(VariabelDkps $v, int|float|null $nilai, array $dibaca, ?string $catatan = null): NilaiVariabel
    {
        return new NilaiVariabel(
            kode: $v->kode,
            nilai: $nilai === null ? null : (float) $nilai,
            lembar: $v->lembar,
            jumlahBaris: count($dibaca),
            belumTerverifikasi: count(array_filter($dibaca, fn (array $b) => $b[1]->diverifikasi_pada === null)),
            catatan: $catatan,
        );
    }

    /**
     * Nilai satu kolom satu baris: kolom `hitung` dari skema, lainnya dari
     * `data`. Baris yang belum lengkap sehingga hitung() menolak dibaca
     * sebagai sel kosong, bukan menggagalkan seluruh variabel. Kolom hitung
     * yang semua kolom masukannya kosong juga sel kosong: rumus `jumlah`
     * atas sel kosong memberi 0, dan 0 itu bukan isian pengguna.
     */
    private function sel(string $lembar, DkpsBaris $baris, string $kunci): mixed
    {
        $skema = $this->skema($lembar);
        $rumus = array_column($skema->kolomHitung(), 'rumus', 'kunci');

        if (! array_key_exists($kunci, $rumus)) {
            return $baris->nilai($kunci);
        }

        $masukan = array_unique($this->daunRumus($skema, $rumus, $kunci));

        if (array_filter($masukan, fn (string $k) => filled($baris->nilai($k))) === []) {
            return null;
        }

        if (! isset($this->hitungan[$baris->getKey()])) {
            try {
                // Kunci baris tetap disimpan normalkan() sebagai `_baris`;
                // kunci_normal berbentuk "22|22|ts" dan bukan kunci baris.
                $this->hitungan[$baris->getKey()] = $skema->hitung($baris->data ?? [], $baris->nilai('_baris'));
            } catch (InvalidArgumentException) {
                $this->hitungan[$baris->getKey()] = [];
            }
        }

        return $this->hitungan[$baris->getKey()][$kunci] ?? null;
    }

    /**
     * Kolom isian yang menjadi masukan kolom hitung `$kunci`, menelusuri
     * kolom hitung lain yang dirujuknya (Tabel 8: rata per semester ←
     * jumlah sks ← enam kolom sks). Jenis rumus mengikuti
     * SkemaLembar::hitungSatu(); jenis baru harus ditambahkan di sini juga.
     *
     * @param  array<string, array<string, mixed>>  $rumus  kunci kolom hitung ⇒ rumus
     * @return array<int, string>
     */
    private function daunRumus(SkemaLembar $skema, array $rumus, string $kunci): array
    {
        if (! array_key_exists($kunci, $rumus)) {
            return [$kunci];
        }

        $r = $rumus[$kunci];
        $rujukan = match ($r['jenis']) {
            'jumlah', 'rata' => $r['kolom'],
            'bagi' => [$r['kolom']],
            'selisih_tahun' => [$r['dari'], $r['sampai']],
            // masaStudiRata() hanya membaca kolom `lulus_akhir_*`.
            'masa_studi_rata' => array_values(array_filter(
                array_column($skema->kolomInput(), 'kunci'),
                fn (string $k) => str_starts_with($k, 'lulus_akhir_'),
            )),
            default => throw new InvalidArgumentException("Rumus `{$r['jenis']}` tidak dikenal pembaca."),
        };

        return array_merge(...array_map(fn (string $k) => $this->daunRumus($skema, $rumus, $k), $rujukan));
    }

    /** @return array<int, DkpsBaris> */
    private function barisLembar(Periode $periode, string $lembar): array
    {
        return $this->baris[$periode->getKey().'|'.$lembar] ??= DkpsBaris::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $periode->getKey())
            ->where('dkps_butir_id', $this->butirLembar($lembar)->getKey())
            ->where('lembar_kode', $lembar)
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    /** Kode tabel = bagian sebelum titik: lembar "24.2" milik Tabel 24. */
    private function butirLembar(string $lembar): DkpsButir
    {
        $kode = explode('.', $lembar)[0];

        return $this->butir[$kode] ??= DkpsButir::dariKode($kode);
    }

    private function skema(string $lembar): SkemaLembar
    {
        return $this->skema[$lembar] ??= $this->butirLembar($lembar)->skema($lembar);
    }

    private static function tercentang(mixed $nilai): bool
    {
        return $nilai === true || $nilai === 1 || $nilai === '1';
    }

    /** Nilai teks unik: dirapikan, yang kosong diabaikan, tidak peka huruf besar. */
    private static function cacahUnikDari(array $nilai): int
    {
        $rapi = array_map(fn (mixed $n) => mb_strtolower(trim((string) $n)), $nilai);

        return count(array_unique(array_filter($rapi, fn (string $n) => $n !== '')));
    }

    private static function angka(mixed $nilai): ?float
    {
        return is_int($nilai) || is_float($nilai) || (is_string($nilai) && is_numeric($nilai))
            ? (float) $nilai
            : null;
    }

    /** @param  array<int, string>  $lembar */
    private static function namaLembar(array $lembar): string
    {
        return 'Tabel '.implode(', ', $lembar);
    }
}
