# Rumus Kuantitatif AK 3.0 dan Jembatan DKPS → Rumus — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Semua rumus kuantitatif instrumen (28 kode) dapat dihitung dari isian Tabel DKPS dengan satu tombol atau satu perintah, hasilnya tersimpan beserta variabel sumbernya, dan Skor A terlihat oleh asesor internal saat mengisi Penilaian.

**Architecture:** Katalog variabel bernama (`data/variabel-dkps.json`, cermin lembar "5. DKPS" kertas kerja) dibaca oleh `PembacaVariabelDkps` dari `dkps_baris` lewat `SkemaLembar`. `KalkulatorRumus` tetap satu metode per rumus (tujuh yang ada diperbaiki, dua puluh ditambah), tanpa pengurai ekspresi. `PenghitungRumusDkps` memetakan kode → metode secara eksplisit, melewati rumus yang variabelnya kosong, dan menyimpan append-only tanpa duplikat. Perintah `rumus:hitung`, aksi "Hitung dari DKPS", halaman Variabel DKPS, dan placeholder Skor A di Penilaian adalah permukaannya.

**Tech Stack:** Laravel 13 / PHP 8.5, Filament 5, Livewire 3, MariaDB 10.11, PHPUnit (uji di `db_sigap_test`), Python 3 untuk `data/verifikasi.py`.

**Spec:** `docs/superpowers/specs/2026-11-05-rumus-kuantitatif-ak-3-design.md` (§2 urutan kewenangan sumber, §5.3 tabel rumus, Lampiran A katalog, §9 jawaban dosen).

## Global Constraints

- Seluruh perintah `php`, `artisan`, `composer` dijalankan di container: `docker exec sigap-php …`.
- Setelah tiap tugas: `docker exec sigap-php vendor/bin/pint --dirty` lalu uji yang disebut. Sebelum tugas terakhir selesai: `docker exec sigap-php php artisan test` seluruhnya hijau dan `python3 -P data/verifikasi.py` keluar 0.
- Sumber kebenaran angka: Buku 4 untuk ambang; kertas kerja untuk bentuk rasio dan untuk rumus interpolasi yang Buku 4 tulis tidak konsisten (spec §2.1). Koefisien pita 2–3 = **1** (bukan 2); pita bawah = `max(1, 2·RW/c)`; E7 A.1 = `max(1, RK)`; BKD dengan lantai 1; PMKI bertangga 4/3/2 (kertas kerja E496, karena rumus Buku 4 untuk pita 3 jatuh di bawah 3).
- Jawaban dosen (spec §9): RN pada RPUB **menyertakan** ND1 + ND2; RT_PEMBIMBINGAN_PPL = Σ pertemuan ÷ jumlah sel pertemuan terisi; NDTPS = NIDN/NIDK unik gabungan Tabel 6 dan 7.
- Tidak ada angka cadangan saat data kosong: variabel `null` → rumus dilewati dengan alasan. Pembagi nol → `InvalidArgumentException` dari `KalkulatorRumus` (pola `pastikanPembagiSah`).
- `nilai_rumus` tetap append-only; `skor` (bulat) tetap diisi `floor(skorTepat)` kecuali RMS (keduanya `null`).
- Pencocokan butir DKPS lewat `DkpsButir::dariKode()`; tidak ada `where('no', …)`.
- Tidak ada angka tahun mati di `app/`; label tahun hanya lewat `JendelaTs`.
- Tidak ada perbandingan peran di luar `App\Support\Izin`; tidak ada `whereJsonContains`.
- Sel `data/izin.json` menjadi 26 × 6 = **156**; menu menjadi 22 × 6 = **132**; rumus menjadi **28**. Semua angka kunci di `IzinSeeder`, `Izin.php`, `MatriksIzinTest`, `CekKesiapanTest`, `RumusSeeder`, `ReferensiInstrumenTest`, `data/verifikasi.py` ikut digeser pada tugas yang mengubahnya.
- Nama parameter closure Filament `$query`, `$state`, `$record`; saringan tabel baru didaftarkan di `SaringanTabelTest::saringan()`.
- Bahasa Indonesia untuk label, pesan, komentar; desimal koma di layar, titik di kode.
- Pesan commit berbahasa Indonesia (`feat(rumus): …`, `data: …`, `fix(rumus): …`), diakhiri `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- Berkas instrumen untuk pemeriksaan tangan (hanya dibaca, tidak disalin ke repo): `/mnt/c/Users/Lenovo/Documents/projects/akreditasi PPG 2027/04-Materi-Riyadi-IAPSK-PPG/AK Kosong PPG.xlsx` (lembar "2. Kertas Kerja" dan "5. DKPS"; buka dengan `python3 -P` + openpyxl) dan `/mnt/c/Users/Lenovo/Documents/projects/akreditasi PPG 2027/01-Instrumen-IAPSK-3.0/Buku-4-TU-PPG-3.0.pdf`.

## Review Focus

1. **Lembar terisi tetapi semua centang kosong** (Tabel 3 tanpa satu pun tingkat dicentang) — variabel bernilai `0.0`, bukan `null`; PRESMA dihitung dan memberi skor 1, tidak dilewati. (Tugas 6: `lembar_terisi_tanpa_centang_memberi_nol_bukan_null`.)
2. **Baris tetap sebagian terisi** (Tabel 23 hanya baris TS-2) — sel baris yang tidak ada dibaca 0 bila lembar punya baris lain; `null` hanya bila lembar kosong sama sekali. (Tugas 6: `sel_baris_tetap_yang_hilang_dibaca_nol_bila_lembar_terisi`.)
3. **Klik "Hitung dari DKPS" dua kali berturut-turut tanpa perubahan data** — tidak ada baris `nilai_rumus` baru; laporan menyebut "tidak berubah". (Tugas 7: `hasil_identik_tidak_disimpan_dua_kali`.)
4. **Periode terkunci** — `rumus:hitung` dan tombol menolak dengan pesan, tanpa menulis apa pun. (Tugas 7: `periode_terkunci_ditolak`.)
5. **NIDN sama di Tabel 6 dan Tabel 7 dengan ejaan nama berbeda** — NDTPS menghitungnya satu orang; NIDN kosong pada salah satu baris tidak menggabungkan dua orang berbeda. (Tugas 6: `ndtps_menghitung_nidn_unik_gabungan_tabel_6_dan_7`, `nidn_kosong_tidak_digabung`.)

---

## Peta berkas

| Berkas | Tugas | Tanggung jawab |
|---|---|---|
| `data/rumus.json` | 1 | 28 kode; kunci baru `variabel_dkps` |
| `data/variabel-dkps.json` | 1 | katalog variabel (Lampiran A spec, dengan dua penyesuaian di Tugas 1) |
| `data/verifikasi.py` | 1, 9 | pemeriksaan jumlah dan rujukan silang |
| `database/migrations/2026_11_06_000001_skor_tepat_dan_variabel_dkps.php` | 1 | kolom `skor_tepat`; tabel `variabel_dkps` |
| `app/Enums/CaraBacaVariabel.php` | 1 | enum cara baca |
| `app/Models/VariabelDkps.php` | 1 | model katalog |
| `database/seeders/VariabelDkpsSeeder.php`, `RumusSeeder.php`, `DatabaseSeeder.php` | 1 | semai katalog; 28 rumus |
| `app/Support/Rumus/HasilRumus.php` | 2 | `skorTepat` |
| `app/Services/KalkulatorRumus.php` | 2, 3, 4, 5 | metode rumus |
| `app/Support/Rumus/NilaiVariabel.php`, `app/Services/PembacaVariabelDkps.php` | 6 | baca variabel |
| `app/Support/Rumus/LaporanPerhitungan.php`, `app/Services/PenghitungRumusDkps.php` | 7 | jembatan |
| `app/Console/Commands/HitungRumus.php` | 8 | `rumus:hitung` |
| `data/izin.json`, `data/menu.json`, `app/Filament/Pages/VariabelDkpsLayar.php`, `app/Filament/Resources/NilaiRumuses/…`, `app/Filament/Resources/Penilaians/Schemas/PenilaianForm.php` | 9 | permukaan |
| `docs/KEPUTUSAN.md`, `docs/manual/*.html` | 10 | dokumentasi |

---

### Task 1: Data referensi — 28 rumus, katalog variabel, migrasi, seeder

**Files:**
- Modify: `data/rumus.json`
- Create: `data/variabel-dkps.json`
- Modify: `data/verifikasi.py:53-54`
- Create: `database/migrations/2026_11_06_000001_skor_tepat_dan_variabel_dkps.php`
- Create: `app/Enums/CaraBacaVariabel.php`, `app/Models/VariabelDkps.php`, `database/seeders/VariabelDkpsSeeder.php`
- Modify: `database/seeders/RumusSeeder.php`, `database/seeders/DatabaseSeeder.php`, `app/Models/Rumus.php`
- Test: `tests/Feature/ReferensiInstrumenTest.php:172-184`, `tests/Feature/VariabelDkpsSeederTest.php`

**Interfaces:**
- Produces: `App\Enums\CaraBacaVariabel` (string enum): `CacahBaris='cacah_baris'`, `CacahCentang='cacah_centang'`, `CacahPilihan='cacah_pilihan'`, `CacahTerisi='cacah_terisi'`, `CacahUnik='cacah_unik'`, `JumlahKolom='jumlah_kolom'`, `RataKolom='rata_kolom'`, `NilaiSel='nilai_sel'`, `JumlahSel='jumlah_sel'`, `Khusus='khusus'`.
- Produces: model `VariabelDkps` (tabel `variabel_dkps`, uuid): `kode string(40) unik`, `label string`, `lembar json` (array kode lembar), `cara string(20)` cast `CaraBacaVariabel`, `kolom string null`, `parameter json null`, `rumus json` (array kode rumus), `urutan int`. Metode `public static function dariKode(string $kode): static` (firstOrFail).
- Produces: `Rumus::$fillable` + `variabel_dkps` (json, cast array). `nilai_rumus.skor_tepat decimal(5,2) null`.
- Produces: bentuk entri `variabel-dkps.json`:
  ```json
  {"kode": "N_GP_MAGISTER_DOKTOR", "label": "Guru pamong berkualifikasi magister/doktor", "lembar": ["13"],
   "cara": "cacah_pilihan", "kolom": "pendidikan_tertinggi",
   "parameter": {"nilai": ["Magister/Magister Terapan", "Doktor/Doktor Terapan"]}, "rumus": ["RGP"]}
  ```
  `parameter` menurut cara: `cacah_pilihan` → `nilai: [..]`; `cacah_terisi`/`cacah_unik`/`cacah_centang` boleh `filter: {kolom, nilai: [..]}` atau `filter: {kolom, terisi: true}` atau `filter: {kolom, centang: true}`; `nilai_sel` → `baris`, `kolom`; `jumlah_sel` → `sel: [[baris, kolom], …]`; `khusus` → tanpa parameter (dihitung di kode, Tugas 6).

- [ ] **Step 1: Tulis uji referensi yang gagal**

Di `ReferensiInstrumenTest`, ubah `rumus_berjumlah_15_dan_memuat_rumus_inti` menjadi `rumus_berjumlah_28_dan_memuat_rumus_inti`: `assertSame(28, Rumus::count())`, daftar inti ditambah `'PRESMA', 'RPUB', 'TKi'`. Tambah uji `setiap_variabel_dkps_rumus_ada_di_katalog`: untuk tiap `Rumus` dengan `variabel_dkps` tidak kosong, setiap kodenya ada di `VariabelDkps`; dan `NA` punya `variabel_dkps` kosong.

Buat `tests/Feature/VariabelDkpsSeederTest.php` (`RefreshDatabase`, seed `DatabaseSeeder`):
- `katalog_tersemai_dan_setiap_lembar_dikenal`: `VariabelDkps::count() >= 90`; untuk tiap variabel, tiap kode di `lembar` ada di `DkpsButir::dariKode(<kode tabel>)->kodeLembar()` (kode tabel = bagian sebelum titik, misal `"24.1"` → butir `"24"`); untuk cara selain `khusus`, `kolom` (bila ada) ada di `skema($lembar)->kolom()` sebagai kunci, dan untuk `nilai_sel`/`jumlah_sel` setiap `baris` ada di `barisTetap()`.
- `cara_pilihan_merujuk_pilihan_yang_ada`: untuk `cacah_pilihan`, setiap `parameter.nilai` ∈ `skema->pilihan($kolom)`.
- `setiap_rumus_di_katalog_ada_di_rumus_json`: setiap kode di `rumus` ada di `Rumus`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter='ReferensiInstrumenTest|VariabelDkpsSeederTest'`
Expected: FAIL (`Rumus::count()` = 15; kelas `VariabelDkps` tidak ada).

- [ ] **Step 3: Tulis `data/rumus.json` menjadi 28 entri**

Pertahankan 15 entri lama; ubah tiga; tambah tiga belas. Setiap entri mendapat `variabel_dkps` (daftar kode katalog) — `NA` mendapat `[]`. Isi `ekspresi` dan `skor` mengikuti tabel spec §5.3 (teks bebas, untuk ditampilkan). Kode baru dan elemen: `PRESMA` 12, `PKIM` 13, `BKD` 18, `RGP` 22, `DANA` 25, `PDIPPKM` 32, `PMKI` 32, `RPPL` 36, `PMTK` 40, `RMKCPL` 45, `RPEN` 49, `RPUB` 50, `RPKM` 54. Perubahan: `RK` (ekspresi dua bagian, `variabel` ditambah NI/NN/NW), `WTMP` dan `TKi` (catatan aturan responden), `RMS` (`catatan`: "Skor menunggu jawaban LAMDIK; nilai tetap dihitung"), `RIPK` (`skor` sesuai Buku 4 dengan lantai 1), `PMKI` (`skor`: "≥ 0,25 → 4; 0,15–0,25 → 3; selain itu 2 (kertas kerja E496)").

- [ ] **Step 4: Tulis `data/variabel-dkps.json`**

Ikuti Lampiran A spec dengan dua penyesuaian yang sudah diputuskan di rencana ini:
1. `WTMP` **bukan** variabel khusus. Gantinya tiga variabel `jumlah_sel` atas Tabel 23: `N_LULUSAN_WT_6BLN` (sel `wt_kurang_6` baris TS-4, TS-3, TS-2), `N_LULUSAN_WT_612BLN` (`wt_6_12`), `N_LULUSAN_WT_12BLN` (`wt_lebih_12`); `N_LULUSAN_3TH` = Σ `jumlah_lulusan` tiga baris; `N_LULUSAN_TERLACAK_3TH` = Σ `terlacak`. Titik tengah dihitung di `wtmp()` (Tugas 5).
2. `N_TKP_TERLACAK_3TH` = Σ `jumlah_tanggapan` tiga baris Tabel 24.2; `N_LULUSAN_TKP_3TH` = Σ `jumlah_lulusan` Tabel 24.2 (bukan Tabel 23, karena pengisian keduanya terpisah).

Variabel `khusus` yang wajib ada (dihitung Tugas 6): `N_DTPS`, `N_DTPS_DOKTOR`, `N_DTPS_GB`, `N_DTPS_LK`, `N_DTPS_LEKTOR`, `RT_PEMBIMBINGAN_PPL`, `RT_MS_LULUSAN`, `N_DTPS_PUBLIKASI`. Variabel untuk Tabel 5 (20 sel `nilai_sel`: `<aspek>_SB/_B/_C/_K` → kolom `sangat_baik`, `baik`, `cukup`, `kurang_baik`), Tabel 24.1 (36 sel, kolom `sangat_baik`, `baik`, `cukup`, `kurang`), Tabel 21 (`N_LULUSAN_TS2/TS1/TS` dan `RT_IPK_TS2/TS1/TS` dari `jumlah_lulusan`, `ipk_rata`), Tabel 22 (`N_MHS_DITERIMA_TS1/TS`, `N_LULUS_PADA_TS1` = sel [TS-1, lulus_akhir_ts1], `N_LULUS_PADA_TS` = [TS, lulus_akhir_ts], `N_DITERIMA_ANGKATAN` = Σ diterima tiga baris, `N_LULUSAN_ANGKATAN` = Σ `jumlah_lulusan` tiga baris — `jumlah_lulusan` adalah kolom hitung, lihat Tugas 6), Tabel 9 (`N_REKOGNISI_DTPS` = `cacah_baris`), Tabel 10 (`N_DTPS_PK` = `cacah_unik` `nama_dtps`), Tabel 11 (`N_TENDIK` = `jumlah_sel` atas kolom hitung `jumlah` empat baris), Tabel 12 (`N_TENDIK_PK` = `cacah_unik` `nama_tendik`), Tabel 28 (`NAS` = `jumlah_kolom` `jumlah_sitasi`), Tabel 27 (`N_DTPS_PUBLIKASI` khusus: `nama_dtps` unik; `N_ARTIKEL_PUBLIKASI` = `cacah_baris`).

- [ ] **Step 5: Perbarui `data/verifikasi.py`**

Ganti `chk("15 rumus", …)` → 28; tambah: `vd = L("variabel-dkps.json")`; setiap `rumus[].variabel_dkps[*]` ada di `vd[].kode`; setiap `vd[].rumus[*]` ada di `rm[].kode`; setiap `vd[].lembar[*]` ada di gabungan `dk[].lembar[].kode`; kode `vd` unik; `cara` ∈ sepuluh nilai enum.

Run: `python3 -P data/verifikasi.py` → keluar 0.

- [ ] **Step 6: Migrasi, enum, model, seeder**

Migrasi: `Schema::table('nilai_rumus', fn ($t) => $t->decimal('skor_tepat', 5, 2)->nullable()->after('skor'))`; `Schema::create('variabel_dkps', …)` sesuai Interfaces; `rumus` mendapat `variabel_dkps json null`. `VariabelDkpsSeeder` memakai trait `MembacaBerkasData` (`bacaJson('variabel-dkps.json', <jumlah>)`), `updateOrCreate` per `kode`, `urutan` = indeks. `RumusSeeder`: `bacaJson('rumus.json', 28)`, simpan `variabel_dkps`, pesan info "Rumus: 28 baris". `DatabaseSeeder` memanggil `VariabelDkpsSeeder` setelah `RumusSeeder`.

- [ ] **Step 7: Migrasi + uji**

Run: `docker exec sigap-php php artisan migrate && docker exec sigap-php php artisan test --filter='ReferensiInstrumenTest|VariabelDkpsSeederTest|CekKesiapanTest'`
Expected: PASS. (`CekKesiapanTest` menyebut "150 sel izin" — belum berubah di tugas ini.)

- [ ] **Step 8: Commit**

```bash
git add data/ database/ app/Enums/CaraBacaVariabel.php app/Models/VariabelDkps.php app/Models/Rumus.php tests/Feature/ReferensiInstrumenTest.php tests/Feature/VariabelDkpsSeederTest.php
git commit -m "data(rumus): 28 rumus AK 3.0, katalog variabel DKPS, kolom skor_tepat"
```

---

### Task 2: `skorTepat`, pita tiga tingkat, RK dua bagian, RIPK dibetulkan

**Files:**
- Modify: `app/Support/Rumus/HasilRumus.php`
- Modify: `app/Services/KalkulatorRumus.php` (`rk`, `ripk`, `pds3`, `pgblkl`, `rsa`, `tkm`, `ppdtps`, kepala komentar)
- Modify: `database/seeders/DemoSeeder.php:438`
- Test: `tests/Feature/KalkulatorRumusTest.php`, `tests/Feature/RumusTigaTingkatTest.php`

**Interfaces:**
- Produces: `HasilRumus::__construct(..., ?float $skorTepat = null)` sebagai parameter terakhir; `untukDisimpan()` menambah `'skor_tepat' => $this->skorTepat`.
- Produces: `KalkulatorRumus::rk(int $n1, int $n2, int $n3, int $ni, int $nn, int $nw, int $ndtps): HasilRumus` — `nilai` = RK dibulatkan 2 desimal; `komponen` memuat `a1`, `a2`, `n1..nw`, `ndtps`; `skorTepat` = round((2·A.1 + A.2)/3, 2) dihitung dari `nilai` yang sudah dibulatkan; `skor` = floor.
- Produces: `KalkulatorRumus::ripk(float $rataIpk): HasilRumus` — ≥ 3,25 → 4; 2,00 ≤ x < 3,25 → max(1, (8x − 6)/5); < 2,00 → 1; `skorTepat` diisi.
- Produces (privat, dipakai Tugas 3–5): `skorTigaTingkat(float $ri, float $rn, float $rw, float $a, float $b, float $c): float` dengan lima pita spec §2.1 dan urutan pemeriksaan kertas kerja (E834–E839): (1) RI ≥ a → 4; (2) RI < a dan RN ≥ b → 3 + RI/a; (3) (0 < RI < a) atau (0 < RN < b) → 2 + RI/a + RN/b − (RI·RN)/(a·b); (4) RI = 0, RN = 0, RW ≥ c → 2; (5) selain itu max(1, 2·RW/c). Tidak dibulatkan di dalamnya.
- Produces: setiap metode lama mengisi `skorTepat` (`pds3`/`pgblkl` = `skor_a_tepat`/`skor_b_tepat` yang sudah ada; `rsa`, `tkm`, `ppdtps` = `(float) skor`).

- [ ] **Step 1: Tulis uji yang gagal**

`tests/Feature/RumusTigaTingkatTest.php` memanggil `skorTigaTingkat` lewat refleksi (`ReflectionMethod::setAccessible` tidak perlu di PHP 8.5; cukup `invoke`) — atau lewat `presma()` setelah Tugas 3; pilih refleksi supaya uji ini berdiri sendiri. Kasus (a=0,005; b=0,05; c=0,1), dengan `assertEqualsWithDelta(…, 0.0001)`:
- `(0.01, 0, 0)` → 4
- `(0.001, 0.06, 0)` → 3,2
- `(13/7521, 6/7521, 15/7521)` → 2,3561 (contoh kertas kerja E147–E162)
- `(0, 0.025, 0.05)` → 2,5
- `(0, 0, 0.1)` → 2
- `(0, 0, 0.05)` → 1
- `(0, 0, 0.08)` → 1,6
- `koefisien_pita_dua_tiga_adalah_satu`: `(0.0045, 0.045, 0)` → ≤ 3 (dengan koefisien 2 hasilnya 3,89).

Di `KalkulatorRumusTest`:
- Ubah `rk_sesuai_patokan`: `rk(n1: 2, n2: 3, n3: 4, ni: 1, nn: 3, nw: 5, ndtps: 12)` → `nilai` 1,33; `komponen['a1']` 1,33; `komponen['a2']` 2,75; `skorTepat` 1,80; `skor` 1.
- Tambah `rk_kertas_kerja_memberi_empat`: `rk(71, 24, 46, 19, 24, 98, 28)` → `nilai` 10,96; `skorTepat` 4,0.
- Ganti `ripk_sesuai_ambang` menjadi `ripk_mengikuti_buku_4`: 3,31 → `skorTepat` 4,0 skor 4; 3,0 → 3,6 skor 3; 2,5 → 2,8 skor 2; 2,0 → 2,0; 1,9 → 1,0 skor 1.
- Tambah `pds3_mengisi_skor_tepat`: `pds3(5, 12, 3, 1)->skorTepat` = 4,0 (PDS3 41,67 ≥ 40) dan `hasil_disimpan_memuat_skor_tepat` (`NilaiRumus` baru punya `skor_tepat` sama).

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter='RumusTigaTingkatTest|KalkulatorRumusTest'`
Expected: FAIL.

- [ ] **Step 3: Implementasikan sesuai Interfaces**

`rk()`: A.1 = `nilai >= 4 ? 4 : max(1, nilai)`; A.2 = `skorTigaTingkat($ni, $nn, $nw, 2, 6, 9)` (angka mentah, bukan rasio). Perbarui pemanggilan di `DemoSeeder::hitungRumus` menjadi `rk(n1: 2, n2: 3, n3: 4, ni: 1, nn: 3, nw: 5, ndtps: 12)`. Kepala komentar kelas: ganti "lima belas" → "dua puluh tujuh metode untuk dua puluh delapan rumus (NA dihitung KalkulatorNa)", dan kalimat tentang ambang menjadi "ambang **ditampilkan** dari tabel `rumus` (`aturanSkor()`), **dihitung** di metode dan dikunci uji".

- [ ] **Step 4: Jalankan uji**

Run: `docker exec sigap-php php artisan test --filter='RumusTigaTingkatTest|KalkulatorRumusTest|DemoTest'`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Rumus/HasilRumus.php app/Services/KalkulatorRumus.php database/seeders/DemoSeeder.php tests/Feature/KalkulatorRumusTest.php tests/Feature/RumusTigaTingkatTest.php
git commit -m "feat(rumus): skorTepat, pita tiga tingkat, RK dua bagian, RIPK mengikuti Buku 4"
```

---

### Task 3: Rumus baru A — PRESMA, PKIM, BKD, RGP, DANA

**Files:**
- Modify: `app/Services/KalkulatorRumus.php`
- Test: `tests/Feature/RumusKuantitatifTest.php` (baru; `RefreshDatabase` tidak perlu — metode murni)

**Interfaces:**
- `presma(int $ni, int $nn, int $nw, int $nm): HasilRumus` — kode `PRESMA`; RI/RN/RW = min(1, n/NM); `nilai` = RI (4 desimal); `skorTepat` = round(tiga tingkat(a 0,005; b 0,05; c 0,1), 2); `komponen` ri/rn/rw/ni/nn/nw/nm.
- `pkim(int $paten, int $bukuIsbn, int $karyaSeni, int $publikasi, int $nm): HasilRumus` — kode `PKIM`; `nilai` = min(1, Σ/NM) (4 desimal); skor 4/3/2/1 pada ≥ 0,2 / 0,15 / 0,1; `skorTepat` = skor.
- `bkd(float $rataSksPerSemester): HasilRumus` — kode `BKD`; 12 ≤ x ≤ 16 → 4; 6 ≤ x < 12 → max(1, (2x − 12)/3); 16 < x ≤ 18 → max(1, 36 − 2x); selain itu 1; `skorTepat` 2 desimal.
- `rgp(int $ngp, int $nbs, int $magisterDoktor, int $madyaUtama, int $serdik): HasilRumus` — kode `RGP`; `nilai` = RGPBS = NGP/NBS (2 desimal); rasio MD/MU/GP = min(1, n/NGP); `skorTepat` = rumus sel E348 (spec §5.3) dibulatkan 2 desimal; `komponen` memuat keempat rasio.
- `dana(float $biayaOperasionalPendidikan, float $danaPenelitian, float $danaPkm, int $nm, int $ndtps): HasilRumus` — kode `DANA`; ROP = op/NM, RPD = penelitian/NDTPS, RPkMD = pkm/NDTPS (rupiah/tahun, 2 desimal); sub-skor 4/3/2/1 pada ambang (18 jt / 10 jt / 5 jt), (10 jt / 7 jt / 4 jt), (5 jt / 3 jt / 1 jt); `skorTepat` = rata ketiganya (2 desimal); `nilai` = ROP; `komponen` memuat rop/rpd/rpkmd/skor_op/skor_pd/skor_pkm.
- Semua: `pastikanPembagiSah` pada NM, NBS, NGP, NDTPS.

- [ ] **Step 1: Tulis uji yang gagal** (`RumusKuantitatifTest`, `assertEqualsWithDelta` 0,01 untuk `skorTepat`)

- `presma_contoh_kertas_kerja`: `presma(13, 6, 15, 7521)` → `skorTepat` 2,36, `skor` 2.
- `presma_internasional_cukup_memberi_empat`: `presma(1, 10, 5, 200)` → 4,0.
- `presma_tanpa_prestasi_memberi_satu`: `presma(0, 0, 0, 200)` → 1,0; `nilai` 0,0.
- `pkim_ambang`: `pkim(5, 43, 21, 31, 7521)` → skor 1; `pkim(10, 10, 0, 0, 100)` → 4 (0,2); `pkim(15, 0, 0, 0, 100)` → 3; `pkim(10, 0, 0, 0, 100)` → 2.
- `bkd_pita`: 14 → 4; 9 → 2,0; 17 → 2,0; 6,5 → 1,0; 18 → 1,0; 5 → 1; 19 → 1.
- `rgp_contoh_kertas_kerja`: `rgp(27, 10, 19, 20, 27)` → 4,0; `rgp(25, 10, 12, 7, 10)` (rasio 2,5 / 0,48 / 0,28 / 0,4) → 3 + rata(0,6; 0,7; 0,8) = 3,70; `rgp(20, 10, 4, 2, 4)` (rasio 2 / 0,2 / 0,1 / 0,2) → 2,67.
- `dana_contoh_kertas_kerja`: `dana(437281818, 4761381820, 1145616667, 7521, 28)` → `skorTepat` 3,0; `komponen['skor_op']` 1, `['skor_pd']` 4, `['skor_pkm']` 4.
- `dana_ambang_tepat_di_batas`: `dana(18e6, 10e6, 5e6, 1, 1)` → 4,0; `dana(10e6, 7e6, 3e6, 1, 1)` → 3,0.
- `pembagi_nol_ditolak`: `presma(1, 1, 1, 0)` dan `rgp(1, 0, 0, 0, 0)` melempar `InvalidArgumentException`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter=RumusKuantitatifTest`
Expected: FAIL (metode tidak ada).

- [ ] **Step 3: Implementasikan kelima metode sesuai Interfaces**

- [ ] **Step 4: Jalankan uji**

Run: `docker exec sigap-php php artisan test --filter='RumusKuantitatifTest|KalkulatorRumusTest'`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KalkulatorRumus.php tests/Feature/RumusKuantitatifTest.php
git commit -m "feat(rumus): PRESMA, PKIM, BKD, RGP, DANA"
```

---

### Task 4: Rumus baru B — PDIPPKM, PMKI, RPPL, PMTK, RMKCPL, RPEN, RPUB, RPKM

**Files:**
- Modify: `app/Services/KalkulatorRumus.php`
- Test: `tests/Feature/RumusKuantitatifTest.php`

**Interfaces:**
- `pdippkm(int $dosenIntegrasi, int $ndtps): HasilRumus` — kode `PDIPPKM`; `nilai` persen (2 desimal, maks 100); ≥ 50 → 4; ≥ 30 → 3; ≥ 10 → 2; selain itu 1.
- `pmki(int $mkIntegrasi, int $nmk): HasilRumus` — kode `PMKI`; `nilai` pecahan maks 1 (4 desimal); ≥ 0,25 → 4; ≥ 0,15 → 3; selain itu 2; `catatan` "Tangga 4/3/2 mengikuti kertas kerja E496; rumus Buku 4 untuk pita 3 jatuh di bawah 3 (pertanyaan ke LAMDIK)".
- `rppl(float $rataPertemuan): HasilRumus` — kode `RPPL`; ≥ 4 → 4; ≥ 3 → 3; ≥ 2 → 2; selain itu 1.
- `pmtk(int $diterimaTs1, int $diterimaTs, int $lulusTs1, int $lulusTs): HasilRumus` — kode `PMTK`; `nilai` = (lulusTs1 + lulusTs)/(diterimaTs1 + diterimaTs) (4 desimal); ≥ 0,5 → 4; selain itu 1 + 6·PMTK (2 desimal); pembagi Σ diterima.
- `rmkcpl(int $mkCpl, int $nmk): HasilRumus` — kode `RMKCPL`; pecahan maks 1; ≥ 0,2 → 4; ≥ 0,15 → 3; > 0 → 2; 0 → 1.
- `rpen(int $luarNegeri, int $dalamNegeri, int $ptMandiri, int $ndtps): HasilRumus` — kode `RPEN`; RI = NI/3/NDTPS, RN = NN/3/NDTPS, RL = NL/3/NDTPS (4 desimal di komponen); `nilai` = RI; tiga tingkat (0,05; 0,3; 1).
- `rpkm(int $luarNegeri, int $dalamNegeri, int $ptMandiri, int $ndtps): HasilRumus` — kode `RPKM`; sama persis, kode berbeda (dibuat lewat satu metode privat bersama).
- `rpub(array $publikasi, int $ndtps): HasilRumus` — kode `RPUB`; `$publikasi` berkunci `na1, na2, na3, na4, nb1, nb2, nb3, nc1, nc2, nc3, nd1, nd2` (kunci yang hilang → `InvalidArgumentException` menyebut kuncinya); RW = (na1 + nb1 + nc1)/NDTPS; RN = (na2 + na3 + nb2 + nc2 + nd1 + nd2)/NDTPS; RI = (na4 + nb3 + nc3)/NDTPS; `nilai` = RI; tiga tingkat (0,1; 1; 2); `catatan` "RN menyertakan ND1 + ND2 sesuai kertas kerja (Buku 4 tidak menyebutnya)".

- [ ] **Step 1: Tulis uji yang gagal**

- `pdippkm_ambang`: (28, 28) → 100,0 / 4; (10, 28) → 35,71 / 3; (3, 28) → 10,71 / 2; (2, 28) → 1.
- `pmki_tangga_kertas_kerja`: (14, 13) → `nilai` 1,0 skor 4; (2, 13) → 3; (1, 13) → 2.
- `rppl_ambang`: 5 → 4; 3,5 → 3; 2 → 2; 1,9 → 1.
- `pmtk_contoh_kertas_kerja`: (104, 173, 102, 169) → `nilai` 0,9783, skor 4; (100, 100, 30, 30) → `skorTepat` 2,8, skor 2.
- `rmkcpl_ambang`: (13, 13) → 4; (2, 13) → 3; (1, 13) → 2; (0, 13) → 1.
- `rpen_contoh_kertas_kerja`: (42, 14, 28, 28) → 4,0; `rpen_hanya_dana_pt`: (0, 0, 60, 28) → 1,43; (0, 0, 28, 28) → 1,0; `rpen_campuran`: (2, 10, 28, 28) → 2,68.
- `rpkm_memakai_kode_sendiri`: `rpkm(17, 3, 64, 28)->kode` = `RPKM`, `skorTepat` 4,0.
- `rpub_contoh_kertas_kerja`: `rpub([na1=>11, na2=>70, na3=>16, na4=>35, nb1=>3, nb2=>15, nb3=>12, nc1=>15, nc2=>3, nc3=>4, nd1=>74, nd2=>4], 28)` → 4,0; `rpub_nd_ikut_dihitung`: semua nol kecuali `nd1 => 28` dengan NDTPS 28 → RN 1,0 → `skorTepat` 3,0; `rpub_kunci_hilang_ditolak`.

- [ ] **Step 2: Jalankan, pastikan gagal** — `--filter=RumusKuantitatifTest` → FAIL.

- [ ] **Step 3: Implementasikan sesuai Interfaces**

- [ ] **Step 4: Jalankan uji** — `--filter='RumusKuantitatifTest|RumusTigaTingkatTest'` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KalkulatorRumus.php tests/Feature/RumusKuantitatifTest.php
git commit -m "feat(rumus): PDIPPKM, PMKI, RPPL, PMTK, RMKCPL, RPEN, RPUB, RPKM"
```

---

### Task 5: Rumus lama tanpa metode — RRD, NDTPSPK, NTENDIKPK, PKMS, RMS, WTMP, TKi

**Files:**
- Modify: `app/Services/KalkulatorRumus.php`
- Test: `tests/Feature/RumusKuantitatifTest.php`

**Interfaces:**
- `rrd(int $rekognisi, int $ndtps): HasilRumus` — kode `RRD`; `nilai` = NRD/NDTPS (2 desimal); ≥ 1 → 4; selain itu 2 + 2·RRD.
- `ndtpspk(int $dtpsPk, int $ndtps): HasilRumus` — kode `NDTPSPK`; persen maks 100; ≥ 80 → 4; ≥ 70 → 3; ≥ 60 → 2; selain itu 1.
- `ntendikpk(int $tendikPk, int $tendik): HasilRumus` — kode `NTENDIKPK`; persen maks 100; ≥ 40 → 4; ≥ 25 → 3; ≥ 10 → 2; selain itu 1.
- `pkms(int $lulusan, int $diterima): HasilRumus` — kode `PKMS`; pecahan maks 1; ≥ 0,85 → 4; ≥ 0,45 → (80·PKMS − 24)/11; selain itu 1.
- `rms(float $rataTahun): HasilRumus` — kode `RMS`; `nilai` = rata (2 desimal); `skor` **null**, `skorTepat` **null**; `catatan` = "Skor menunggu jawaban LAMDIK: rumus resmi (Buku 4, kertas kerja E586) memberi skor 1 untuk masa studi satu tahun; lihat docs/KEPUTUSAN.md §2."
- `wtmp(int $kurang6Bulan, int $antara6Dan12Bulan, int $lebih12Bulan, int $lulusan, int $terlacak): HasilRumus` — kode `WTMP`; WTMP = (3·k + 9·a + 15·l)/(k + a + l) bulan (2 desimal, pembagi Σ ketiganya); skor awal: < 6 → 4; 6–12 → (18 − WTMP)/3; > 12 → 1; `skorTepat` = skor awal × faktor responden (2 desimal); `komponen` memuat `prmin`, `pj`, `faktor`, `skor_awal`; `catatan` bila faktor < 1: "Responden {pj} % di bawah minimum {prmin} %; skor dikali {faktor}".
- `tki(array $aspek, int $lulusan, int $tanggapan): HasilRumus` — kode `TKi`; `$aspek` = sembilan elemen `{a, b, c, d}` dalam persen (jumlah elemen ≠ 9 → `InvalidArgumentException`); TKi = 4a + 3b + 2c + d dengan a..d/100; `nilai` = Σ TKi/9 (4 desimal, skala 1–4); `skorTepat` = `nilai` × faktor responden (2 desimal); komponen seperti `wtmp`.
- Privat: `penyesuaianResponden(int $lulusan, int $terlacak): array{prmin: float, pj: float, faktor: float}` — Prmin = 0,3 bila NL ≥ 150, selain itu 0,5 − (NL/150)·0,2; PJ = min(1, NJ/NL); faktor = min(1, PJ/Prmin); NL = 0 → `InvalidArgumentException` ("Jumlah lulusan tiga tahun nol").

- [ ] **Step 1: Tulis uji yang gagal**

- `rrd`: (100, 28) → 4,0; (7, 28) → 2,5.
- `ndtpspk`: (29, 28) → 100 / 4; (20, 28) → 71,43 / 3; (17, 28) → 2; (10, 28) → 1.
- `ntendikpk`: (30, 29) → 4; (8, 29) → 27,59 / 3; (3, 29) → 2; (2, 29) → 1.
- `pkms`: (90, 100) → 4; (50, 100) → 1,45; (40, 100) → 1.
- `rms_tanpa_skor`: `rms(1.0)` → `nilai` 1,0, `skor` null, `skorTepat` null, `catatan` memuat "LAMDIK".
- `wtmp_contoh_kertas_kerja`: `wtmp(87, 0, 0, 104, 87)` → `nilai` 3,0, `skorTepat` 4,0, `komponen['faktor']` 1,0, `komponen['prmin']` 0,3613.
- `wtmp_titik_tengah`: `wtmp(10, 20, 10, 200, 40)` → `nilai` 9,0; `komponen['skor_awal']` 3,0; Prmin = 0,5 − 200/150·0,2 = 0,2333; PJ = 0,2; faktor 0,8571; `skorTepat` 2,57.
- `wtmp_responden_kurang_mengalikan_skor`: `wtmp(20, 0, 0, 100, 20)` → faktor 0,55 (0,5454), `skorTepat` 2,18, `catatan` memuat "di bawah minimum".
- `wtmp_lulusan_besar_prmin_tiga_puluh_persen`: `wtmp(50, 0, 0, 200, 50)` → prmin 0,3, pj 0,25, faktor 0,8333.
- `tki_semua_sangat_baik`: sembilan aspek `{a:100, b:0, c:0, d:0}`, (104, 87) → `nilai` 4,0, `skorTepat` 4,0.
- `tki_contoh_etika`: aspek pertama `{98.85, 1.15, 0, 0}` sisanya `{100,0,0,0}` → `nilai` (3,9885 + 8·4)/9 = 3,9987.
- `tki_bukan_sembilan_aspek_ditolak`.

- [ ] **Step 2: Jalankan, pastikan gagal** → FAIL.

- [ ] **Step 3: Implementasikan sesuai Interfaces**

- [ ] **Step 4: Jalankan uji** — `--filter='RumusKuantitatifTest|KalkulatorRumusTest'` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/KalkulatorRumus.php tests/Feature/RumusKuantitatifTest.php
git commit -m "feat(rumus): RRD, NDTPSPK, NTENDIKPK, PKMS, RMS tanpa skor, WTMP dan TKi dengan aturan responden"
```

---

### Task 6: `PembacaVariabelDkps` — katalog menjadi angka

**Files:**
- Create: `app/Support/Rumus/NilaiVariabel.php`, `app/Services/PembacaVariabelDkps.php`
- Test: `tests/Feature/PembacaVariabelDkpsTest.php`

**Interfaces:**
- Produces: `final readonly class NilaiVariabel { public function __construct(public string $kode, public ?float $nilai, public array $lembar, public int $jumlahBaris, public int $belumTerverifikasi, public ?string $catatan = null) {} }`.
- Produces: `PembacaVariabelDkps::semua(Periode $periode): array` (kode → `NilaiVariabel`, urut `urutan`), `satu(Periode $periode, string $kode): NilaiVariabel` (kode tak dikenal → `InvalidArgumentException`).
- Consumes: `VariabelDkps`, `DkpsButir::dariKode()->skema()`, `DkpsBaris::nilai()`, `SkemaLembar::hitung()`, `SkemaLembar::barisTetap()`.
- Aturan baca (satu metode privat per `CaraBacaVariabel`):
  - Baris = `DkpsBaris` hidup pada `periode_id`, `dkps_butir_id` butir, `lembar_kode` lembar (global scope `lingkup_periode` **dilepas** dengan `withoutGlobalScope('lingkup_periode')`, karena perintah artisan berjalan tanpa sesi).
  - Kolom bertipe `hitung` dibaca dari `$skema->hitung($baris->data, $baris->kunci_normal)[$kunci]`; kolom lain dari `$baris->nilai($kunci)`.
  - `null` bila **semua** lembar sumber tanpa baris; selain itu angka (0 termasuk). Untuk `nilai_sel`/`jumlah_sel`: baris tetap yang tidak ada → 0 bila lembar punya baris lain.
  - `cacah_centang`: nilai kolom dianggap tercentang bila `=== true` atau `=== 1` atau `=== '1'`; `cacah_terisi`: `filled()`; `cacah_unik`: `trim`, kosong diabaikan, pembanding tidak peka huruf besar.
  - `khusus`: `N_DTPS` = NIDN/NIDK unik (trim) gabungan lembar 6 dan 7, baris dengan NIDN kosong dihitung **masing-masing satu** (tidak digabung); `N_DTPS_DOKTOR` = NIDN unik dengan `doktor` terisi pada salah satu lembar; `N_DTPS_GB/LK/LEKTOR` = NIDN unik dengan `jabatan_akademik` = `Guru Besar` / `Lektor Kepala` / `Lektor` (bila berbeda antarlembar, ambil lembar 7); `RT_PEMBIMBINGAN_PPL` = Σ (`pertemuan_ts2`, `pertemuan_ts1`, `pertemuan_ts`) ÷ jumlah sel yang terisi (bukan null) atas semua baris Tabel 20; `RT_MS_LULUSAN` = rata tertimbang `rata_masa_studi` (kolom hitung) berbobot `jumlah_lulusan` atas baris Tabel 22 TS-1 dan TS; `N_DTPS_PUBLIKASI` = `nama_dtps` unik Tabel 27.
  - `belumTerverifikasi` = baris yang dihitung dengan `diverifikasi_pada` null.

- [ ] **Step 1: Tulis uji yang gagal** (`RefreshDatabase`, seed `DatabaseSeeder`, TS 2027, helper `pengguna()` dan `$this->pengisi` seperti `PengisiDkpsTest`; fixture ditulis lewat `PengisiDkps::simpan()` oleh koordinator pokja pemilik tabel — pokja per tabel ada di `dkps-tabel.json` kunci `pokja`)

- `lembar_kosong_memberi_null`: tanpa baris, `satu($periode, 'N_KERJASAMA_PENDIDIKAN')->nilai` null, `catatan` memuat "1.1".
- `cacah_baris_dan_centang_tabel_1`: dua baris 1.1 (satu internasional, satu nasional), satu baris 1.2 (nasional) → `N_KERJASAMA_PENDIDIKAN` 2, `N_KERJASAMA_PENELITIAN` 1, `N_KERJASAMA_INTERNASIONAL` 1, `N_KERJASAMA_NASIONAL` 2, `N_KERJASAMA_WILAYAH` 0.
- `lembar_terisi_tanpa_centang_memberi_nol_bukan_null`: satu baris Tabel 3 tanpa tingkat → `N_PRESTASI_INTERNASIONAL` 0,0 (bukan null).
- `nilai_sel_dan_jumlah_sel_tabel_2`: baris TS dengan aktif 173 + 7348 → `N_MHS_PPG` 7521.
- `sel_baris_tetap_yang_hilang_dibaca_nol_bila_lembar_terisi`: Tabel 23 hanya baris TS-2 (`jumlah_lulusan` 104, `terlacak` 87, `wt_kurang_6` 87) → `N_LULUSAN_3TH` 104, `N_LULUSAN_WT_6BLN` 87, `N_LULUSAN_WT_12BLN` 0; Tabel 24.2 kosong → `N_TKP_TERLACAK_3TH` null.
- `ndtps_menghitung_nidn_unik_gabungan_tabel_6_dan_7`: Tabel 6 dua dosen (NIDN A, B; B doktor, jabatan Lektor Kepala), Tabel 7 dua dosen (NIDN B dengan nama berbeda ejaan, C Guru Besar) → `N_DTPS` 3, `N_DTPS_DOKTOR` 1, `N_DTPS_LK` 1, `N_DTPS_GB` 1.
- `nidn_kosong_tidak_digabung`: dua baris Tabel 7 tanpa NIDN → `N_DTPS` 2.
- `kolom_hitung_dibaca_dari_skema`: Tabel 8 dua dosen (jumlah sks 28 dan 24) → `RBK_DTPS` 13,0 (rata `rata_per_semester` 14 dan 12).
- `cacah_unik_dan_pilihan_tabel_13_dan_19`: Tabel 13 tiga guru pamong (2 Magister, 1 Sarjana; 1 Guru Madya; 2 `no_serdik` terisi) → `N_GP` 3, `N_GP_MAGISTER_DOKTOR` 2, `N_GP_MADYA_UTAMA` 1, `N_GP_SERDIK` 2; Tabel 19 tiga baris oleh dua dosen pada dua MK → `N_INTEGRASI` 2, `N_INTEGRASI_MK` 2.
- `rt_pembimbingan_per_dosen_per_tahun`: Tabel 20 dosen A pertemuan (4, 6, null) bimbingan (3, 3, null); dosen B (2, null, null) → `RT_PEMBIMBINGAN_PPL` (4 + 6 + 2)/3 = 4,0.
- `belum_terverifikasi_dilaporkan`: dua baris Tabel 9, satu diverifikasi lewat `VerifikatorDkps` → `N_REKOGNISI_DTPS` nilai 2, `belumTerverifikasi` 1.
- `semua_mengembalikan_setiap_kode_katalog`: `count(semua())` = `VariabelDkps::count()`.
- `kode_tak_dikenal_ditolak`.

- [ ] **Step 2: Jalankan, pastikan gagal** — `--filter=PembacaVariabelDkpsTest` → FAIL.

- [ ] **Step 3: Implementasikan sesuai Interfaces**

Muat baris sekali per lembar per panggilan `semua()` (cache lokal dalam objek, dikunci periode) supaya 90 variabel tidak berarti 90 kueri per lembar.

- [ ] **Step 4: Jalankan uji** — PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Rumus/NilaiVariabel.php app/Services/PembacaVariabelDkps.php tests/Feature/PembacaVariabelDkpsTest.php
git commit -m "feat(rumus): PembacaVariabelDkps membaca katalog variabel dari dkps_baris"
```

---

### Task 7: `PenghitungRumusDkps` — jembatan

**Files:**
- Create: `app/Support/Rumus/LaporanPerhitungan.php`, `app/Services/PenghitungRumusDkps.php`
- Modify: `app/Services/KalkulatorRumus.php` (`simpan()` menyalin `skor_tepat` — sudah lewat `untukDisimpan()`; tidak ada perubahan lain)
- Test: `tests/Feature/PenghitungRumusDkpsTest.php`

**Interfaces:**
- Produces: `final readonly class LaporanPerhitungan { public function __construct(public array $disimpan, public array $dilewati, public array $tidakBerubah) {} public function ringkasan(): string; }` — `disimpan` kode → `NilaiRumus`; `dilewati` kode → alasan; `tidakBerubah` kode → "sama dengan hasil terakhir"; `ringkasan()` = "Disimpan N, tidak berubah N, dilewati N".
- Produces: `PenghitungRumusDkps::hitungSemua(Periode $periode, ?User $oleh = null): LaporanPerhitungan`; periode terkunci (`$periode->terkunci()`) → `RuntimeException("Periode terkunci; rumus tidak dihitung ulang.")` sebelum membaca apa pun.
- Pemetaan kode → pemanggilan dalam satu `match ($kode)` eksplisit, memakai nama variabel katalog; `NA` tidak ada di `match` dan tidak dilaporkan.
- Alasan lewat: "Variabel {kode} kosong: {catatan NilaiVariabel}" (gabungkan semua yang kosong, dipisah "; "); pembagi nol / `InvalidArgumentException` → pesannya.
- `komponen['variabel']` = daftar `{kode, nilai, lembar, jumlah_baris, belum_terverifikasi}` untuk setiap variabel rumus itu, ditambahkan ke `HasilRumus` sebelum `simpan()` (buat `HasilRumus` baru dengan `komponen` digabung, karena readonly).
- Duplikat: baris terakhir kode itu pada periode (`orderByDesc('dihitung_pada')`) dengan `nilai`, `skor_tepat`, dan `komponen['variabel']` (dibandingkan setelah `json_encode` dengan kunci terurut) sama → tidak disimpan.

- [ ] **Step 1: Tulis uji yang gagal** (fixture seperti Tugas 6; cukup Tabel 1 lengkap + Tabel 6/7 tiga dosen; TS 2027)

- `menghitung_rk_dari_tabel_1_dan_menyimpan_variabel`: setelah `hitungSemua`, `disimpan['RK']` ada; `NilaiRumus` terakhir `RK` punya `komponen['variabel']` memuat `N_KERJASAMA_PENDIDIKAN` dengan `lembar ['1.1']`; `skor_tepat` tidak null.
- `rumus_dengan_variabel_kosong_dilewati_dengan_alasan`: `dilewati['PRESMA']` memuat "N_MHS_PPG" dan "Tabel 2".
- `hasil_identik_tidak_disimpan_dua_kali`: panggil dua kali → `NilaiRumus::where('rumus_kode', 'RK')->count()` 1; laporan kedua `tidakBerubah['RK']`.
- `perubahan_data_menghasilkan_baris_baru`: tambah satu baris 1.1 lalu hitung → count 2.
- `periode_terkunci_ditolak`: `status` = `StatusPeriode` yang `terkunci()` → `RuntimeException`, `NilaiRumus::count()` tidak berubah.
- `pembagi_nol_dilaporkan_bukan_dilempar`: Tabel 1 terisi, Tabel 6 dan 7 kosong → `dilewati['RK']` memuat "N_DTPS".
- `na_tidak_dihitung_jembatan`: tidak ada kunci `NA` di ketiga daftar.
- `rms_disimpan_tanpa_skor`: Tabel 22 terisi → `disimpan['RMS']->skor` null, `skor_tepat` null, `nilai` terisi.

- [ ] **Step 2: Jalankan, pastikan gagal** → FAIL.

- [ ] **Step 3: Implementasikan sesuai Interfaces**

- [ ] **Step 4: Jalankan uji** — `--filter='PenghitungRumusDkpsTest|PembacaVariabelDkpsTest'` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Rumus/LaporanPerhitungan.php app/Services/PenghitungRumusDkps.php tests/Feature/PenghitungRumusDkpsTest.php
git commit -m "feat(rumus): PenghitungRumusDkps menghitung semua rumus dari DKPS tanpa duplikat"
```

---

### Task 8: Perintah `rumus:hitung`

**Files:**
- Create: `app/Console/Commands/HitungRumus.php`
- Test: `tests/Feature/HitungRumusCommandTest.php`

**Interfaces:**
- `signature = 'rumus:hitung {--periode= : ID periode; tanpa ini, semua periode aktif}'`; `description = 'Menghitung seluruh rumus kuantitatif dari isian Tabel DKPS dan menyimpan hasil barunya'`.
- Periode aktif: `Periode::aktif()` (scope yang ada, `status Berjalan` dan bukan simulasi), diproses berurutan `created_at`.
- Keluaran per periode: judul "Periode {nama} (TS {ts_tahun})", lalu tiga blok `table()`: Disimpan (kode, nilai, skor A), Tidak berubah (kode), Dilewati (kode, alasan). Baris terakhir `ringkasan()`. `RuntimeException` periode terkunci → `error()` dan lanjut ke periode berikutnya; kode keluar 0 bila tidak ada galat lain.
- `--periode` tak dikenal → `error("Periode {id} tidak ditemukan")`, kode keluar 1.

- [ ] **Step 1: Tulis uji yang gagal**

- `menghitung_periode_aktif_dan_melaporkan_tiga_blok`: fixture Tabel 1 + 6/7 → `artisan('rumus:hitung')->expectsOutputToContain('RK')->expectsOutputToContain('Dilewati')->assertExitCode(0)`; `NilaiRumus::where('rumus_kode','RK')->exists()`.
- `periode_tak_dikenal_gagal`: `--periode=bukan-uuid` → exit 1.
- `periode_terkunci_dilaporkan_tanpa_menulis`.

- [ ] **Step 2: Jalankan, pastikan gagal** → FAIL.

- [ ] **Step 3: Implementasikan**

- [ ] **Step 4: Jalankan uji** — `--filter=HitungRumusCommandTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/HitungRumus.php tests/Feature/HitungRumusCommandTest.php
git commit -m "feat(rumus): perintah rumus:hitung"
```

---

### Task 9: Izin, menu, dan layar — Hitung dari DKPS, Variabel DKPS, Skor A di Penilaian

**Files:**
- Modify: `data/izin.json` (aksi ke-26), `data/menu.json` (menu ke-22), `data/verifikasi.py:88-89`
- Modify: `database/seeders/IzinSeeder.php:11,58-59`, `app/Support/Izin.php:17,112`
- Modify: `tests/Feature/MatriksIzinTest.php:19,214-219`, `tests/Feature/CekKesiapanTest.php:97`, `tests/Feature/MenuPeranTest.php` (konstanta `KELAS` + `harapanSidebar()`)
- Modify: `app/Filament/Resources/NilaiRumuses/Pages/ListNilaiRumuses.php`, `app/Filament/Resources/NilaiRumuses/Tables/NilaiRumusesTable.php`
- Create: `app/Filament/Pages/VariabelDkpsLayar.php`, `resources/views/filament/pages/variabel-dkps.blade.php`
- Modify: `app/Filament/Resources/Penilaians/Schemas/PenilaianForm.php`
- Test: `tests/Feature/NilaiRumusLayarTest.php`, `tests/Feature/VariabelDkpsLayarTest.php`, `tests/Feature/PenilaianSkorATest.php`

**Interfaces:**
- `izin.json` aksi baru: `{"kode": "nilai_rumus.hitung", "label": "Menghitung rumus dari DKPS", "admin": "tidak", "ketua": "ya", "pimpinan": "tidak", "koordinator": "pokja_data", "anggota": "tidak", "auditor": "tidak"}`.
- `menu.json` menu baru: `{"kode": "variabel_dkps", "label": "Variabel DKPS", "grup": "Data", "admin": "tidak", "ketua": "ya", "pimpinan": "ya", "koordinator": "pokja_data", "anggota": "pokja_data", "auditor": "tidak"}` (sama dengan `nilai_rumus`).
- `ListNilaiRumuses::getHeaderActions()`: `Action::make('hitung')->label('Hitung dari DKPS')->visible(fn () => Izin::boleh(auth()->user(), 'nilai_rumus.hitung'))->requiresConfirmation()->action(...)` memanggil `PenghitungRumusDkps::hitungSemua(Periode::aktif()->firstOrFail(), auth()->user())` (pola yang dipakai `NilaiRumusesTable:58`); notifikasi `success` dengan `ringkasan()` bila `dilewati` kosong, `warning` dengan ringkasan + daftar alasan (maks 5 baris) bila ada; `RuntimeException` → notifikasi `danger` dengan pesannya.
- `NilaiRumusesTable`: kolom `skor_tepat` berlabel "Skor A" (2 desimal, koma) setelah `skor`; kolom `komponen` menampilkan `komponen['variabel']` sebagai daftar "KODE = nilai (lembar)" bila ada.
- `VariabelDkpsLayar extends Page`: `slug 'dkps/variabel'`, grup navigasi `Data`, `shouldRegisterNavigation()` lewat `Izin::bolehMenu($user, 'variabel_dkps')`, `canAccess()` lewat `Izin::bolehMenu` yang sama; tabel sederhana di Blade (bukan Filament Table): kolom Kode, Label, Nilai (koma; "—" bila null), Lembar (tautan `TabelDkpsLayar::urlUntuk($kodeTabel, $kodeLembar)`), Baris, Belum terverifikasi, Rumus. Data dari `PembacaVariabelDkps::semua(Periode::aktif()->firstOrFail())`.
- `PenilaianForm`: setelah `Radio::make('skor')`, `Placeholder::make('skor_a')->label('Skor A dari rumus')->content(fn ($get) => …)` — untuk `elemen_id` terpilih, ambil `Rumus` elemen itu, lalu `NilaiRumus` terakhir per kode pada periode aktif; tampil "KODE: nilai → Skor A x,xx (dihitung d F Y)" per baris, atau "Belum dihitung dari DKPS" bila kosong, atau "Elemen ini tidak punya rumus kuantitatif". `->live()` pada `elemen_id` supaya placeholder ikut berubah.

- [ ] **Step 1: Tulis uji yang gagal**

- `MatriksIzinTest`: 150 → 156; komentar "25 aksi x 6 peran" → 26. `CekKesiapanTest:97`: "156 sel izin tersemai". `MenuPeranTest::KELAS` + `'variabel_dkps' => VariabelDkpsLayar::class`; `harapanSidebar()` disesuaikan untuk peran yang melihat grup Data.
- `NilaiRumusLayarTest` (Livewire): `ketua_melihat_tombol_hitung_dan_menjalankannya` (`Livewire::test(ListNilaiRumuses::class)->assertActionVisible('hitung')->callAction('hitung')` lalu `NilaiRumus` bertambah dengan fixture Tabel 1 + 6/7); `anggota_tidak_melihat_tombol` (`assertActionHidden`); `kolom_skor_a_tampil`.
- `VariabelDkpsLayarTest`: `koordinator_pokja_data_melihat_halaman_dan_nilai` (get URL → 200, melihat "N_KERJASAMA_PENDIDIKAN" dan nilainya); `auditor_ditolak` (403); `tautan_lembar_mengarah_ke_tabel_dkps` (melihat `TabelDkpsLayar::urlUntuk('1', '1.1')`).
- `PenilaianSkorATest`: setelah `rumus:hitung` dengan fixture, form Penilaian elemen 7 menampilkan "RK" dan "Skor A"; elemen tanpa rumus menampilkan "tidak punya rumus kuantitatif".

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter='MatriksIzinTest|MenuPeranTest|NilaiRumusLayarTest|VariabelDkpsLayarTest|PenilaianSkorATest|CekKesiapanTest'`
Expected: FAIL.

- [ ] **Step 3: Implementasikan data, seeder, Izin.php, layar sesuai Interfaces**

`verifikasi.py`: "26 aksi izin", "156 sel izin (26 x 6)"; tambah pemeriksaan menu 22.

- [ ] **Step 4: Jalankan uji** — saringan di atas + `SaringanTabelTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add data/ database/seeders/IzinSeeder.php app/Support/Izin.php app/Filament resources/views/filament/pages/variabel-dkps.blade.php tests/
git commit -m "feat(rumus): aksi Hitung dari DKPS, halaman Variabel DKPS, Skor A di Penilaian"
```

---

### Task 10: Dokumentasi, pemeriksaan penuh

**Files:**
- Modify: `docs/KEPUTUSAN.md` §2 (bagian "Rumus kuantitatif yang belum ada di SIGAP" → diganti), §1 (keputusan ND1/ND2, RT_PEMBIMBINGAN_PPL, NDTPS gabungan unik)
- Modify: `docs/manual/ketua.html`, `docs/manual/koordinator.html` (bagian Nilai Rumus: tombol Hitung dari DKPS; bagian baru Variabel DKPS), `docs/manual/tur.json` bila memuat daftar halaman
- Modify: `README.md` bila memuat daftar perintah artisan (tambah `rumus:hitung`)

- [ ] **Step 1: Tulis `docs/KEPUTUSAN.md`**

§2 bagian baru "Rumus kuantitatif AK 3.0: lima tafsir yang menunggu LAMDIK": (1) koefisien pita 2–3 (Buku 4 menulis 2, kertas kerja 1; SIGAP 1, alasan matematis); (2) pita bawah 1–2 diinterpolasi; (3) ujung BKD (6–7 dan 18) dengan lantai 1; (4) PMKI bertangga 4/3/2 karena rumus Buku 4 jatuh di bawah 3; (5) RN pada E50 menyertakan ND1 + ND2 (kertas kerja) padahal Buku 4 tidak menyebutnya. Bagian RMS yang ada ditambah kalimat: "SIGAP menghitung nilainya tanpa skor (`rms()`)." §1 "Yang diputuskan bersama dosen": tiga jawaban §9 spec, tanggal 5 November 2026.

- [ ] **Step 2: Perbarui manual dan README**

- [ ] **Step 3: Uji penuh dan verifikasi data**

Run: `docker exec sigap-php vendor/bin/pint --dirty && docker exec sigap-php php artisan test && python3 -P data/verifikasi.py`
Expected: semua PASS; verifikasi keluar 0.

- [ ] **Step 4: Jalankan `rumus:hitung` pada basis data pengembangan**

Run: `docker exec sigap-php php artisan migrate && docker exec sigap-php php artisan db:seed --class=RumusSeeder && docker exec sigap-php php artisan db:seed --class=VariabelDkpsSeeder && docker exec sigap-php php artisan rumus:hitung`
Expected: tabel tiga blok tercetak; tidak ada exception.

- [ ] **Step 5: Commit**

```bash
git add docs/ README.md
git commit -m "docs: tafsir rumus kuantitatif AK 3.0, manual Hitung dari DKPS dan Variabel DKPS"
```

---

## Tindak lanjut di luar rencana ini

1. DKPS demo realistis sehingga `DemoSeeder` menempuh `rumus:hitung` (spec §7).
2. Ekspor ke templat `DKPS PPG.xlsx`.
3. Tangkapan layar manual untuk halaman Variabel DKPS dan aksi Hitung dari DKPS.
4. Bila LAMDIK menjawab lima pertanyaan §2 KEPUTUSAN: hanya metode terkait dan ujinya yang berubah.
