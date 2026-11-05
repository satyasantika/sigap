# Keputusan & Pengecualian — SIGAP

Keputusan desain, asumsi yang masih menunggu konfirmasi LAMDIK, dan hal-hal
yang paling mungkin menyusahkan di masa depan. Tidak terbaca dari kode dan
tidak akan ditebak sendiri oleh orang yang melanjutkan.

---

## 1. Keputusan yang diambil sendiri

Dokumen rancangan awal tidak menyebut hal-hal berikut, atau menyebutnya
secara bertentangan. Keputusannya sudah diambil dan tidak perlu diadili ulang.

### Yang diputuskan bersama dosen

| Hal | Keputusan | Mengapa |
|---|---|---|
| Basis data | **MariaDB 10.11**, bukan MySQL 8 | Itu yang terpasang; `README` proyek memang sudah menyebut MariaDB sejak awal |
| `prodi_id` | Ada di **semua** tabel transaksional | Aturan baku proyek menang atas rancangan awal yang hanya memberikannya pada `bukti` |
| `Izin::boleh` tanpa obyek | Mengembalikan **true** | Menolak akan menghilangkan menu dari koordinator dan anggota; konsekuensinya tiap kueri daftar wajib menyaring sendiri |
| `ImporDtps`, `ImporKerjaSama` | Menyimpan ke `dkps_baris.data` | `docs/02` menyatakan tabel dosen sengaja belum dibuat, dan rumus DTPS memang membaca dari DKPS |
| Normalisasi TKM | Dibagi 4 | **Terkonfirmasi** oleh kertas kerja resmi `AK Kosong PPG.xlsx`, sel E207 — lihat di bawah tabel |
| Sumber rumus kuantitatif AK 3.0 | **Buku 4 patokan utama**, kertas kerja `AK Kosong PPG.xlsx` untuk operasionalisasi | Buku 4 dokumen resmi yang dipublikasikan; kertas kerja dipakai bila Buku 4 hanya menulis kalimat, tidak menyebut tabel DKPS, atau rumusnya tidak konsisten secara matematis (5 November 2026) |
| RN pada E50 | **Ikut kertas kerja**: ND1 + ND2 (buku ber-ISBN, paten) disertakan | Keputusan dosen 5 November 2026; tetap ditanyakan ke LAMDIK, lihat bagian 2 |
| `RT_PEMBIMBINGAN_PPL` | **Rerata per dosen per tahun**: Σ pertemuan ÷ jumlah sel pertemuan yang terisi pada Tabel 20 | Keputusan dosen 5 November 2026 |
| NDTPS | **NIDN/NIDK unik gabungan Tabel 6 dan 7** | Dosen yang muncul di kedua tabel dihitung sekali (5 November 2026) |

**Normalisasi TKM, dari asumsi menjadi keputusan.** `vibecoding/docs/03-perhitungan.md`
menetapkan `TKMi = (4×ai) + (3×bi) + (2×ci) + di` dengan a..d dalam persen.
Hasilnya berskala **100–400**, sementara ambangnya dinyatakan sebagai
**persentase**. Sistem membaginya dengan 4 sebelum dibandingkan, sehingga 400
menjadi 100% dan 300 menjadi 75%.

Tafsir itu sempat menjadi satu-satunya asumsi yang benar-benar menggantung.
Kertas kerja asesor AK IAPSK 3.0 (`AK Kosong PPG.xlsx`, lembar *2. Kertas
Kerja*) menuliskannya sendiri di sel **E207**:
`(4·E203/100 + 3·E204/100 + 2·E205/100 + E206/100) / 4`, dengan ambang skor
**0,25 / 0,5 / 0,75** (sel D229–D231). Jadi `/4` benar. Peringatan di tiga
tempat sudah diganti menjadi rujukan ke sel itu: komentar
`KalkulatorRumus::tkm()`, kolom `catatan` tiap baris `nilai_rumus`
("Normalisasi /4 sesuai kertas kerja AK IAPSK 3.0 (sel E207)"), dan catatan
informatif di layar Nilai Rumus.

Ambang skornya pun mengikuti kertas kerja dan `data/rumus.json`: TKM ≥ 75 % → 4,
≥ 50 % → 3, ≥ 25 % → 2, selain itu 1. Sebelumnya `tkm()` memakai 90 / 75 / 60 %,
tafsir lama yang tidak bersumber; kini diselaraskan, dan `KalkulatorRumusTest`
mengunci setiap batasnya.

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

9. **`skor_tepat` selalu dipotong ke bawah dua desimal, tidak dibulatkan.**
   Pembulatan bisa mendorong Skor A melewati ambang yang belum dicapai:
   PGBLKL 69,84 % memberi `2 + 20 × 0,6984 / 7 = 3,995…`; dibulatkan menjadi
   4,00, dipotong menjadi **3,99**. Yang benar 3,99, karena ambang skor 4
   adalah 70 %. Lihat `KalkulatorRumus::potong2()`. Pemotongan ini aturan
   SIGAP sendiri, juga ketika diterapkan pada sub-skor (misalnya tiga sub-skor
   RGP); kertas kerja hanya membulatkan skor akhir, jadi selisihnya paling
   besar 0,01.

10. **Ambang selalu dibandingkan pada rasio mentah; pembulatan hanya untuk
    tampilan.** 1504 / 7521 = 19,997 % tampil sebagai "20,0" di layar, tetapi
    skornya tetap **3**, bukan skor milik ambang 20 %. Kolom `nilai` disimpan
    sudah dibulatkan; jangan dipakai untuk menghitung ulang skor.

11. **Variabel kosong membuat rumus dilewati, bukan dihitung dengan nol.**
    `PenghitungRumusDkps` mencatat rumus itu sebagai "dilewati" beserta
    alasannya (lembar atau kolom mana yang belum terisi). Menghitung dengan nol akan
    memberi skor terendah yang tampak sah padahal datanya belum diisi. Hasil
    yang identik dengan baris `nilai_rumus` terakhir tidak disimpan dua kali,
    sehingga `rumus:hitung` dan tombol "Hitung dari DKPS" boleh dijalankan
    berulang.

12. **Rata yang tak terdefinisi dibaca null, bukan 0.** Bila lembarnya terisi
    tetapi semua sel yang dirata kosong, `PembacaVariabelDkps` mengembalikan
    null, sehingga rumusnya dilewati menurut butir 11. Rata 0 akan berarti
    "masa studi nol tahun" atau "IPK nol", dua hal yang tidak pernah benar.
    Begitu pula kolom hitung yang semua kolom masukannya kosong (jumlah sks
    Tabel 8 tanpa isian bukan 0 sks). Pada baris tetap yang sudah ada, sel
    kosong bertipe `angka` (cacah: lulusan, terlacak, waktu tunggu) dibaca 0
    seperti Excel, sedangkan sel kosong bertipe `desimal` (ukuran: IPK, persen
    kepuasan) membuat variabelnya null dengan catatan yang menyebut tabel,
    baris, dan kolomnya. Baris tetap yang tidak ada sama sekali dibaca 0.

---

## 2. Asumsi yang perlu dikonfirmasi ke LAMDIK

Normalisasi TKM tidak lagi di sini: kertas kerja AK IAPSK 3.0 sudah
menjawabnya (lihat bagian 1). Kertas kerja yang sama justru memunculkan dua
pertanyaan baru, keduanya cacat pada berkas resmi, bukan pada SIGAP. Pekerjaan
"Rumus kuantitatif AK 3.0" menambah sembilan tafsir lagi, dikumpulkan di
subbagian tersendiri di bawah.

### Rujukan `F264` pada rumus PGBLKL (elemen E17)

Untuk PGBLKL di bawah 70 %, rumus Excel kertas kerja merujuk sel `F264` —
sel yang **kosong**. Bila diikuti mentah-mentah, skornya bergantung pada sel
yang tidak diisi siapa pun. SIGAP memakai `2 + (20 × PGBLKL) / 7`, tafsir yang
masuk akal dan sambung dengan skor 4 di ambang 70 %. Tanyakan ke LAMDIK sel
mana yang dimaksud. Elemen yang terdampak: E17, salah satu syarat perlu.

### Rumus RMS memberi skor 1 untuk masa studi satu tahun (elemen E39)

Kertas kerja menilai rata-rata masa studi dengan
`3,5 < RMS ≤ 4 → 4; 4 < RMS ≤ 5 → max(1; min(3,8; 5,6·RMS − 15,8)); selain itu 1`.
Rentang itu milik program sarjana empat tahun. Untuk PPG yang masa studinya
**satu tahun**, setiap lulusan yang tepat waktu jatuh ke "selain itu" dan
memberi **skor 1**. Ini cacat instrumen; jangan ditiru apa adanya sebelum LAMDIK
menjawab. `data/rumus.json` tetap menyebutnya "berskala".
SIGAP menghitung nilainya (rata masa studi, `rms()`) tetapi tidak memberi skor
sampai LAMDIK menjawab; baris `nilai_rumus`-nya berskor kosong dengan catatan
yang merujuk ke bagian ini.

### Rumus kuantitatif AK 3.0: tafsir yang menunggu LAMDIK

Sejak rencana "Rumus kuantitatif AK 3.0" (spesifikasi
`docs/superpowers/specs/2026-11-05-rumus-kuantitatif-ak-3-design.md`),
`data/rumus.json` memuat 28 rumus dan `KalkulatorRumus` menghitung semuanya
dari DKPS. Buku 4 patokan utamanya, tetapi di beberapa tempat Buku 4 dan kertas
kerja berbeda, atau Buku 4 sendiri tidak konsisten. Di setiap tempat itu kami
memilih satu tafsir dan menuliskannya di bawah. Bila LAMDIK menjawab lain, yang
berubah hanya metode terkait di `KalkulatorRumus` dan ujinya.

1. **Koefisien pita 2–3 pola RI/RN/RW** (E7 A.2, E12, E49, E50, E54). Buku 4
   menulis `2 + (2 × RI/a) + RN/b − (RI × RN)/(a × b)`, kertas kerja
   `2 + 1 × RI/a + …`. SIGAP memakai **1**: dengan koefisien 2, pita "2–3"
   bisa melampaui 3 (RI = 0,9a dan RN = 0,9b memberi 3,89), sedangkan dengan
   koefisien 1 nilainya selalu di antara 2 dan 3.

2. **Pita bawah 1–2 diinterpolasi.** Buku 4 menulis "RI = 0, RN = 0, RW < c →
   Skor = 1"; kertas kerja menulis `max(1, 2 × RW/c)`. SIGAP mengikuti kertas
   kerja karena Buku 4 sendiri menamai pita itu "1–2", yang menyiratkan
   rentang, bukan nilai tunggal.

3. **Ujung BKD.** Rumus Buku 4 memberi 0 pada BKD = 18 dan tidak sambung di
   antara 6 dan 7. Buku 4 hanya mengenal skor 1–4, jadi SIGAP memberi lantai 1
   (`max(1, …)`) di kedua ujung.

4. **PMKI (E32) bertangga 4/3/2.** Rumus Buku 4 untuk pita 3,
   `3 + (x − 0,25)/0,10`, memberi 2–3 untuk 0,15 ≤ x < 0,25, jatuh di bawah 3
   meski pitanya bernama "3". SIGAP mengikuti kertas kerja sel E496: ≥ 0,25 →
   4, 0,15 ≤ x < 0,25 → 3, selain itu 2.

5. **RN pada E50 menyertakan ND1 + ND2** (buku ber-ISBN, paten), seperti
   kertas kerja. Buku 4 hanya menjumlahkan `NA2 + NA3 + NB2 + NC2`. Ini
   keputusan dosen 5 November 2026 (bagian 1), tetapi tetap perlu ditanyakan
   karena Buku 4 tidak menyebut keduanya.

6. **RGP (E22) pada kasus yang tak punya pita.** Saat RGPBS = 2 (tepat dua
   guru pamong per bidang studi) dan RGPMD
   ≥ 30 % tetapi RGPMU ≥ 40 % atau RGPGP ≥ 50 %, rumus kertas kerja sel E348
   jatuh ke skor 2, padahal rasio yang sama dengan nilai lebih buruk memberi 3.
   Buku 4 tidak punya pita untuk kasus ini. SIGAP memberi **3**, dengan tafsir
   monoton: rasio yang lebih baik tidak boleh menurunkan skor.

7. **Lantai 1 untuk WTMP dan TKi setelah faktor responden.** Kertas kerja sel
   E667 menulis `PJ/Prmin × Skor` apa adanya, yang bisa memberi 0 (misalnya
   bila responden nol karena tidak ada lulusan yang terlacak).
   Buku 4 hanya mengenal skor 1–4, jadi SIGAP memberi lantai 1 setelah faktor
   responden diterapkan.

8. **RMS** dihitung tanpa skor; lihat subbagian RMS di atas.

9. **PKMS (E41) memakai dua kohort terakhir (TS-1 dan TS)** mengikuti kertas
   kerja E612–E616; Buku 4 tidak menyebut jendela kohort; konsisten dengan
   RMS. Baris TS-2 Tabel 22 tidak ikut dalam `N_DITERIMA_ANGKATAN` maupun
   `N_LULUSAN_ANGKATAN`.

### Hal lain yang sebaiknya ditanyakan

- **Kertas kerja sel E739 (skor akhir E44) merujuk E727** (sel "% Baik"),
  bukan E732. SIGAP mengikuti E732.

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

### Yang memang di luar lingkup

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

Satu lagi ditambahkan kemudian, dan alasannya sama kerasnya:

| Uji | Berkas |
|---|---|
| Halaman muka tidak menjalankan satu kueri pun | `BerandaTest` |

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

### 4.8 Panel tanpa tema Vite sendiri tampil polos, dan uji tidak menangkapnya

Sampai fitur impersonasi dibangun, panel tidak punya
`resources/css/filament/panel/theme.css`. Akibatnya CSS yang dimuat panel adalah
CSS bawaan Filament, yang hanya memuat kelas utilitas milik komponen Filament
sendiri. Setiap kelas Tailwind yang ditulis di Blade kita — grid bento dasbor,
angka besar KPI, bilah progres pokja — tidak punya aturan CSS sama sekali.
Dasbor K1–K9 tampil sebagai tumpukan teks polos selama enam tahap, dan
514 uji tetap hijau seluruhnya.

Kenapa tidak ketahuan: `php artisan test` memeriksa HTML, bukan tampilan. Uji
yang mencari teks "Gerbang Unggul" tetap lulus walau teks itu tampil tanpa
warna, tanpa ukuran, dan tanpa tata letak.

Baru ketahuan saat layarnya ditangkap untuk manual pengguna. Itu alasan
tambahan untuk memperbarui `docs/manual/gambar/` setiap kali tampilan berubah:
tangkapan layar adalah satu-satunya pemeriksaan tampilan yang ada di proyek ini.

Yang mengikat sekarang: `ManualPenggunaTest::tema_panel_terpasang` menjaga agar
`->viteTheme()` tidak hilang lagi, dan menambah folder Blade baru berarti
menambah `@source` di berkas tema lalu `npm run build`.

### 4.9 Impersonasi: yang menahannya adalah jejak, bukan pagar

Admin yang sedang menyamar memegang wewenang PENUH peran yang ditirunya,
termasuk menyetujui tagihan — dan itu keputusan manusia, bukan kelalaian.
Yang menjaga pertanggungjawaban adalah `impersonasi_oleh` yang ikut tersimpan di
`tagihan_riwayat`, `narasi_versi`, `komentar`, dan `log_aktivitas`.

Kolom itu diisi trait `MencatatImpersonasi` lewat peristiwa `creating`, bukan
oleh pemanggil. Sengaja begitu: pemanggilnya lima tempat sekarang dan akan
bertambah, dan satu tempat yang lupa mengisinya berarti satu persetujuan yang
tampak ditekan ketua padahal ditekan admin. Tabel riwayat baru yang menyimpan
"siapa melakukan" WAJIB memakai trait itu.

Satu jebakan yang sudah ditangani dan mudah dipatahkan kembali:
`Impersonasi::segarkanHashSandi()`. Middleware `AuthenticateSession`
membandingkan hash sandi pengguna yang sedang masuk dengan salinan di sesi, dan
mengeluarkan siapa pun yang tidak cocok. Berganti pengguna di tengah permintaan
meninggalkan salinan milik pengguna lama, jadi permintaan BERIKUTNYA menendang
keluar orang yang baru saja mulai menyamar. Menghapus pemanggilan itu membuat
impersonasi "kadang jalan, kadang langsung terlempar ke halaman masuk" —
gejala yang sangat mahal untuk dilacak.

### 4.10 Menu yang terbuka lebar karena `viewAny` dipetakan ke `dasbor.lihat`

Enam Policy — Periode, Pokja, Prodi, Penilaian, StatusSyaratPerlu, NilaiRumus —
memetakan `viewAny` ke `dasbor.lihat`, yang bernilai `ya` untuk keenam peran.
Filament memakai `viewAny` untuk memutuskan apakah sebuah Resource muncul di
menu, jadi akibatnya **anggota pokja melihat menu Pengguna, Periode, Pokja, dan
Prodi**. Tidak ada yang gagal karenanya: Policy tetap menolak begitu tombolnya
ditekan. Yang rusak adalah kepercayaan — menu penuh halaman yang menolak
membuat orang berhenti percaya sistem tahu siapa mereka.

Diperbaiki dengan memisahkan dua hal yang sebelumnya menumpang pada satu nilai:

- **Boleh berbuat** tetap `data/izin.json` lewat Policy. Tidak berubah sama
  sekali, dan 144 sel `MatriksIzinTest` tetap hijau.
- **Muncul di menu** kini `data/menu.json` lewat `Izin::bolehMenu()`, dipakai
  `shouldRegisterNavigation()`.

Jebakan yang tersisa: **jangan tergoda menyatukan keduanya.** Menyamakan
`canAccess()` dengan `bolehMenu()` akan membuat menu menjadi pagar otorisasi,
dan aturan 7 melarangnya justru karena pagar yang berupa tombol tersembunyi
akan bocor pada tautan langsung, pada aksi massal, dan pada endpoint Livewire.

### 4.11 Kode rujukan galat hanya berguna bila layar dan log sepakat

Halaman 500 menampilkan `App\Exceptions\KodeRujukan::kode()`, dan
`bootstrap/app.php` menyuntikkan nilai yang SAMA ke setiap baris log lewat
`$exceptions->context()`. Nilainya diingat per permintaan justru supaya keduanya
sepakat.

Bila suatu saat kode itu dibangkitkan ulang di salah satu sisi — misalnya dengan
memanggil `Str::random()` langsung di Blade — fiturnya tidak akan gagal, ia akan
menjadi jebakan: pelapor menyebut satu kode, `grep` tidak menemukannya, dan
waktu habis untuk mencari galat yang sebenarnya tercatat rapi.

Satu hal lagi: halaman 500 hanya tampil bila `APP_DEBUG=false`. Menguji
tampilannya di lingkungan pengembangan berarti merender view-nya langsung,
seperti yang dilakukan `HalamanGalatTest`.

### 4.12 Halaman muka publik: satu kueri saja sudah cukup merusaknya

`/` kini halaman muka terbuka, bukan pengalihan ke `/panel`. Batas yang menjaganya
bukan pagar teknis melainkan satu aturan: halaman itu tidak menyentuh basis data.

Godaannya nyata dan akan datang. "Sekalian tampilkan berapa persen progresnya"
terdengar wajar, tidak akan terasa salah saat ditulis, dan tidak akan membuat satu
pun uji lama menjadi merah. Yang terjadi sesudahnya: angka Nilai Akreditasi prodi
terbaca siapa pun yang tahu alamatnya, berbulan-bulan sebelum ada yang sadar.

`BerandaTest::halaman_muka_tidak_menyentuh_basis_data` memeriksa log kueri kosong
setelah permintaan. Uji itu masuk daftar yang tidak boleh disesuaikan agar cocok
dengan kode — bila ia merah, yang salah kodenya.

Angka di halaman muka dibaca dari `data/*.json` lewat cache larik, jadi ia tetap
tampil ketika basis data mati. Itu bukan kebetulan: halaman muka yang masih hidup
saat sistemnya tidak adalah halaman yang masih bisa memberi tahu orang apa yang
sedang terjadi.

### 4.13 Zona waktu yang diam-diam UTC selama tujuh tahap

`config/app.php` bawaan Laravel menuliskan `'timezone' => 'UTC'` apa adanya dan
TIDAK membaca `APP_TIMEZONE`. Sementara itu `.env` dan `.env.example` sama-sama
menuliskan `Asia/Jakarta`. Tidak ada galat, tidak ada peringatan, dan 880 uji
tetap hijau — karena seluruh uji memakai zona waktu yang sama secara konsisten.

Akibatnya di dunia nyata: selisih tujuh jam. Antara pukul 00.00 dan 07.00 WIB
aplikasi masih menganggap hari kemarin, jadi tagihan yang jatuh tempo hari ini
belum terhitung terlambat dan tagihan kemarin masih tampak belum lewat. Ubin
"Tagihan terlambat" di dasbor salah selama tujuh jam setiap hari. Penjadwal
`dailyAt('02:00')` sebenarnya berjalan pukul 09.00 WIB — di tengah jam kerja,
bukan dini hari seperti yang dimaksud.

Ditemukan `sigap:cek-kesiapan`, bukan oleh uji. Itu bukan kebetulan: perbedaan
antara "yang tertulis di .env" dan "yang benar-benar dipakai aplikasi" hanya
kelihatan bila ada yang membandingkan keduanya dengan sengaja.

Satu catatan bila basis data sungguhan sudah berisi data sebelum perbaikan ini:
baris lama ditulis dengan jam UTC, baris baru dengan jam WIB, dan keduanya
duduk di kolom yang sama. Untuk SIGAP ini tidak menjadi masalah karena
perbaikannya mendahului penggelaran pertama — tetapi bila suatu saat zona waktu
diubah lagi, data lama harus digeser, bukan dibiarkan.

### 4.14 Subfolder: kegagalan yang tidak berisik

Di `https://supportfkip.unsil.ac.id/sigap`, tautan yang ditulis absolut
(`href="/panel"`) menunjuk ke `https://supportfkip.unsil.ac.id/panel` — ke luar
aplikasi. Yang membuatnya mahal: halaman mukanya **tetap tampil dengan benar**.
Penggelarannya kelihatan berhasil sampai ada yang menekan tombol Masuk.

Dua pelanggaran memang ada dan sudah diperbaiki: satu di `dasbor.blade.php`
(tautan ke Bukti bermasalah) dan satu di kepala manual. Keduanya ditulis jauh
sebelum ada rencana menggelar ke subfolder, dan keduanya benar sampai saat itu.

Yang menjaga sekarang:
`PemasanganTest::tidak_ada_jalur_absolut_yang_ditulis_mati_di_tampilan`
menyisir `resources/views/` dan `app/` mencari `href="/`, `src="/`, dan
`action="/`. Bila uji itu merah, yang salah adalah jalurnya — bukan ujinya.

Awalan diturunkan dari `APP_URL`, bukan dari header permintaan. Itu pilihan
sadar: server balik yang memangkas `/sigap` sebelum meneruskan ke PHP tidak
meninggalkan jejak yang bisa diandalkan, dan menebaknya dari `X-Forwarded-*`
berarti menaruh kepercayaan pada header yang bisa dipalsukan.

### 4.15 Aturan 8 sempat dipenuhi sebagian, tanpa satu pun uji

Sampai pemeriksaan ini, sembilan tabel memang sudah memakai hapus lunak dan
tiga tabel riwayat memang sudah menolak dihapus — semuanya bekerja. Yang tidak
ada: satu pun uji yang membuktikannya. `assertSoftDeleted` hanya muncul sekali
di seluruh berkas uji, dan itu pun untuk `simulasi`.

Akibatnya empat tabel lolos tanpa pengaman apa pun — `nilai_rumus`,
`status_syarat_perlu`, `impor_batch`, `impersonasi_sesi` — ditambah `komentar`.
Tidak ada yang menghapusnya hari ini, jadi tidak ada gejala. Bahayanya justru
itu: satu `DeleteAction` yang ditambahkan enam bulan lagi akan menghapusnya
permanen, dan tidak ada yang akan menyadarinya sampai ada yang mencari baris
yang sudah tidak ada.

Yang menjaga sekarang: `PenghapusanDataTest` memuat daftar lengkap semua model
beserta nasibnya, dan **menolak model baru yang belum didaftarkan**. Menambah
model tanpa memutuskan nasibnya membuat ujinya merah.

Dua jebakan yang sudah dihindari dan mudah dilupakan:

- **Kendala unik bertabrakan dengan hapus lunak.** `status_syarat_perlu` punya
  `unique(periode_id, elemen_id)`. Bila ia dibuat hapus-lunak, satu baris
  terhapus akan menghalangi `updateOrCreate` di layar Syarat Perlu dengan galat
  SQL mentah. Karena itu ia menolak dihapus, bukan dihapus lunak. Jebakan yang
  sama mengintai `prodi.kode`, `periode(prodi_id, nama)`, `pokja(periode_id,
  kode)`, dan `users.email` — semuanya sudah hapus-lunak, jadi nama yang sama
  tidak bisa dipakai ulang selama barisnya masih tersimpan. Validasi Laravel
  menahannya dengan pesan "sudah dipakai" atas baris yang tidak kelihatan di
  layar; bila ada yang melapor bingung, itu sebabnya.
- **Penghapusan berantai basis data melewati peristiwa model.** `MenolakDihapus`
  dipasang lewat `deleting`, jadi ia tidak menahan `cascadeOnDelete`.
  Satu-satunya yang memicunya adalah `forceDelete()` atas periode sandbox di
  `Simulator`, dan membuang periode latihan beserta isinya memang gunanya. Bila
  suatu saat ada `forceDelete()` lain, ia akan menembus seluruh pengaman ini
  tanpa suara.

### 4.16 Periode latihan sempat bocor ke layar kerja sungguhan

Fitur periode sandbox menandai dirinya dengan `periode.simulasi`, dan
`Periode::scopeAktif()` mengecualikannya — tetapi scope itu menjawab "periode
mana yang sedang berjalan", bukan "baris mana yang muncul di daftar". Tidak ada
satu pun Resource yang menyaring menurut periode.

Akibatnya terukur: satu periode latihan membuat ketua melihat **274 tagihan
alih-alih 137**, bercampur tanpa penanda apa pun. Fitur yang dimaksudkan sebagai
tempat berlatih justru merusak layar yang dipakai bekerja, dan tidak ada galat
apa pun yang menandainya.

Penyebabnya bukan kecerobohan satu tempat. `TagihanResource::getEloquentQuery()`
menyaring menurut peran dengan sangat rapi — penyaringan periode memang belum
terpikir saat itu ditulis, karena periode simulasi belum ada. Delapan Resource
yang harus mengingat adalah delapan tempat yang bisa terlewat, jadi
penjagaannya sekarang berupa global scope `TerikatPeriode`, bukan catatan.

Dua hal yang wajib diingat tentang scope itu:

- **Layanan yang menerima periode sebagai parameter harus memakai
  `LingkupPeriode::paksa()`.** Tanpa itu `Simulator::buatSandbox()` gagal dengan
  "periode belum punya pokja": pokja yang baru saja ia buat tidak terlihat
  olehnya sendiri, karena admin terikat periode sungguhan. Ini sudah terjadi
  sekali saat scope-nya dipasang.
- **Akun demo tidak pernah jatuh kembali ke periode sungguhan.** Akun demo yang
  periodenya sudah dibuang adalah akun yatim; ia melihat kosong, bukan
  diam-diam berubah menjadi pengguna biasa. Versi pertama kode ini salah di
  titik itu, dan ujinya yang menangkap.

### 4.17 Demo hidup adalah fitur paling berisiko di SIGAP

Ia memberi **sesi sungguhan di dalam aplikasi** kepada orang di luar organisasi.
Setiap pagar di bawah ini menahan sesuatu yang konkret; melonggarkan salah
satunya tidak akan menimbulkan gejala sampai ada yang mencarinya.

| Pagar | Yang ditahannya |
|---|---|
| Kode akses + masa berlaku | sesi anonim untuk siapa pun di internet |
| `TerikatPeriode` | sesi demo membaca data akreditasi sungguhan |
| Aksi `demo.coba` (admin `tidak`) | demo admin menyunting pengguna sungguhan |
| `User::scopeBisaDitugaskan` | akun demo ditugaskan tagihan sungguhan lalu lenyap |
| `Impersonasi` menolak akun demo | jejak penyamaran yang menggantung |
| `UserResource` menyembunyikannya | admin mengira ada enam pengguna yang tak ia buat |

Yang paling mudah terlupa justru bukan salah satu di atas, melainkan **demo
yang dibuat sekali lalu dilupakan**. Ia tidak menimbulkan gejala apa pun, dan
kodenya tetap berlaku sampai ada yang menutupnya. Karena itu masa berlaku wajib
diisi saat membuka, dan `sigap:cek-kesiapan` menyebutkan demo yang terbuka
sekaligus menggagalkan pemeriksaan bila ada yang kedaluwarsa tetapi akunnya
belum dicabut.

Satu batas yang disengaja dan bisa terasa seperti kekurangan: **peran
administrator sistem tidak bisa didemokan.** Wewenangnya menyentuh pengguna,
prodi, dan periode — tidak satu pun terikat periode, jadi tidak ada cara
mengurungnya di dalam periode latihan. Peran itu dipelajari lewat tur terpandu.
Bila suatu saat ada yang ingin "sekalian demo admin juga", jawabannya bukan
melonggarkan `demo.coba` melainkan mengikat data admin ke periode lebih dulu —
dan itu pekerjaan yang jauh lebih besar daripada kelihatannya.


### 4.18 Penomoran DKPS 3.0 bergeser dari Buku 3 — pencocokan butir lewat `kode`, bukan `no`

`data/dkps-tabel.json` lama mengikuti Buku 3: 28 butir, melompati Tabel 13.
DKPS 3.0 (`DKPS PPG.xlsx` v3.0) punya 29 tabel tanpa lompatan, menyisipkan
Tabel 4 baru (Karya Inovatif Mahasiswa), dan memecah Tabel 1 serta Tabel 4
menjadi beberapa lembar. Akibatnya Tabel 5–13 bergeser satu nomor: yang dulu
"Tabel 4 Kepuasan" kini Tabel 5, "Tabel 6 DBS" kini Tabel 7, dan seterusnya.

Yang menggigit bukan pergeseran itu sendiri, melainkan kode yang mencocokkan
butir lewat **nomor urut**. Setiap rujukan seperti `where('no', 6)` diam-diam
menunjuk tabel yang salah setelah referensinya diganti, tanpa satu pun galat.
Karena itu pencocokan butir selalu lewat `kode` (`DkpsButir::dariKode('7')`),
dan `no` hanya dipakai untuk mengurutkan tampilan. Pengimpor (`ImporDtps`,
`ImporKerjaSama`, `ImporPublikasiDtps`) dan `DkpsBaris::KODE_DARI_SIAKAD`
sudah dipetakan ulang ke kode DKPS 3.0.

Baris `dkps_baris` dari sebelum DKPS 3.0 tidak dihapus. `php artisan
dkps:selaraskan` mengisi `lembar_kode` dan `pokja_id`-nya, dan
`sigap:cek-kesiapan` gagal selama masih ada baris tanpa pokja. Bila suatu saat
LAMDIK menerbitkan DKPS 3.1 dengan nomor bergeser lagi, yang perlu diubah
adalah `kode` di berkas json beserta pemetaan pengimpor — bukan kuerinya.
---

## 5. Pertentangan dalam dokumen yang ditemukan, dan cara menanganinya

Ringkasnya:

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

Satu kalimat yang layak diulang di sini:

> Sistem ini membuat pekerjaan pengumpulan menjadi terlihat dan terukur. Ia
> tidak menaikkan skor dengan sendirinya.

Angka di dasbor hanya sebaik data yang dimasukkan. Frekuensi praktik
microteaching, sebaran publikasi dosen, dan siklus penjaminan mutu tetap harus
benar-benar berjalan di dunia nyata.

Yang bisa dilakukan sistem ini — dan sudah dilakukannya — adalah memastikan
tidak ada klaim yang mati di tangan asesor karena tautannya tidak bisa dibuka,
dan tidak ada yang mengira sudah aman karena melihat persentase tanpa melihat
gerbangnya.
