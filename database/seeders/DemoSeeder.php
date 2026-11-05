<?php

namespace Database\Seeders;

use App\Enums\AksesTautan;
use App\Enums\LevelSyaratPerlu;
use App\Enums\PeranPengguna;
use App\Enums\StatusTagihan;
use App\Enums\SumberData;
use App\Enums\ValidasiBukti;
use App\Exceptions\IsianDkpsTidakSah;
use App\Exceptions\TahunDiLuarJendela;
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
use App\Services\JendelaTs;
use App\Services\KalkulatorNa;
use App\Services\PembangkitTagihan;
use App\Services\PenghitungRumusDkps;
use App\Services\PengisiDkps;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Data demonstrasi yang SENGAJA TIDAK SERBA HIJAU.
 *
 * Dasbor yang penuh warna hijau pada demonstrasi pertama akan membuat pimpinan
 * menyimpulkan bahwa pekerjaannya hampir selesai, lalu berhenti menanyakannya.
 * Keadaan yang ditiru di sini adalah posisi yang paling mungkin di pertengahan
 * periode dan paling berguna untuk didiskusikan:
 *
 *   NA proyeksi di atas 321
 *   1 syarat perlu `lima` (E34), 3 `tiga` (E17, E45, E51), 1 `belum` (E58)
 *
 * CATATAN PERTENTANGAN. Prompt tahap 7 meminta dua hal yang tidak bisa
 * bersamaan: NA di kisaran 330–350 yang menghasilkan "Unggul 3 tahun", DAN
 * satu syarat perlu berlevel `belum`. Status "Unggul 3 tahun" menuntut KELIMA
 * syarat perlu sekurangnya berlevel `tiga`; dengan satu `belum`, NA 332,75 tetap
 * berujung "Terakreditasi 5 tahun".
 *
 * Yang dipilih di sini adalah sebaran syarat perlunya, bukan label statusnya —
 * justru karena hasilnya lebih mengajar: NA-nya sudah cukup untuk Unggul
 * 3 tahun, tetapi satu syarat perlu yang belum terpenuhi membatalkannya. Itu
 * persis kegagalan yang seluruh sistem ini dibangun untuk mencegah, dan
 * demonstrasi yang menyembunyikannya justru memberi rasa aman palsu.
 *   sekitar 40% tagihan disetujui, TIDAK merata antarpokja
 *   beberapa tagihan terlambat, beberapa tanpa penanggung jawab
 *   seluruh 35 lembar DKPS terisi dari data/demo-dkps.json lewat PengisiDkps,
 *   sepertiga barisnya belum terverifikasi
 *   seluruh rumus dihitung dari DKPS lewat PenghitungRumusDkps; Skor A
 *   campuran 2 sampai 4, dan PDS3 41,67 memenuhi syarat 3 tahun tetapi
 *   BELUM syarat 5 tahun
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
            // Katalog variabel wajib ada sebelum hitungRumus(); tanpanya
            // PenghitungRumusDkps melempar LogicException.
            VariabelDkpsSeeder::class,
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

    /**
     * Seluruh lembar DKPS diisi dari `data/demo-dkps.json` lewat PengisiDkps —
     * jalur yang sama dengan formulir, sehingga baris demo lolos skema yang
     * sama dan tahun acuannya dihitung dengan cara yang sama.
     *
     * `sumber` bergantian (indeks baris global modulo 3 → Manual, sisanya
     * Siakad), termasuk pada tabel yang semestinya dari SIAKAD, supaya
     * saringan "calon selisih" punya baris. Dua pertiga baris diverifikasi
     * langsung lewat forceFill: verifikasi lewat layanan menuntut bukti sah
     * per baris, dan itu di luar maksud data demo.
     *
     * Baris yang ditolak skema menggagalkan seeder dengan menyebut lembar dan
     * barisnya — demo yang diam-diam kehilangan baris adalah kegagalan.
     */
    private function isiDkps(Periode $periode): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();
        $pengisi = app(PengisiDkps::class);

        $demo = json_decode(file_get_contents(base_path('data/demo-dkps.json')), true, flags: JSON_THROW_ON_ERROR);

        if (count($demo) !== 35) {
            throw new RuntimeException('data/demo-dkps.json harus berisi 35 lembar, ditemukan '.count($demo).'.');
        }

        $i = 0;

        foreach ($demo as $kodeLembar => $lembar) {
            $kodeLembar = (string) $kodeLembar;
            $butir = DkpsButir::dariKode(explode('.', $kodeLembar)[0]);

            foreach ($lembar['baris'] as $n => $data) {
                $data = $this->gantiToken($data, $periode);

                try {
                    $baris = $pengisi->simpan(
                        $butir, $kodeLembar, $periode, $data,
                        $i % 3 === 0 ? SumberData::Manual : SumberData::Siakad,
                        $ketua,
                        kunciBaris: $data['_baris'] ?? null,
                    );
                } catch (IsianDkpsTidakSah|TahunDiLuarJendela|InvalidArgumentException $e) {
                    $pesan = $e instanceof IsianDkpsTidakSah
                        ? json_encode($e->galat, JSON_UNESCAPED_UNICODE)
                        : $e->getMessage();

                    throw new RuntimeException('Demo DKPS lembar '.$kodeLembar.' baris '.($n + 1).': '.$pesan, previous: $e);
                }

                // Dua pertiga baris sudah diverifikasi; tanggalnya tersebar
                // secara tetap supaya hasil seeding bisa diulang.
                if ($i % 3 !== 2) {
                    $baris->forceFill([
                        'diverifikasi_oleh' => $ketua->id,
                        'diverifikasi_pada' => now()->subDays(($i % 25) + 1),
                    ])->save();
                }

                $i++;
            }
        }
    }

    /**
     * Mengganti token tahun relatif dengan tahun absolut dari jendela TS:
     * `{TS-1}` menjadi bilangan tahun, `{TS-1}-05-20` menjadi tanggal penuh.
     * Berkas demo tidak memuat tahun absolut supaya tetap sah saat TS bergeser.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function gantiToken(array $data, Periode $periode): array
    {
        foreach ($data as $kunci => $nilai) {
            if (! is_string($nilai) || preg_match('/^\{(TS(?:-[1-4])?)\}(-\d{2}-\d{2})?$/', $nilai, $cocok) !== 1) {
                continue;
            }

            $tahun = JendelaTs::tahunDariLabel($periode, $cocok[1]);
            $data[$kunci] = isset($cocok[2]) && $cocok[2] !== '' ? $tahun.$cocok[2] : $tahun;
        }

        return $data;
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

        // Sasaran NA sekitar 333 (hasil ukur 332,75): surplus 40 di atas 300,
        // artinya sekitar 40 bobot berskor 4 dan sisanya 3.
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

        // E58 diturunkan ke skor 2: syarat perlunya berlevel `belum`
        // (tetapkanSyaratPerlu), dan ambang 3 tahun E58 adalah skor >= 3.
        // Tanpa ini level `belum` tidak punya dasar pada Penilaian ketua.
        Penilaian::where('periode_id', $periode->id)
            ->where('penilai_id', $ketua->id)
            ->where('elemen_id', $elemen->firstWhere('no', 58)?->id)
            ->update(['skor' => 2]);

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

    /**
     * Satu `lima` (E34), tiga `tiga` (E17, E45, E51), satu `belum` (E58) —
     * bukan lima-limanya hijau. E17 `tiga`, bukan `lima`, karena PDS3 demo
     * 41,67 memenuhi syarat 3 tahun tetapi belum syarat 5 tahun.
     */
    private function tetapkanSyaratPerlu(Periode $periode): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        // Urut nomor elemen: E17, E34, E45, E51, E58.
        $level = [
            LevelSyaratPerlu::Tiga,
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

    /**
     * Seluruh rumus dihitung dari DKPS lewat PenghitungRumusDkps, bukan
     * diketik. Perangkap PDS3 41,67 (skor 4, syarat 5 tahun belum) lahir dari
     * data Tabel 6–7. Rumus yang dilewati menggagalkan seeder: demo yang
     * diam-diam kehilangan rumus adalah kegagalan.
     */
    private function hitungRumus(Periode $periode): void
    {
        $ketua = User::where('peran', PeranPengguna::Ketua)->firstOrFail();

        $laporan = app(PenghitungRumusDkps::class)->hitungSemua($periode, $ketua);

        if ($laporan->dilewati !== []) {
            throw new RuntimeException('Demo: rumus dilewati — '.implode('; ', array_map(
                fn (string $kode, string $alasan) => "{$kode}: {$alasan}",
                array_keys($laporan->dilewati), $laporan->dilewati,
            )));
        }

        $this->command?->info('Demo: rumus DKPS — '.$laporan->ringkasan());
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
