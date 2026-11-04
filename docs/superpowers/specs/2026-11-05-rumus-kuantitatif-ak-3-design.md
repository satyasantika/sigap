# Rumus Kuantitatif AK 3.0 dan Jembatan DKPS → Rumus — Rancangan

Tanggal: 5 November 2026
Status: disetujui dosen 5 November 2026 (jawaban §9)
Lanjutan dari: `docs/superpowers/specs/2026-11-04-tabel-dkps-per-pokja-design.md` (Bagian A.3 dan B.9)

---

## 1. Maksud dan alasan

SIGAP sudah punya lima belas rumus di `data/rumus.json` (tujuh di antaranya
punya metode di `KalkulatorRumus`) dan layar Nilai Rumus, tetapi **tidak
ada satu pun jalur produksi yang memanggilnya**.
Satu-satunya pemanggil adalah `DemoSeeder` dan uji. Angka yang dilihat
ketua prodi di layar Nilai Rumus adalah angka demo, bukan hasil isian
pokja.

Pada saat yang sama, kertas kerja asesor (AK Kosong PPG.xlsx) dan Buku 4
memberi komponen kuantitatif ("Skor A") untuk tiga belas elemen lagi yang
belum ada di `data/rumus.json`.

Proyek ini mengerjakan dua lapis sekaligus, karena lapis pertama tanpa
lapis kedua hanya menambah angka demo:

1. **Rumus baru**: tiga belas rumus kuantitatif yang hilang, ditambah
   metode untuk tujuh kode lama yang sudah ada di `rumus.json` tetapi
   belum punya metode (RMS, PKMS, WTMP, TKi, NDTPSPK, NTENDIKPK, RRD),
   semuanya dengan pola yang sama seperti tujuh metode yang ada.
2. **Jembatan DKPS → rumus**: katalog variabel bernama yang dibaca dari
   `dkps_baris`, satu layanan penghitung yang menjalankan semua rumus
   (lama dan baru) dari variabel itu, satu perintah artisan, dan satu
   tombol di layar Nilai Rumus.

Setelah proyek ini, alurnya menjadi: pokja mengisi Tabel DKPS → ketua
prodi (atau koordinator pokja data) menekan "Hitung dari DKPS" → Skor A
setiap elemen kuantitatif muncul di Nilai Rumus beserta variabel
sumbernya → asesor internal melihat Skor A itu saat mengisi Penilaian.

## 2. Sumber kebenaran dan urutan kewenangannya

Keputusan dosen (5 November 2026): **Buku 4** (Panduan dan Matriks
Penilaian PPG IAPSK 3.0) adalah patokan utama. Kertas kerja AK
(`AK Kosong PPG.xlsx`, lembar "2. Kertas Kerja" dan "5. DKPS") dipakai
untuk memeriksa silang dan untuk mengoperasionalkan rubrik yang Buku 4
tulis sebagai kalimat.

Urutan kewenangan saat keduanya berbeda:

| Hal | Yang menang | Alasan |
|---|---|---|
| Ambang skor (a, b, c, persentase, rupiah) | Buku 4 | dokumen resmi yang dipublikasikan |
| Struktur skor (Skor A vs B, pembagi) | Buku 4 | sama |
| Rumus interpolasi yang Buku 4 tulis **tidak konsisten secara matematis** | kertas kerja | lihat §2.1 |
| Rubrik kalimat tanpa rumus (E13, E22, E25, E45) | ambang Buku 4, bentuk rasio dari kertas kerja | Buku 4 tidak memberi rasionya |
| Definisi variabel DKPS (dari tabel mana, dihitung bagaimana) | lembar "5. DKPS" kertas kerja | Buku 4 tidak menyebut tabel |

Semua perbedaan yang ditemukan dicatat di `docs/KEPUTUSAN.md` §2 sebagai
pertanyaan ke LAMDIK, dengan tafsir yang dipilih SIGAP dan alasannya,
mengikuti perlakuan PGBLKL dan RMS.

### 2.1 Perbedaan yang ditemukan antara Buku 4 dan kertas kerja

Pola interpolasi "RI/RN/RW" dipakai lima elemen (E7 bagian A.2, E12, E49,
E50, E54). Buku 4 menulis pita 2–3 sebagai
`2 + (2 × RI/a) + RN/b − (RI × RN)/(a × b)`; kertas kerja menulis
`2 + 1 × RI/a + RN/b − (RI × RN)/(a × b)`. Dengan koefisien 2, nilai pita
itu bisa melampaui 3 (RI = 0,9a dan RN = 0,9b memberi 3,89), sehingga
pita "2–3" tidak lagi berada di antara 2 dan 3. Dengan koefisien 1,
nilainya selalu di dalam [2, 3]. **SIGAP memakai koefisien 1** dan
mencatatnya sebagai pertanyaan ke LAMDIK.

Pita bawah: Buku 4 menulis "RI = 0, RN = 0, RW < c → Skor = 1"; kertas
kerja menginterpolasi `max(1, 2 × RW/c)`. **SIGAP memakai interpolasi
kertas kerja** karena Buku 4 sendiri menamai pita itu "1–2" pada E12, E49,
E50, E54, yang menyiratkan rentang, bukan nilai tunggal.

E7 bagian A.1: Buku 4 menulis "RK < 4 → A = RK"; kertas kerja memberi 1
bila RK ≤ 1. Keduanya sama untuk RK > 1. **SIGAP memakai `max(1, RK)`**.

E50 RN: Buku 4 menjumlahkan `NA2 + NA3 + NB2 + NC2`; kertas kerja
menambahkan `ND1 + ND2` (buku ber-ISBN, paten). **Keputusan dosen
(5 November 2026): ikut kertas kerja**, RN menyertakan ND1 + ND2. Dicatat
sebagai pertanyaan ke LAMDIK karena Buku 4 tidak menyebut keduanya.

E44 TKi: Buku 4 dan kertas kerja sepakat `TKi = 4a + 3b + 2c + d` dengan
a..d dalam pecahan (0–1), **tanpa** dibagi 4, dan Skor = Σ TKi / 9. Ini
berbeda dari TKM (E14) yang memang dibagi 4. Keduanya benar menurut
sumbernya masing-masing; jangan disamakan.

## 3. Sasaran, bukan sasaran, anti-sasaran

**Sasaran**

- Semua rumus di `rumus.json` (lima belas yang ada, ditambah yang baru)
  bisa dihitung dari isian Tabel DKPS tanpa mengetik angka.
- Setiap hasil menyimpan variabel sumbernya (angka dan dari lembar mana),
  supaya ketua prodi bisa menelusuri "RK = 2,4 ini dari baris mana".
- Lembar kosong atau pembagi nol tidak menghasilkan angka diam-diam;
  rumus itu dilewati dan alasannya terlihat di layar.
- Hasil yang sama persis tidak disimpan dua kali.
- Skor A terlihat oleh asesor internal saat mengisi Penilaian.

**Bukan sasaran** (ditunda ke rencana lain)

- Menggabungkan Skor A dengan skor asesor menjadi skor elemen secara
  otomatis, atau mengubah `KalkulatorNa`. Penilaian tetap diketik
  manusia; Skor A hanya petunjuk di sebelahnya.
- Mengubah `jenis` elemen di `data/elemen.json` (patokan 20/30/9 di
  `KriteriaTerimaTest` tetap).
- Ekspor ke templat `DKPS PPG.xlsx`.
- Rumus untuk elemen yang Buku 4 nilai murni dengan rubrik asesor (E35,
  E33, E34, dan sebagainya).
- NA (`rumus.json` kode `NA`) tetap dihitung `KalkulatorNa`, bukan jembatan
  ini.

**Anti-sasaran** (yang membuatnya gagal walau "jalan")

- Angka cadangan diam-diam saat data kosong. Rumus yang kekurangan
  variabel harus **dilewati dengan alasan**, bukan dihitung dengan nol.
- Mengurai teks ekspresi di `rumus.json` menjadi mesin rumus. Alasannya
  sudah ditulis di kepala `KalkulatorRumus`: pengurai yang salah
  diam-diam lebih berbahaya daripada metode yang jelas.
- Menjadikan tabel `nilai_rumus` bisa ditimpa. Ia tetap append-only.

## 4. Batasan

- Laravel + Filament seperti yang ada; uji dijalankan dengan
  `docker exec sigap-php php artisan test`.
- Pencocokan butir DKPS lewat `kode`, bukan `no` (KEPUTUSAN §4.18).
- Baris DKPS sudah diperiksa jendelanya saat ditulis (`tahun_acuan`
  berisi label `TS`, `TS-1`, … atau kunci baris tetap). Pembaca variabel
  **tidak** memfilter ulang berdasarkan tahun; ia percaya `tahun_acuan`.
- Baris yang dihitung adalah semua baris hidup (`deleted_at` null) pada
  periode itu, **terverifikasi maupun belum**. Verifikasi adalah gerbang
  manusia yang terpisah; jumlah baris belum terverifikasi dilaporkan di
  komponen hasil supaya terlihat, bukan dipakai untuk menyaring.
- Hak akses mengikuti `data/izin.json` dan `data/menu.json`; tombol
  hitung memerlukan izin baru `nilai_rumus.hitung` (ketua: ya;
  koordinator pokja data: ya; lainnya: tidak).

## 5. Rancangan

### 5.1 Katalog variabel: `data/variabel-dkps.json`

Cermin dari lembar "5. DKPS" kertas kerja: daftar variabel bernama yang
dibaca dari DKPS. Satu berkas data, satu baris per variabel:

```json
{
  "kode": "N_KERJASAMA_INTERNASIONAL",
  "label": "Jumlah kerja sama tingkat internasional (3 tahun)",
  "lembar": ["1.1", "1.2", "1.3"],
  "cara": "cacah_centang",
  "kolom": "internasional",
  "rumus": ["RK"]
}
```

Kolom `cara` adalah enum kecil (`App\Enums\CaraBacaVariabel`), bukan
ekspresi bebas:

| cara | arti | contoh |
|---|---|---|
| `cacah_baris` | jumlah baris pada lembar | N1 = baris 1.1 |
| `cacah_centang` | baris dengan kolom centang bernilai benar | NI kerja sama |
| `cacah_pilihan` | baris dengan kolom pilihan termasuk nilai tertentu (`nilai: [...]`) | NGPMD = pendidikan ∈ {Magister…, Doktor…} |
| `cacah_terisi` | baris dengan kolom tidak kosong | NGPGP = `no_serdik` terisi |
| `cacah_unik` | nilai unik sebuah kolom (dengan filter opsional) | NDIPPKM = `nama_dtps` unik di Tabel 19; NBS = `nama_bidang_studi` unik di Tabel 7 |
| `jumlah_kolom` | jumlah sebuah kolom atas semua baris | Σ pertemuan Tabel 20 |
| `rata_kolom` | rata-rata sebuah kolom | BKD = rata `rata_per_semester` Tabel 8 |
| `nilai_sel` | satu sel pada lembar berbaris tetap (`baris`, `kolom`) | N_MHS_PPG = Tabel 2 baris `TS`, `aktif_calon_guru` + `aktif_guru_tertentu` |
| `jumlah_sel` | jumlah beberapa sel baris tetap (`sel: [[baris, kolom], …]`) | B_OP = Σ `ps_rata` lima baris operasional Tabel 14 |

Variabel dengan `lembar` lebih dari satu dijumlahkan antarlembar (dipakai
Tabel 1 dan Tabel 4). Filter tambahan (`filter: {kolom, nilai}`) boleh
untuk `cacah_*` dan `cacah_unik`, misalnya NMKCPL = baris Tabel 18 dengan
`mk_inti` benar **dan** `tautan_asesmen_cpl` terisi.

Dua variabel turunan yang **tidak** bisa dinyatakan sebagai satu cara dan
dihitung di kode `PembacaVariabelDkps`, ditandai `"cara": "khusus"`:

- `N_DTPS`: dosen unik berdasarkan `nidn_nidk` gabungan Tabel 6 dan
  Tabel 7. Contoh di kertas kerja menunjukkan N_DPP = N_DBS = N_DTPS = 28,
  jadi NDTPS **bukan** DPP + DBS melainkan gabungan orang yang sama
  (disetujui dosen 5 November 2026; menggantikan kalimat "NDTPS = DPP +
  DBS" di spesifikasi 4 November).
- `WTMP`: rata-rata tertimbang titik tengah 3 / 9 / 15 bulan atas
  `wt_kurang_6`, `wt_6_12`, `wt_lebih_12` tiga tahun Tabel 23 (mengikuti
  sel D663–D665 dan E659 kertas kerja).

Katalog lengkap (nama variabel mengikuti lembar "5. DKPS" bila ada di
sana) ada di Lampiran A. Seeder `VariabelDkpsSeeder` memuatnya ke tabel
`variabel_dkps` supaya layar bisa menampilkannya dan uji referensi bisa
mengunci jumlahnya.

### 5.2 `PembacaVariabelDkps` (layanan baca)

```php
final class PembacaVariabelDkps
{
    /** @return array<string, NilaiVariabel>  kode => nilai */
    public function semua(Periode $periode): array;

    public function satu(Periode $periode, string $kode): NilaiVariabel;
}

final readonly class NilaiVariabel
{
    public string $kode;
    public ?float $nilai;          // null bila lembar sumber kosong
    public array $lembar;          // kode lembar yang dibaca
    public int $jumlahBaris;       // baris yang dihitung
    public int $belumTerverifikasi;
    public ?string $catatan;       // "Tabel 13 belum berisi baris" dsb.
}
```

Pembacaan memakai `DkpsButir::dariKode()->skema($lembar)` dan
`DkpsBaris::nilai($kunci)` sehingga tidak ada nama kolom yang ditulis
ulang di kode: semuanya datang dari `dkps-tabel.json` lewat katalog.
Kolom bertipe `hitung` (misalnya `rata_per_semester`, `ps_rata`,
`jumlah`) dibaca dari hasil `SkemaLembar::hitung()`, bukan dari `data`
mentah, supaya satu rumus kolom tidak ditulis dua kali.

`nilai` bernilai `null` bila lembar sumber tidak punya baris sama
sekali. Nol sungguhan (lembar terisi, tidak ada centang) tetap `0.0`.
Perbedaan ini yang membuat "belum diisi" dan "memang nol" bisa dibedakan
di layar.

### 5.3 Rumus baru di `KalkulatorRumus` dan `rumus.json`

Keadaan awal: `rumus.json` punya 15 kode; `KalkulatorRumus` punya metode
untuk 7 (pds3, pgblkl, ppdtps, rk, rsa, tkm, ripk). Setelah proyek ini:
28 kode di `rumus.json`, 27 metode (semua kecuali `NA`).

**Metode untuk kode lama yang belum punya metode** (ambang dari
`rumus.json`, dicocokkan dengan kertas kerja):

| Kode | Elemen | Skor A | Keterangan |
|---|---|---|---|
| `RRD` | 19 | RRD ≥ 1 → 4; selain itu 2 + 2·RRD | sel E296 |
| `NDTPSPK` | 20 | ≥80 % → 4; ≥70 → 3; ≥60 → 2; selain itu 1 | sel E311 |
| `NTENDIKPK` | 21 | ≥40 % → 4; ≥25 → 3; ≥10 → 2; selain itu 1 | sel E326 |
| `PKMS` | 41 | ≥85 % → 4; 45–85 → (80·PKMS − 24)/11; <45 → 1 | sel E616–E619 |
| `RMS` | 39 | **tidak diberi skor** | lihat di bawah |
| `WTMP`, `TKi` | 43, 44 | lihat tabel berikutnya | |

RMS (keputusan rancangan ini, mengikuti `docs/KEPUTUSAN.md` §2 yang
melarang meniru rumus RMS apa adanya): `rms()` menghitung **nilai** (rata
masa studi dalam tahun, dari Tabel 22) tetapi mengembalikan `skor` dan
`skorTepat` **null** dengan catatan "skor menunggu jawaban LAMDIK; rumus
resmi memberi 1 untuk masa studi satu tahun". Layar menampilkan nilainya
dan catatannya; asesor E39 melihat angka masa studi, bukan skor 1 yang
menyesatkan. Bila LAMDIK menjawab, hanya metode ini yang berubah.

**RIPK dibetulkan.** `ripk()` yang ada memakai tangga 3,25 / 3,00 / 2,75,
sedangkan Buku 4, kertas kerja (sel E569), dan `rumus.json` memakai
interpolasi `((8·RIPK) − 6)/5` untuk 2,00 ≤ RIPK < 3,25 dengan lantai 1.
Karena §2 menetapkan Buku 4 menang untuk ambang dan `skorTepat` kini
dipakai asesor, `ripk()` diganti dalam proyek ini dan uji RIPK di
`KalkulatorRumusTest` disesuaikan ke angka Buku 4 (bukan ke angka
`vibecoding/docs/03-perhitungan.md`). Uji yang "tidak boleh dihapus"
(PDS3 41,67; PPDTPS cacah dosen) tidak tersentuh.

**Rumus bertangga** (RSA, TKM, PPDTPS, NDTPSPK, NTENDIKPK, PKIM, RPPL,
PDIPPKM, RMKCPL) mengisi `skorTepat = skor` supaya kolom Skor A dan
placeholder Penilaian tidak kosong.

**Tiga belas kode baru**, satu metode per kode, tanda tangan bernama
seperti yang ada. Kode dan ambang (Buku 4, dioperasionalkan kertas kerja):

| Kode | Elemen | Variabel | Nilai | Skor A |
|---|---|---|---|---|
| `RK` (diganti) | 7 | N1, N2, N3, NI, NN, NW, NDTPS | RK = (3N1 + 2N2 + N3)/NDTPS | A.1 = RK ≥ 4 → 4, selain itu max(1, RK); A.2 = pola RI/RN/RW dengan a=2, b=6, c=9 atas NI/NN/NW mentah; **Skor A = (2·A.1 + A.2)/3** |
| `PRESMA` | 12 | NI, NN, NW, NM | RI = NI/NM, RN, RW | pola RI/RN/RW, a=0,005 b=0,05 c=0,1 |
| `PKIM` | 13 | N_MHS_PATEN, N_MHS_BUKU_ISBN, N_MHS_SENI, N_MHS_PUBLIKASI_MIN_SINTA5, NM | PKIM = min(1, ΣN/NM) | ≥0,2 → 4; ≥0,15 → 3; ≥0,1 → 2; selain itu 1 |
| `BKD` | 18 | RBK_DTPS | rata sks/semester | 12–16 → 4; 7 < x < 12 → (2x−12)/3; 6 ≤ x ≤ 7 → 1; 16 < x < 18 → 36−2x; x = 18 → 1 (?) lihat catatan; < 6 atau > 18 → 1 |
| `RGP` | 22 | NGP, NBS, NGPMD, NGPMU, NGPGP | RGPBS = NGP/NBS; RGPMD, RGPMU, RGPGP (pecahan, maks 1) | rumus sel E348 kertas kerja, ambang a..d = 0,3 / 0,4 / 0,5 / 0,6, hasil dibulatkan 2 desimal |
| `DANA` | 25 | B_OP, B_PENELITIAN, B_PKM, NM, NDTPS | ROP = B_OP/NM; RPD = B_PENELITIAN/NDTPS; RPkMD = B_PKM/NDTPS (rupiah/tahun) | tiga sub-skor (18/10/5 jt; 10/7/4 jt; 5/3/1 jt), **Skor A = rata ketiganya** |
| `PDIPPKM` | 32 | N_INTEGRASI, NDTPS | persen | ≥50 → 4; ≥30 → 3; ≥10 → 2; selain itu 1 |
| `PMKI` | 32 | N_INTEGRASI_MK, N_MK_KU | pecahan, maks 1 | ≥0,25 → 4; 0,15 ≤ x < 0,25 → 3 + (x − 0,25)/0,10 (Buku 4), selain itu 2 |
| `RPPL` | 36 | RT_PEMBIMBINGAN_PPL | rata pertemuan | ≥4 → 4; ≥3 → 3; ≥2 → 2; selain itu 1 |
| `PMTK` | 40 | N_MHS_DITERIMA_TS1, N_MHS_DITERIMA_TS, N_LULUS_PADA_TS1, N_LULUS_PADA_TS | pecahan | ≥0,5 → 4; selain itu 1 + 6·PMTK |
| `WTMP` (diganti) | 43 | WTMP, N_LULUSAN_TS4..TS2, N_LULUSAN_TERLACAK_TS4..TS2 | bulan | <6 → 4; 6–12 → (18−x)/3; >12 → 1; **dikali PJ/Prmin bila responden < Prmin** |
| `TKi` (diganti) | 44 | 36 sel Tabel 24.1, N_LULUSAN_TS4..TS2, N_TKP_TERLACAK_TS4..TS2 | Σ TKi/9 (skala 1–4) | nilai itu sendiri, dikali PJ/Prmin bila responden < Prmin |
| `RMKCPL` | 45 | N_MK_CPL, N_MK_KU | pecahan | ≥0,2 → 4; ≥0,15 → 3; >0 → 2; 0 → 1 |
| `RPEN` | 49 | N_PENELITIAN_SB_LN/DN/PT, NDTPS | RI = NI/3/NDTPS, RN, RL | pola RI/RN/RW, a=0,05 b=0,3 c=1 |
| `RPUB` | 50 | NA1..ND2, NDTPS | RW = (NA1+NB1+NC1)/NDTPS; RN = (NA2+NA3+NB2+NC2+ND1+ND2)/NDTPS; RI = (NA4+NB3+NC3)/NDTPS | pola RI/RN/RW, a=0,1 b=1 c=2 |
| `RPKM` | 54 | N_PKM_SB_LN/DN/PT, NDTPS | seperti RPEN | seperti RPEN |

Catatan BKD: kertas kerja sel E283 memberi 1 untuk 6 ≤ BKD ≤ 7 dan
untuk BKD = 18 tepat, sedangkan Buku 4 menulis "6 ≤ BKD < 12 → (2x−12)/3"
dan "16 < BKD ≤ 18 → 36 − 2x" (yang memberi 0 pada x = 18). Buku 4
tidak konsisten di ujung (skor 0 tidak ada). **SIGAP memakai Buku 4
dengan lantai 1**: `max(1, …)` pada kedua pita interpolasi. Dicatat
sebagai pertanyaan ke LAMDIK.

Pola RI/RN/RW dibuat satu metode privat `skorTigaTingkat(float $ri,
float $rn, float $rw, float $a, float $b, float $c): float` yang dipakai
lima rumus, dengan uji yang mengunci kelima pita dan kedua perbedaan §2.1.

Aturan responden E43/E44 dibuat satu metode privat
`penyesuaianResponden(int $nl, int $nj): array{prmin: float, pj: float,
faktor: float}` dengan Prmin = 0,3 bila NL ≥ 150, selain itu
0,5 − (NL/150)·0,2; faktor = min(1, PJ/Prmin).

**`skorTepat`.** Skor A bernilai pecahan (3,4; 2,75) dan pecahan itulah
yang dipakai asesor dalam (3A + B)/4. Selama ini pecahan itu disimpan di
`komponen['skor_a_tepat']` dan `skor` dibulatkan ke bawah. Rancangan ini
menambahkan kolom `skor_tepat decimal(5,2) null` pada `nilai_rumus` dan
properti `skorTepat` pada `HasilRumus`; `skor` (bulat) tetap ada untuk
lencana dan uji lama. Metode lama (`pds3`, `pgblkl`, `rk`) diisi
`skorTepat`-nya juga.

Entri `rumus.json` untuk setiap kode baru mengikuti bentuk yang ada
(`kode`, `nama`, `elemen`, `ekspresi`, `variabel`, `skor`, `jendela`,
`catatan`) dan menambah satu kunci `variabel_dkps: [kode katalog…]` yang
menautkan rumus ke §5.1. Entri `RK`, `WTMP`, `TKi` diperbarui. Uji
referensi `RumusSeederTest` (atau yang sepadan) mengunci jumlah rumus
menjadi **28** dan memastikan setiap `variabel_dkps` ada di katalog.
Uji kunci lain yang ikut bergeser dan disesuaikan: sel izin (bertambah
enam untuk `nilai_rumus.hitung`), jumlah menu (bertambah satu untuk
Variabel DKPS). `rumus:hitung` menolak periode terkunci, mengikuti
`PengisiDkps`. Sel baris tetap yang belum diisi dibaca sebagai 0 bila
lembar itu sudah punya baris lain (contoh kertas kerja: NL4 = NL3 = 0),
dan `null` bila lembarnya kosong sama sekali.

### 5.4 `PenghitungRumusDkps` (jembatan)

```php
final class PenghitungRumusDkps
{
    public function __construct(
        private PembacaVariabelDkps $pembaca,
        private KalkulatorRumus $kalkulator,
    ) {}

    /** Menghitung semua rumus yang variabelnya tersedia; append-only; tanpa duplikat. */
    public function hitungSemua(Periode $periode, ?User $oleh = null): LaporanPerhitungan;
}

final readonly class LaporanPerhitungan
{
    /** @var array<string, NilaiRumus>  kode => baris baru yang disimpan */
    public array $disimpan;
    /** @var array<string, string>  kode => alasan (variabel kosong, pembagi nol) */
    public array $dilewati;
    /** @var array<string, string>  kode => "sama dengan hasil terakhir" */
    public array $tidakBerubah;
}
```

Aturan:

- Untuk setiap kode di `rumus.json` kecuali `NA`: ambil variabelnya dari
  pembaca; bila ada yang `null`, lewati dengan alasan yang menyebut
  lembar mana yang kosong. Bila pembagi nol, tangkap
  `InvalidArgumentException` dari `KalkulatorRumus` dan lewati dengan
  pesannya.
- Setiap `HasilRumus` membawa `komponen['variabel']` = daftar
  `{kode, nilai, lembar, jumlah_baris, belum_terverifikasi}` supaya
  penelusuran balik ke lembar mungkin dari layar.
- **Tanpa duplikat**: sebelum menyimpan, bandingkan `nilai`, `skor_tepat`,
  dan `komponen['variabel']` dengan baris terakhir kode itu di periode
  itu. Sama persis → tidak disimpan, dilaporkan di `tidakBerubah`.
- Pemetaan kode → pemanggilan metode ditulis **eksplisit** di satu
  `match` (bukan refleksi), karena tanda tangan tiap metode berbeda dan
  itulah tempat kesalahan pemetaan paling mudah terbaca.

### 5.5 Perintah artisan `rumus:hitung`

```
php artisan rumus:hitung [--periode=ID]
```

Tanpa `--periode`, memakai periode aktif tiap prodi (mengikuti pola
`dkps:selaraskan`). Keluaran tiga blok: disimpan (kode, nilai, skor
tepat), tidak berubah, dilewati (kode, alasan). Kode keluar 0 walau ada
yang dilewati; yang dilewati adalah keadaan data, bukan galat.

`sigap:cek-kesiapan` **tidak** diubah: rumus belum dihitung bukan
penghalang penggelaran.

### 5.6 Layar

**Nilai Rumus** (`ListNilaiRumuses`) mendapat satu aksi kepala
"Hitung dari DKPS", tampil hanya bila `Izin::boleh($user,
'nilai_rumus.hitung')`. Setelah berjalan, notifikasi Filament meringkas
tiga blok laporan; bila ada yang dilewati, notifikasinya `warning` dan
menyebut lembar yang kosong. Kolom tabel mendapat `skor_tepat`
("Skor A") di sebelah `skor`, dan kolom `komponen` yang sudah ada
menampilkan daftar variabel sumber (kode, nilai, lembar) sebagai teks
kecil.

**Variabel DKPS** (halaman Filament baru, hanya-baca, grup Data,
izin menu sama dengan `nilai_rumus`): tabel katalog §5.1 dengan nilai
saat ini (`PembacaVariabelDkps::semua`), lembar sumber (tautan ke
halaman Tabel DKPS lembar itu), jumlah baris, dan jumlah belum
terverifikasi. Inilah padanan lembar "5. DKPS" kertas kerja yang akan
dibandingkan asesor saat asesmen lapangan.

**Penilaian** (`PenilaianForm`): di bawah `Radio::make('skor')`, satu
`Placeholder` "Skor A dari rumus" yang menampilkan hasil terakhir setiap
rumus elemen itu (kode, nilai, skor tepat, kapan dihitung), atau "belum
dihitung". Tidak mengubah nilai yang disimpan.

### 5.7 Basis data

Satu migrasi:

- `nilai_rumus.skor_tepat decimal(5,2) null`.
- Tabel `variabel_dkps` (uuid, `kode` unik, `label`, `lembar` json,
  `cara`, `kolom` null, `parameter` json null, `urutan`), diisi seeder dari
  `data/variabel-dkps.json`. Tabel ini hanya untuk tampilan dan uji
  referensi; pembaca membaca katalognya lewat model `VariabelDkps`.

Tidak ada perubahan pada `dkps_baris`.

### 5.8 Dokumentasi

- `docs/KEPUTUSAN.md` §2: ganti bagian "Rumus kuantitatif yang belum ada
  di SIGAP" dengan pertanyaan-pertanyaan §2.1 (koefisien pita 2–3,
  interpolasi pita 1–2, ujung BKD, ND1/ND2 pada E50, NDTPS sebagai
  gabungan unik).
- `docs/manual` (README prosedur tangkapan layar): tambah halaman
  Variabel DKPS dan aksi Hitung dari DKPS. Tangkapan layarnya sendiri
  tetap tindak lanjut terpisah.
- Komentar kepala `KalkulatorRumus` diperbarui: "dua puluh delapan
  rumus", dan kalimat "ambang dibaca dari tabel" dijelaskan apa adanya
  (ambang **ditampilkan** dari tabel, **dihitung** di metode dan dikunci
  uji).

## 6. Yang diserahkan ke pembangun

- Nama pasti kelas enum dan direktori (`App\Support\Rumus\…` atau
  `App\Services\…`), mengikuti yang ada.
- Bentuk persis `LaporanPerhitungan` dan teks notifikasi.
- Apakah `VariabelDkps` perlu relasi ke `Rumus` atau cukup kolom json.
- Urutan pengerjaan; disarankan: katalog + pembaca (dengan fixture DKPS
  lewat `PengisiDkps`), lalu rumus baru satu per satu (uji angka tangan dari
  Buku 4 dan contoh kertas kerja), lalu jembatan, perintah, layar,
  dokumentasi.
- Angka uji: contoh di kertas kerja (E73–E96 untuk RK, E147–E162 untuk
  PRESMA, E791–E806 untuk RPEN, E815–E839 untuk RPUB, E335–E348 untuk
  RGP) sudah berisi angka dan bisa dihitung tangan menjadi patokan.

## 7. Risiko yang diketahui dan keputusan lingkup

- **`DemoSeeder` tidak beralih ke jembatan dalam proyek ini.** Data DKPS
  demo saat ini hanya satu sel teks per lembar genap; membuat isian DKPS
  demo yang masuk akal untuk ±20 lembar adalah proyek sendiri. Keputusan
  rancangan ini: `DemoSeeder` tetap memanggil `simpan()` langsung (angka
  demo PDS3 41,67 dan kawan-kawan tetap tampil), dan uji pembaca variabel
  serta jembatan memakai fixture DKPS sendiri yang ditulis lewat
  `PengisiDkps`. Tindak lanjut terpisah: "DKPS demo realistis + demo
  menempuh `rumus:hitung`".
- Tabel 2 (`N_MHS_PPG`) dipakai tiga rumus sebagai pembagi; bila pokja
  MAWA belum mengisi baris TS, ketiganya dilewati sekaligus. Itu disengaja
  dan harus terbaca di notifikasi.

## 8. Lampiran A — Katalog variabel

Nama mengikuti lembar "5. DKPS" kertas kerja bila ada. Kolom "cara" dan
"kolom" merujuk §5.1; "lembar" merujuk kode lembar `dkps-tabel.json`.

| Kode | Lembar | Cara | Kolom / parameter | Dipakai |
|---|---|---|---|---|
| N_KERJASAMA_PENDIDIKAN | 1.1 | cacah_baris | | RK |
| N_KERJASAMA_PENELITIAN | 1.2 | cacah_baris | | RK |
| N_KERJASAMA_PKM | 1.3 | cacah_baris | | RK |
| N_KERJASAMA_INTERNASIONAL | 1.1, 1.2, 1.3 | cacah_centang | internasional | RK |
| N_KERJASAMA_NASIONAL | 1.1, 1.2, 1.3 | cacah_centang | nasional | RK |
| N_KERJASAMA_WILAYAH | 1.1, 1.2, 1.3 | cacah_centang | wilayah_lokal | RK |
| N_MHS_PPG | 2 | jumlah_sel | [TS, aktif_calon_guru], [TS, aktif_guru_tertentu] | PRESMA, PKIM, DANA |
| N_MHS_DITERIMA_TS1 | 22 | nilai_sel | [TS-1, diterima] | PMTK |
| N_MHS_DITERIMA_TS | 22 | nilai_sel | [TS, diterima] | PMTK |
| N_LULUS_PADA_TS1 | 22 | nilai_sel | [TS-1, lulus_akhir_ts1] | PMTK |
| N_LULUS_PADA_TS | 22 | nilai_sel | [TS, lulus_akhir_ts] | PMTK |
| N_PRESTASI_INTERNASIONAL | 3 | cacah_centang | internasional | PRESMA |
| N_PRESTASI_NASIONAL | 3 | cacah_centang | nasional | PRESMA |
| N_PRESTASI_LOKAL | 3 | cacah_centang | wilayah_lokal | PRESMA |
| N_MHS_PATEN | 4.1 | cacah_unik | nim | PKIM |
| N_MHS_BUKU_ISBN | 4.2 | cacah_unik | nim | PKIM |
| N_MHS_SENI | 4.3 | cacah_unik | nim | PKIM |
| N_MHS_PUBLIKASI_MIN_SINTA5 | 4.4 | cacah_unik | nim, filter peringkat_jurnal ∈ {SINTA 1–5, Jurnal Internasional, Jurnal Internasional Bereputasi} — "minimal Sinta 5" dibaca sebagai lantai mutu, jadi jurnal internasional ikut (keputusan rancangan) | PKIM |
| N_DPP | 6 | cacah_baris | | (tampilan) |
| N_DBS | 7 | cacah_baris | | (tampilan) |
| N_DTPS | 6, 7 | khusus | nidn_nidk unik gabungan | RK, DANA, PDIPPKM, RPEN, RPUB, RPKM, PDS3, PGBLKL, RRD, NDTPSPK, PPDTPS, RSA |
| N_DTPS_DOKTOR | 6, 7 | khusus | nidn_nidk unik dengan `doktor` terisi | PDS3 |
| N_DTPS_GB / _LK / _LEKTOR | 6, 7 | khusus | nidn_nidk unik per jabatan | PDS3, PGBLKL |
| N_BIDANG_STUDI | 7 | cacah_unik | nama_bidang_studi | RGP |
| RBK_DTPS | 8 | rata_kolom | rata_per_semester | BKD |
| N_REKOGNISI_DTPS | 9 | cacah_baris | | RRD |
| N_DTPS_PK | 10 | cacah_unik | nama_dtps (atau kolom identitas lembar 10) | NDTPSPK |
| N_TENDIK | 11 | jumlah_sel | seluruh sel jumlah lembar 11 | NTENDIKPK |
| N_TENDIK_PK | 12 | cacah_unik | identitas tendik lembar 12 | NTENDIKPK |
| N_GP | 13 | cacah_baris | | RGP |
| N_GP_MAGISTER_DOKTOR | 13 | cacah_pilihan | pendidikan_tertinggi ∈ {Magister…, Doktor…} | RGP |
| N_GP_MADYA_UTAMA | 13 | cacah_pilihan | jabatan_fungsional ∈ {Guru Madya, Guru Utama} | RGP |
| N_GP_SERDIK | 13 | cacah_terisi | no_serdik | RGP |
| B_OP | 14 | jumlah_sel | ps_rata pada biaya_dosen … biaya_kemahasiswaan | DANA |
| B_PENELITIAN | 14 | nilai_sel | [biaya_penelitian, ps_rata] | DANA |
| B_PKM | 14 | nilai_sel | [biaya_pkm, ps_rata] | DANA |
| N_MK_KU | 18 | cacah_centang | mk_inti | PMKI, RMKCPL |
| N_MK_CPL | 18 | cacah_terisi | tautan_asesmen_cpl, filter mk_inti = benar | RMKCPL |
| N_INTEGRASI | 19 | cacah_unik | nama_dtps | PDIPPKM |
| N_INTEGRASI_MK | 19 | cacah_unik | mata_kuliah | PMKI |
| RT_PEMBIMBINGAN_PPL | 20 | khusus | rerata **per dosen per tahun**: Σ pertemuan_ts2..ts ÷ jumlah sel pertemuan yang terisi (keputusan dosen 5 November 2026); kolom bimbingan hanya ditampilkan | RPPL |
| RELIABILITY_SB … TANGIBLE_KB (20 sel) | 5 | nilai_sel | [aspek, kolom] | TKM |
| N_LULUSAN_TS2 / TS1 / TS, RT_IPK_* | 21 | nilai_sel | | RIPK |
| RT_MS_LULUSAN_* | 22 | nilai_sel | rata_masa_studi | RMS, PKMS |
| N_LULUSAN_TS_4 / _3 / _2 | 23 | nilai_sel | [TS-4..TS-2, jumlah_lulusan] | WTMP, TKi |
| N_LULUSAN_TERLACAK_TS4..TS2 | 23 | nilai_sel | [.., terlacak] | WTMP |
| WTMP | 23 | khusus | titik tengah 3/9/15 | WTMP |
| ETIKA_SB … KREATIVITAS_K (36 sel) | 24.1 | nilai_sel | [aspek, kolom] | TKi |
| N_TKP_TERLACAK_TS4..TS2 | 24.2 | nilai_sel | [.., jumlah_tanggapan] | TKi |
| N_PENELITIAN_SB_LN / DN / PT | 25 | nilai_sel | [luar_negeri / dalam_negeri / pt_mandiri, jumlah] | RPEN |
| NA1 … ND2 (12) | 26 | nilai_sel | [na1 … nd2, jumlah] | RPUB |
| N_DTPS_PUBLIKASI | 27 | khusus | dosen unik berpublikasi memenuhi syarat | PPDTPS |
| N_SITASI / NAS | 28 | sesuai skema lembar 28 | | RSA |
| N_PKM_SB_LN / DN / PT | 29 | nilai_sel | [.., jumlah] | RPKM |

Baris bertanda "(tampilan)" hanya muncul di halaman Variabel DKPS untuk
dibandingkan dengan pembacaan asesor; tidak dipakai rumus. Lembar 9, 10,
11, 12, 21, 27, 28 belum dibaca skemanya dalam kajian ini; pembangun
melengkapi cara bacanya dari `dkps-tabel.json` dengan pola yang sama.

## 9. Jawaban dosen (5 November 2026)

1. **E50 RN ikut kertas kerja**: ND1 + ND2 disertakan.
2. **RT_PEMBIMBINGAN_PPL = rerata per dosen per tahun**: Σ pertemuan ÷
   jumlah sel pertemuan yang terisi pada Tabel 20.
3. **NDTPS = dosen unik gabungan Tabel 6 dan 7** berdasarkan NIDN/NIDK.
