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

# 7. Aset frontend
docker exec -w /var/www/html/sigap laravel-node22 npm run build
```

Buka http://localhost:8021 — akan langsung dialihkan ke `/panel`.

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

Aturan yang mengikat ada di [`CLAUDE.md`](CLAUDE.md) — baca lebih dulu. Ringkasnya:

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

Dokumen rancangan lengkap dan prompt bertahap ada di `vibecoding/` — tidak
terlacak git, tetapi tetap ada di cakram dan tetap wajib dibaca.

## Status pembangunan

| Tahap | Isi | Status |
|---|---|---|
| 1 | Fondasi: login, peran, prodi, periode, pokja, matriks izin | **selesai** |
| 2 | Referensi instrumen: 59 elemen, 5 syarat perlu, 15 rumus, 28 butir DKPS | **selesai** |
| 3 | Tagihan, penugasan, alur status, riwayat | **selesai** |
| 4 | Bukti, tautan Drive, narasi LED | **selesai** |
| 5 | DKPS dan perhitungan rumus | **selesai** |
| 6 | Dasbor progres | **selesai** |
| 7 | Uji, seed contoh, serah terima | belum |

## Rujukan

- Peraturan LAMDIK Nomor 5 Tahun 2025 tentang IAPSK 3.0
- Buku 2 — Pedoman Umum Akreditasi
- Buku 3 — Panduan Penyusunan LED dan Pengisian DKPS Program PPG
- Buku 4 — Panduan dan Matriks Penilaian Program PPG
