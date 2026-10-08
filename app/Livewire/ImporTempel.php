<?php

namespace App\Livewire;

use App\Models\ImporBatch;
use App\Models\Periode;
use App\Services\PelaksanaImpor;
use App\Support\Impor\MemilikiSandiBaru;
use App\Support\Impor\PenguraiTempelan;
use App\Support\Impor\PratinjauImpor;
use App\Support\Impor\ProfilImpor;
use Livewire\Component;
use Throwable;

/**
 * Impor tempel-tabel: tempel -> petakan -> pratinjau -> jalankan.
 *
 * Keempat langkah terjadi di DALAM SATU MODAL yang tidak pernah tertutup di
 * tengah jalan, termasuk perbandingan dengan baris lama dan hasil akhirnya.
 * Modal yang menutup di langkah ketiga membuat orang kehilangan tempelan yang
 * baru saja disusunnya, dan mereka berhenti memakai fiturnya.
 *
 * Komponen ini dipakai ulang apa adanya; yang berbeda hanya profilnya.
 *
 * Profil dan periode TIDAK disimpan sebagai objek pada properti Livewire:
 * Livewire menghidrasi ulang komponen dari payload publik di setiap
 * request, dan constructor-promoted property (bentuk semula berkas ini)
 * tidak pernah terisi kembali setelah mount pertama -- $this->profil selalu
 * null begitu pengguna menekan tombol apa pun. Sebagai gantinya disimpan
 * nama kelas profil beserta argumen primitifnya, dan profil dibangun ulang
 * lewat profil() pada setiap pemanggilan method.
 */
class ImporTempel extends Component
{
    public int $langkah = 1;

    public string $tempelan = '';

    public bool $barisPertamaKepala = true;

    /** @var array<int, string> */
    public array $kepala = [];

    /** @var array<int, array<int, string>> */
    public array $barisMentah = [];

    /** @var array<string, int|null> */
    public array $pemetaan = [];

    /** @var array<int, array<string, mixed>> */
    public array $pratinjau = [];

    /** @var array<int, string> */
    public array $keputusan = [];

    public ?string $galat = null;

    public ?string $hasil = null;

    public ?string $batchId = null;

    /**
     * Sandi acak yang baru dibuat untuk pengguna baru, ditampilkan SATU KALI
     * di langkah hasil. Hanya terisi bila profilnya ImporPengguna -- lihat
     * App\Support\Impor\MemilikiSandiBaru.
     *
     * @var array<int, array{email: string, sandi: string}>
     */
    public array $sandiBaru = [];

    /** Kelas profil impor, mis. App\Support\Impor\ImporBukti::class. */
    public string $profilKelas = '';

    /** Argumen konstruktor profil, dalam urutan yang diterimanya. */
    public array $profilArgs = [];

    /** Periode akreditasi; null bila profilnya tidak terikat periode (mis. pengguna). */
    public ?string $periodeId = null;

    public function mount(string $profilKelas, array $profilArgs = [], ?string $periodeId = null): void
    {
        $this->profilKelas = $profilKelas;
        $this->profilArgs = $profilArgs;
        $this->periodeId = $periodeId;
    }

    public function profil(): ProfilImpor
    {
        return app()->make($this->profilKelas, $this->profilArgs);
    }

    public function periode(): ?Periode
    {
        return $this->periodeId === null ? null : Periode::find($this->periodeId);
    }

    public function medan(): array
    {
        return $this->profil()->medan();
    }

    /** Langkah 1 -> 2: uraikan tempelan dan tebak pemetaannya. */
    public function uraikan(): void
    {
        $this->galat = null;

        try {
            $hasil = PenguraiTempelan::urai($this->tempelan, $this->barisPertamaKepala);

            $this->kepala = $hasil['kepala'];
            $this->barisMentah = $hasil['baris'];
            $this->pemetaan = PenguraiTempelan::tebakPemetaan($this->kepala, $this->medan());
            $this->langkah = 2;
        } catch (Throwable $e) {
            $this->galat = $e->getMessage();
        }
    }

    /** Langkah 2 -> 3: susun pratinjau beserta deteksi duplikasi dua arah. */
    public function pratinjaukan(): void
    {
        $this->galat = null;

        $kurang = collect($this->medan())
            ->filter(fn (array $d, string $k) => $d['wajib'] && ($this->pemetaan[$k] ?? null) === null)
            ->map(fn (array $d) => $d['label']);

        if ($kurang->isNotEmpty()) {
            $this->galat = 'Kolom wajib belum dipetakan: '.$kurang->join(', ').'.';

            return;
        }

        $terpetakan = collect($this->barisMentah)->map(function (array $baris) {
            $hasil = [];

            foreach ($this->pemetaan as $kunci => $indeks) {
                $hasil[$kunci] = $indeks === null ? null : ($baris[$indeks] ?? null);
            }

            return $hasil;
        })->all();

        $this->pratinjau = PratinjauImpor::susun($terpetakan, $this->profil(), $this->periodeId);

        // Keputusan bawaan: yang baru diimpor, sisanya dilewati. Memperbarui
        // diam-diam menimpa pekerjaan orang lain.
        $this->keputusan = collect($this->pratinjau)
            ->mapWithKeys(fn (array $b) => [
                $b['no'] => $b['status'] === PratinjauImpor::BARU ? 'impor' : 'lewati',
            ])->all();

        $this->langkah = 3;
    }

    public function ringkasan(): array
    {
        return PratinjauImpor::ringkas($this->pratinjau);
    }

    public function kalimatRingkasan(): string
    {
        return PratinjauImpor::kalimat($this->ringkasan());
    }

    /** Aksi borongan di kepala tabel pratinjau. */
    public function semua(string $status, string $pilihan): void
    {
        foreach ($this->pratinjau as $baris) {
            if ($baris['status'] === $status) {
                $this->keputusan[$baris['no']] = $pilihan;
            }
        }
    }

    /** Langkah 3 -> 4: jalankan dalam satu transaksi. */
    public function jalankan(): void
    {
        $this->galat = null;

        try {
            // Instance yang SAMA diteruskan ke pelaksana dan dibaca lagi
            // setelahnya -- ImporPengguna menyimpan sandi acak yang baru
            // dibuat ke properti instance-nya sendiri selama simpan()
            // dipanggil; instance baru dari profil() tidak akan membawanya.
            $profil = $this->profil();

            $batch = app(PelaksanaImpor::class)->jalankan(
                $this->pratinjau, $this->keputusan, $profil, $this->periode(), auth()->user(),
            );

            $this->batchId = $batch->getKey();
            $this->hasil = "{$batch->jumlah_impor} baris diimpor, "
                ."{$batch->jumlah_perbarui} diperbarui, {$batch->jumlah_lewati} dilewati.";
            $this->sandiBaru = $profil instanceof MemilikiSandiBaru ? $profil->sandiBaruDibuat() : [];
            $this->langkah = 4;
        } catch (Throwable $e) {
            // Satu baris gagal berarti tidak ada yang tersimpan, dan pesannya
            // menyebut apa yang salah -- impor separuh jalan jauh lebih buruk.
            $this->galat = 'Impor dibatalkan seluruhnya: '.$e->getMessage();
        }
    }

    public function batalkanImpor(): void
    {
        $batch = ImporBatch::find($this->batchId);

        if ($batch === null) {
            return;
        }

        app(PelaksanaImpor::class)->batalkan($batch, $this->profil(), auth()->user());
        $this->hasil = 'Impor dibatalkan. Baris yang dibuat dihapus, baris yang diperbarui dikembalikan.';
    }

    public function ulangi(): void
    {
        $this->reset(['langkah', 'tempelan', 'kepala', 'barisMentah', 'pemetaan',
            'pratinjau', 'keputusan', 'galat', 'hasil', 'batchId', 'sandiBaru']);
        $this->langkah = 1;
    }

    public function render()
    {
        return view('livewire.impor-tempel');
    }
}
