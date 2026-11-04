<?php

namespace App\Support\Impor;

use App\Enums\SumberData;
use App\Exceptions\TahunDiLuarJendela;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\ImporBatch;
use App\Models\Periode;
use App\Models\User;
use App\Services\PengisiDkps;
use App\Support\Dkps\SkemaLembar;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Impor tempel untuk satu lembar DKPS, dengan medan persis kolom isian
 * lembarnya (data/dkps-tabel.json).
 *
 * Normalisasi, validasi, kunci duplikasi, dan tahun acuan seluruhnya
 * diserahkan ke SkemaLembar, dan penulisan ke PengisiDkps — jalur yang sama
 * dengan layar isian. Kalau impor punya aturan sendiri, baris yang lolos
 * impor bisa ditolak saat disunting di layar (atau sebaliknya).
 *
 * Konstruktornya sengaja hanya menerima nilai primitif: ImporTempel membangun
 * ulang profil dari argumen ini di setiap request Livewire.
 */
class ImporLembarDkps implements ProfilImpor
{
    public function __construct(
        private readonly string $periodeId,
        private readonly string $prodiId,
        private readonly string $penggunaId,
        private readonly string $kodeTabel,
        private readonly string $kodeLembar,
    ) {}

    public function nama(): string
    {
        return $this->skema()->judul();
    }

    public function medan(): array
    {
        $skema = $this->skema();

        $medan = [
            'sumber' => ['label' => 'Sumber data', 'wajib' => true, 'tipe' => 'pilihan',
                'contoh' => SumberData::Manual->value],
        ];

        if ($skema->adaBarisTetap()) {
            $medan['_baris'] = ['label' => (string) $skema->labelKolomBaris(), 'wajib' => true,
                'tipe' => 'pilihan', 'contoh' => $skema->barisTetap()[0]['kunci'] ?? ''];
        }

        foreach ($skema->kolomInput() as $kolom) {
            $medan[$kolom['kunci']] = [
                'label' => $kolom['label'],
                'wajib' => (bool) ($kolom['wajib'] ?? false),
                'tipe' => $kolom['tipe'],
                'contoh' => (string) ($kolom['pilihan'][0] ?? ''),
            ];
        }

        return $medan;
    }

    public function kunciDuplikat(array $baris): ?string
    {
        return $baris['_kunci'] ?? null;
    }

    /**
     * Pada lembar berbaris tetap `_baris` dicocokkan lebih dulu, longgar
     * terhadap kunci maupun label barisnya: SkemaLembar melempar pengecualian
     * untuk kunci asing, sedangkan pratinjau perlu menampilkannya sebagai
     * galat baris. Kunci yang tak dikenali dibiarkan mentah untuk validasi().
     */
    public function normalkan(array $baris): array
    {
        $skema = $this->skema();
        $sumber = mb_strtolower(trim((string) ($baris['sumber'] ?? '')));
        $kolom = array_intersect_key($baris, array_flip(array_column($skema->kolomInput(), 'kunci')));

        $hasil = ['sumber' => $sumber === '' ? SumberData::Manual->value : $sumber];

        if ($skema->adaBarisTetap()) {
            $kunciBaris = $this->cocokkanBaris($skema, $baris['_baris'] ?? null);

            if ($kunciBaris === null) {
                return $hasil + ['_baris' => $baris['_baris'] ?? null] + $kolom + ['_kunci' => null];
            }

            $normal = $skema->normalkan($kolom, $kunciBaris);

            return $hasil + $normal + ['_kunci' => $skema->kunciBaris($normal, $kunciBaris)];
        }

        $normal = $skema->normalkan($kolom);

        return $hasil + $normal + ['_kunci' => $this->kunciLengkap($normal) ? $skema->kunciBaris($normal) : null];
    }

    public function validasi(array $baris): array
    {
        $skema = $this->skema();
        $galat = [];

        if (SumberData::tryFrom($baris['sumber'] ?? '') === null) {
            $galat[] = 'Sumber data harus salah satu dari: '
                .collect(SumberData::cases())->map(fn ($s) => $s->value)->join(', ').'.';
        }

        $kunciBaris = null;

        if ($skema->adaBarisTetap()) {
            $kunciBaris = $baris['_baris'] ?? null;

            if (blank($kunciBaris)) {
                return [...$galat, "{$skema->labelKolomBaris()} wajib diisi."];
            }

            if (! in_array($kunciBaris, array_column($skema->barisTetap(), 'kunci'), true)) {
                return [...$galat, "Baris tetap tidak dikenal: {$kunciBaris}."];
            }
        }

        $galatSkema = $skema->validasi($baris, $kunciBaris);

        // Tahun acuan baru bisa ditentukan dari isian yang sudah sah; kolom
        // tahun yang kosong sudah disebut oleh galat skema di atas.
        if ($galatSkema === []) {
            try {
                $skema->tahunAcuan($this->periode(), $baris, $kunciBaris);
            } catch (TahunDiLuarJendela|InvalidArgumentException $e) {
                $galat[] = $e->getMessage();
            }
        }

        return array_values([...$galat, ...array_values($galatSkema)]);
    }

    public function simpan(array $baris, ImporBatch $batch): Model
    {
        return $this->tulis($baris, $batch, null);
    }

    public function perbarui(Model $lama, array $baris, ImporBatch $batch): Model
    {
        return $this->tulis($baris, $batch, $lama);
    }

    public function cariYangAda(string $kunci, ?string $periodeId): ?Model
    {
        return DkpsBaris::where('periode_id', $periodeId)
            ->where('dkps_butir_id', $this->butir()->id)
            ->where('lembar_kode', $this->kodeLembar)
            ->where('kunci_normal', $kunci)
            ->first();
    }

    /**
     * Penolakan PengisiDkps (isian, jendela tahun, wewenang) sengaja tidak
     * ditangkap: PelaksanaImpor berjalan dalam satu transaksi, dan satu baris
     * yang ditolak harus membatalkan seluruh impor.
     */
    private function tulis(array $baris, ImporBatch $batch, ?Model $lama): DkpsBaris
    {
        $hasil = app(PengisiDkps::class)->simpan(
            butir: $this->butir(),
            kodeLembar: $this->kodeLembar,
            periode: $this->periode(),
            data: array_diff_key($baris, array_flip(['_kunci', 'sumber', '_baris'])),
            sumber: SumberData::from($baris['sumber']),
            oleh: User::findOrFail($this->penggunaId),
            baris: $lama,
            kunciBaris: $baris['_baris'] ?? null,
        );

        // Ditandai seperti profil DKPS lain, supaya asal baris tetap terlacak
        // ke impor yang membuatnya.
        $hasil->impor_batch_id = $batch->getKey();
        $hasil->save();

        return $hasil;
    }

    /** Kunci baris tetap yang cocok dengan kunci atau labelnya, tanpa peduli huruf dan spasi. */
    private function cocokkanBaris(SkemaLembar $skema, mixed $nilai): ?string
    {
        if (blank($nilai)) {
            return null;
        }

        $dicari = NormalisasiKunci::dari((string) $nilai);

        foreach ($skema->barisTetap() as $b) {
            if (in_array($dicari, [NormalisasiKunci::dari($b['kunci']), NormalisasiKunci::dari($b['label'])], true)) {
                return $b['kunci'];
            }
        }

        return null;
    }

    /**
     * Kunci duplikasi hanya dibentuk bila seluruh kolom kuncinya terisi —
     * kalau tidak, dua baris yang sama-sama kosong kolom kuncinya dianggap
     * kembar, padahal keduanya akan ditolak validasi.
     */
    private function kunciLengkap(array $normal): bool
    {
        $kunci = $this->butir()->lembarMentah($this->kodeLembar)['kunci'] ?? [];

        return $kunci !== [] && collect($kunci)->every(fn (string $k) => filled($normal[$k] ?? null));
    }

    private function skema(): SkemaLembar
    {
        return $this->butir()->skema($this->kodeLembar);
    }

    private function butir(): DkpsButir
    {
        return DkpsButir::dariKode($this->kodeTabel);
    }

    private function periode(): Periode
    {
        return Periode::findOrFail($this->periodeId);
    }
}
