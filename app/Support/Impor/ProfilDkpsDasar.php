<?php

namespace App\Support\Impor;

use App\Enums\SumberData;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\ImporBatch;
use Illuminate\Database\Eloquent\Model;

/**
 * Induk keempat profil impor yang mendarat di `dkps_baris`.
 *
 * Seluruhnya menyimpan ke kolom `data` berbentuk json, bukan ke tabel dosen
 * atau kerja sama tersendiri — tabel dosen sengaja belum dibuat, dan rumus
 * PDS3, PGBLKL, serta PPDTPS memang membaca angka DTPS dari DKPS.
 *
 * Konsekuensinya kunci duplikasi (NIDN, DOI) hidup di dalam json, jadi
 * salinan ternormalkannya disimpan ke kolom nyata `kunci_normal` — mencarinya
 * di dalam json tidak mungkin di MariaDB (JSON hanya alias LONGTEXT di sana,
 * jadi pencarian isi json tidak didukung).
 */
abstract class ProfilDkpsDasar implements ProfilImpor
{
    public function __construct(
        protected readonly string $periodeId,
        protected readonly string $prodiId,
        protected readonly string $penggunaId,
    ) {}

    /** Nomor butir DKPS tempat baris ini mendarat. */
    abstract protected function nomorButir(): int;

    /** Kolom json yang disimpan dari satu baris tempelan. */
    abstract protected function kolomData(array $baris): array;

    public function kunciDuplikat(array $baris): ?string
    {
        return $baris['_kunci'] ?? null;
    }

    public function validasi(array $baris): array
    {
        $galat = [];

        foreach ($this->medan() as $kunci => $def) {
            if ($def['wajib'] && blank($baris[$kunci] ?? null)) {
                $galat[] = "{$def['label']} kosong.";
            }
        }

        if (! in_array($baris['tahun_acuan'] ?? '', ['TS', 'TS-1', 'TS-2', 'TS-3', 'TS-4'], true)) {
            $galat[] = 'Tahun acuan harus TS, TS-1, TS-2, TS-3, atau TS-4 — bukan tahun mutlak.';
        }

        if (SumberData::tryFrom($baris['sumber'] ?? '') === null) {
            $galat[] = 'Sumber data harus salah satu dari: '
                .collect(SumberData::cases())->map(fn ($s) => $s->value)->join(', ').'.';
        }

        return $galat;
    }

    public function simpan(array $baris, ImporBatch $batch): Model
    {
        return DkpsBaris::create([
            'prodi_id' => $this->prodiId,
            'periode_id' => $this->periodeId,
            'dkps_butir_id' => $this->idButir(),
            'tahun_acuan' => $baris['tahun_acuan'],
            'data' => $this->kolomData($baris),
            'sumber' => $baris['sumber'],
            'kunci_normal' => $baris['_kunci'] ?? null,
            'impor_batch_id' => $batch->getKey(),
        ]);
    }

    public function perbarui(Model $lama, array $baris, ImporBatch $batch): Model
    {
        $lama->update([
            'data' => $this->kolomData($baris),
            'sumber' => $baris['sumber'],
            'impor_batch_id' => $batch->getKey(),
        ]);

        return $lama;
    }

    public function cariYangAda(string $kunci, ?string $periodeId): ?Model
    {
        return DkpsBaris::where('periode_id', $periodeId)
            ->where('dkps_butir_id', $this->idButir())
            ->where('kunci_normal', $kunci)
            ->first();
    }

    protected function idButir(): string
    {
        return DkpsButir::where('no', $this->nomorButir())->value('id');
    }

    /** Medan yang dimiliki semua profil DKPS. */
    protected function medanBersama(): array
    {
        return [
            'tahun_acuan' => ['label' => 'Tahun acuan', 'wajib' => true, 'tipe' => 'pilihan',
                'contoh' => 'TS-1'],
            'sumber' => ['label' => 'Sumber data', 'wajib' => true, 'tipe' => 'pilihan',
                'contoh' => 'siakad'],
        ];
    }
}
