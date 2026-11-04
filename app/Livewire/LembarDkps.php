<?php

namespace App\Livewire;

use App\Enums\SumberData;
use App\Exceptions\IsianDkpsTidakSah;
use App\Exceptions\TahunDiLuarJendela;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\User;
use App\Services\PengisiDkps;
use App\Services\VerifikatorDkps;
use App\Support\Dkps\SkemaLembar;
use App\Support\Dkps\TipeKolom;
use App\Support\Izin;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

/**
 * Satu lembar DKPS sebagai grid bergaya Excel.
 *
 * Kepala, tipe sel, kolom hitung, dan aturan seluruhnya dibaca dari
 * SkemaLembar; penulisan seluruhnya lewat PengisiDkps. Komponen ini hanya
 * memegang isian yang sedang diketik — bila ia punya aturan sendiri, baris
 * yang lolos di layar bisa ditolak impor (atau sebaliknya).
 *
 * Penanda baris (`$id`) ada tiga bentuk: UUID untuk baris tersimpan,
 * `tetap:<kunci>` untuk baris tetap yang belum tersimpan, dan `baru:<n>`
 * untuk baris bebas yang diketik di `$baru[n]`.
 *
 * Baris baru yang sudah tersimpan TETAP tinggal di `$baru[n]` (dengan `_id`
 * = UUID-nya) sampai halaman berganti: kunci klien `_kunci` dan posisinya
 * tidak berubah, jadi `<tr>` tidak diganti di tengah pengisian, fokus tidak
 * lepas, sel yang diketik sesudahnya tetap masuk ke baris yang sama, dan
 * baris itu tidak melompat ke halaman berikutnya.
 *
 * Simpan terjadi per baris setiap kali sel berubah (hook `updated`), bukan
 * lewat satu tombol besar: pengisi yang menutup tab di tengah jalan tidak
 * kehilangan apa pun kecuali sel terakhir yang sedang diketiknya.
 */
class LembarDkps extends Component
{
    public const PER_HALAMAN = 50;

    public string $butirId;

    public string $kodeLembar;

    public string $periodeId;

    public int $halaman = 1;

    /** Sumber bawaan baris baru, dipilih sekali di kepala lembar. */
    public string $sumberBaru = 'manual';

    /** @var array<string, array<string, mixed>> penanda baris ⇒ isian per kolom */
    public array $baris = [];

    /** @var array<string, array<string, string>> penanda baris ⇒ (kunci kolom | `_baris`) ⇒ pesan */
    public array $galat = [];

    /** @var array<int, array<string, mixed>> baris bebas yang diketik; `_kunci` = kunci klien, `_id` = UUID setelah tersimpan */
    public array $baru = [];

    public ?string $disimpanTerakhir = null;

    public string $buktiUrl = '';

    public string $buktiJudul = '';

    public ?string $buktiTanggal = null;

    public string $buktiSumber = 'manual';

    /** UUID baris yang sedang ditautkan buktinya. */
    public ?string $buktiUntuk = null;

    public function mount(string $butirId, string $kodeLembar, string $periodeId): void
    {
        $this->butirId = $butirId;
        $this->kodeLembar = $kodeLembar;
        $this->periodeId = $periodeId;

        $this->muat();

        // Lembar bebas yang masih kosong langsung menyediakan satu baris
        // ketik, seperti lembar Excel yang dibuka pertama kali.
        if (! $this->skema()->adaBarisTetap() && $this->baris === [] && $this->bolehIsi()) {
            $this->baru[] = $this->baruKosong();
        }
    }

    /**
     * Calon baris untuk memeriksa `dkps.isi` sebelum lembar punya baris —
     * dibangun sama dengan PengisiDkps::simpan, supaya tombol yang tampil
     * dan penolakan di layanan tidak pernah berselisih.
     */
    public static function calonBaris(DkpsButir $butir, string $kodeLembar, Periode $periode): DkpsBaris
    {
        return new DkpsBaris([
            'dkps_butir_id' => $butir->id,
            'lembar_kode' => $kodeLembar,
            'periode_id' => $periode->id,
            'prodi_id' => $periode->prodi_id,
            'pokja_id' => $butir->pokja_kode === null ? null : Pokja::withoutGlobalScope('lingkup_periode')
                ->where('periode_id', $periode->id)
                ->where('kode', $butir->pokja_kode)
                ->value('id'),
        ]);
    }

    // ── Bacaan ──────────────────────────────────────────────────────────

    #[Computed]
    public function butir(): DkpsButir
    {
        return DkpsButir::findOrFail($this->butirId);
    }

    #[Computed]
    public function periode(): Periode
    {
        return Periode::findOrFail($this->periodeId);
    }

    public function skema(): SkemaLembar
    {
        return $this->butir->skema($this->kodeLembar);
    }

    /** @return Collection<string, DkpsBaris> semua baris lembar, termasuk baris lama, menurut UUID */
    #[Computed]
    public function semua(): Collection
    {
        return app(PengisiDkps::class)
            ->barisLembar($this->butir, $this->kodeLembar, $this->periode)
            ->keyBy('id')
            ->toBase();
    }

    /** @return Collection<string, DkpsBaris> */
    #[Computed]
    public function barisLama(): Collection
    {
        return $this->semua->filter(fn (DkpsBaris $b) => $this->lama($b));
    }

    #[Computed]
    public function bolehIsi(): bool
    {
        return Izin::boleh($this->pengguna(), 'dkps.isi', self::calonBaris($this->butir, $this->kodeLembar, $this->periode));
    }

    public function bolehVerifikasi(DkpsBaris $baris): bool
    {
        return Izin::boleh($this->pengguna(), 'dkps.verifikasi', $baris);
    }

    /** Cacah baris bebas tersimpan (tanpa baris lama), untuk navigasi halaman. */
    public function totalBaris(): int
    {
        return $this->semua->reject(fn (DkpsBaris $b) => $this->lama($b))->count();
    }

    public function jumlahHalaman(): int
    {
        return max(1, (int) ceil($this->totalBaris() / self::PER_HALAMAN));
    }

    /**
     * Mengisi `$baris` dari basis data. `$baru` sengaja tidak disentuh:
     * baris yang sedang diketik tidak boleh hilang karena baris lain tersimpan.
     */
    public function muat(): void
    {
        unset($this->semua, $this->barisLama);
        $skema = $this->skema();
        $cocok = $this->semua->reject(fn (DkpsBaris $b) => $this->lama($b));
        $this->baris = [];

        if ($skema->adaBarisTetap()) {
            foreach ($skema->barisTetap() as $tetap) {
                $tersimpan = $cocok->first(fn (DkpsBaris $b) => ($b->data['_baris'] ?? null) === $tetap['kunci']);
                $this->baris[$tersimpan?->id ?? 'tetap:'.$tetap['kunci']] = $this->isian($tersimpan?->data ?? []);
            }

            return;
        }

        $this->halaman = min(max(1, $this->halaman), $this->jumlahHalaman());

        // Baris yang tersimpan dari `$baru` sudah tampil di tempatnya sendiri.
        $diBaru = array_filter(array_column($this->baru, '_id'));

        foreach ($cocok->slice(($this->halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN)->reject(fn (DkpsBaris $b) => in_array($b->id, $diBaru, true)) as $b) {
            $this->baris[$b->id] = $this->isian($b->data ?? []);
        }
    }

    // ── Aksi ────────────────────────────────────────────────────────────

    /** Sel yang berubah langsung menyimpan barisnya; halaman yang berganti memuat ulang. */
    public function updated(string $properti): void
    {
        if ($properti === 'halaman') {
            // Baris baru yang sudah tersimpan kembali ke urutan aslinya.
            $this->baru = array_filter($this->baru, fn (array $b) => ! isset($b['_id']));
            $this->muat();

            return;
        }

        if (preg_match('/^baris\.([^.]+)\./', $properti, $cocok) === 1) {
            $this->simpan($cocok[1]);
        } elseif (preg_match('/^baru\.(\d+)\./', $properti, $cocok) === 1) {
            $this->simpan('baru:'.$cocok[1]);
        }
    }

    public function simpan(string $id): void
    {
        $skema = $this->skema();
        $baris = null;
        $kunciBaris = null;

        if (str_starts_with($id, 'baru:')) {
            abort_unless($this->bolehIsi, 403);
            $indeks = (int) substr($id, strlen('baru:'));

            if (! isset($this->baru[$indeks])) {
                return;
            }

            $data = $this->baru[$indeks];
            $baris = isset($data['_id']) ? ($this->semua->get($data['_id']) ?? abort(404)) : null;

            // Baris baru menunggu sampai kolom wajibnya terisi; menyimpan
            // lebih awal hanya menghujani pengisi dengan "wajib diisi"
            // ketika ia baru mengetik sel pertama.
            if ($baris === null && $this->kolomWajibKosong($data) !== []) {
                unset($this->galat[$id]);

                return;
            }
        } elseif (str_starts_with($id, 'tetap:')) {
            abort_unless($this->bolehIsi, 403);
            $kunciBaris = substr($id, strlen('tetap:'));

            if (! isset($this->baris[$id]) || $this->kosong($this->baris[$id])) {
                return;
            }

            $data = $this->baris[$id];
        } else {
            $baris = $this->semua->get($id) ?? abort(404);

            if (! isset($this->baris[$id])) {
                return;
            }

            $data = $this->baris[$id];
            $kunciBaris = $skema->adaBarisTetap() ? $baris->data['_baris'] : null;
        }

        // Blur tanpa perubahan tidak menulis apa pun — terutama pada baris
        // terverifikasi, yang verifikasinya akan tercabut.
        if ($baris !== null && $skema->normalkan($this->isian($data), $kunciBaris) === $skema->normalkan($this->isian($baris->data ?? []), $kunciBaris)) {
            return;
        }

        try {
            $tersimpan = app(PengisiDkps::class)->simpan(
                $this->butir, $this->kodeLembar, $this->periode,
                $this->isian($data),
                $baris?->sumber ?? (SumberData::tryFrom($this->sumberBaru) ?? SumberData::Manual),
                $this->pengguna(), $baris, $kunciBaris,
            );
        } catch (AuthorizationException) {
            abort(403);
        } catch (IsianDkpsTidakSah $e) {
            $this->galat[$id] = $e->galat;

            return;
        } catch (TahunDiLuarJendela $e) {
            $this->galat[$id] = [$this->kolomTahun() ?? '_baris' => $e->getMessage()];

            return;
        }

        unset($this->galat[$id]);

        if (isset($indeks)) {
            $this->baru[$indeks]['_id'] = $tersimpan->id;
        }

        $this->disimpanTerakhir = $tersimpan->id;
        $this->muat();
    }

    public function tambahBaris(): void
    {
        abort_unless($this->bolehIsi && ! $this->skema()->adaBarisTetap(), 403);

        $this->baru[] = $this->baruKosong();
    }

    public function hapusBaris(string $id): void
    {
        if (str_starts_with($id, 'baru:')) {
            unset($this->baru[(int) substr($id, strlen('baru:'))], $this->galat[$id]);

            return;
        }

        $baris = $this->semua->get($id) ?? abort(404);

        try {
            app(PengisiDkps::class)->hapus($baris, $this->pengguna());
        } catch (AuthorizationException) {
            abort(403);
        } catch (RuntimeException $e) {
            $this->galat[$id] = ['_baris' => $e->getMessage()];

            return;
        }

        unset($this->galat[$id]);
        $this->baru = array_filter($this->baru, fn (array $b) => ($b['_id'] ?? null) !== $id);
        $this->muat();
    }

    public function verifikasi(string $id): void
    {
        $baris = $this->semua->get($id) ?? abort(404);
        abort_unless($this->bolehVerifikasi($baris), 403);

        try {
            app(VerifikatorDkps::class)->verifikasi($baris, $this->pengguna());
        } catch (RuntimeException $e) {
            $this->galat[$id] = ['_baris' => $e->getMessage()];

            return;
        }

        unset($this->galat[$id]);
        $this->muat();
    }

    public function bukaBukti(string $id): void
    {
        if (! $this->semua->has($id)) {
            $this->galat[$id] = ['_baris' => 'Simpan baris ini lebih dulu sebelum menautkan bukti.'];

            return;
        }

        $this->reset('buktiUrl', 'buktiJudul', 'buktiTanggal');
        $this->buktiSumber = $this->sumberBaru;
        $this->buktiUntuk = $id;
        $this->resetValidation();
        $this->dispatch('open-modal', id: $this->idModalBukti());
    }

    public function tautkanBukti(): void
    {
        $baris = $this->semua->get((string) $this->buktiUntuk) ?? abort(404);

        $this->validate([
            'buktiUrl' => ['required', 'url:http,https'],
            'buktiJudul' => ['required', 'string', 'max:255'],
            'buktiTanggal' => ['required', 'date'],
            'buktiSumber' => ['required', Rule::enum(SumberData::class)],
        ], [], [
            'buktiUrl' => 'URL', 'buktiJudul' => 'judul', 'buktiTanggal' => 'tanggal kejadian', 'buktiSumber' => 'sumber',
        ]);

        try {
            app(PengisiDkps::class)->tautkanBuktiTautan($baris, $this->buktiUrl, [
                'judul' => $this->buktiJudul,
                'tanggal_kejadian' => $this->buktiTanggal,
                'sumber' => SumberData::from($this->buktiSumber),
            ], $this->pengguna());
        } catch (AuthorizationException) {
            abort(403);
        } catch (RuntimeException $e) {
            $this->addError('buktiUrl', $e->getMessage());

            return;
        }

        $this->reset('buktiUrl', 'buktiJudul', 'buktiTanggal', 'buktiUntuk');
        $this->dispatch('close-modal', id: $this->idModalBukti());
        $this->muat();
    }

    public function render(): View
    {
        return view('livewire.lembar-dkps');
    }

    // ── Pembantu tampilan ───────────────────────────────────────────────

    public function idModalBukti(): string
    {
        return 'bukti-'.$this->kodeLembar;
    }

    /** Nomor Excel kolom label/No.: "1", atau "1–2" bila Excel memakai dua kolom di sana. */
    public function nomorKolomPertama(): string
    {
        $pertama = (int) ($this->skema()->kolom()[0]['nomor_excel'] ?? 2);

        return $pertama <= 2 ? '1' : '1–'.($pertama - 1);
    }

    /**
     * Baris grid dalam urutan tampil, lengkap dengan yang dibutuhkan Blade.
     *
     * @return array<int, array{id: string, jalur: string, nomor: string, kelompok: ?string, kunciBaris: ?string, isian: array, hitung: array, nonaktif: array, model: ?DkpsBaris, galatId: string, kunciKlien: string}>
     */
    public function barisTampil(): array
    {
        $skema = $this->skema();
        $hasil = [];

        if ($skema->adaBarisTetap()) {
            $idPerKunci = [];

            foreach (array_keys($this->baris) as $id) {
                $kunci = str_starts_with($id, 'tetap:')
                    ? substr($id, strlen('tetap:'))
                    : ($this->semua->get($id)?->data['_baris'] ?? null);
                $idPerKunci[$kunci] = $id;
            }

            foreach ($skema->barisTetap() as $tetap) {
                $id = $idPerKunci[$tetap['kunci']] ?? 'tetap:'.$tetap['kunci'];
                $hasil[] = $this->barisUntuk($id, "baris.{$id}", $tetap['label'], $this->baris[$id] ?? $this->isianKosong(), $tetap['kunci'], $tetap['kelompok'] ?? null);
            }

            return $hasil;
        }

        $nomor = ($this->halaman - 1) * self::PER_HALAMAN;

        foreach ($this->baris as $id => $isian) {
            $hasil[] = $this->barisUntuk($id, "baris.{$id}", (string) ++$nomor, $isian, null, null);
        }

        $nomor = $this->totalBaris() - count(array_filter(array_column($this->baru, '_id')));

        foreach ($this->baru as $n => $isian) {
            $hasil[] = ['galatId' => "baru:{$n}", 'kunciKlien' => $isian['_kunci'] ?? "baru-{$n}"]
                + $this->barisUntuk($isian['_id'] ?? "baru:{$n}", "baru.{$n}", (string) ++$nomor, $isian, null, null);
        }

        return $hasil;
    }

    private function barisUntuk(string $id, string $jalur, string $nomor, array $isian, ?string $kunciBaris, ?string $kelompok): array
    {
        return [
            'id' => $id,
            'jalur' => $jalur,
            'nomor' => $nomor,
            'kelompok' => $kelompok,
            'kunciBaris' => $kunciBaris,
            'isian' => $isian,
            'hitung' => $this->hitungan($isian, $kunciBaris),
            'nonaktif' => $kunciBaris === null ? [] : $this->skema()->kolomNonaktif($kunciBaris),
            'model' => $this->semua->get($id),
            'galatId' => $id,
            'kunciKlien' => $id,
        ];
    }

    /**
     * Baris jumlah/rata-rata dan subtotal di bawah lembar berbaris tetap.
     *
     * @param  array<int, array>  $barisTampil  hasil barisTampil()
     * @return array<int, array{label: string, setelah: ?string, nilai: array<string, int|float|null>}>
     */
    public function barisRingkasan(array $barisTampil): array
    {
        $skema = $this->skema();

        if (! $skema->adaBarisTetap()) {
            return [];
        }

        $nilai = [];

        foreach ($barisTampil as $r) {
            $nilai[$r['kunciBaris']] = $skema->normalkan($r['isian'], $r['kunciBaris']) + $r['hitung'];
        }

        $hasil = [];

        foreach ($skema->subtotal() as $sub) {
            $hasil[] = ['label' => $sub['label'], 'setelah' => end($sub['dari']), 'nilai' => $this->ringkas($nilai, $sub['dari'], 'jumlah')];
        }

        $jenis = $this->jenisFooter();

        if ($jenis !== null) {
            $hasil[] = ['label' => $jenis === 'rata' ? 'Rata-rata' : 'Jumlah', 'setelah' => null, 'nilai' => $this->ringkas($nilai, array_keys($nilai), $jenis)];
        }

        return $hasil;
    }

    /** Nilai kolom hitung satu baris; isian yang belum sah dianggap kosong oleh SkemaLembar. */
    public function hitungan(array $isian, ?string $kunciBaris): array
    {
        $skema = $this->skema();

        return $skema->hitung($skema->normalkan($this->isian($isian), $kunciBaris), $kunciBaris);
    }

    /**
     * Baris jumlah/rata-rata (`footer`) dan subtotal di bawah lembar berbaris
     * tetap, per kolom angka.
     *
     * @param  array<string, array<string, mixed>>  $nilaiPerBaris  kunci baris tetap ⇒ nilai normal + hitung
     * @param  array<int, string>  $kunciBaris
     * @return array<string, int|float|null> kunci kolom ⇒ nilai
     */
    public function ringkas(array $nilaiPerBaris, array $kunciBaris, string $jenis): array
    {
        $hasil = [];

        foreach ($this->skema()->kolom() as $kolom) {
            if (! in_array($kolom['tipe'], ['angka', 'desimal', 'hitung'], true)) {
                continue;
            }

            $nilai = array_values(array_filter(
                array_map(fn (string $k) => $nilaiPerBaris[$k][$kolom['kunci']] ?? null, $kunciBaris),
                fn ($n) => is_int($n) || is_float($n),
            ));

            $hasil[$kolom['kunci']] = $nilai === [] ? null : match ($jenis) {
                'rata' => array_sum($nilai) / count($nilai),
                default => array_sum($nilai),
            };
        }

        return $hasil;
    }

    /** Jenis baris ringkasan lembar ("jumlah" atau "rata"), atau null. */
    public function jenisFooter(): ?string
    {
        return $this->butir->lembarMentah($this->kodeLembar)['footer'] ?? null;
    }

    public static function tampil(array $kolom, mixed $nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        return match (TipeKolom::tryFrom($kolom['tipe'])) {
            TipeKolom::Centang => $nilai === true ? 'V' : '',
            TipeKolom::Angka, TipeKolom::Desimal, TipeKolom::Hitung => is_int($nilai) || is_float($nilai) ? self::angka($nilai) : (string) $nilai,
            TipeKolom::Tanggal => self::tanggal((string) $nilai),
            default => (string) $nilai,
        };
    }

    /** 1500000 → "1.500.000", 7.5 → "7,5", 2.3333 → "2,33". */
    public static function angka(int|float $angka): string
    {
        if (is_int($angka) || floor($angka) == $angka) {
            return number_format($angka, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($angka, 2, ',', '.'), '0'), ',');
    }

    private static function tanggal(string $nilai): string
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $nilai)->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $nilai;
        }
    }

    // ── Pembantu ────────────────────────────────────────────────────────

    private function pengguna(): User
    {
        /** @var User */
        return auth()->user();
    }

    /**
     * Isian KeyValue sebelum DKPS 3.0: tak satu kunci pun dikenal skema
     * (atau, pada lembar berbaris tetap, kunci barisnya asing). Baris ini
     * tidak dicampur ke grid — kolomnya akan tampil kosong dan simpan
     * berikutnya menimpa isinya — melainkan ditampilkan apa adanya.
     */
    private function lama(DkpsBaris $baris): bool
    {
        $skema = $this->skema();
        $data = $baris->data ?? [];

        if ($skema->adaBarisTetap()) {
            return ! in_array($data['_baris'] ?? null, array_column($skema->barisTetap(), 'kunci'), true);
        }

        return array_intersect(array_keys($data), array_column($skema->kolom(), 'kunci')) === [];
    }

    /** Seluruh kolom isian, dengan yang tidak ada di `$data` diisi kosong. */
    private function isian(array $data): array
    {
        $hasil = [];

        foreach ($this->skema()->kolomInput() as $kolom) {
            $hasil[$kolom['kunci']] = $data[$kolom['kunci']] ?? ($kolom['tipe'] === 'centang' ? false : null);
        }

        return $hasil;
    }

    private function isianKosong(): array
    {
        return $this->isian([]);
    }

    /** Baris ketik baru dengan kunci klien yang tetap sepanjang hidupnya di layar. */
    private function baruKosong(): array
    {
        return $this->isianKosong() + ['_kunci' => (string) Str::uuid()];
    }

    private function kosong(array $data): bool
    {
        foreach ($data as $nilai) {
            if ($nilai !== null && $nilai !== '' && $nilai !== false) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, string> label kolom wajib yang belum terisi */
    public function kolomWajibKosong(array $data): array
    {
        $kosong = [];

        foreach ($this->skema()->kolomInput() as $kolom) {
            if (($kolom['wajib'] ?? false) && in_array($data[$kolom['kunci']] ?? null, [null, '', false], true)) {
                $kosong[] = $kolom['label'];
            }
        }

        return $kosong;
    }

    /** Kolom yang menjadi sumber tahun acuan, tempat galat "di luar jendela" ditempelkan. */
    private function kolomTahun(): ?string
    {
        $sumber = $this->skema()->tahunDari();

        return str_starts_with($sumber, 'kolom:') ? Str::after($sumber, 'kolom:') : null;
    }
}
