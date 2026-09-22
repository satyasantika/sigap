<?php

namespace App\Services;

use App\Models\Bukti;
use App\Models\Elemen;
use App\Models\Narasi;
use App\Models\Periode;
use Illuminate\Support\Collection;

/**
 * Mengekspor seluruh naskah LED ke satu berkas Markdown.
 *
 * Bila masih ada bukti yang tidak bisa dibuka asesor, ekspor TETAP BERJALAN
 * tetapi memuat halaman ringkasan di depan. Menolak mengekspor akan membuat
 * orang menunda memeriksa sampai tenggat, sementara menyembunyikan masalahnya
 * akan membuat naskah dikirim dengan tautan mati di dalamnya.
 *
 * Penghasil PDF sengaja tidak dibangun di tahap ini.
 */
class EksporNaskah
{
    public function markdown(Periode $periode): string
    {
        $baris = [];

        $baris[] = "# Naskah Evaluasi Diri — {$periode->nama}";
        $baris[] = '';
        $baris[] = "Tahun acuan (TS): {$periode->ts_tahun} · Instrumen: {$periode->versi_instrumen}";
        $baris[] = 'Diekspor '.now()->translatedFormat('d F Y, H:i');
        $baris[] = '';

        $bermasalah = $this->buktiBermasalah($periode);

        if ($bermasalah->isNotEmpty()) {
            $baris = array_merge($baris, $this->halamanRingkasan($bermasalah));
        }

        $baris[] = '---';
        $baris[] = '';

        foreach ($this->elemenTerurut() as $elemen) {
            $baris = array_merge($baris, $this->bagianElemen($periode, $elemen));
        }

        return implode("\n", $baris)."\n";
    }

    public function namaBerkas(Periode $periode): string
    {
        return 'naskah-led-'.str($periode->nama)->slug().'-'.now()->format('Ymd-His').'.md';
    }

    /** @return Collection<int, Bukti> */
    private function buktiBermasalah(Periode $periode)
    {
        return Bukti::where('periode_id', $periode->id)
            ->bermasalah()
            ->with('elemen:id,no,nama')
            ->orderBy('judul')
            ->get();
    }

    /**
     * @param  Collection<int, Bukti>  $bermasalah
     * @return array<int, string>
     */
    private function halamanRingkasan($bermasalah): array
    {
        $baris = [];
        $baris[] = '## ⚠ Peringatan: '.$bermasalah->count().' bukti bermasalah';
        $baris[] = '';
        $baris[] = 'Bukti berikut **tidak bisa dibuka asesor** atau dinyatakan tidak sah.';
        $baris[] = 'Perbaiki sebelum naskah ini diunggah — asesor akan melihat halaman masuk,';
        $baris[] = 'bukan bukti Anda.';
        $baris[] = '';
        $baris[] = '| Bukti | Elemen | Keterbacaan | Keabsahan | Tautan |';
        $baris[] = '|---|---|---|---|---|';

        foreach ($bermasalah as $b) {
            $elemen = $b->elemen->map(fn (Elemen $e) => 'E'.$e->no)->join(', ') ?: '—';
            $baris[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $b->judul,
                $elemen,
                $b->akses_status->label(),
                $b->validasi_status->label(),
                $b->url_kanonik ?? '(berkas terunggah)',
            );
        }

        $baris[] = '';

        return $baris;
    }

    /** @return Collection<int, Elemen> */
    private function elemenTerurut()
    {
        return Elemen::with('kriteria')
            ->orderBy('no')
            ->get()
            ->sortBy(fn (Elemen $e) => [$e->kriteria->urutan, $e->no])
            ->values();
    }

    /** @return array<int, string> */
    private function bagianElemen(Periode $periode, Elemen $elemen): array
    {
        $baris = [];

        $baris[] = "## E{$elemen->no}. {$elemen->nama}";
        $baris[] = '';
        $baris[] = sprintf(
            '*%s · bobot %s · %s%s*',
            $elemen->kriteria->kode.' '.$elemen->kriteria->nama,
            number_format((float) $elemen->bobot, 2, ',', '.'),
            $elemen->jenis->label(),
            $elemen->syarat_perlu ? ' · **SYARAT PERLU**' : '',
        );
        $baris[] = '';

        $narasi = Narasi::where('periode_id', $periode->id)
            ->where('elemen_id', $elemen->id)->first();

        if ($narasi === null || blank($narasi->isi)) {
            $baris[] = '> **Naskah belum ditulis.**';
        } else {
            $baris[] = $narasi->isi;
            $baris[] = '';
            $baris[] = "*{$narasi->jumlah_kata} kata.*";
        }

        $baris[] = '';
        $baris = array_merge($baris, $this->daftarBukti($periode, $elemen));
        $baris[] = '';

        return $baris;
    }

    /** @return array<int, string> */
    private function daftarBukti(Periode $periode, Elemen $elemen): array
    {
        $bukti = $elemen->bukti()->where('bukti.periode_id', $periode->id)->get();

        if ($bukti->isEmpty()) {
            return ['**Bukti pendukung:** *belum ada.*'];
        }

        $baris = ['**Bukti pendukung:**', ''];

        foreach ($bukti as $b) {
            // url_kanonik dan akses_status terakhir ikut dicantumkan: asesor
            // yang membaca ekspor ini harus bisa menilai sendiri apakah
            // tautannya masih hidup.
            $tanda = $b->layakDipakai() ? '' : ' ⚠';

            $baris[] = sprintf(
                '- %s%s — %s (%s) · keterbacaan: %s · keabsahan: %s',
                $b->judul,
                $tanda,
                $b->url_kanonik ?? 'berkas terunggah: '.$b->nama_asli,
                $b->tanggal_kejadian->translatedFormat('d F Y'),
                $b->akses_status->label(),
                $b->validasi_status->label(),
            );

            if (filled($b->pivot->keterangan ?? null)) {
                $baris[] = '  - '.$b->pivot->keterangan;
            }
        }

        return $baris;
    }
}
