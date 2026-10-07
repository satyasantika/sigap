# SIGAP

**Sistem Informasi Gerbang Akreditasi PPG** — aplikasi internal satu-pintu untuk
mengumpulkan data, bukti, dan naskah yang dibutuhkan akreditasi Program Studi
Pendidikan Profesi Guru di LAMDIK, sekaligus memperlihatkan progres pengumpulan
itu kepada ketua task force dan pimpinan.

Aplikasi ini bukan aplikasi akademik. Ia tidak mengelola KRS, nilai, atau
jadwal. Ia mengelola *pekerjaan pengumpulan bukti akreditasi*.

| Item | Nilai |
|---|---|
| Framework | Laravel 13.32 / PHP 8.3 |
| Antarmuka | Filament 5.8 (panel di `/panel`) |
| Basis data | `db_sigap` — MariaDB 10.11 di host, pengguna `app` |
| URL lokal | http://localhost:8021 |
| Image PHP | `php/laravel12.Dockerfile` |

Semua `php`, `composer`, dan `artisan` dijalankan **di dalam container**
`sigap-php`, tidak pernah di host.

## Memasang dari nol

```bash
# 1. Nyalakan container
docker compose -f ~/code/docker-compose.yml up -d sigap-php sigap-nginx node22

# 2. Dependensi
docker exec sigap-php composer install
docker exec -w /var/www/html/sigap laravel-node22 npm install

# 3. Konfigurasi
cp .env.example .env
docker exec sigap-php php artisan key:generate

# 4. Basis data — satu untuk aplikasi, satu untuk uji
docker exec sigap-php php -r '
$p = new PDO("mysql:host=host.docker.internal;port=3306", "app", "app123");
foreach (["db_sigap", "db_sigap_test"] as $d) {
    $p->exec("CREATE DATABASE IF NOT EXISTS {$d} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}'

# 5. Migrasi dan data awal
docker exec sigap-php php artisan migrate:fresh --seed

# 6. Bangkitkan tagihan untuk periode itu (137 tagihan, bobot total 100,000)
docker exec sigap-php php artisan tagihan:bangkitkan "PPG 2027"

# Pemeriksaan keterbacaan tautan berjalan harian lewat penjadwal; untuk
# menjalankannya sekarang juga:
docker exec sigap-php php artisan bukti:periksa-tautan

# 7. Aset frontend — WAJIB, bukan opsional.
# Panel memakai tema Vite sendiri (resources/css/filament/panel/theme.css).
# Tanpa langkah ini, dasbor bento tampil sebagai tumpukan teks polos.
# Node dijalankan di container terpisah: Vite 8 butuh Node 20+.
docker exec -w /var/www/html/sigap laravel-node22 npm run build
```

Buka http://localhost:8021 — halaman muka, dengan tautan ke manual pengguna
dan ke halaman masuk. Panelnya sendiri di `/panel`.

Halaman muka terbuka tanpa masuk dan **tidak memuat satu pun angka akreditasi**:
tidak ada NA, tidak ada progres, tidak ada nama dosen. Yang tampil hanya bentuk
instrumennya, dibaca dari `data/*.json`. `BerandaController` sengaja tidak
menyentuh basis data sama sekali, dan ada uji yang menegakkannya.

Pengguna contoh dari seeder, satu per peran, kata sandi `password`:
`admin@sigap.test`, `ketua@sigap.test`, `pimpinan@sigap.test`,
`koordinator@sigap.test`, `anggota@sigap.test`, `auditor@sigap.test`.
Semuanya diminta mengganti kata sandi pada masuk pertama.

## Memeriksa bahwa semuanya benar

```bash
docker exec sigap-php php artisan test     # seluruh uji harus hijau
python3 data/verifikasi.py                 # 33 pemeriksaan, harus keluar 0
```

`verifikasi.py` memeriksa data instrumen LAMDIK di `data/*.json` — 59 elemen
berbobot total tepat 100,00, enam pokja yang membagi habis elemen 1–59, dan 144
sel matriks izin. Jika ada yang `GAGAL`, jangan lanjutkan: seluruh aritmetika
Nilai Akreditasi bergantung pada angka-angka itu.

Uji berjalan di atas **MariaDB** (`db_sigap_test`), bukan SQLite. Alasannya
konkret: `whereJsonContains()` jalan di SQLite tetapi gagal di MariaDB, jadi uji
di atas SQLite akan meloloskan kueri yang rusak di produksi.

## Yang perlu diketahui sebelum menyentuh kode

Aturan yang mengikat untuk pengembangan lanjutan. Ringkasnya:

- **`data/*.json` adalah sumber kebenaran.** Nomor elemen, bobot, rumus, ambang
  syarat perlu, dan butir DKPS diambil dari dokumen resmi LAMDIK. Seeder membaca
  berkas itu; jangan pernah menyalin isinya ke larik PHP. Layar Referensi pun
  hanya-baca: mengubah instrumen berarti menyunting `data/*.json` lalu
  menjalankan seeder, dengan commit tersendiri berjenis `data:`.
- **Seluruh kunci utama UUID**, termasuk tabel bawaan Laravel.
- **TS adalah parameter.** Tidak boleh ada tahun yang ditulis mati di `app/`
  atau `database/migrations/`; semuanya diturunkan dari `periode.ts_tahun`
  lewat `app/Services/JendelaTs.php` — satu-satunya tempat aritmetika tahun
  boleh terjadi. Ada uji yang menjaganya.
- **Skor penuh bukan berarti syarat perlu terpenuhi.** Pada PDS3 dan PPDTPS,
  ambang skor 4 justru lebih rendah daripada ambang syarat perlu lima tahun.
  `KalkulatorRumus` mengembalikan keduanya sebagai medan terpisah, dan
  antarmuka tidak boleh menyamakannya.
- **Progres selalu tertimbang bobot, tidak pernah cacah tagihan**, dan setiap
  persen didampingi angka absolut. Dasbor yang menghitung cacah akan membuat
  orang mengerjakan yang mudah lebih dulu dan meninggalkan elemen berbobot
  besar sampai tenggat.
- **Otorisasi diputuskan di satu tempat**: `app/Support/Izin.php` yang membaca
  `data/izin.json`. Tidak ada perbandingan peran di tempat lain, tidak ada
  `Gate::before`, tidak ada peran super.
- **Status tagihan hanya berpindah lewat `AlurTagihan::pindah()`.** Kolomnya
  tidak fillable. Setiap perpindahan menulis satu baris `tagihan_riwayat`, dan
  riwayat itu menolak disunting maupun dihapus.
- **Jumlah `bobot_terkait` satu periode harus tetap 100,000.** Progres dihitung
  dari bobot, jadi selisih di sini muncul sebagai persentase yang salah di
  dasbor ketua.
- **Bukti dinilai pada dua sumbu terpisah.** `akses_status` diperiksa mesin
  tanpa kredensial apa pun — meniru asesor yang tidak punya akses; memakai
  kredensial membuat pemeriksaan selalu lulus dan tidak berguna.
  `validasi_status` dinilai manusia. Keduanya harus hijau sebelum tagihan bisa
  disetujui, dan alasan penolakannya disebut satu per satu.
- **Isi `storage/app/bukti/` tidak pernah masuk riwayat git.** Isinya nama
  dosen, nomor serdik, dan dokumen bertanda tangan.

Dokumen rancangan lengkap dan prompt bertahap ada di `vibecoding/` (lokal,
tidak terlacak git).

## Merawat sistem ini

### Membuat periode akreditasi baru

Satu periode mewakili satu siklus akreditasi. Periode lama **tidak dihapus** —
seluruh tagihan, bukti, dan naskahnya tetap tertaut ke sana, dan itulah yang
membuat "bagaimana kita tahun lalu?" bisa dijawab.

1. **Pengaturan → Periode → Buat.** Isi nama (`PPG 2032`), tahun acuan TS, dan
   tanggal target unggah. Biarkan statusnya `persiapan`.
2. **Pengaturan → Pokja.** Pokja terikat periode, jadi keenamnya perlu dibuat
   ulang untuk periode baru. Pakai kode yang sama (`POKJA-DIK` dan seterusnya)
   supaya pembangkit tagihan menemukannya.
3. Ubah status periode menjadi `berjalan`. **Hanya satu periode boleh
   `berjalan`** — dasbor dan seluruh layar membaca periode aktif dari sana.
4. Bangkitkan tagihannya:

   ```bash
   docker exec sigap-php php artisan tagihan:bangkitkan "PPG 2032"
   ```

   Perintah ini aman dijalankan ulang: tagihan yang sudah ada dilewati, bukan
   digandakan.

### Mengganti TS dan akibatnya

`periode.ts_tahun` adalah **satu-satunya** sumber tahun di seluruh aplikasi.
Mengubahnya di satu tempat menggeser seluruh jendela data sekaligus:

| Jendela | TS = 2027 | TS = 2030 |
|---|---|---|
| `saat TS` | 2027 | 2030 |
| `TS-2 s.d. TS` | 2025–2027 | 2028–2030 |
| `TS-4 s.d. TS-2` | 2023–2025 | 2026–2028 |
| `5 tahun terakhir` | 2023–2027 | 2026–2030 |

Yang ikut bergeser: pilihan tahun pada layar isian DKPS, rentang data yang
dipakai `KalkulatorRumus`, dan keterangan jendela di tiap butir.

Yang **tidak** ikut bergeser: baris DKPS yang sudah telanjur diisi. Kolomnya
menyimpan label relatif (`TS-2`), bukan tahun mutlak, jadi baris lama akan
menunjuk tahun yang berbeda setelah TS diubah. **Ubah TS sebelum data diisi**,
atau periksa ulang seluruh baris DKPS sesudahnya.

### Menambah prodi lain

Tidak perlu migrasi. `prodi_id` sudah ada di setiap tabel transaksional sejak
migrasi pertama — `tagihan`, `bukti`, `narasi`, `dkps_baris`, `penilaian`,
`nilai_rumus`, `status_syarat_perlu`, dan `impor_batch` — meskipun sampai
sekarang hanya ada satu prodi.

1. **Pengaturan → Prodi → Buat.**
2. Buat periode untuk prodi itu, lalu pokja-pokjanya, lalu bangkitkan tagihan.
3. Tetapkan `prodi_id` pengguna yang bekerja di prodi itu.

Instrumen (59 elemen, 9 kriteria, 15 rumus, 28 butir DKPS) **dipakai bersama**
seluruh prodi — ia berasal dari dokumen LAMDIK, bukan milik satu prodi.

### Mencadangkan

Dua hal yang harus dicadangkan bersama-sama; salah satunya saja tidak berguna.

```bash
# 1. Basis data
docker exec sigap-php sh -c 'mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"' \
  > ~/cadangan/sigap-$(date +%F).sql

# 2. Berkas bukti — TIDAK ada di git dan tidak bisa dibangun ulang
tar czf ~/cadangan/bukti-$(date +%F).tar.gz -C storage/app bukti
```

Berkas `.sql` dan isi `storage/app/bukti/` **tidak pernah masuk riwayat git**:
isinya nama dosen, nomor serdik, dan dokumen bertanda tangan. Simpan cadangannya
di luar folder proyek.

Untuk memulihkan, kembalikan keduanya lalu jalankan `php artisan migrate` —
jangan `migrate:fresh`, yang akan menghapus isinya.

### Data demonstrasi

```bash
docker exec sigap-php php artisan migrate:fresh --seed --seeder=Database\\Seeders\\DemoSeeder
```

Menghasilkan keadaan pertengahan periode yang **sengaja tidak serba hijau**:
ada tagihan terlambat, bukti yang tidak bisa dibuka asesor, dan satu syarat
perlu yang belum terpenuhi. **Jangan dijalankan di basis data sungguhan** —
`migrate:fresh` menghapus seluruh isinya.

### Menu per peran

Menu yang muncul diatur `data/menu.json` (20 menu x 6 peran), dibaca
`Izin::bolehMenu()` dan dipakai `shouldRegisterNavigation()` di setiap Resource
dan Page. Menu **menyembunyikan, bukan menolak**: penolakan tetap milik Policy
dan `data/izin.json`, dan halaman yang hilang dari menu tetap menolak bila
dibuka lewat tautan langsung.

`MenuPeranTest` menelusuri seluruh 120 sel dua kali — sekali di tingkat
`Izin::bolehMenu()`, sekali pada HTML sidebar yang benar-benar dirender.

### Halaman galat

`resources/views/errors/` memuat 401, 403, 404, 419, 429, 500, dan 503.
Masing-masing menyebut apa yang terjadi, mengapa, dan apa yang bisa dilakukan
sekarang. Gayanya inline dengan sengaja — halaman galat harus tetap terbaca
ketika yang rusak justru panel atau berkas asetnya.

Halaman 500 menampilkan kode rujukan seperti `SIGAP-7KQ3M2XA`. Kode yang sama
masuk ke setiap baris log, jadi laporan galat bisa ditelusuri langsung:

```bash
docker exec sigap-php grep -n SIGAP-7KQ3M2XA storage/logs/laravel.log
```

Halaman 500 hanya muncul bila `APP_DEBUG=false`. Di lingkungan pengembangan
Laravel menampilkan jejak tumpukan, dan itu memang lebih berguna.

### Tur terpandu dan demo hidup

Selain manual, ada dua jalan lain mengenal SIGAP, keduanya bergerbang dari
halaman muka:

| | Alamat | Perlu apa | Menjawab |
|---|---|---|---|
| **Tur terpandu** | `/tur` | tidak perlu apa-apa | "saya harus mulai dari mana" |
| **Demo hidup** | `/demo` | kode akses dari admin | "bagaimana rasanya memakainya" |

Tur dibangkitkan dari `docs/manual/tur.json` — sumber yang sama dengan manual,
jadi keduanya berubah bersama. Ia tidak menyentuh basis data sama sekali.

Demo memberi **sesi sungguhan** di dalam aplikasi, jadi ia bergerbang kode.
Admin membukanya dari layar **Penilaian &rsaquo; Simulasi**, pada simulasi
berjenis *periode latihan*:

1. Buat periode latihan (menyalin kerangka periode sungguhan: pokja + 137 tagihan).
2. Tekan **Buka demo**, tentukan masa berlakunya (maksimal 90 hari).
3. Bagikan kodenya — berbentuk `DEMO-XXXXXX`, tanpa huruf yang mudah tertukar
   saat didiktekan lewat telepon.
4. **Tutup demo** mencabut kode dan menghapus seluruh akun demonya. Membuang
   periode latihannya melakukan keduanya sekaligus.

Yang menjaga demo tetap aman, dan tidak boleh dilonggarkan:

- Sesi demo hanya melihat periode demonya. Tidak ada satu baris pun dari periode
  sungguhan yang terjangkau — dijaga global scope `TerikatPeriode`.
- **Tidak ada akun demo berperan admin.** Wewenang admin menyentuh pengguna,
  prodi, dan periode, dan tidak satu pun dari itu terikat periode.
- Akun demo tidak muncul di daftar Pengguna, tidak bisa ditiru, dan tidak bisa
  ditugaskan tagihan sungguhan.
- `php artisan sigap:cek-kesiapan` menyebutkan demo yang sedang terbuka dan
  **menggagalkan** pemeriksaan bila ada demo kedaluwarsa yang akunnya belum
  dicabut.

### Manual pengguna

Enam manual bergambar, satu per peran, ada di [`docs/manual/`](docs/manual/) dan
tersaji di http://localhost:8021/manual. Seluruh gambarnya adalah tangkapan layar
sungguhan dari sistem ini, bukan mockup.

Disajikan lewat symlink `public/manual` &rarr; `docs/manual`, pola yang sama
dengan `public/storage`. Bila symlink-nya hilang setelah klon ulang:

```bash
ln -s ../docs/manual public/manual
```

Bukan lewat pengendali PHP, karena nginx menangani `.png` dan `.css` pada
`location ~* \.(js|css|png|...)$` yang tidak pernah meneruskan ke PHP.

Bila tampilan berubah, perbarui keduanya:

```bash
# sekali saja: pasang peramban untuk penangkap layar (tanpa root)
python3 -m pip install --user --break-system-packages playwright
python3 -m playwright install chromium

# siapkan data demo, lalu tangkap ulang dan susun ulang
docker exec sigap-php php artisan migrate:fresh --seed --seeder=Database\\Seeders\\DemoSeeder
docker exec sigap-php php artisan sigap:siapkan-manual
docker exec sigap-php php artisan cache:clear          # lepaskan pembatas laju masuk
python3 tools/tangkap-layar.py
python3 tools/susun-manual.py
```

Bila `chrome-headless-shell` mengeluh `libnspr4.so` hilang dan Anda tidak punya
`sudo`, unduh pustakanya sebagai pengguna biasa:

```bash
mkdir -p ~/.local/pw-deps/debs && cd ~/.local/pw-deps/debs
apt-get download libnspr4 libnss3 libasound2t64 libasound2-data fonts-liberation
for d in *.deb; do dpkg-deb -x "$d" ~/.local/pw-deps/root; done
export LD_LIBRARY_PATH=~/.local/pw-deps/root/usr/lib/x86_64-linux-gnu
```

`ManualPenggunaTest` menjaga agar setiap peran punya halaman, setiap gambar yang
dirujuk benar-benar ada, dan tidak ada gambar yatim yang tertinggal.

## Menggelar ke server

SIGAP berjalan di dua tempat dengan bentuk alamat yang berbeda:
`http://localhost:8021` di akar domain, dan
`https://supportfkip.unsil.ac.id/sigap` **di bawah subfolder**. Yang kedua itu
yang menuntut perhatian.

### Pemeriksa kesiapan

Jalankan setelah setiap penggelaran, bukan sekali saat pemasangan:

```bash
php artisan sigap:cek-kesiapan
php artisan sigap:cek-kesiapan --subfolder=/sigap   # bila APP_URL belum disetel
```

Ia memeriksa lingkungan, aset terbangun, migrasi tertunda, keutuhan data
instrumen, izin tulis berkas, dan — yang paling mudah terlupa — **kata sandi
contoh yang tertinggal**. Seeder memasang sandi `password` pada enam pengguna
contoh; di basis data demo itu pantas, di server sungguhan itu pintu terbuka,
dan tidak ada satu pun galat yang muncul karenanya.

Keluaran `GAGAL` membuat perintahnya keluar dengan kode 1, jadi ia bisa dipakai
sebagai gerbang di skrip penggelaran. Baris `PERIKSA` adalah hal yang hanya
manusia bisa pastikan — cron berjalan, pekerja antrean hidup, jalur keluar ke
internet terbuka.

### Yang wajib disetel untuk subfolder

Salin blok penggelaran di akhir `.env.example`. Tiga yang paling menentukan:

| Kunci | Nilai | Bila salah |
|---|---|---|
| `APP_URL` | `https://supportfkip.unsil.ac.id/sigap` | setiap tautan menunjuk ke luar aplikasi |
| `SESSION_PATH` | `/sigap` | cookie sesi bertabrakan dengan aplikasi tetangga |
| `SESSION_SECURE_COOKIE` | `true` | cookie sesi bisa terkirim lewat HTTP |

`APP_URL` adalah satu-satunya sumber yang dipakai `App\Support\Pemasangan`
untuk membangun tautan — bukan header permintaan, karena server balik yang
memangkas awalan tidak meninggalkan jejak yang bisa diandalkan.

Kegagalan yang paling mahal di sini tidak berisik: kalau `APP_URL` salah,
halaman mukanya **tetap tampil**. Pemasangannya kelihatan berhasil sampai ada
yang menekan tombol Masuk. `PemasanganTest` menguji bentuk tautannya di kedua
alamat supaya kekeliruan itu tertangkap sebelum digelar, bukan sesudah.

### Yang harus disiapkan di server

```bash
# Folder bukti — tidak ada di git, dan tidak bisa dibangun ulang
mkdir -p storage/app/bukti && chown -R www-data: storage bootstrap/cache

# Symlink manual, bila hilang setelah klon ulang
ln -s ../docs/manual public/manual

# Penjadwal: pemeriksaan tautan bukti harian pukul 02:00 WIB
* * * * * cd /path/ke/sigap && php artisan schedule:run >> /dev/null 2>&1

# Pekerja antrean, karena QUEUE_CONNECTION=database
php artisan queue:work
```

`PemeriksaTautan` membuka tautan Drive **tanpa kredensial apa pun**. Bila server
berada di balik proksi keluar, pastikan jalurnya terbuka — kalau tidak, seluruh
bukti akan ditandai tidak terbaca padahal sebenarnya baik-baik saja.

### Penggelaran otomatis

`.github/workflows/deploy.yml` mengikuti pola yang sama dengan repositori lain
di server ini: mendorong ke `main` memicu SSH ke server lalu menjalankan
`~/docker-apps/deploy.sh sigap`. Rahasia yang dibutuhkan:
`SERVER_HOST`, `SERVER_USER`, `SSH_PRIVATE_KEY`.

`.github/workflows/uji.yml` menjalankan seluruh uji dan `data/verifikasi.py` di
atas MariaDB 10.11 pada setiap dorongan dan setiap pull request.

## Status pembangunan

| Tahap | Isi | Status |
|---|---|---|
| 1 | Fondasi: login, peran, prodi, periode, pokja, matriks izin | **selesai** |
| 2 | Referensi instrumen: 59 elemen, 5 syarat perlu, 15 rumus, 28 butir DKPS | **selesai** |
| 3 | Tagihan, penugasan, alur status, riwayat | **selesai** |
| 4 | Bukti, tautan Drive, narasi LED | **selesai** |
| 5 | DKPS dan perhitungan rumus | **selesai** |
| 6 | Dasbor progres | **selesai** |
| 7 | Uji, seed contoh, serah terima | **selesai** |
| + | Impersonasi, konfirmasi keluar, tema terang, footer, simulasi, manual | **selesai** |
| + | Menu per peran dan halaman galat yang menjelaskan | **selesai** |
| + | Halaman muka publik dengan tautan ke manual | **selesai** |
| + | Kesiapan penggelaran: subfolder, pemeriksa, GitHub Actions | **selesai** |
| + | Pemisahan periode, tur terpandu, dan demo hidup bergerbang kode | **selesai** |

## Sebelum menyerahkan ke orang lain

Baca [`docs/KEPUTUSAN.md`](docs/KEPUTUSAN.md). Isinya keputusan desain,
asumsi yang masih menunggu konfirmasi LAMDIK, bagian yang sengaja belum
dibangun, dan hal-hal yang paling mungkin menyusahkan di masa depan.

## Rujukan

- Peraturan LAMDIK Nomor 5 Tahun 2025 tentang IAPSK 3.0
- Buku 2 — Pedoman Umum Akreditasi
- Buku 3 — Panduan Penyusunan LED dan Pengisian DKPS Program PPG
- Buku 4 — Panduan dan Matriks Penilaian Program PPG
