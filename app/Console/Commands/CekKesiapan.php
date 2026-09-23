<?php

namespace App\Console\Commands;

use App\Models\DkpsButir;
use App\Models\Elemen;
use App\Models\Izin as ModelIzin;
use App\Models\Periode;
use App\Models\Pokja;
use App\Models\Rumus;
use App\Models\Simulasi;
use App\Models\SyaratPerlu;
use App\Models\User;
use App\Support\Pemasangan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Memeriksa kesiapan pemasangan sebelum dipakai sungguhan.
 *
 * Dibuat untuk dijalankan DI SERVER setelah setiap penggelaran, bukan sekali
 * saat pemasangan. Yang diperiksa bukan apakah kodenya benar — itu urusan
 * `php artisan test` — melainkan apakah LINGKUNGANNYA benar: kunci terpasang,
 * aset terbangun, migrasi sudah jalan, data instrumen utuh, berkas bisa
 * ditulis, dan tidak ada kata sandi contoh yang tertinggal.
 *
 * Tiga tingkat, dan bedanya penting:
 *   GAGAL   sistem tidak layak dipakai. Perintah keluar dengan kode 1.
 *   PERIKSA sesuatu yang mungkin benar, mungkin tidak — hanya manusia yang tahu.
 *   OK      sudah sesuai.
 */
class CekKesiapan extends Command
{
    protected $signature = 'sigap:cek-kesiapan {--subfolder= : Subfolder pemasangan, misalnya /sigap}';

    protected $description = 'Memeriksa kesiapan pemasangan: lingkungan, aset, data instrumen, dan berkas';

    private array $hasil = [];

    public function handle(): int
    {
        $this->lingkungan();
        $this->subfolder();
        $this->basisData();
        $this->dataInstrumen();
        $this->berkas();
        $this->aset();
        $this->keamanan();
        $this->operasional();

        return $this->laporkan();
    }

    // --- pemeriksaan ---------------------------------------------------------

    private function lingkungan(): void
    {
        $this->periksa('APP_KEY terpasang',
            filled(config('app.key')),
            'Jalankan: php artisan key:generate');

        $produksi = app()->environment('production');

        $this->periksa('APP_ENV', true, null,
            'sekarang: '.config('app.env'));

        if ($produksi) {
            $this->periksa('APP_DEBUG mati di produksi',
                config('app.debug') === false,
                'APP_DEBUG=true membocorkan jejak tumpukan, isi .env, dan kueri ke siapa pun '
                .'yang memicu galat. Setel APP_DEBUG=false.');
        } else {
            $this->catat('PERIKSA', 'APP_DEBUG',
                'APP_ENV bukan production, jadi APP_DEBUG=true tidak diperiksa. '
                .'Di server sungguhan, setel APP_ENV=production dan APP_DEBUG=false.');
        }

        $this->periksa('Zona waktu Asia/Jakarta',
            config('app.timezone') === 'Asia/Jakarta',
            'Tenggat tagihan dihitung menurut zona waktu ini. Setel APP_TIMEZONE=Asia/Jakarta.');

        $this->periksa('Bahasa antarmuka Indonesia',
            config('app.locale') === 'id',
            'Setel APP_LOCALE=id, jika tidak pesan validasi muncul dalam bahasa Inggris.');
    }

    private function subfolder(): void
    {
        $url = (string) config('app.url');
        $subfolder = rtrim((string) ($this->option('subfolder') ?: Pemasangan::subfolder()), '/');

        if ($subfolder === '') {
            $this->catat('OK', 'Pemasangan di akar domain', "APP_URL={$url}");

            return;
        }

        $this->catat('PERIKSA', 'Pemasangan di subfolder '.$subfolder,
            "APP_URL={$url}. Server web WAJIB meneruskan SCRIPT_NAME berisi "
            ."{$subfolder}/index.php supaya Laravel membangun tautan dengan awalan itu. "
            .'Uji dengan membuka halaman muka dan menekan tombol Masuk.');

        $this->periksa('SESSION_PATH sesuai subfolder',
            config('session.path') === $subfolder,
            "config('session.path') sekarang '".config('session.path')."'. Setel SESSION_PATH={$subfolder} "
            .'supaya cookie sesi tidak bertabrakan dengan aplikasi lain di domain yang sama.');

        if (str_starts_with($url, 'https://')) {
            $this->periksa('SESSION_SECURE_COOKIE menyala di HTTPS',
                (bool) config('session.secure') === true,
                'Setel SESSION_SECURE_COOKIE=true agar cookie sesi tidak pernah dikirim lewat HTTP.');
        }
    }

    private function basisData(): void
    {
        try {
            DB::connection()->getPdo();
            $versi = DB::selectOne('select version() as v')->v ?? '?';
            $this->catat('OK', 'Basis data tersambung', $versi);
        } catch (Throwable $e) {
            $this->catat('GAGAL', 'Basis data tersambung', $e->getMessage());

            return;
        }

        $this->periksa('Tidak ada migrasi tertunda',
            $this->migrasiTertunda() === 0,
            'Jalankan: php artisan migrate --force');

        $tabel = DB::selectOne(
            'select table_collation as c from information_schema.tables where table_schema = ? and table_name = ?',
            [DB::getDatabaseName(), 'users'],
        );

        $this->periksa('Collation utf8mb4_unicode_ci',
            ($tabel->c ?? '') === 'utf8mb4_unicode_ci',
            'Collation sekarang: '.($tabel->c ?? 'tidak terbaca')
            .'. Nama dosen dengan huruf beraksen akan diurutkan dan dibandingkan keliru.');
    }

    private function dataInstrumen(): void
    {
        foreach (['elemen.json', 'izin.json', 'izin-sistem.json', 'menu.json', 'pokja.json'] as $berkas) {
            $this->periksa("data/{$berkas} terbaca",
                is_readable(base_path("data/{$berkas}")),
                'Berkas ini dibaca saat runtime oleh seeder dan kelas Izin. Tanpa data/menu.json '
                .'seluruh menu lenyap; tanpa data/izin.json seluruh orang ditolak.');
        }

        if (! Schema::hasTable('elemen')) {
            $this->catat('GAGAL', 'Data instrumen tersemai', 'Tabel elemen belum ada. Jalankan migrasi dan seeder.');

            return;
        }

        $bobot = round((float) Elemen::sum('bobot'), 2);

        $this->periksa('59 elemen tersemai', Elemen::count() === 59,
            'Yang ada: '.Elemen::count().'. Jalankan: php artisan db:seed --force');
        $this->periksa('Total bobot elemen 100,00', $bobot === 100.0,
            "Yang ada: {$bobot}. Seluruh aritmetika Nilai Akreditasi bergantung pada angka ini.");
        $this->periksa('144 sel izin tersemai', ModelIzin::count() === 144,
            'Yang ada: '.ModelIzin::count().'. Tanpa ini setiap peran ditolak tanpa alasan yang jelas.');
        $this->periksa('5 syarat perlu', SyaratPerlu::count() === 5, 'Yang ada: '.SyaratPerlu::count());
        $this->periksa('15 rumus', Rumus::count() === 15, 'Yang ada: '.Rumus::count());
        $this->periksa('28 butir DKPS', DkpsButir::count() === 28, 'Yang ada: '.DkpsButir::count());
        $this->periksa('6 pokja', Pokja::count() >= 6, 'Yang ada: '.Pokja::count());
    }

    private function berkas(): void
    {
        $wajibTulis = [
            'storage/app/bukti' => 'tempat berkas bukti disimpan',
            'storage/framework/views' => 'cache tampilan',
            'storage/framework/sessions' => 'sesi berbasis berkas',
            'storage/logs' => 'berkas log',
            'bootstrap/cache' => 'cache konfigurasi dan rute',
        ];

        foreach ($wajibTulis as $jalur => $guna) {
            $penuh = base_path($jalur);

            if (! is_dir($penuh)) {
                $this->catat('PERIKSA', "{$jalur} ada", "Belum ada ({$guna}). Buat foldernya bila dipakai.");

                continue;
            }

            $this->periksa("{$jalur} bisa ditulis", is_writable($penuh),
                "Dipakai untuk {$guna}. Jalankan: chown -R www-data: {$jalur}");
        }

        $manual = public_path('manual');

        $this->periksa('public/manual menunjuk docs/manual',
            (is_link($manual) || is_dir($manual)) && realpath($manual) === realpath(base_path('docs/manual')),
            'Manual pengguna tidak akan tersaji. Buat ulang: ln -s ../docs/manual public/manual');
    }

    private function aset(): void
    {
        $manifes = public_path('build/manifest.json');

        if (! is_file($manifes)) {
            $this->catat('GAGAL', 'Aset frontend terbangun',
                'public/build/manifest.json tidak ada. Tanpa ini setiap halaman gagal dirender. '
                .'Jalankan: npm ci && npm run build');

            return;
        }

        $isi = json_decode(file_get_contents($manifes), true) ?: [];

        $this->periksa('Aset frontend terbangun', isset($isi['resources/css/app.css']),
            'manifest.json ada tetapi tidak memuat resources/css/app.css. Bangun ulang.');

        $this->periksa('Tema panel terbangun',
            isset($isi['resources/css/filament/panel/theme.css']),
            'Tanpa tema panel, dasbor bento tampil sebagai tumpukan teks polos. '
            .'Pastikan resources/css/filament/panel/theme.css ada di input vite.config.js, lalu bangun ulang.');

        foreach (['css/filament', 'js/filament'] as $folder) {
            $this->periksa("Aset Filament public/{$folder} terpasang",
                is_dir(public_path($folder)),
                'Jalankan: php artisan filament:assets');
        }
    }

    private function keamanan(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        // Seeder memasang kata sandi contoh `password`. Di basis data demo itu
        // pantas; di server sungguhan itu pintu terbuka, dan tidak ada yang
        // akan menyadarinya karena tidak ada satu pun galat yang muncul.
        $lemah = User::query()->get(['id', 'email', 'password'])
            ->filter(fn (User $u) => filled($u->password) && Hash::check('password', $u->password))
            ->pluck('email');

        $this->periksa('Tidak ada kata sandi contoh yang tertinggal',
            $lemah->isEmpty(),
            'Akun berikut masih memakai sandi "password": '.$lemah->implode(', ')
            .'. Setel ulang lewat layar Pengguna sebelum sistem dipakai.');

        $adminAktif = User::where('peran', 'admin')->where('aktif', true)->count();

        $this->periksa('Ada administrator sistem yang aktif',
            $adminAktif > 0,
            'Tanpa admin aktif, tidak ada yang bisa membuat pengguna atau membuka periode. '
            .'Jalankan: php artisan make:filament-user lalu ubah perannya di basis data.');

        // Demo memberi sesi sungguhan di dalam aplikasi kepada siapa pun yang
        // memegang kodenya. Demo yang dibuat sekali lalu dilupakan adalah pintu
        // yang dibiarkan terbuka — dan tidak ada gejala apa pun yang
        // menandainya.
        if (Schema::hasTable('simulasi')) {
            $terbuka = Simulasi::demoBerjalan()->get();

            if ($terbuka->isNotEmpty()) {
                $this->catat('PERIKSA', 'Ada demo yang sedang terbuka',
                    $terbuka->map(fn (Simulasi $s) => $s->nama.' (kode '.$s->kode_demo
                        .', sampai '.$s->demo_berlaku_sampai->format('d M Y H:i').')')->implode('; ')
                    .'. Pastikan itu memang disengaja; tutup lewat layar Simulasi bila tidak.');
            }

            $basi = Simulasi::whereNotNull('kode_demo')
                ->where('demo_berlaku_sampai', '<=', now())
                ->get();

            $this->periksa('Tidak ada demo kedaluwarsa yang akunnya tertinggal',
                $basi->isEmpty(),
                'Demo berikut sudah lewat masa berlakunya tetapi kode dan akun demonya belum '
                .'dicabut: '.$basi->pluck('nama')->implode(', ')
                .'. Tutup lewat layar Simulasi agar akunnya benar-benar dihapus.');
        }

        $this->periksa('Berkas bukti tidak tersaji langsung dari web',
            ! is_dir(public_path('bukti')) && ! is_link(public_path('bukti')),
            'Ada public/bukti. Berkas bukti memuat nama dosen dan dokumen bertanda tangan; '
            .'ia hanya boleh diakses lewat aplikasi, bukan sebagai berkas statis.');
    }

    private function operasional(): void
    {
        $this->catat('PERIKSA', 'Penjadwal berjalan',
            'Pemeriksaan tautan bukti dijadwalkan harian pukul 02:00. Pastikan cron server memuat: '
            .'* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

        if (config('queue.default') === 'database') {
            $this->periksa('Tabel antrean ada', Schema::hasTable('jobs'),
                'QUEUE_CONNECTION=database tetapi tabel jobs tidak ada. Jalankan migrasi.');

            $this->catat('PERIKSA', 'Pekerja antrean berjalan',
                'QUEUE_CONNECTION=database menuntut satu proses: php artisan queue:work. '
                .'Tanpa itu pekerjaan antrean menumpuk diam-diam.');
        }

        if (Schema::hasTable('periode')) {
            $aktif = Periode::aktif()->count();

            $this->periksa('Ada periode berjalan', $aktif > 0,
                'Belum ada periode berstatus berjalan, jadi dasbor akan kosong. '
                .'Buat lewat layar Periode, lalu jalankan: php artisan tagihan:bangkitkan');
        }

        $this->catat('PERIKSA', 'Pemeriksa tautan bisa keluar ke internet',
            'PemeriksaTautan membuka tautan Drive TANPA kredensial apa pun. Bila server berada di '
            .'balik proksi keluar, pastikan jalurnya terbuka — kalau tidak, seluruh bukti akan '
            .'ditandai tidak terbaca padahal sebenarnya baik-baik saja.');
    }

    // --- pelaporan -----------------------------------------------------------

    private function periksa(string $label, bool $lulus, ?string $saran, ?string $catatan = null): void
    {
        $this->catat($lulus ? 'OK' : 'GAGAL', $label, $lulus ? $catatan : $saran);
    }

    private function catat(string $tingkat, string $label, ?string $keterangan = null): void
    {
        $this->hasil[] = compact('tingkat', 'label', 'keterangan');
    }

    private function laporkan(): int
    {
        $gaya = ['OK' => 'info', 'PERIKSA' => 'comment', 'GAGAL' => 'error'];

        foreach ($this->hasil as $h) {
            $tanda = match ($h['tingkat']) {
                'OK' => '  OK    ',
                'PERIKSA' => ' PERIKSA',
                default => ' GAGAL  ',
            };

            $this->line("<{$gaya[$h['tingkat']]}>{$tanda}</> | {$h['label']}");

            if (filled($h['keterangan'])) {
                $this->line('         '.wordwrap($h['keterangan'], 92, "\n         "));
            }
        }

        $gagal = collect($this->hasil)->where('tingkat', 'GAGAL')->count();
        $periksa = collect($this->hasil)->where('tingkat', 'PERIKSA')->count();
        $ok = collect($this->hasil)->where('tingkat', 'OK')->count();

        $this->newLine();
        $this->line("HASIL: {$ok} OK, {$periksa} perlu diperiksa manusia, {$gagal} gagal");

        if ($gagal > 0) {
            $this->newLine();
            $this->error('Sistem BELUM siap dipakai. Perbaiki yang GAGAL lebih dulu.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Tidak ada yang gagal. Baca baris PERIKSA — itu hal yang hanya manusia bisa pastikan.');

        return self::SUCCESS;
    }

    private function migrasiTertunda(): int
    {
        $terjalan = Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')->all()
            : [];

        $ada = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $f) => basename($f, '.php'));

        return $ada->diff($terjalan)->count();
    }
}
