<?php

namespace App\Support\Dkps;

use App\Exceptions\TahunDiLuarJendela;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Services\JendelaTs;
use App\Support\Impor\NormalisasiKunci;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Penafsir tunggal skema satu lembar DKPS (data/dkps-tabel.json).
 *
 * Layar isian, impor tempel, penyimpan baris, dan progres semuanya bertanya ke
 * kelas ini, bukan membaca json sendiri-sendiri — kalau tidak, aturan seperti
 * "koma desimal diterima" atau "kolom nonaktif tidak wajib" akan berbeda
 * antara layar dan impor, dan baris yang lolos di satu jalur ditolak di jalur
 * lain.
 */
final class SkemaLembar
{
    /** Nilai centang yang dianggap tercentang; Excel DKPS memakai "V". */
    private const CENTANG_YA = ['v', '1', 'true', 'ya'];

    /** Bentuk tanggal yang diterima: isian Excel, ISO, dan hasil sel tanggal. */
    private const BENTUK_TANGGAL = ['!d/m/Y', '!Y-m-d', '!Y-m-d H:i:s'];

    /** Toleransi pembulatan untuk aturan `jumlah_persen_100`. */
    private const TOLERANSI_PERSEN = 0.05;

    private function __construct(
        private readonly DkpsButir $butir,
        private readonly array $lembar,
    ) {}

    public static function dari(DkpsButir $butir, string $kodeLembar): self
    {
        return new self($butir, $butir->lembarMentah($kodeLembar));
    }

    // ── Bacaan skema ────────────────────────────────────────────────────

    public function kode(): string
    {
        return $this->lembar['kode'];
    }

    public function judul(): string
    {
        return $this->lembar['judul'];
    }

    public function catatan(): ?string
    {
        return $this->lembar['catatan'] ?? null;
    }

    public function tahunDari(): string
    {
        return $this->lembar['tahun_dari'];
    }

    public function butir(): DkpsButir
    {
        return $this->butir;
    }

    /** @return array<int, array> Semua kolom, urut sesuai json. */
    public function kolom(): array
    {
        return $this->lembar['kolom'];
    }

    public function kolomInput(): array
    {
        return array_values(array_filter($this->kolom(), fn (array $k) => self::tipe($k)->isian()));
    }

    public function kolomHitung(): array
    {
        return array_values(array_filter($this->kolom(), fn (array $k) => ! self::tipe($k)->isian()));
    }

    /** @return array<int, string> */
    public function kunciWajib(): array
    {
        return array_values(array_map(
            fn (array $k) => $k['kunci'],
            array_filter($this->kolom(), fn (array $k) => $k['wajib'] ?? false),
        ));
    }

    public function pilihan(string $kunci): array
    {
        return $this->kolomBerkunci($kunci)['pilihan'] ?? [];
    }

    public function adaBarisTetap(): bool
    {
        return isset($this->lembar['baris_tetap']);
    }

    /** @return array<int, array{kunci: string, label: string, kelompok?: string}> */
    public function barisTetap(): array
    {
        return $this->lembar['baris_tetap']['baris'] ?? [];
    }

    public function subtotal(): array
    {
        return $this->lembar['baris_tetap']['subtotal'] ?? [];
    }

    public function labelKolomBaris(): ?string
    {
        return $this->lembar['baris_tetap']['label_kolom'] ?? null;
    }

    /** @return array<int, string> Kolom yang diarsir (tidak diisi) pada satu baris tetap. */
    public function kolomNonaktif(string $kunciBaris): array
    {
        return $this->lembar['kolom_nonaktif'][$kunciBaris] ?? [];
    }

    // ── Kepala bertingkat ───────────────────────────────────────────────

    /**
     * Baris-baris kepala tabel siap render. Sel kelompok muncul sekali dengan
     * `colspan` sebanyak kolom bersebelahan yang berbagi jalur kelompok yang
     * sama; kolom berjalur pendek turun mengisi sisa baris lewat `rowspan`.
     *
     * @return array<int, array<int, array{label: string, colspan: int, rowspan: int}>>
     */
    public function kepala(): array
    {
        $jalur = array_map(fn (array $k) => $k['kelompok'] ?? [], $this->kolom());
        $kedalaman = 1 + max(array_map('count', $jalur));
        $baris = array_fill(0, $kedalaman, []);
        $baris[0][] = ['label' => $this->labelKolomBaris() ?? 'No.', 'colspan' => 1, 'rowspan' => $kedalaman];

        for ($tingkat = 0; $tingkat < $kedalaman; $tingkat++) {
            $awalanSebelumnya = null;

            foreach ($this->kolom() as $i => $kolom) {
                $panjang = count($jalur[$i]);

                if ($tingkat < $panjang) {
                    $awalan = array_slice($jalur[$i], 0, $tingkat + 1);

                    if ($awalan === $awalanSebelumnya) {
                        $baris[$tingkat][array_key_last($baris[$tingkat])]['colspan']++;

                        continue;
                    }

                    $baris[$tingkat][] = ['label' => end($awalan), 'colspan' => 1, 'rowspan' => 1];
                    $awalanSebelumnya = $awalan;

                    continue;
                }

                $awalanSebelumnya = null;

                if ($tingkat === $panjang) {
                    $baris[$tingkat][] = ['label' => $kolom['label'], 'colspan' => 1, 'rowspan' => $kedalaman - $panjang];
                }
            }
        }

        return $baris;
    }

    // ── Normalisasi ─────────────────────────────────────────────────────

    /**
     * Hanya kunci yang ada di `$data` yang dikembalikan (kolom yang tidak
     * dikirim tetap tidak ada), supaya simpan per sel tidak menimpa sel lain
     * dengan `null`. Nilai yang tak bisa diurai dibiarkan apa adanya agar
     * `validasi()` bisa menyebut kolomnya.
     */
    public function normalkan(array $data, ?string $kunciBaris = null): array
    {
        $kunciBaris = $this->barisDari($data, $kunciBaris);
        $nonaktif = $kunciBaris === null ? [] : $this->kolomNonaktif($kunciBaris);
        $hasil = [];

        foreach ($this->kolomInput() as $kolom) {
            $kunci = $kolom['kunci'];

            if (! array_key_exists($kunci, $data)) {
                continue;
            }

            $hasil[$kunci] = in_array($kunci, $nonaktif, true)
                ? null
                : $this->normalkanNilai($kolom, $data[$kunci]);
        }

        if ($kunciBaris !== null) {
            $hasil['_baris'] = $kunciBaris;
        }

        return $hasil;
    }

    private function normalkanNilai(array $kolom, mixed $nilai): mixed
    {
        $tipe = self::tipe($kolom);

        if ($tipe === TipeKolom::Centang) {
            return $nilai === true || in_array(mb_strtolower(trim((string) $nilai)), self::CENTANG_YA, true);
        }

        if (is_string($nilai)) {
            $nilai = trim($nilai);
        }

        if ($nilai === null || $nilai === '') {
            return null;
        }

        return match ($tipe) {
            TipeKolom::Angka => self::bilanganBulat($nilai) ?? $nilai,
            TipeKolom::Desimal => self::desimal($nilai) ?? $nilai,
            TipeKolom::Tanggal => self::tanggal($nilai) ?? $nilai,
            TipeKolom::Tahun => is_int($nilai) || preg_match('/^\d{4}$/', (string) $nilai) === 1 ? (int) $nilai : $nilai,
            TipeKolom::Pilihan => $this->pilihanBaku($kolom, (string) $nilai),
            default => is_string($nilai) ? $nilai : (string) $nilai,
        };
    }

    /** "1.500.000" (rupiah ditulis lengkap) dan "65" sama-sama bilangan bulat. */
    private static function bilanganBulat(mixed $nilai): ?int
    {
        if (is_int($nilai)) {
            return $nilai;
        }

        $teks = (string) $nilai;

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $teks) === 1) {
            $teks = str_replace('.', '', $teks);
        }

        return preg_match('/^-?\d+$/', $teks) === 1 ? (int) $teks : null;
    }

    /** Koma desimal Indonesia diterima: "94,32" dan "1.234,5". */
    private static function desimal(mixed $nilai): ?float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        $teks = str_replace(' ', '', (string) $nilai);

        if (str_contains($teks, ',')) {
            $teks = str_replace(['.', ','], ['', '.'], $teks);
        }

        return is_numeric($teks) ? (float) $teks : null;
    }

    private static function tanggal(mixed $nilai): ?string
    {
        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        foreach (self::BENTUK_TANGGAL as $bentuk) {
            $tanggal = DateTimeImmutable::createFromFormat($bentuk, (string) $nilai);
            $galat = DateTimeImmutable::getLastErrors();

            if ($tanggal !== false && ($galat === false || $galat['warning_count'] + $galat['error_count'] === 0)) {
                return $tanggal->format('Y-m-d');
            }
        }

        return null;
    }

    /** Pilihan dicocokkan tanpa peduli huruf besar, lalu ditulis dalam bentuk bakunya. */
    private function pilihanBaku(array $kolom, string $nilai): string
    {
        foreach ($kolom['pilihan'] ?? [] as $p) {
            if (mb_strtolower($p) === mb_strtolower($nilai)) {
                return $p;
            }
        }

        return $nilai;
    }

    // ── Validasi ────────────────────────────────────────────────────────

    /** @return array<string, string> kunci kolom (atau `_baris`) ⇒ pesan */
    public function validasi(array $dataNormal, ?string $kunciBaris = null): array
    {
        $kunciBaris = $this->barisDari($dataNormal, $kunciBaris);
        $nonaktif = $kunciBaris === null ? [] : $this->kolomNonaktif($kunciBaris);
        $galat = [];

        foreach ($this->kolomInput() as $kolom) {
            if (in_array($kolom['kunci'], $nonaktif, true)) {
                continue;
            }

            $pesan = $this->galatKolom($kolom, $dataNormal[$kolom['kunci']] ?? null);

            if ($pesan !== null) {
                $galat[$kolom['kunci']] = $pesan;
            }
        }

        foreach ($this->galatUrutanTanggal($dataNormal) as $kunci => $pesan) {
            $galat[$kunci] ??= $pesan;
        }

        $galatBaris = array_filter(array_map(
            fn (array $aturan) => $this->galatAturan($aturan, $dataNormal),
            $this->lembar['aturan'] ?? [],
        ));

        if ($galatBaris !== []) {
            $galat['_baris'] = implode(' ', $galatBaris);
        }

        return $galat;
    }

    /**
     * Kolom `sampai` pada rumus `selisih_tahun` (mis. tanggal akhir kerja
     * sama) tidak boleh lebih awal dari kolom `dari`-nya.
     *
     * @return array<string, string>
     */
    private function galatUrutanTanggal(array $data): array
    {
        $galat = [];

        foreach ($this->kolomHitung() as $kolom) {
            $rumus = $kolom['rumus'];

            if ($rumus['jenis'] !== 'selisih_tahun') {
                continue;
            }

            $dari = self::tanggal($data[$rumus['dari']] ?? '');
            $sampai = self::tanggal($data[$rumus['sampai']] ?? '');

            if ($dari !== null && $sampai !== null && $sampai < $dari) {
                $galat[$rumus['sampai']] = "{$this->kolomBerkunci($rumus['sampai'])['label']} tidak boleh mendahului {$this->kolomBerkunci($rumus['dari'])['label']}.";
            }
        }

        return $galat;
    }

    private function galatKolom(array $kolom, mixed $nilai): ?string
    {
        $label = $kolom['label'];

        if ($nilai === null) {
            return ($kolom['wajib'] ?? false) ? "{$label} wajib diisi." : null;
        }

        return match (self::tipe($kolom)) {
            TipeKolom::Angka => is_int($nilai) && $nilai >= 0 ? null : "{$label} harus bilangan bulat ≥ 0.",
            TipeKolom::Desimal => is_int($nilai) || is_float($nilai) ? null : "{$label} harus angka.",
            TipeKolom::Tanggal => is_string($nilai) && self::tanggal($nilai) === $nilai ? null : "{$label} harus tanggal (dd/mm/yyyy).",
            TipeKolom::Tahun => is_int($nilai) && $nilai >= 1000 && $nilai <= 9999 ? null : "{$label} harus tahun empat digit.",
            TipeKolom::Pilihan => in_array($nilai, $kolom['pilihan'] ?? [], true)
                ? null
                : "{$label} harus salah satu dari: ".implode(', ', $kolom['pilihan'] ?? []).'.',
            TipeKolom::Tautan => self::tautanSah($nilai) ? null : "{$label} harus URL yang sah.",
            default => null,
        };
    }

    /** Hanya http(s): tautan dirender sebagai `href`, jadi `javascript:` harus ditolak. */
    private static function tautanSah(mixed $nilai): bool
    {
        return is_string($nilai)
            && filter_var($nilai, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($nilai, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function galatAturan(array $aturan, array $data): ?string
    {
        $kolom = $aturan['kolom'];
        $label = fn (array $kunci) => implode(', ', array_map(fn (string $k) => $this->kolomBerkunci($k)['label'], $kunci));
        $tercentang = count(array_filter($kolom, fn (string $k) => ($data[$k] ?? false) === true));

        return match ($aturan['jenis']) {
            'tepat_satu' => $tercentang === 1 ? null : "Pilih tepat satu: {$label($kolom)}.",
            'minimal_satu' => $tercentang >= 1 ? null : "Centang minimal satu: {$label($kolom)}.",
            'jumlah_persen_100' => $this->galatPersen($kolom, $data),
            'tidak_melebihi' => $this->galatBatas($kolom, $aturan['batas'], $data),
            default => throw new InvalidArgumentException("Aturan `{$aturan['jenis']}` tidak dikenal."),
        };
    }

    /** Baris yang belum diisi sama sekali tidak dinilai; itu urusan `lengkap()`. */
    private function galatPersen(array $kolom, array $data): ?string
    {
        $nilai = self::nilaiAngka($kolom, $data);

        if ($nilai === null) {
            return null;
        }

        $jumlah = array_sum($nilai);

        return abs($jumlah - 100) <= self::TOLERANSI_PERSEN
            ? null
            : 'Jumlah persentase harus 100 (sekarang '.self::angkaTampil($jumlah).').';
    }

    private function galatBatas(array $kolom, string $batas, array $data): ?string
    {
        $nilai = self::nilaiAngka($kolom, $data);
        $nilaiBatas = self::angkaAtauNull($data[$batas] ?? null);

        if ($nilai === null || $nilaiBatas === null || array_sum($nilai) <= $nilaiBatas) {
            return null;
        }

        $labelKolom = count($kolom) === 1
            ? $this->kolomBerkunci($kolom[0])['label']
            : 'Jumlah '.implode(' + ', array_map(fn (string $k) => $this->kolomBerkunci($k)['label'], $kolom));

        return "{$labelKolom} tidak boleh melebihi {$this->kolomBerkunci($batas)['label']}.";
    }

    /**
     * Nilai angka kolom-kolom itu (kosong = 0), atau `null` bila semuanya
     * kosong atau ada yang bukan angka — galat tipe sudah dilaporkan per kolom.
     */
    private static function nilaiAngka(array $kolom, array $data): ?array
    {
        $nilai = array_map(fn (string $k) => $data[$k] ?? null, $kolom);

        if (array_filter($nilai, fn ($n) => $n !== null) === []) {
            return null;
        }

        foreach ($nilai as $n) {
            if ($n !== null && ! is_int($n) && ! is_float($n)) {
                return null;
            }
        }

        return array_map(fn ($n) => $n ?? 0, $nilai);
    }

    /** 101 → "101", 100.5 → "100,5": bentuk yang dibaca pengisi, bukan 101.00000001. */
    private static function angkaTampil(float|int $angka): string
    {
        $bulat = round($angka, 2);

        return floor($bulat) == $bulat
            ? (string) (int) $bulat
            : rtrim(rtrim(number_format($bulat, 2, ',', ''), '0'), ',');
    }

    // ── Hitung ──────────────────────────────────────────────────────────

    /**
     * Kolom hitung dihitung berurutan, jadi `bagi` boleh merujuk kolom hitung
     * sebelumnya (Tabel 8: rata per semester = jumlah sks / 2).
     *
     * @return array<string, int|float|null>
     */
    public function hitung(array $dataNormal, ?string $kunciBaris = null): array
    {
        $kunciBaris = $this->barisDari($dataNormal, $kunciBaris);
        $data = $dataNormal;
        $hasil = [];

        foreach ($this->kolomHitung() as $kolom) {
            $hasil[$kolom['kunci']] = $data[$kolom['kunci']] = $this->hitungSatu($kolom['rumus'], $data, $kunciBaris);
        }

        return $hasil;
    }

    private function hitungSatu(array $rumus, array $data, ?string $kunciBaris): int|float|null
    {
        return match ($rumus['jenis']) {
            'selisih_tahun' => self::selisihTahun($data[$rumus['dari']] ?? null, $data[$rumus['sampai']] ?? null),
            'jumlah' => array_sum(array_map(fn (string $k) => self::angkaAtauNull($data[$k] ?? null) ?? 0, $rumus['kolom'])),
            'rata' => self::rata(array_map(fn (string $k) => self::angkaAtauNull($data[$k] ?? null), $rumus['kolom'])),
            'bagi' => self::bagi($data, $rumus['kolom'], $rumus['pembagi']),
            'masa_studi_rata' => $this->masaStudiRata($data, $kunciBaris),
            default => throw new InvalidArgumentException("Rumus `{$rumus['jenis']}` tidak dikenal."),
        };
    }

    private static function selisihTahun(mixed $dari, mixed $sampai): int
    {
        $dari = self::tanggal($dari ?? '');
        $sampai = self::tanggal($sampai ?? '');

        // Urutan terbalik dilaporkan validasi(); di sini cukup tidak negatif.
        if ($dari === null || $sampai === null || $sampai < $dari) {
            return 0;
        }

        return (int) floor(CarbonImmutable::parse($dari)->diffInYears($sampai, absolute: true));
    }

    private static function rata(array $nilai): int|float|null
    {
        $terisi = array_filter($nilai, fn ($n) => $n !== null);

        return $terisi === [] ? null : array_sum($terisi) / count($terisi);
    }

    private static function bagi(array $data, string $kolom, int|float|string $pembagi): int|float|null
    {
        $pembilang = self::angkaAtauNull($data[$kolom] ?? null);
        $penyebut = is_string($pembagi) ? self::angkaAtauNull($data[$pembagi] ?? null) : $pembagi;

        return $pembilang === null || ! $penyebut ? null : $pembilang / $penyebut;
    }

    /**
     * Tabel 22: lulusan angkatan baris ini pada kolom `lulus_akhir_*` ke-j
     * berbobot (j − i + 1) tahun, dengan i urutan barisnya — angkatan TS-2
     * yang lulus akhir TS-2 berbobot 1, akhir TS berbobot 3.
     */
    private function masaStudiRata(array $data, ?string $kunciBaris): ?float
    {
        $urutanBaris = array_search($kunciBaris, array_column($this->barisTetap(), 'kunci'), true);
        $kolomLulus = array_values(array_filter(
            array_column($this->kolomInput(), 'kunci'),
            fn (string $k) => str_starts_with($k, 'lulus_akhir_'),
        ));

        if ($urutanBaris === false) {
            return null;
        }

        $lulusan = 0;
        $tertimbang = 0;
        $adaIsian = false;

        foreach ($kolomLulus as $j => $kunci) {
            $jumlah = self::angkaAtauNull($data[$kunci] ?? null);
            $bobot = $j - $urutanBaris + 1;

            if ($jumlah === null || $bobot < 1) {
                continue;
            }

            $adaIsian = true;
            $lulusan += $jumlah;
            $tertimbang += $jumlah * $bobot;
        }

        if (! $adaIsian) {
            return null;
        }

        return $lulusan == 0 ? 0.0 : $tertimbang / $lulusan;
    }

    private static function angkaAtauNull(mixed $nilai): int|float|null
    {
        return is_int($nilai) || is_float($nilai) ? $nilai : null;
    }

    // ── Kelengkapan, kunci, dan tahun ───────────────────────────────────

    /** Baris tetap dianggap lengkap hanya bila semua sel yang tidak diarsir terisi. */
    public function lengkap(array $dataNormal, ?string $kunciBaris = null): bool
    {
        $kunciBaris = $this->barisDari($dataNormal, $kunciBaris);

        if ($this->validasi($dataNormal, $kunciBaris) !== []) {
            return false;
        }

        $harusTerisi = $this->kunciWajib();

        if ($kunciBaris !== null) {
            $harusTerisi = array_diff(array_column($this->kolomInput(), 'kunci'), $this->kolomNonaktif($kunciBaris));
        }

        foreach ($harusTerisi as $kunci) {
            if (($dataNormal[$kunci] ?? null) === null) {
                return false;
            }
        }

        return true;
    }

    public function kunciBaris(array $dataNormal, ?string $kunciBaris = null): string
    {
        $kunciBaris = $this->barisDari($dataNormal, $kunciBaris);

        if ($this->adaBarisTetap()) {
            return NormalisasiKunci::dari($this->butir->kode, $this->kode(), (string) $kunciBaris);
        }

        $nilai = array_map(fn (string $k) => (string) ($dataNormal[$k] ?? ''), $this->lembar['kunci'] ?? []);

        return NormalisasiKunci::dari($this->butir->kode, $this->kode(), ...$nilai);
    }

    /**
     * Label `tahun_acuan` baris ini. Tahun dari kolom ditolak bila di luar
     * jendela tabel, bukan dipotong diam-diam ke tepi jendela.
     *
     * @throws TahunDiLuarJendela
     */
    public function tahunAcuan(Periode $periode, array $dataNormal, ?string $kunciBaris = null): string
    {
        $kunciBaris = $this->barisDari($dataNormal, $kunciBaris);
        $sumber = $this->tahunDari();

        if ($sumber === 'TS') {
            return 'TS';
        }

        if ($sumber === 'baris_tetap') {
            return $kunciBaris;
        }

        $kunci = substr($sumber, strlen('kolom:'));
        $tahun = self::tahunDariNilai($dataNormal[$kunci] ?? null)
            ?? throw new InvalidArgumentException("{$this->kolomBerkunci($kunci)['label']} belum berisi tahun yang sah.");
        $label = JendelaTs::labelDariTahun($periode, $tahun);

        if ($label === null || ! JendelaTs::tahunDalamJendela($periode, $this->butir->jendela_data, $tahun)) {
            throw TahunDiLuarJendela::untuk($tahun, JendelaTs::keterangan($periode, $this->butir->jendela_data));
        }

        return $label;
    }

    private static function tahunDariNilai(mixed $nilai): ?int
    {
        if (is_int($nilai)) {
            return $nilai;
        }

        if (is_string($nilai) && preg_match('/^(\d{4})(-\d{2}-\d{2})?$/', $nilai, $cocok) === 1) {
            return (int) $cocok[1];
        }

        return null;
    }

    // ── Pembantu ────────────────────────────────────────────────────────

    /**
     * Kunci baris tetap yang berlaku: parameter, kalau tidak `_baris` di data.
     * Pada lembar berbaris tetap kunci yang kosong atau asing ditolak — kalau
     * tidak, ia menjadi baris liar yang tak pernah tampil di layar tetapi
     * ikut terhitung. Lembar berbaris bebas selalu `null`.
     */
    private function barisDari(array $data, ?string $kunciBaris): ?string
    {
        if (! $this->adaBarisTetap()) {
            return null;
        }

        $kunciBaris ??= $data['_baris'] ?? null;

        if ($kunciBaris === null || ! in_array($kunciBaris, array_column($this->barisTetap(), 'kunci'), true)) {
            throw new InvalidArgumentException("Kunci baris `{$kunciBaris}` tidak ada pada lembar {$this->kode()}.");
        }

        return $kunciBaris;
    }

    private static function tipe(array $kolom): TipeKolom
    {
        return TipeKolom::from($kolom['tipe']);
    }

    private function kolomBerkunci(string $kunci): array
    {
        foreach ($this->kolom() as $kolom) {
            if ($kolom['kunci'] === $kunci) {
                return $kolom;
            }
        }

        throw new InvalidArgumentException("Lembar {$this->kode()} tidak punya kolom `{$kunci}`.");
    }
}
