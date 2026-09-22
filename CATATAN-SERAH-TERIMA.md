# Catatan Serah Terima — SIGAP

Ditulis oleh agen pembangun pada akhir tahap 7, untuk orang yang melanjutkan.

Sistemnya jalan dan ujinya hijau. Yang tidak terbaca dari kode dan tidak akan
ditebak sendiri oleh orang berikutnya ada di bawah ini.

---

## 1. Keputusan yang diambil sendiri

Dokumen rancangan di `vibecoding/docs/` tidak menyebut hal-hal berikut, atau
menyebutnya secara bertentangan. Keputusannya diambil dan dicatat di
[`CLAUDE.md`](CLAUDE.md) bagian 7 supaya tiap tahap tidak mengadilinya ulang.

### Yang diputuskan bersama dosen

| Hal | Keputusan | Mengapa |
|---|---|---|
| Basis data | **MariaDB 10.11**, bukan MySQL 8 | Itu yang terpasang; `README` proyek memang sudah menyebut MariaDB sejak awal |
| `prodi_id` | Ada di **semua** tabel transaksional | `AGENTS.md` aturan 4 menang atas `docs/02` yang hanya memberikannya pada `bukti` |
| `Izin::boleh` tanpa obyek | Mengembalikan **true** | Menolak akan menghilangkan menu dari koordinator dan anggota; konsekuensinya tiap kueri daftar wajib menyaring sendiri |
| `ImporDtps`, `ImporKerjaSama` | Menyimpan ke `dkps_baris.data` | `docs/02` menyatakan tabel dosen sengaja belum dibuat, dan rumus DTPS memang membaca dari DKPS |
| Normalisasi TKM | Dibagi 4 | Lihat bagian 2 — **masih menunggu konfirmasi LAMDIK** |

### Yang diputuskan sendiri, tanpa bertanya

Semuanya rutin, tetapi semuanya mengubah perilaku sistem.

1. **`whereJsonContains()` dilarang.** Di MariaDB kolom `JSON` hanyalah alias
   `LONGTEXT`; Laravel melempar kesalahan untuk operasi itu. Yang berbahaya:
   di SQLite ia **jalan**, jadi uji di atas SQLite akan meloloskan kueri yang
   rusak di produksi. Itu sebabnya uji dijalankan di MariaDB.

2. **Uji berjalan di MariaDB (`db_sigap_test`), bukan SQLite.** Lebih lambat
   (~2,5 menit untuk 500 uji), tetapi menangkap hal-hal yang SQLite lewatkan —
   dan memang sudah menangkap satu: `getOriginal()` mengubah tanggal menjadi
   Carbon yang tersimpan di json sebagai ISO-8601 dan ditolak MariaDB.

3. **Kolom `sumber` ditolak kosong di tingkat model.** MariaDB memakai nilai
   pertama ENUM secara diam-diam bila kolom NOT NULL tanpa default diabaikan —
   bahkan pada `sql_mode` ketat. Nilai pertamanya `siakad`, sumber yang paling
   dipercaya asesor. Tanpa pagar ini, bukti yang lupa diberi sumber tercatat
   berasal dari SIAKAD. Lihat `app/Models/Concerns/MenolakSumberKosong.php`.

4. **Cap waktu `bukti` dan `dkps_baris` berpresisi milidetik.** Pembatalan
   impor ditutup begitu ada baris hasil impor yang disunting orang lain;
   dengan presisi detik, suntingan yang terjadi pada detik yang sama dengan
   impornya tidak terlihat sama sekali.

5. **Nomor versi narasi diturunkan dari cacah baris `narasi_versi`**, bukan
   dinaikkan sendiri. Baris narasi lahir lebih dulu lewat `firstOrCreate` saat
   layar dibuka, jadi penghitung terpisah menghasilkan "versi 4" padahal baru
   tiga kali disimpan.

6. **Bukti berjenis `berkas` tidak punya sumbu keterbacaan.** Ia ada di disk
   sendiri, jadi asesor pasti bisa membukanya lewat ekspor. Memeriksanya hanya
   akan menghasilkan status palsu.

7. **`ppdtps()` menolak cacah dosen yang melebihi NDTPS.** Pagar terhadap
   kesalahan yang paling sering: menyerahkan cacah **artikel** ke parameter
   yang meminta cacah **dosen**.

8. **Pemetaan ubin dasbor per peran diletakkan di enum `PeranPengguna`**,
   bukan di `Izin`. Ia tampilan, bukan wewenang — keenam peran boleh membuka
   dasbor. Penyaringan daftar K9 justru diturunkan dari lingkup izin.

---

## 2. Asumsi yang perlu dikonfirmasi ke LAMDIK

### Normalisasi TKM — satu-satunya yang benar-benar menggantung

`vibecoding/docs/03-perhitungan.md` menetapkan:

```
TKMi = (4×ai) + (3×bi) + (2×ci) + di        dengan a..d dalam persen
```

Hasilnya berskala **100–400**, sementara ambangnya dinyatakan sebagai
**persentase** (TKM ≥ 75%). Keduanya tidak sebanding.

**Tafsir yang dipakai:** bagi 4 sebelum dibandingkan, sehingga 400 menjadi 100%
dan 300 menjadi 75%. Konsisten — "seluruh responden menjawab Sangat Baik" wajar
bernilai 100%.

**Tafsir ini belum dikonfirmasi.** Penandaannya ada di tiga tempat supaya tidak
terlewat: komentar di `KalkulatorRumus::tkm()`, kolom `catatan` pada tiap baris
`nilai_rumus`, dan kotak peringatan di layar Nilai Rumus.

Bila LAMDIK menjawab berbeda, yang berubah hanya **satu metode dan satu
peringatan** — bukan seluruh perhitungan. Elemen yang terdampak: E14
(Tingkat Kepuasan Mahasiswa), bobot 1,50.

### Dua hal lain yang sebaiknya ditanyakan

- **Patokan PDS3 di dokumen 03 tidak lengkap.** Ia menyebut "NDS3=6, NDTPS=12 →
  syarat 5 tahun terpenuhi" tanpa memberikan NDLK dan NDGB, padahal syaratnya
  menuntut **PDS3 ≥ 50 DAN NDLK + NDGB ≥ 4**. Uji di `KalkulatorRumusTest`
  menyertakan angkanya (NDLK=3, NDGB=1); pastikan itu memang tafsir yang benar.

- **Bentuk kanonik tautan Google Docs memakai `/view`.** Itu jalur pratinjau
  milik Google sendiri dan berfungsi, tetapi berbeda dari `/preview` atau
  `/edit` yang lebih sering dilihat orang. Bila asesor melaporkan tautan tidak
  bisa dibuka, periksa `NormalisasiTautan::pola()` lebih dulu.

---

## 3. Yang sengaja belum dibangun

### Dua pemberitahuan yang hilang dari rancangan

`vibecoding/docs/04-peran-dan-alur.md` menyebut **empat** pemberitahuan; prompt
tahap 3 mempersempitnya menjadi **dua**, dan tahap-tahap setelahnya tidak
memungutnya kembali. Yang tidak dibangun:

- *tagihan di pokja saya diajukan* → untuk koordinator;
- *tenggat tiga hari lagi* → untuk penanggung jawab, lewat perintah terjadwal.

Keduanya bukan kelalaian melainkan penyempitan lingkup yang tidak pernah
dibatalkan. Bila dibangun, tempatnya di `app/Notifications/` mengikuti pola dua
yang sudah ada, dan yang kedua butuh satu entri di `routes/console.php`.

### Yang memang di luar lingkup, sesuai `AGENTS.md`

- **SIAKAD tandingan.** Data mahasiswa dan dosen masuk lewat impor
  tempel-tabel; tabel `dosen` dan `mahasiswa` sengaja tidak dibuat.
- **Penghasil narasi LED otomatis.** Sistem menyediakan angka, bukti, dan
  kerangka; kalimatnya ditulis manusia.
- **API publik.** Aplikasi internal, akses lewat panel.
- **SSO.** Hanya mekanisme masuknya yang akan berubah; `users`, kolom `peran`,
  dan seluruh Policy tetap.
- **Penghasil PDF.** Ekspor naskah menghasilkan Markdown; ubah ke PDF di luar
  aplikasi bila perlu.

### Yang tertunda karena pertentangan dokumen

- **`bukti_dkps_baris`** baru dibuat di tahap 5 karena `dkps_baris` belum ada
  di tahap 4. Sudah terpasang; disebut di sini hanya agar jejaknya jelas.

---

## 4. Yang paling mungkin menyusahkan enam bulan lagi

Bagian ini bukan formalitas. Berikut urutannya menurut seberapa besar
kemungkinan ia menggigit, bukan menurut seberapa rumit.

### 4.1 Filament menyuntik argumen closure berdasarkan NAMA parameter

**Ini yang paling mungkin menyusahkan, dan sudah menggigit dua kali.**

```php
// RUSAK — Filament tidak pernah mengirim kueri tabelnya
->query(fn (Builder $q) => $q->terlambat())

// BENAR
->query(fn (Builder $query) => $query->terlambat())
```

Nama parameter harus persis `$query`, `$state`, atau `$record`. Salah nama
tidak menghasilkan galat saat menulis — halamannya pecah dengan **500** saat
closure itu dijalankan.

Yang membuatnya berbahaya: saringan yang tidak `->default()` **hanya berjalan
saat dinyalakan pengguna**. Enam belas saringan di tujuh berkas pernah rusak
sekaligus dan tidak ketahuan sampai satu saringan bawaan kebetulan
menjalankannya.

**Pagarnya sudah dipasang:** `tests/Feature/SaringanTabelTest.php` menyalakan
setiap saringan satu per satu. **Jangan hapus berkas itu**, dan tambahkan baris
baru ke data providernya setiap kali menambah saringan.

### 4.2 Nilai bawaan ENUM MariaDB yang diam-diam

Kolom ENUM NOT NULL tanpa default akan **diisi nilai pertama enum** bila
diabaikan — bahkan pada `sql_mode` ketat, tanpa galat apa pun.

Saat ini hanya kolom `sumber` yang dipagari (`MenolakSumberKosong`). Kolom ENUM
lain yang belum dipagari dan nilai pertamanya berbahaya:

| Tabel | Kolom | Nilai pertama | Akibat bila lolos |
|---|---|---|---|
| `bukti` | `jenis` | `berkas` | Tautan tercatat sebagai berkas terunggah |
| `tagihan` | `jenis` | `narasi` | Tagihan bukti tercatat sebagai naskah |
| `tagihan` | `prioritas` | `biasa` | Aman — memang bawaan yang dikehendaki |
| `dkps_baris` | `tahun_acuan` | `TS` | Data TS-2 tercatat sebagai TS |

Tiga yang pertama saat ini selalu diisi eksplisit oleh pembangkit dan pengelola,
jadi belum pernah menggigit. Bila menambah jalur penulisan baru, isi kolom itu
terang-terangan.

### 4.3 Label tahun DKPS relatif, tetapi datanya tidak ikut bergeser

`dkps_baris.tahun_acuan` menyimpan `TS`, `TS-1`, dan seterusnya — label
relatif, bukan tahun mutlak. Itu benar dan disengaja: `periode.ts_tahun` adalah
satu-satunya sumber tahun.

Tetapi **mengubah TS setelah data diisi akan menggeser arti seluruh baris yang
sudah ada** tanpa satu pun peringatan. Baris yang tadinya berarti 2025 mendadak
berarti 2028.

Belum ada pagar untuk ini. Bila TS pernah diubah di tengah periode, seluruh
baris DKPS perlu diperiksa ulang. Pagar yang masuk akal: tolak perubahan
`ts_tahun` bila periode itu sudah punya baris DKPS, atau catat tahun mutlaknya
sekalian saat baris dibuat.

### 4.4 `Izin::boleh` mengembalikan true tanpa obyek

Konsekuensi dari keputusan di bagian 1: **setiap Resource dan kueri daftar
wajib menyaring sendiri.** `Izin` tidak lagi menjadi satu-satunya pagar untuk
halaman daftar.

Sudah dilakukan di `TagihanResource::getEloquentQuery()`. Resource baru yang
menampilkan data berlingkup **harus melakukan hal yang sama**, dan tidak ada
yang mengingatkan bila lupa — Policy tetap menjaga tiap barisnya, tetapi judul
di daftar sudah telanjur bocor.

### 4.5 Riwayat yang tidak bisa dihapus adalah fitur, bukan kelalaian

`tagihan_riwayat` dan `narasi_versi` menolak `update` dan `delete` lewat event
model. Suatu saat seseorang akan mengeluh karena tidak bisa membersihkan baris
yang salah, dan menghapus event itu adalah "perbaikan" satu baris.

**Jangan.** Keduanya jejak audit: siapa menyetujui apa dan kapan. Untuk
membatalkan sebuah perpindahan, catat perpindahan baru.

### 4.6 Uji yang tidak boleh disesuaikan agar cocok dengan kode

Empat ini dikunci oleh `vibecoding/docs/06-kriteria-terima.md`. Bila salah
satunya merah, yang salah adalah kodenya.

| Uji | Berkas |
|---|---|
| `SUM(elemen.bobot)` = 100,00 | `ReferensiInstrumenTest` |
| `SUM(tagihan.bobot_terkait)` = 100,000 | `PembangkitTagihanTest` |
| NA "rubrik+refleksi 4, data 3" = 363,25 | `KalkulatorNaTest` |
| PDS3 41,67 → skor 4, syarat 5 tahun **tidak** terpenuhi | `KalkulatorRumusTest` |

### 4.7 Hal kecil yang memakan waktu bila tidak tahu

- **`php artisan tinker` gagal menulis konfigurasi** di dalam container.
  Tambahkan `-e XDG_CONFIG_HOME=/tmp` pada `docker exec`.
- **`Http::fake()` yang dipanggil dua kali MENGGABUNG stub**, tidak
  menggantikannya. Untuk urutan respons berbeda, pakai `Http::sequence()`.
- **`AuthenticateSession` membatalkan sesi** begitu pengguna berganti dalam
  satu uji. Uji yang menyentuh beberapa peran harus dipecah lewat data
  provider, bukan gelung.
- **`migrate:fresh --seed --class=`** tidak sah; yang benar `--seeder=`.
  Prompt tahap 7 menuliskannya keliru.

---

## 5. Pertentangan dalam dokumen yang ditemukan, dan cara menanganinya

Tercatat lengkap di [`CLAUDE.md`](CLAUDE.md) bagian 7. Ringkasnya:

| Pertentangan | Yang menang |
|---|---|
| `docs/02` menulis `bigint PK` pada 8 tabel vs aturan UUID | UUID |
| Matriks peran `docs/04` memberi admin wewenang isi akreditasi | `data/izin.json` |
| `docs/02` menamai model `NarasiLed` vs tanda tangan `Narasi` | `Narasi` |
| Nama kolom `docs/02` vs kunci JSON (`aturan_skor`/`skor` dan lain-lain) | Seeder memetakan eksplisit |
| Prompt tahap 7: NA 330–350 "Unggul 3 tahun" **dan** satu syarat perlu `belum` | Sebaran syarat perlunya |

Yang terakhir perlu penjelasan. Keduanya tidak bisa bersamaan: status "Unggul
3 tahun" menuntut **kelima** syarat perlu sekurangnya berlevel `tiga`, jadi
dengan satu `belum`, NA 335,75 tetap berujung "Terakreditasi 5 tahun".

`DemoSeeder` memilih sebaran syarat perlunya justru karena hasilnya lebih
mengajar: angka NA yang bagus tidak menjamin apa pun selama satu syarat perlu
masih menganga. Itu persis kegagalan yang seluruh sistem ini dibangun untuk
mencegah, dan demonstrasi yang menyembunyikannya memberi rasa aman palsu.
Seeder-nya mengatakan itu terang-terangan di keluarannya.

---

## 6. Satu catatan jujur

`vibecoding/README.md` menutup dengan kalimat yang layak diulang di sini:

> Sistem ini membuat pekerjaan pengumpulan menjadi terlihat dan terukur. Ia
> tidak menaikkan skor dengan sendirinya.

Angka di dasbor hanya sebaik data yang dimasukkan. Frekuensi praktik
microteaching, sebaran publikasi dosen, dan siklus penjaminan mutu tetap harus
benar-benar berjalan di dunia nyata.

Yang bisa dilakukan sistem ini — dan sudah dilakukannya — adalah memastikan
tidak ada klaim yang mati di tangan asesor karena tautannya tidak bisa dibuka,
dan tidak ada yang mengira sudah aman karena melihat persentase tanpa melihat
gerbangnya.
