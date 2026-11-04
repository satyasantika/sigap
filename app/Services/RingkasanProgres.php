<?php

namespace App\Services;

use App\Enums\StatusTagihan;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Kriteria;
use App\Models\Narasi;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Tagihan;
use App\Models\TagihanRiwayat;
use App\Support\Dkps\SkemaLembar;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Seluruh progres di sini TERTIMBANG BOBOT, tidak pernah cacah tagihan.
 *
 * Alasannya bukan kerapian statistik. Sepuluh tagihan berbobot 0,50 yang
 * selesai berarti 5 bobot; satu tagihan berbobot 3,00 yang selesai berarti 3
 * bobot — tetapi cacah tagihan akan melaporkan yang pertama sepuluh kali lebih
 * maju. Dasbor yang menghitung cacah akan membuat orang mengerjakan yang mudah
 * lebih dulu, dan meninggalkan elemen berbobot besar sampai tenggat.
 *
 * Satu elemen dihitung selesai hanya bila SELURUH tagihannya disetujui.
 * Separuh selesai bukan setengah bobot: naskah tanpa bukti tidak bisa dinilai.
 */
class RingkasanProgres
{
    public const HARI_LAJU = 28;

    /** Bobot elemen yang seluruh tagihannya sudah disetujui. */
    public function bobotSelesai(Periode $periode): float
    {
        return round($this->elemenSelesai($periode)->sum('bobot'), 2);
    }

    public function progresKeseluruhan(Periode $periode): float
    {
        return round($this->bobotSelesai($periode) / 100, 4);
    }

    /**
     * Elemen yang seluruh tagihannya disetujui.
     *
     * @return Collection<int, Elemen>
     */
    public function elemenSelesai(Periode $periode): Collection
    {
        $belum = Tagihan::where('periode_id', $periode->id)
            ->whereNotNull('elemen_id')
            ->where('status', '!=', StatusTagihan::Disetujui)
            ->pluck('elemen_id')->unique();

        $punyaTagihan = Tagihan::where('periode_id', $periode->id)
            ->whereNotNull('elemen_id')
            ->pluck('elemen_id')->unique();

        return Elemen::whereIn('id', $punyaTagihan)
            ->whereNotIn('id', $belum)
            ->get();
    }

    /**
     * Progres tiap pokja, terurut menurut bobot yang dipegang.
     *
     * POKJA-DATA dilaporkan dengan ukuran DKPS, bukan bobot: ia memang tidak
     * memegang elemen, dan menampilkannya nol akan dibaca sebagai "belum
     * mengerjakan apa-apa" padahal ia mengawal 35 lembar DKPS.
     *
     * @return array<int, array{kode: string, nama: string, selesai: float, total: float, rasio: float, pakai_dkps: bool, syarat_perlu: bool}>
     */
    public function progresPokja(Periode $periode): array
    {
        $selesai = $this->elemenSelesai($periode)->groupBy('pokja_kode')
            ->map(fn (Collection $e) => round($e->sum('bobot'), 2));

        $total = Elemen::get()->groupBy('pokja_kode')
            ->map(fn (Collection $e) => round($e->sum('bobot'), 2));

        $pemegangSyarat = Elemen::bersyaratPerlu()->pluck('pokja_kode')->unique();

        $hasil = Pokja::where('periode_id', $periode->id)->orderBy('kode')->get()
            ->map(function (Pokja $p) use ($selesai, $total, $pemegangSyarat, $periode) {
                $totalBobot = (float) ($total[$p->kode] ?? 0.0);
                $pakaiDkps = $totalBobot === 0.0;

                if ($pakaiDkps) {
                    $dkps = $this->progresDkps($periode);

                    return [
                        'kode' => $p->kode, 'nama' => $p->nama,
                        'selesai' => (float) $dkps['terverifikasi'],
                        'total' => (float) $dkps['total'],
                        'rasio' => $dkps['rasio'],
                        'pakai_dkps' => true,
                        'syarat_perlu' => false,
                    ];
                }

                $selesaiBobot = (float) ($selesai[$p->kode] ?? 0.0);

                return [
                    'kode' => $p->kode, 'nama' => $p->nama,
                    'selesai' => $selesaiBobot,
                    'total' => $totalBobot,
                    'rasio' => round($selesaiBobot / $totalBobot, 4),
                    'pakai_dkps' => false,
                    'syarat_perlu' => $pemegangSyarat->contains($p->kode),
                ];
            })->all();

        // Terurut menurut bobot yang dipegang; POKJA-DATA di akhir karena
        // ukurannya berbeda dan tidak sebanding.
        usort($hasil, fn (array $a, array $b) => [$a['pakai_dkps'], -$a['total']] <=> [$b['pakai_dkps'], -$b['total']]);

        return $hasil;
    }

    /**
     * @return array<int, array{kode: string, nama: string, selesai: float, total: float, rasio: float}>
     */
    public function progresKriteria(Periode $periode): array
    {
        $selesai = $this->elemenSelesai($periode)->groupBy('kriteria_id')
            ->map(fn (Collection $e) => round($e->sum('bobot'), 2));

        return Kriteria::orderBy('urutan')->get()->map(function (Kriteria $k) use ($selesai) {
            $s = (float) ($selesai[$k->id] ?? 0.0);
            $t = (float) $k->bobot;

            return [
                'kode' => $k->kode, 'nama' => $k->nama,
                'selesai' => $s, 'total' => $t,
                'rasio' => $t > 0 ? round($s / $t, 4) : 0.0,
            ];
        })->all();
    }

    /**
     * Progres satu elemen, dihitung dari bobot_terkait tagihannya.
     *
     * Berbeda dari progres keseluruhan yang memakai elemen utuh: di sini
     * separuh tagihan yang selesai memang berarti separuh, karena yang
     * ditanyakan adalah kemajuan di dalam elemen itu sendiri.
     */
    public function progresElemen(Periode $periode, Elemen $elemen): float
    {
        $tagihan = Tagihan::where('periode_id', $periode->id)
            ->where('elemen_id', $elemen->id)->get();

        $total = (float) $tagihan->sum('bobot_terkait');

        if ($total <= 0.0) {
            return 0.0;
        }

        $selesai = (float) $tagihan
            ->where('status', StatusTagihan::Disetujui)->sum('bobot_terkait');

        return round($selesai / $total, 4);
    }

    /**
     * Kesiapan DKPS — TERPISAH dari progres bobot.
     *
     * Butir DKPS tidak berbobot; mencampurnya ke dalam progres bobot akan
     * menggeser angka yang seharusnya berjumlah 100. Satuannya lembar (35),
     * bukan tabel: Tabel 1 dengan satu dari tiga lembarnya terverifikasi
     * belum siap dibaca asesor.
     *
     * @return array{terverifikasi: int, total: int, rasio: float}
     */
    public function progresDkps(Periode $periode): array
    {
        $statusLembar = $this->semuaStatusLembar($periode)->flatMap(fn (array $t) => $t['lembar']);

        $total = $statusLembar->count();
        $terverifikasi = $statusLembar->where('status', 'terverifikasi')->count();

        return [
            'terverifikasi' => $terverifikasi,
            'total' => $total,
            'rasio' => $total > 0 ? round($terverifikasi / $total, 4) : 0.0,
        ];
    }

    /**
     * Progres DKPS per pokja pemilik tabel, terurut kode pokja. `terisi`,
     * `lengkap`, dan `terverifikasi` bersifat menumpuk: lembar terverifikasi
     * juga terhitung lengkap dan terisi.
     *
     * @return array<int, array{kode: string, nama: string, total: int, terisi: int, lengkap: int, terverifikasi: int, rasio: float, tabel: array<int, array{butir: DkpsButir, lembar: array<string, array>}>}>
     */
    public function progresDkpsPerPokja(Periode $periode): array
    {
        $nama = Pokja::withoutGlobalScope('lingkup_periode')
            ->where('periode_id', $periode->id)->pluck('nama', 'kode');

        return $this->semuaStatusLembar($periode)
            ->groupBy(fn (array $t) => $t['butir']->pokja_kode)
            ->sortKeys()
            ->map(function (Collection $tabel, string $kode) use ($nama) {
                $status = $tabel->flatMap(fn (array $t) => array_column($t['lembar'], 'status'));
                $total = $status->count();
                $terverifikasi = $status->filter(fn ($s) => $s === 'terverifikasi')->count();

                return [
                    'kode' => $kode,
                    'nama' => (string) ($nama[$kode] ?? $kode),
                    'total' => $total,
                    'terisi' => $status->reject(fn ($s) => $s === 'kosong')->count(),
                    'lengkap' => $status->filter(fn ($s) => in_array($s, ['lengkap', 'terverifikasi'], true))->count(),
                    'terverifikasi' => $terverifikasi,
                    'rasio' => $total > 0 ? round($terverifikasi / $total, 4) : 0.0,
                    'tabel' => $tabel->values()->all(),
                ];
            })->values()->all();
    }

    /**
     * Status satu lembar DKPS pada satu periode (spesifikasi B.6).
     *
     * @return array{baris: int, lengkap: int, terverifikasi: int, target: ?int, status: 'kosong'|'terisi'|'lengkap'|'terverifikasi'}
     */
    public function statusLembar(Periode $periode, DkpsButir $butir, string $kodeLembar): array
    {
        $baris = DkpsBaris::where('periode_id', $periode->id)
            ->where('dkps_butir_id', $butir->id)
            ->where('lembar_kode', $kodeLembar)
            ->get();

        return $this->hitungStatusLembar($butir->skema($kodeLembar), $baris);
    }

    /**
     * Seluruh tabel milik pokja beserta status tiap lembarnya. Baris periode
     * dimuat sekali, lalu dikelompokkan per tabel dan lembar.
     *
     * @return Collection<int, array{butir: DkpsButir, lembar: array<string, array>}>
     */
    private function semuaStatusLembar(Periode $periode): Collection
    {
        $baris = DkpsBaris::where('periode_id', $periode->id)->get()
            ->groupBy(fn (DkpsBaris $b) => $b->dkps_butir_id.'|'.$b->lembar_kode);

        return DkpsButir::whereNotNull('pokja_kode')->orderBy('no')->get()
            ->map(fn (DkpsButir $butir) => [
                'butir' => $butir,
                'lembar' => collect($butir->kodeLembar())->mapWithKeys(fn (string $kode) => [
                    $kode => $this->hitungStatusLembar($butir->skema($kode), $baris->get($butir->id.'|'.$kode, collect())),
                ])->all(),
            ]);
    }

    /**
     * @param  Collection<int, DkpsBaris>  $baris
     * @return array{baris: int, lengkap: int, terverifikasi: int, target: ?int, status: string}
     */
    private function hitungStatusLembar(SkemaLembar $skema, Collection $baris): array
    {
        $kunciTetap = array_column($skema->barisTetap(), 'kunci');
        $target = $skema->adaBarisTetap() ? count($kunciTetap) : null;

        $lengkap = $baris->filter(function (DkpsBaris $b) use ($skema, $kunciTetap) {
            $data = $b->data ?? [];

            // Isian sebelum DKPS 3.0 (kunci baris asing, atau tak satu kunci
            // pun dikenal skema) tidak pernah lengkap — dan tidak boleh
            // membuat SkemaLembar melempar.
            if ($skema->adaBarisTetap()
                ? ! in_array($data['_baris'] ?? null, $kunciTetap, true)
                : array_intersect(array_keys($data), array_column($skema->kolom(), 'kunci')) === []) {
                return false;
            }

            return $skema->lengkap($data, $b->nilai('_baris'));
        });

        $jumlah = $baris->count();
        $cacahLengkap = $lengkap->count();
        $terverifikasi = $baris->filter(fn (DkpsBaris $b) => $b->terverifikasi())->count();

        $semuaLengkap = $cacahLengkap === $jumlah
            && ($target === null || $lengkap->map(fn (DkpsBaris $b) => $b->nilai('_baris'))->unique()->count() === $target);

        $status = match (true) {
            $jumlah === 0 => 'kosong',
            ! $semuaLengkap => 'terisi',
            $terverifikasi === $jumlah => 'terverifikasi',
            default => 'lengkap',
        };

        return [
            'baris' => $jumlah,
            'lengkap' => $cacahLengkap,
            'terverifikasi' => $terverifikasi,
            'target' => $target,
            'status' => $status,
        ];
    }

    // ---- Daftar kerja ----------------------------------------------------

    /** @return Collection<int, Tagihan> */
    public function tagihanTerlambat(Periode $periode): Collection
    {
        return Tagihan::where('periode_id', $periode->id)
            ->terlambat()
            ->with(['elemen:id,no,nama', 'pokja:id,kode', 'penanggungJawab:id,nama_lengkap'])
            ->orderBy('tenggat')
            ->get();
    }

    /** Bobot yang tertahan oleh tagihan terlambat. */
    public function bobotTertahan(Periode $periode): float
    {
        return round((float) Tagihan::where('periode_id', $periode->id)
            ->terlambat()->sum('bobot_terkait'), 3);
    }

    /** @return Collection<int, Tagihan> */
    public function tagihanTanpaPj(Periode $periode): Collection
    {
        return Tagihan::where('periode_id', $periode->id)
            ->whereNull('penanggung_jawab_id')
            ->belumSelesai()
            ->with(['elemen:id,no,nama', 'pokja:id,kode'])
            ->orderByDesc('bobot_terkait')
            ->get();
    }

    /** @return Collection<int, Elemen> */
    public function elemenTanpaBukti(Periode $periode): Collection
    {
        return Elemen::whereDoesntHave('bukti', fn ($q) => $q
            ->where('bukti.periode_id', $periode->id))
            ->orderByDesc('bobot')
            ->get();
    }

    /** @return Collection<int, Narasi> */
    public function narasiDiBawahMinimal(Periode $periode): Collection
    {
        return Narasi::where('periode_id', $periode->id)
            ->where('jumlah_kata', '<', PengelolaNarasi::MINIMAL_KATA)
            ->with('elemen:id,no,nama,bobot')
            ->orderByDesc('elemen_id')
            ->get();
    }

    /** @return array<string, int> */
    public function cacahPerStatus(Periode $periode): array
    {
        return Tagihan::where('periode_id', $periode->id)
            ->selectRaw('status, COUNT(*) c')->groupBy('status')
            ->pluck('c', 'status')->all();
    }

    // ---- Laju dan perkiraan ---------------------------------------------

    /**
     * Laju mingguan, perkiraan rampung, dan selisihnya terhadap tanggal target.
     *
     * Ubin yang paling menggerakkan perilaku. Kalimat "dengan laju sekarang,
     * pengumpulan rampung 23 hari setelah tenggat" jauh lebih berguna bagi
     * pimpinan daripada persentase mana pun.
     *
     * Laju nol TIDAK menghasilkan tak hingga dan ubinnya tidak disembunyikan —
     * "belum bisa diperkirakan" adalah informasi, dan justru informasi yang
     * paling perlu terlihat.
     *
     * @return array{laju_mingguan: float, sisa_bobot: float, perkiraan_minggu: ?float, perkiraan_tanggal: ?CarbonInterface, selisih_hari: ?int, bisa_diperkirakan: bool}
     */
    public function lajuDanPerkiraan(Periode $periode): array
    {
        $sejak = now()->subDays(self::HARI_LAJU);

        // Bobot yang BERUBAH menjadi disetujui dalam 28 hari terakhir, dibaca
        // dari riwayat — bukan dari status sekarang. Tagihan yang disetujui
        // setahun lalu tidak menyumbang laju hari ini.
        $bobotBaru = (float) TagihanRiwayat::query()
            ->join('tagihan', 'tagihan.id', '=', 'tagihan_riwayat.tagihan_id')
            ->where('tagihan.periode_id', $periode->id)
            ->where('tagihan_riwayat.status_ke', StatusTagihan::Disetujui->value)
            ->where('tagihan_riwayat.created_at', '>=', $sejak)
            ->sum('tagihan.bobot_terkait');

        $laju = round($bobotBaru / (self::HARI_LAJU / 7), 3);
        $sisa = round(100 - $this->bobotSelesai($periode), 2);

        if ($laju <= 0.0) {
            return [
                'laju_mingguan' => 0.0,
                'sisa_bobot' => $sisa,
                'perkiraan_minggu' => null,
                'perkiraan_tanggal' => null,
                'selisih_hari' => null,
                'bisa_diperkirakan' => false,
            ];
        }

        $minggu = round($sisa / $laju, 1);
        $tanggal = now()->addWeeks((int) ceil($minggu));

        return [
            'laju_mingguan' => $laju,
            'sisa_bobot' => $sisa,
            'perkiraan_minggu' => $minggu,
            'perkiraan_tanggal' => $tanggal,
            'selisih_hari' => $periode->tanggal_target_unggah === null
                ? null
                : (int) $periode->tanggal_target_unggah->diffInDays($tanggal, false),
            'bisa_diperkirakan' => true,
        ];
    }

    /**
     * Kalimat yang dibaca pimpinan. Warna semantik menyertainya, tetapi
     * kalimatnya berdiri sendiri — mengandalkan warna saja menyingkirkan
     * pembaca yang tidak membedakan merah dan hijau.
     *
     * @return array{kalimat: string, warna: string}
     */
    public function kalimatLaju(Periode $periode): array
    {
        $l = $this->lajuDanPerkiraan($periode);

        if (! $l['bisa_diperkirakan']) {
            return [
                'kalimat' => 'Belum bisa diperkirakan — belum ada tagihan yang disetujui dalam '
                    .self::HARI_LAJU.' hari terakhir.',
                'warna' => 'gray',
            ];
        }

        $tanggal = $l['perkiraan_tanggal']->translatedFormat('d F Y');
        $selisih = $l['selisih_hari'];

        if ($selisih === null) {
            return [
                'kalimat' => "Dengan laju sekarang, pengumpulan rampung sekitar {$tanggal}. "
                    .'Tanggal target unggah belum ditetapkan.',
                'warna' => 'info',
            ];
        }

        if ($selisih <= 0) {
            return [
                'kalimat' => "Dengan laju sekarang, pengumpulan rampung sekitar {$tanggal} — "
                    .abs($selisih).' hari sebelum tenggat.',
                'warna' => 'success',
            ];
        }

        return [
            'kalimat' => "Dengan laju sekarang, pengumpulan rampung sekitar {$tanggal} — "
                ."{$selisih} hari SETELAH tenggat.",
            'warna' => $selisih <= 30 ? 'warning' : 'danger',
        ];
    }
}
