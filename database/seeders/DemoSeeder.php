<?php

namespace Database\Seeders;

use App\Enums\AksesTautan;
use App\Enums\LevelSyaratPerlu;
use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Models\Bukti;
use App\Models\DkpsBaris;
use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Narasi;
use App\Models\NarasiVersi;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\StatusSyaratPerlu;
use App\Models\Tagihan;
use App\Models\TagihanRiwayat;
use App\Models\User;
use App\Services\KalkulatorNa;
use App\Services\KalkulatorRumus;
use App\Services\PembangkitTagihan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data demonstrasi yang SENGAJA TIDAK SERBA HIJAU.
 *
 * Dasbor yang penuh warna hijau pada demonstrasi pertama akan membuat pimpinan
 * menyimpulkan bahwa pekerjaannya hampir selesai, lalu berhenti menanyakannya.
 * Keadaan yang ditiru di sini adalah posisi yang paling mungkin di pertengahan
 * periode dan paling berguna untuk didiskusikan:
 *
 *   NA proyeksi 330–350
 *   2 syarat perlu `lima`, 2 `tiga`, 1 `belum`
 *
 * CATATAN PERTENTANGAN. Prompt tahap 7 meminta dua hal yang tidak bisa
 * bersamaan: NA di kisaran 330–350 yang menghasilkan "Unggul 3 tahun", DAN
 * satu syarat perlu berlevel `belum`. Status "Unggul 3 tahun" menuntut KELIMA
 * syarat perlu sekurangnya berlevel `tiga`; dengan satu `belum`, NA 335 tetap
 * berujung "Terakreditasi 5 tahun".
 *
 * Yang dipilih di sini adalah sebaran syarat perlunya, bukan label statusnya —
 * justru karena hasilnya lebih mengajar: NA 335,75 sudah cukup untuk Unggul
 * 3 tahun, tetapi satu syarat perlu yang belum terpenuhi membatalkannya. Itu
 * persis kegagalan yang seluruh sistem ini dibangun untuk mencegah, dan
 * demonstrasi yang menyembunyikannya justru memberi rasa aman palsu.
 *   sekitar 40% tagihan disetujui, TIDAK merata antarpokja
 *   beberapa tagihan terlambat, beberapa tanpa penanggung jawab
 *   sekitar setengah butir DKPS terisi, sebagian belum terverifikasi
 *   beberapa bukti tidak bisa dibuka asesor
 *
 * Jalankan dengan:
 *   php artisan migrate:fresh --seed --seeder=Database\Seeders\DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /**
     * Porsi tagihan yang disetujui per pokja — sengaja timpang.
     *
     * Pokja yang tertinggal jauh adalah hal pertama yang ingin dilihat ketua,
     * dan demo yang meratakan semuanya justru menyembunyikan gunanya dasbor.
     */
    private const PORSI_SELESAI = [
        'POKJA-MUTU' => 0.75,
        'POKJA-MAWA' => 0.60,
        'POKJA-DIK' => 0.45,
        'POKJA-SARPRAS' => 0.40,
        'POKJA-SDM' => 0.20,
        'POKJA-DATA' => 0.40,
    ];

    public function run(): void
    {
        $this->call([
            IzinSeeder::class,
            KriteriaSeeder::class,
            ElemenSeeder::class,
            SyaratPerluSeeder::class,
            RumusSeeder::class,
            DkpsButirSeeder::class,
            OrganisasiSeeder::class,
        ]);

        $periode = Periode::aktif()->firstOrFail();
        $periode->update(['tanggal_target_unggah' => now()->addMonths(5)]);

        app(PembangkitTagihan::class)->untuk($periode);

        $this->tugaskanPenanggungJawab($periode);
        $this->buatBukti($periode);
        $this->tulisNarasi($periode);
        $this->isiDkps($periode);
        $this->setujuiSebagian($periode);
        $this->isiAsesmenMandiri($periode);
        $this->tetapkanSyaratPerlu($periode);
        $this->hitungRumus($periode);

        $this->laporkan($periode);
    }

    /**
     * Sebagian tagihan sengaja dibiarkan tanpa penanggung jawab, dan sebagian
     * tenggatnya sudah lewat — keduanya muncul di K9 dan K5.
     */
    private function tugaskanPenanggungJawab(Periode $periode): void
    {
        $anggota = User::whereIn('peran', [PeranPengguna::Anggota, PeranPengguna::Koordinator])->get();

        if ($anggota->isEmpty()) {
            return;
        }

        foreach (Tagihan::where('periode_id', $periode->id)->get() as $i => $t) {
            // Satu dari delapan dibiarkan tanpa penanggung jawab.
            if ($i % 8 === 7) {
                continue;
            }

            $t->update([
                'penanggung_jawab_id' => $anggota[$i % $anggota->count()]->id,
                // Satu dari enam sudah lewat tenggat.
                'tenggat' => $i % 6 === 0
                    ? now()->subDays(random_int(3, 40))
                    : now()->addDays(random_int(7, 90)),
            ]);
        }
    }

    /**
     * Bukti dengan campuran status yang realistis: sebagian terbuka dan sah,
     * sebagian perlu izin, satu tidak ditemukan, beberapa belum divalidasi.
     */
    private function buatBukti(Periode $periode): void
    {
        $pengunggah = User::where('peran', PeranPengguna::Anggota)->firstOrFail();
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        foreach (Elemen::orderBy('no')->get() as $i => $elemen) {
            // Sekitar dua pertiga elemen sudah punya bukti; sisanya muncul di
            // daftar "elemen tanpa bukti".
            if ($i % 3 === 2) {
                continue;
            }

            $tautan = $i % 2 === 0;

            $bukti = Bukti::create([
                'prodi_id' => $periode->prodi_id,
                'periode_id' => $periode->id,
                'judul' => "Bukti pendukung E{$elemen->no}",
                'keterangan' => 'Halaman '.random_int(1, 12).', bagian yang menopang klaim elemen ini.',
                'jenis' => $tautan ? 'tautan' : 'berkas',
                'url' => $tautan ? "https://drive.google.com/file/d/DEMO{$elemen->no}/view" : null,
                'url_kanonik' => $tautan ? "https://drive.google.com/file/d/DEMO{$elemen->no}/view" : null,
                'penyedia' => $tautan ? 'drive' : null,
                'tautan_bentuk' => $tautan ? 'berkas' : null,
                'nama_asli' => $tautan ? null : "sk-e{$elemen->no}.pdf",
                'path' => $tautan ? null : 'demo/'.Str::uuid().'.pdf',
                'sha256' => $tautan ? null : hash('sha256', "demo-{$elemen->no}"),
                'tanggal_kejadian' => now()->subMonths(random_int(2, 20)),
                'sumber' => $this->sumberDemo($i),
                'diunggah_oleh' => $pengunggah->id,
            ]);

            $bukti->elemen()->attach($elemen->id, [
                'keterangan' => "Menopang klaim elemen {$elemen->no}.",
            ]);

            $this->tetapkanStatusBukti($bukti, $i, $tautan, $ketua);

            // Tautkan ke tagihan elemen itu supaya alurnya bisa berjalan.
            $tagihan = Tagihan::where('periode_id', $periode->id)
                ->where('elemen_id', $elemen->id)->get();

            foreach ($tagihan as $t) {
                $t->bukti()->syncWithoutDetaching([$bukti->id]);
            }
        }
    }

    private function sumberDemo(int $i): SumberData
    {
        return match ($i % 4) {
            0 => SumberData::Siakad,
            1 => SumberData::Manual,
            2 => SumberData::Pddikti,
            default => SumberData::Eksternal,
        };
    }

    private function tetapkanStatusBukti(Bukti $bukti, int $i, bool $tautan, User $ketua): void
    {
        $akses = match (true) {
            ! $tautan => AksesTautan::BelumDiperiksa,
            // Satu dari sembilan tidak bisa dibuka asesor, satu dari lima belas
            // hilang sama sekali. Keduanya muncul di K4 dan di ekspor.
            $i % 15 === 3 => AksesTautan::TidakDitemukan,
            $i % 9 === 4 => AksesTautan::PerluIzin,
            default => AksesTautan::Terbuka,
        };

        $validasi = match (true) {
            $i % 11 === 5 => ValidasiBukti::Meragukan,
            $i % 17 === 7 => ValidasiBukti::TidakSah,
            // Sekitar seperempat masih antre divalidasi.
            $i % 4 === 3 => ValidasiBukti::BelumDivalidasi,
            default => ValidasiBukti::Sah,
        };

        $bukti->forceFill([
            'akses_status' => $akses,
            'akses_pesan' => $tautan ? 'HTTP '.($akses === AksesTautan::Terbuka ? '200' : '403').' (demo)' : null,
            'akses_diperiksa_pada' => $tautan ? now()->subDays(random_int(0, 5)) : null,
            'validasi_status' => $validasi,
            'divalidasi_oleh' => $validasi === ValidasiBukti::BelumDivalidasi ? null : $ketua->id,
            'divalidasi_pada' => $validasi === ValidasiBukti::BelumDivalidasi ? null : now()->subDays(random_int(1, 30)),
            'catatan_validasi' => $validasi->butuhCatatan()
                ? 'Tanggal kejadiannya di luar jendela data; mohon diperiksa ulang.'
                : null,
        ])->save();
    }

    /**
     * Naskah dengan panjang bervariasi: sebagian cukup, sebagian masih di
     * bawah 200 kata, sebagian belum ditulis sama sekali.
     */
    private function tulisNarasi(Periode $periode): void
    {
        $penulis = User::where('peran', PeranPengguna::Anggota)->firstOrFail();

        foreach (Elemen::orderBy('no')->get() as $i => $elemen) {
            // Sepertiga belum ditulis sama sekali.
            if ($i % 3 === 1) {
                continue;
            }

            $kata = $i % 4 === 0 ? random_int(60, 190) : random_int(210, 480);

            $narasi = Narasi::create([
                'prodi_id' => $periode->prodi_id,
                'periode_id' => $periode->id,
                'elemen_id' => $elemen->id,
                'isi' => $this->paragrafDemo($elemen->nama, $kata),
                'penulis_id' => $penulis->id,
            ]);

            $narasi->forceFill(['jumlah_kata' => $kata, 'versi' => 1])->save();

            NarasiVersi::create([
                'narasi_id' => $narasi->id, 'isi' => $narasi->isi,
                'jumlah_kata' => $kata, 'user_id' => $penulis->id,
            ]);
        }
    }

    private function paragrafDemo(string $judul, int $kata): string
    {
        $pembuka = "Program studi menyelenggarakan {$judul} secara terencana dan terdokumentasi. ";

        return $pembuka.implode(' ', array_fill(
            0, max(1, $kata - str_word_count($pembuka)), 'contoh'
        )).'.';
    }

    /** Sekitar setengah butir DKPS terisi; sebagian belum diverifikasi. */
    private function isiDkps(Periode $periode): void
    {
        $verifikator = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        foreach (DkpsButir::orderBy('no')->get() as $i => $butir) {
            if ($i % 2 === 1) {
                continue;
            }

            $baris = DkpsBaris::create([
                'prodi_id' => $periode->prodi_id,
                'periode_id' => $periode->id,
                'dkps_butir_id' => $butir->id,
                'tahun_acuan' => 'TS',
                'data' => ['jumlah' => random_int(5, 120), 'keterangan' => 'Data contoh demonstrasi.'],
                'sumber' => $i % 3 === 0 ? SumberData::Manual : SumberData::Siakad,
            ]);

            // Sekitar dua pertiga yang terisi sudah diverifikasi.
            if ($i % 3 !== 2) {
                $baris->forceFill([
                    'diverifikasi_oleh' => $verifikator->id,
                    'diverifikasi_pada' => now()->subDays(random_int(1, 25)),
                ])->save();
            }
        }
    }

    /**
     * Menyetujui tagihan menurut porsi per pokja yang sengaja timpang, lalu
     * menulis riwayatnya dengan tanggal tersebar supaya K8 punya laju.
     */
    private function setujuiSebagian(Periode $periode): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        foreach (Pokja::where('periode_id', $periode->id)->get() as $pokja) {
            $porsi = self::PORSI_SELESAI[$pokja->kode] ?? 0.35;

            $tagihan = Tagihan::where('periode_id', $periode->id)
                ->where('pokja_id', $pokja->id)
                ->whereNotNull('penanggung_jawab_id')
                ->orderBy('urutan')->get();

            $jumlah = (int) floor($tagihan->count() * $porsi);

            foreach ($tagihan->take($jumlah) as $i => $t) {
                // Tanggal persetujuan tersebar dalam 40 hari terakhir supaya
                // laju mingguan K8 punya angka yang masuk akal.
                $kapan = now()->subDays(random_int(0, 40));

                $t->forceFill([
                    'status' => StatusTagihan::Disetujui,
                    'disetujui_oleh' => $ketua->id,
                    'disetujui_pada' => $kapan,
                ])->save();

                TagihanRiwayat::create([
                    'tagihan_id' => $t->id, 'user_id' => $ketua->id,
                    'status_dari' => StatusTagihan::Direviu,
                    'status_ke' => StatusTagihan::Disetujui,
                ])->forceFill(['created_at' => $kapan])->saveQuietly();
            }

            // Sisanya tersebar di status menengah supaya alurnya terlihat
            // hidup, bukan hanya "belum" dan "disetujui".
            foreach ($tagihan->skip($jumlah)->take(6) as $i => $t) {
                $t->forceFill([
                    'status' => [
                        StatusTagihan::Dikerjakan, StatusTagihan::Diajukan,
                        StatusTagihan::Direviu, StatusTagihan::Dikembalikan,
                    ][$i % 4],
                ])->save();
            }
        }
    }

    /**
     * Asesmen mandiri diisi sebagian sehingga NA proyeksi mendarat di 330-350
     * — "Unggul 3 tahun, belum 5 tahun".
     *
     * Kisaran itu dipilih karena inilah posisi paling mungkin di pertengahan
     * periode: cukup baik untuk membuktikan sistemnya bekerja, cukup kurang
     * untuk memancing pertanyaan yang benar.
     */
    private function isiAsesmenMandiri(Periode $periode): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();
        $auditor = User::where('peran', PeranPengguna::Auditor)->firstOrFail();

        $elemen = Elemen::orderBy('no')->get();

        // Sasaran NA sekitar 340: surplus 40 di atas 300, artinya sekitar 40
        // bobot berskor 4 dan sisanya 3.
        $bobotSkor4 = 0.0;

        foreach ($elemen as $e) {
            $skor = $bobotSkor4 < 40.0 && $e->jenis->value !== 'data' ? 4 : 3;

            if ($skor === 4) {
                $bobotSkor4 += (float) $e->bobot;
            }

            Penilaian::create([
                'prodi_id' => $periode->prodi_id, 'periode_id' => $periode->id,
                'elemen_id' => $e->id, 'skor' => $skor,
                'penilai_id' => $ketua->id, 'tanggal' => now()->subDays(random_int(1, 20)),
            ]);
        }

        // Penilai kedua menskor sebagian, dan sengaja berselisih pada beberapa
        // elemen — itulah yang paling berguna didiskusikan sebelum asesor datang.
        foreach ($elemen->take(20) as $i => $e) {
            $skorKetua = Penilaian::where('elemen_id', $e->id)->where('penilai_id', $ketua->id)->value('skor');

            Penilaian::create([
                'prodi_id' => $periode->prodi_id, 'periode_id' => $periode->id,
                'elemen_id' => $e->id,
                'skor' => $i % 5 === 0 ? max(1, $skorKetua - 1) : $skorKetua,
                'penilai_id' => $auditor->id, 'tanggal' => now()->subDays(random_int(1, 15)),
            ]);
        }
    }

    /** Dua `lima`, dua `tiga`, satu `belum` — bukan lima-limanya hijau. */
    private function tetapkanSyaratPerlu(Periode $periode): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        $level = [
            LevelSyaratPerlu::Lima,
            LevelSyaratPerlu::Lima,
            LevelSyaratPerlu::Tiga,
            LevelSyaratPerlu::Tiga,
            LevelSyaratPerlu::Belum,
        ];

        foreach (Elemen::bersyaratPerlu()->orderBy('no')->get() as $i => $e) {
            StatusSyaratPerlu::create([
                'prodi_id' => $periode->prodi_id,
                'periode_id' => $periode->id,
                'elemen_id' => $e->id,
                'level' => $level[$i] ?? LevelSyaratPerlu::Belum,
                'nilai_terukur' => "Contoh demonstrasi untuk E{$e->no}",
                'catatan' => 'Ditetapkan pada data demonstrasi, bukan dari pengukuran sesungguhnya.',
                'diperbarui_oleh' => $ketua->id,
            ]);
        }
    }

    /** Beberapa rumus dihitung supaya layar Nilai Rumus tidak kosong. */
    private function hitungRumus(Periode $periode): void
    {
        $k = app(KalkulatorRumus::class);
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        // PDS3 41,67: skor penuh tetapi syarat lima tahun BELUM terpenuhi.
        // Sengaja dipilih supaya perangkap paling penting instrumen ini
        // terlihat sejak demonstrasi pertama.
        $k->simpan($k->pds3(nds3: 5, ndtps: 12, ndlk: 3, ndgb: 1), $periode, $ketua);
        $k->simpan($k->pgblkl(ndgb: 1, ndlk: 3, ndl: 4, ndtps: 12), $periode, $ketua);
        $k->simpan($k->ppdtps(dosenBerpublikasi: 3, ndtps: 12, jumlahArtikel: 20), $periode, $ketua);
        $k->simpan($k->rk(n1: 2, n2: 3, n3: 4, ndtps: 12), $periode, $ketua);
        $k->simpan($k->rsa(nas: 100, ndtps: 12), $periode, $ketua);
        $k->simpan($k->ripk(3.31), $periode, $ketua);
        $k->simpan($k->tkm([['a' => 55, 'b' => 30, 'c' => 12, 'd' => 3]]), $periode, $ketua);
    }

    private function laporkan(Periode $periode): void
    {
        $na = app(KalkulatorNa::class)->hitung($periode);
        $disetujui = Tagihan::where('periode_id', $periode->id)
            ->where('status', StatusTagihan::Disetujui)->count();
        $total = Tagihan::where('periode_id', $periode->id)->count();

        $this->command?->info(sprintf(
            'Demo: NA %s (%s) · %d dari %d tagihan disetujui (%d%%) · %d bukti · %d baris DKPS',
            number_format($na->na, 2, ',', '.'),
            $na->kalimatStatus(),
            $disetujui, $total, round($disetujui / max(1, $total) * 100),
            Bukti::where('periode_id', $periode->id)->count(),
            DkpsBaris::where('periode_id', $periode->id)->count(),
        ));

        $this->command?->warn(
            'Data ini sengaja TIDAK serba hijau: ada tagihan terlambat, bukti yang '
            .'tidak bisa dibuka asesor, dan satu syarat perlu yang belum terpenuhi.'
        );

        // Pelajaran utama demonstrasi ini dinyatakan terang-terangan, bukan
        // dibiarkan ditemukan sendiri: angka NA yang bagus tidak menjamin apa
        // pun selama satu syarat perlu masih menganga.
        if ($na->na >= 321 && ! $na->syarat3) {
            $this->command?->warn(sprintf(
                'Perhatikan: NA %s sudah cukup untuk "Unggul — masa berlaku 3 tahun", '
                .'tetapi satu syarat perlu masih berlevel `belum` sehingga statusnya '
                .'tetap "%s". Inilah kegagalan yang sistem ini dibangun untuk mencegah.',
                number_format($na->na, 2, ',', '.'),
                $na->kalimatStatus(),
            ));
        }
    }
}
