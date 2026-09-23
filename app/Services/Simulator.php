<?php

namespace App\Services;

use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Simulasi;
use App\Models\User;
use App\Support\Izin;
use App\Support\Na\HasilNa;
use App\Support\Na\Skenario;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Simulasi dalam dua bentuk, keduanya bisa dibuat dan dihapus seperlunya.
 *
 *   skor     Pengandaian di atas periode sungguhan: "bagaimana bila E58 naik
 *            ke 4 dan syarat perlu E12 terpenuhi untuk lima tahun?" Dijawab
 *            dengan menghitung ulang NA memakai skor pengganti, TANPA menulis
 *            apa pun ke `penilaian` atau `status_syarat_perlu`.
 *
 *   periode  Periode sandbox: salinan kerangka periode sungguhan — pokja dan
 *            137 tagihannya — yang bisa dikerjakan bebas lalu dibuang utuh.
 *            Berguna untuk melatih anggota baru tanpa mengotori data asli.
 *
 * Batas yang memisahkan keduanya dari data sungguhan:
 *
 *   - Simulasi skor tidak pernah menulis ke tabel penilaian. Satu-satunya
 *     tempat hasilnya disimpan adalah kolom `simulasi.hasil`, dan itu cache
 *     tampilan, bukan sumber kebenaran.
 *   - Periode sandbox ditandai `periode.simulasi = true`, dan `scopeAktif`
 *     mengecualikannya. Ia tidak akan pernah muncul sebagai periode berjalan.
 *
 * Menghapus simulasi skor tidak menghapus apa pun selain pengandaiannya.
 * Menghapus simulasi periode MEMBUANG periode sandbox beserta seluruh isinya —
 * itulah gunanya, dan itulah sebabnya ia meminta konfirmasi di layar.
 */
class Simulator
{
    public function __construct(
        private readonly KalkulatorNa $kalkulator,
        private readonly PembangkitTagihan $pembangkit,
        private readonly Impersonasi $impersonasi,
    ) {}

    // --- simulasi skor -------------------------------------------------------

    public function buatSimulasiSkor(
        User $oleh,
        Periode $periode,
        string $nama,
        Skenario $skenario,
        ?string $keterangan = null,
    ): Simulasi {
        $this->pastikanBoleh($oleh);

        return DB::transaction(function () use ($oleh, $periode, $nama, $skenario, $keterangan) {
            $simulasi = Simulasi::create([
                'prodi_id' => $periode->prodi_id,
                'periode_id' => $periode->id,
                'jenis' => 'skor',
                'nama' => $nama,
                'keterangan' => $keterangan,
                'parameter' => $skenario->keParameter(),
                'dibuat_oleh' => $oleh->getKey(),
            ]);

            $this->perbaruiHasil($simulasi);

            $this->impersonasi->catat(
                'simulasi.buat', $oleh,
                keterangan: "Membuat simulasi skor \"{$nama}\" dengan {$skenario->jumlahPengandaian()} pengandaian.",
                subjek: $simulasi,
            );

            return $simulasi->refresh();
        });
    }

    /** Menghitung ulang NA simulasi dan menyimpan ringkasannya. */
    public function perbaruiHasil(Simulasi $simulasi): HasilNa
    {
        $hasil = $this->hitung($simulasi);

        $simulasi->update(['hasil' => [
            'na' => $hasil->na,
            'status' => $hasil->status,
            'masa_berlaku' => $hasil->masaBerlaku,
            'syarat3' => $hasil->syarat3,
            'syarat5' => $hasil->syarat5,
            'belum_dinilai' => $hasil->jumlahElemenBelumDinilai,
            'dihitung_pada' => now()->toDateTimeString(),
        ]]);

        return $hasil;
    }

    /** NA menurut pengandaian simulasi ini. */
    public function hitung(Simulasi $simulasi): HasilNa
    {
        $periode = $simulasi->jenis === 'periode'
            ? ($simulasi->periodeSandbox ?? $simulasi->periode)
            : $simulasi->periode;

        return $this->kalkulator->hitung(
            $periode,
            skenario: Skenario::dariParameter($simulasi->parameter),
        );
    }

    /**
     * Selisih terhadap keadaan sungguhan.
     *
     * Angka inilah yang dicari orang saat membuka simulasi: bukan "NA-nya 341"
     * melainkan "naik 5,25 dan statusnya berubah". Tanpa pembanding, NA
     * simulasi mudah dibaca sebagai NA sungguhan.
     *
     * @return array{nyata: HasilNa, simulasi: HasilNa, selisih: float, statusBerubah: bool}
     */
    public function bandingkan(Simulasi $simulasi): array
    {
        $nyata = $this->kalkulator->hitung($simulasi->periode);
        $andai = $this->hitung($simulasi);

        return [
            'nyata' => $nyata,
            'simulasi' => $andai,
            'selisih' => round($andai->na - $nyata->na, 2),
            'statusBerubah' => $andai->kalimatStatus() !== $nyata->kalimatStatus(),
        ];
    }

    // --- periode sandbox -----------------------------------------------------

    /**
     * Menyalin kerangka periode: pokja dan seluruh tagihannya.
     *
     * Yang TIDAK ikut disalin: narasi, bukti, penilaian, dan riwayat. Sandbox
     * yang berisi salinan bukti sungguhan berarti dokumen yang sama hidup di
     * dua tempat, dan cepat atau lambat salah satunya disunting sendirian.
     * Sandbox dimulai kosong — memang untuk dilatih mengisinya.
     */
    public function buatSandbox(
        User $oleh,
        Periode $acuan,
        string $nama,
        ?string $keterangan = null,
    ): Simulasi {
        $this->pastikanBoleh($oleh);

        return DB::transaction(function () use ($oleh, $acuan, $nama, $keterangan) {
            $sandbox = Periode::create([
                'prodi_id' => $acuan->prodi_id,
                'nama' => $this->namaSandboxUnik($acuan, $nama),
                'ts_tahun' => $acuan->ts_tahun,
                'tanggal_target_unggah' => $acuan->tanggal_target_unggah,
                'versi_instrumen' => $acuan->versi_instrumen,
                'status' => StatusPeriode::Berjalan,
                'simulasi' => true,
            ]);

            foreach ($acuan->pokja()->get() as $p) {
                $salinan = Pokja::create([
                    'periode_id' => $sandbox->id,
                    'kode' => $p->kode,
                    'nama' => $p->nama,
                    'koordinator_id' => $p->koordinator_id,
                    'catatan' => $p->catatan,
                ]);

                // Keanggotaan ikut disalin supaya orang yang berlatih menemukan
                // dirinya di pokja yang sama seperti di periode sungguhan.
                $salinan->anggota()->sync(
                    $p->anggota()->pluck('users.id')->all()
                );
            }

            $this->pembangkit->untuk($sandbox);

            $simulasi = Simulasi::create([
                'prodi_id' => $acuan->prodi_id,
                'periode_id' => $acuan->id,
                'jenis' => 'periode',
                'nama' => $nama,
                'keterangan' => $keterangan,
                'periode_sandbox_id' => $sandbox->id,
                'dibuat_oleh' => $oleh->getKey(),
            ]);

            $this->impersonasi->catat(
                'simulasi.buat', $oleh,
                keterangan: "Membuat periode sandbox \"{$sandbox->nama}\" dari {$acuan->nama}.",
                subjek: $simulasi,
            );

            return $simulasi->refresh();
        });
    }

    // --- menghapus -----------------------------------------------------------

    /**
     * Menghapus simulasi.
     *
     * Simulasi skor: barisnya di-soft-delete, tidak ada data lain yang
     * tersentuh — memang tidak pernah ada.
     *
     * Simulasi periode: periode sandbox ikut dihapus beserta isinya. Dihapus
     * PERMANEN, bukan soft delete, karena aturan 8 melindungi data akreditasi
     * dan data latihan bukan data akreditasi. Menyimpannya justru berbahaya:
     * periode simulasi yang bangkit kembali dari kubur soft delete akan
     * tampak seperti periode sungguhan yang hilang.
     */
    public function hapus(User $oleh, Simulasi $simulasi): void
    {
        $this->pastikanBoleh($oleh);

        DB::transaction(function () use ($oleh, $simulasi) {
            $nama = $simulasi->nama;
            $jenis = $simulasi->jenis;

            if ($jenis === 'periode' && $simulasi->periode_sandbox_id !== null) {
                $sandbox = Periode::withTrashed()->find($simulasi->periode_sandbox_id);

                if ($sandbox !== null && ! $sandbox->simulasi) {
                    throw new RuntimeException(
                        'Periode yang ditunjuk simulasi ini bukan periode simulasi. '
                        .'Penghapusan dibatalkan.'
                    );
                }

                $simulasi->update(['periode_sandbox_id' => null]);
                $sandbox?->forceDelete();
            }

            $simulasi->delete();

            $this->impersonasi->catat(
                'simulasi.hapus', $oleh,
                keterangan: "Menghapus simulasi {$jenis} \"{$nama}\".",
            );
        });
    }

    // --- pagar ---------------------------------------------------------------

    private function pastikanBoleh(User $oleh): void
    {
        if (! Izin::bolehSistem($oleh, 'simulasi.kelola')) {
            throw new RuntimeException('Anda tidak berwenang mengelola simulasi.');
        }
    }

    /**
     * Nama periode sandbox wajib unik per prodi — tabel `periode` memaksanya.
     * Diberi awalan "[SIMULASI]" supaya tidak pernah terbaca sebagai periode
     * sungguhan di daftar mana pun, termasuk daftar yang lupa menyaring.
     */
    private function namaSandboxUnik(Periode $acuan, string $nama): string
    {
        $dasar = mb_substr('[SIMULASI] '.$nama, 0, 80);
        $calon = $dasar;
        $n = 2;

        while (Periode::withTrashed()
            ->where('prodi_id', $acuan->prodi_id)
            ->where('nama', $calon)
            ->exists()
        ) {
            $calon = mb_substr($dasar, 0, 76).' '.$n++;
        }

        return $calon;
    }
}
