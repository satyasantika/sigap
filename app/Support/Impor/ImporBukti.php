<?php

namespace App\Support\Impor;

use App\Enums\JenisBukti;
use App\Enums\SumberData;
use App\Models\Bukti;
use App\Models\ImporBatch;
use App\Services\NormalisasiTautan;
use Illuminate\Database\Eloquent\Model;

/**
 * Profil impor untuk bukti berupa tautan.
 *
 * Kunci duplikasinya `url_kanonik` — itu sebabnya normalisasi tautan penting:
 * empat bentuk URL Drive yang berbeda menunjuk berkas yang sama, dan tanpa
 * dikanonkan keempatnya lolos sebagai baris berbeda.
 */
class ImporBukti implements ProfilImpor
{
    public function __construct(
        private readonly string $periodeId,
        private readonly string $prodiId,
        private readonly string $penggunaId,
    ) {}

    public function nama(): string
    {
        return 'Bukti (tautan)';
    }

    public function medan(): array
    {
        return [
            'judul' => ['label' => 'Judul bukti', 'wajib' => true, 'tipe' => 'teks',
                'contoh' => 'SK Dekan Nomor 2401'],
            'url' => ['label' => 'Tautan', 'wajib' => true, 'tipe' => 'url',
                'contoh' => 'https://drive.google.com/file/d/.../view'],
            // Contoh tanggal diturunkan dari waktu berjalan, bukan ditulis
            // mati: AGENTS.md aturan 3 melarang tahun literal di app/, dan
            // contoh yang menua ("2026-03-15" dibaca tahun 2029) justru
            // menyesatkan orang yang menyalinnya apa adanya.
            'tanggal_kejadian' => ['label' => 'Tanggal kejadian', 'wajib' => true, 'tipe' => 'tanggal',
                'contoh' => now()->subYear()->format('Y-m-d')],
            'sumber' => ['label' => 'Sumber data', 'wajib' => true, 'tipe' => 'pilihan',
                'contoh' => 'manual'],
            'keterangan' => ['label' => 'Keterangan isi', 'wajib' => false, 'tipe' => 'teks',
                'contoh' => 'Halaman 2, daftar nama dosen'],
        ];
    }

    public function kunciDuplikat(array $baris): ?string
    {
        return $baris['url_kanonik'] ?? null;
    }

    public function normalkan(array $baris): array
    {
        $baris['judul'] = trim($baris['judul'] ?? '');
        $baris['url'] = trim($baris['url'] ?? '');

        $urai = $baris['url'] === '' ? null : NormalisasiTautan::urai($baris['url']);

        if ($urai !== null) {
            $baris['url_kanonik'] = $urai['url_kanonik'];
            $baris['penyedia'] = $urai['penyedia'];
            $baris['tautan_id'] = $urai['tautan_id'];
            $baris['tautan_bentuk'] = $urai['tautan_bentuk'];
        }

        $baris['sumber'] = mb_strtolower(trim($baris['sumber'] ?? 'manual'));

        return $baris;
    }

    public function validasi(array $baris): array
    {
        $galat = [];

        if (blank($baris['judul'] ?? null)) {
            $galat[] = 'Judul kosong.';
        }

        if (blank($baris['url_kanonik'] ?? null)) {
            $galat[] = 'Tautan kosong atau tidak sah.';
        }

        if (blank($baris['tanggal_kejadian'] ?? null)) {
            $galat[] = 'Tanggal kejadian wajib diisi.';
        } elseif (strtotime($baris['tanggal_kejadian']) === false) {
            $galat[] = "Tanggal `{$baris['tanggal_kejadian']}` tidak terbaca.";
        }

        if (SumberData::tryFrom($baris['sumber'] ?? '') === null) {
            $galat[] = 'Sumber data harus salah satu dari: '
                .collect(SumberData::cases())->map(fn ($s) => $s->value)->join(', ').'.';
        }

        return $galat;
    }

    public function simpan(array $baris, ImporBatch $batch): Model
    {
        return Bukti::create([
            'prodi_id' => $this->prodiId,
            'periode_id' => $this->periodeId,
            'judul' => $baris['judul'],
            'keterangan' => $baris['keterangan'] ?? null,
            'jenis' => JenisBukti::Tautan,
            'url' => $baris['url'],
            'url_kanonik' => $baris['url_kanonik'],
            'penyedia' => $baris['penyedia'] ?? null,
            'tautan_id' => $baris['tautan_id'] ?? null,
            'tautan_bentuk' => $baris['tautan_bentuk'] ?? null,
            'kunci_normal' => $baris['url_kanonik'],
            'tanggal_kejadian' => $baris['tanggal_kejadian'],
            'sumber' => $baris['sumber'],
            'diunggah_oleh' => $this->penggunaId,
            'impor_batch_id' => $batch->getKey(),
        ]);
    }

    public function perbarui(Model $lama, array $baris, ImporBatch $batch): Model
    {
        $lama->update([
            'judul' => $baris['judul'],
            'keterangan' => $baris['keterangan'] ?? $lama->keterangan,
            'tanggal_kejadian' => $baris['tanggal_kejadian'],
            'sumber' => $baris['sumber'],
            'impor_batch_id' => $batch->getKey(),
        ]);

        return $lama;
    }

    public function cariYangAda(string $kunci, string $periodeId): ?Model
    {
        return Bukti::where('periode_id', $periodeId)->where('kunci_normal', $kunci)->first();
    }
}
