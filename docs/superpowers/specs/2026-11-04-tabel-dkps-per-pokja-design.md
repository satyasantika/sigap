# Tabel DKPS per Pokja — Rancangan dan Hasil Kajian Instrumen

Tanggal: 4 November 2026. Sumber yang dikaji:

- `AK Kosong PPG.xlsx` — kertas kerja Asesmen Kecukupan IAPSK 3.0 PPG
  (`AK-3.1-TU-PPG`, 8 Juni 2026), lima lembar: Data, Kertas Kerja, Penilaian
  Individu, Panduan, DKPS.
- `DKPS PPG.xlsx` — Data Kinerja Program Studi `DKPS-3.1-TU-PPG` versi 3.0
  (15 April 2026), 34 lembar data.
- Kode SIGAP di `main` (commit `8a95e29`), terutama `data/*.json`,
  `app/Models/DkpsBaris.php`, `app/Services/RingkasanProgres.php`,
  `app/Services/KalkulatorRumus.php`, dan Resource `DkpsBaris`.

Dokumen ini dua bagian. **Bagian A** hasil kajian instrumen dan perbandingannya
dengan sistem. **Bagian B** rancangan fitur yang diminta: tiap pokja mengisi
tabel DKPS dalam tampilan seperti Excel, dan ketua task force melihat
progresnya.

---

## Bagian A — Bagaimana instrumen AK menilai, dan di mana sistem berbeda

### A.1 Mekanisme penilaian di kertas kerja

1. **59 elemen, bobot total 100.** Lembar *3. Penilaian Individu* menjumlahkan
   `skor × bobot` untuk 59 elemen (baris 25–83). Nilai AK = jumlah itu; lolos
   AK bila ≥ 200. Bobotnya **identik** dengan `data/elemen.json` (diperiksa satu
   per satu, termasuk E58 = 3,00 dan E13 = 2,25).
2. **Elemen kualitatif** (rubrik dan refleksi) dinilai asesor per
   sub-indikator dengan skala `0; 1; 1,5; 2; 2,5; 3; 3,5; 4`. Skor elemen =
   rata-rata sub-indikator yang terisi (`AVERAGEIF(... ">=0")`). Contoh E1 punya
   lima sub-indikator (ketepatan, kekhasan, berwawasan ke depan, relevansi,
   keselarasan).
3. **Elemen kuantitatif** memakai angka DKPS yang dibaca otomatis (sel abu-abu)
   menjadi **Skor A**, lalu digabung dengan skor analisis asesor **Skor B**
   dengan pembobot tetap, misalnya E7, E12, E13: `(3A + B) / 4`; E14:
   `(A + 3B) / 4`. Asesor tidak boleh mengubah angka DKPS; bila tidak sesuai
   ia menulis deskripsi ketidaksesuaian untuk diverifikasi saat AL.
4. **Syarat perlu Unggul** dihitung terpisah (baris 16–20) dengan kolom 3 tahun
   dan 5 tahun. Kelimanya persis `data/syarat-perlu.json`: E17 (PDS3 ≥ 20 % / 50 %
   **dan** NDGB + NDLK ≥ 3 / 4), E45 (≥ 3 / ≥ 3,5), E34 (≥ 3 / ≥ 3,5), E58
   (≥ 3 / ≥ 3,5), E51 (PPDTPS ≥ 20 % / 40 %).
5. **Lembar 5. DKPS** memuat 190 variabel bernama (`N_KERJASAMA_PENDIDIKAN`,
   `N_DTPS_DOKTOR`, `RELIABILITY_SB`, …) beserta lembar DKPS asalnya dan sel
   Kertas Kerja tujuannya. Ini daftar resmi "angka mana dari DKPS yang dibaca
   mesin", dan menjadi dasar pemetaan tabel → elemen pada Bagian B.

### A.2 Yang sudah cocok dengan sistem

| Hal | AK Kosong PPG | SIGAP |
|---|---|---|
| Jumlah dan bobot elemen | 59, total 100, bobot per elemen | identik (`elemen.json`, `ReferensiInstrumenTest`) |
| Bobot kriteria K1–K9 | 5,5 / 6 / 11,75 / 12,75 / 8 / 30,5 / 13 / 4 / 8,5 | identik (`kriteria.json`) |
| Rumus NA dan ambang 200 / 321 / 361 | `SUM(skor×bobot)`, lolos AK ≥ 200 | `KalkulatorNa`, `rumus.json` NA |
| Lima syarat perlu dan ambangnya | E17, E34, E45, E51, E58 | identik (`syarat-perlu.json`) |
| PDS3, PGBLKL, PPDTPS, RSA, RIPK, PKMS, WTMP, RRD, NDTPSPK, NTENDIKPK | ambang 40 %/70 %/20 %/9/3,25/85 %/6 bln/1/80 %/40 % | identik (`rumus.json`) |
| **Normalisasi TKM** — asumsi yang selama ini menggantung di `docs/KEPUTUSAN.md` §2 | `TKMi = (4·SB + 3·B + 2·C + K)/100 **/ 4**`, ambang 0,25 / 0,5 / 0,75 | **terkonfirmasi**: tafsir "dibagi 4" yang dipakai `KalkulatorRumus::tkm()` benar. Peringatan di tiga tempat bisa dicabut. |

### A.3 Perbedaan dan kekurangan yang ditemukan

Rumus di `rumus.json` yang **berbeda** dari kertas kerja:

| Elemen | Kertas kerja AK | `rumus.json` sekarang | Dampak |
|---|---|---|---|
| E7 Kerja sama (RK) | Dua bagian. **A.1**: `RK = (3·N1 + 2·N2 + N3)/NDTPS` dengan N1 = kerja sama **pendidikan**, N2 = **penelitian**, N3 = **PkM**; skor 4 bila RK ≥ 4. **A.2** menurut tingkat: NI ≥ 2 → 4; NN ≥ 6 → 3–4; NW ≥ 9 → 2; rumus interpolasi. Skor A = `(2·A.1 + A.2)/3`, Skor elemen = `(3A + B)/4`. | N1/N2/N3 ditafsirkan sebagai internasional/nasional/wilayah, tanpa bagian A.2 | Nilai E7 (bobot 1,75) dihitung keliru |
| E17 PDS3 saat NDS3 = 0 | skor kualifikasi = **2** (bukan 2 + 0) | `2 + 5·PDS3` memberi 2 juga — setara | tidak ada |
| E17 PGBLKL | rumus Excel merujuk `F264` (sel kosong) untuk kasus < 70 % — cacat pada berkas resmi | `2 + (20·PGBLKL)/7` | tafsir SIGAP yang masuk akal; catat sebagai pertanyaan ke LAMDIK |
| E39 RMS | `3,5 < RMS ≤ 4 → 4; 4 < RMS ≤ 5 → max(1, min(3,8; 5,6·RMS − 15,8)); selain itu 1`. Untuk PPG (masa studi 1 tahun) rumus ini memberi **skor 1** | "berskala" | cacat instrumen yang harus ditanyakan ke LAMDIK; jangan ditiru mentah-mentah |

Rumus kuantitatif yang **ada di kertas kerja tetapi belum ada** di
`rumus.json` (semuanya memberi Skor A yang lalu digabung dengan skor asesor):

| Elemen | Variabel DKPS | Ambang skor 4 / 3 / 2 | Tabel DKPS |
|---|---|---|---|
| E12 Prestasi mahasiswa | RI = NI/NM, RN = NN/NM, RW = NW/NM | a = 0,005; b = 0,05; c = 0,1 (pola interpolasi sama dengan E7 A.2) | 3, 2 |
| E13 Karya inovatif mahasiswa | PKIM = (NA + NB + NC + ND)/NM | ≥ 0,2 / 0,15 / 0,1 | 4.1–4.4, 2 |
| E18 Beban kerja DTPS | rata-rata BKD/semester | 12–16 sks → 4; 7–12 → (2x − 12)/3; 16–18 → 36 − 2x; lainnya 1 | 8 |
| E22 Guru pamong | RGPBS = NGP/NBS, RGPMD, RGPMU, RGPGP | a..d = 0,3 / 0,4 / 0,5 / 0,6 | 13, 7 |
| E25 Penggunaan anggaran | ROP per mahasiswa; RPD, RPkMD per dosen | 18 jt / 10 jt / 5 jt; 10 jt / 7 jt / 4 jt; 5 jt / 3 jt / 1 jt | 14, 2, 6+7 |
| E32 Integrasi penelitian | RDIPPkM = NDIPPkM/NDTPS; PMKI = NMKI/NMK | 0,5 / 0,3 / 0,1; 0,25 / 0,15 | 19, 18 |
| E36 Pembimbingan PPL | rata-rata pertemuan | ≥ 4 / 3 / 2 | 20 |
| E40 Kelulusan tepat waktu | PMTK | ≥ 0,5 → 4; selain itu 1 + 6·PMTK | 22 |
| E43 Waktu tunggu | ditambah **aturan responden minimum**: Prmin = 30 % bila NL ≥ 150, selain itu 50 % − NL/150 · 20 %; Skor A dikali rasio bila responden < Prmin | — | 23 |
| E44 Kepuasan pengguna | TKi = 4·SB + 3·B + 2·C + K (skala 1–4, **tanpa** dibagi 4), rata-rata 9 aspek, aturan responden minimum sama dengan E43 | — | 24 |
| E45 Asesmen CPL (syarat perlu) | RMKCPL = NMKCPL/NMK | ≥ 0,2 / 0,15 / 0,1 | 18 |
| E49 Penelitian, E54 PkM | RI = NI/3/NDTPS, RN, RL | a = 0,05; b = 0,3; c = 1 (pola interpolasi E7 A.2) | 25, 29 |
| E50 Publikasi | RI = (NA4 + NB3 + NC3)/NDTPS; RN = (NA2 + NA3 + NB2 + NC2 + ND1 + ND2)/NDTPS; RW = (NA1 + NB1 + NC1)/NDTPS | a = 0,1; b = 1; c = 2 | 26 |

Catatan: beberapa elemen di atas berjenis `rubrik` di `elemen.json` (E18, E22,
E25, E32, E36, E45) padahal kertas kerja memberinya komponen kuantitatif.
Mengubah `jenis` akan memecahkan patokan 20/30/9 yang dikunci
`KriteriaTerimaTest`; yang tepat adalah **menambah rumus** tanpa mengubah
jenis. Ini dikerjakan dalam **rencana terpisah** ("Rumus kuantitatif AK 3.0"),
bukan dalam rencana ini.

### A.4 DKPS: versi di sistem sudah tertinggal

`data/dkps-tabel.json` (28 butir, "Buku 3 melompati Tabel 13") tidak lagi
sesuai `DKPS PPG.xlsx` v3.0:

| | `dkps-tabel.json` sekarang | DKPS-3.1-TU-PPG v3.0 |
|---|---|---|
| Jumlah tabel | 28 | **29** (Tabel 1–29, tanpa lompatan) |
| Jumlah lembar isian | 28 | **34 lembar** di Excel; dimodelkan **35 lembar** (Tabel 24 punya dua blok isian) |
| Tabel 1 Kerja sama | satu tabel | tiga lembar: 1.1 Pendidikan, 1.2 Penelitian, 1.3 PkM — pemisahan ini yang dipakai RK (N1/N2/N3) |
| Tabel 4 | tidak ada | **baru**: Karya Inovatif Mahasiswa, empat lembar (paten, buku ISBN, karya seni, publikasi ≥ Sinta 5) — sumber E13 |
| Tabel 5–13 | bergeser satu nomor (Tabel 4 Kepuasan, 5 DPP, … 12 Guru Pamong) | Tabel 5 Kepuasan, 6 DPP, 7 DBS, 8 BKD, 9 Rekognisi, 10 PK DTPS, 11 Tendik, 12 PK Tendik, **13 Guru Pamong** |
| Skema kolom | tidak ada (isian bebas `KeyValue`) | setiap lembar punya kolom tetap, kepala bertingkat, sel kuning, daftar pilihan, kolom rumus |
| Rujukan kode | `ImporDtps` → butir 6 ("DBS" lama), `ImporKerjaSama` → 1, `ImporPublikasiDtps` → 25, `BUTIR_DARI_SIAKAD` | semuanya harus dipetakan ulang ke penomoran v3.0 |

Pemetaan tabel → elemen yang dipakai kertas kerja (dari rujukan "(Tabel N)" dan
lembar 5. DKPS): 1→E7; 2→E9, E12, E13, E25 (NM); 3→E12; 4→E13; 5→E14; 6, 7→E17
(NDTPS = DPP + DBS); 7→E19, E20 (NDTPS); 8→E18; 9→E19; 10→E20; 11, 12→E21;
13→E22; 14→E25; 15, 16→E26; 17→E27; 18→E30, E45; 19→E32; 20→E36; 21→E38;
22→E39, E40, E41; 23→E43; 24→E44; 25→E49; 26→E50; 27→E51; 28→E52; 29→E54.

### A.5 Keputusan dari kajian ini

1. `docs/KEPUTUSAN.md` §2: tafsir TKM "dibagi 4" **dikonfirmasi** oleh kertas
   kerja resmi; peringatan di `KalkulatorRumus::tkm()`, kolom `catatan`
   `nilai_rumus`, dan layar Nilai Rumus diganti menjadi rujukan ke sel E207
   kertas kerja. Dua pertanyaan baru ke LAMDIK: rujukan `F264` pada PGBLKL,
   dan rumus RMS yang memberi skor 1 untuk masa studi satu tahun.
2. Referensi DKPS diganti ke v3.0 (29 tabel, 35 lembar) — prasyarat Bagian B.
3. Rumus kuantitatif yang hilang (A.3) menjadi rencana berikutnya.

---

## Bagian B — Rancangan fitur Tabel DKPS per Pokja

### B.1 Masalah dan sasaran

Sistem sekarang disusun menurut *tagihan* (narasi/bukti/isian DKPS). Untuk
DKPS, taskforce melihat formulir `KeyValue` bebas per baris, tanpa tahu kolom
apa yang diminta. Sasaran:

1. Setiap pokja membuka **tabel DKPS miliknya** dalam bentuk yang sama dengan
   lembar Excel resmi: judul tabel, kepala kolom bertingkat, baris nomor kolom
   `1 2 3 …`, sel isian berlatar kuning, kolom rumus berlatar abu-abu, daftar
   pilihan sesuai validasi Excel.
2. Ketua task force (dan pimpinan, auditor) melihat **progres DKPS per pokja
   dan per lembar**: berapa baris terisi, lengkap, terverifikasi; mana yang
   masih kosong.
3. Aturan lama tetap berlaku: TS parameter, setiap baris punya bukti sebelum
   diverifikasi, impor tempel-tabel, otorisasi hanya lewat `Izin`.

### B.2 Sumber kebenaran: `data/dkps-tabel.json` v3.0

Dibangkitkan oleh `tools/dkps-skema-dari-xlsx.py` dari `DKPS PPG.xlsx`. Label
kolom, kelompok kepala, label baris tetap, dan daftar pilihan **dibaca dari
Excel**; yang ditetapkan tangan hanya kunci mesin, tipe, kolom hitung, aturan,
dan pemilik. Bentuk:

```json
{
  "no": 22, "kode": "22", "label_tabel": "Tabel 22", "nama": "Masa Studi Lulusan",
  "pokja": "POKJA-DIK", "elemen": [39, 40, 41], "jendela_data": "TS-2 s.d. TS",
  "keterangan": "Isi mengikuti format Tabel 22 pada DKPS-3.1-TU-PPG ...",
  "lembar": [{
    "kode": "22", "judul": "Tabel 22 Masa Studi Lulusan", "tahun_dari": "baris_tetap",
    "kolom": [
      {"kunci": "diterima", "label": "Jumlah Mahasiswa diterima", "tipe": "angka", "nomor_excel": "2"},
      {"kunci": "lulus_akhir_ts2", "label": "Akhir TS-2", "tipe": "angka",
       "kelompok": ["Jumlah mahasiswa yang lulus pada"], "nomor_excel": "3"},
      {"kunci": "jumlah_lulusan", "label": "Jumlah Lulusan s.d Akhir TS", "tipe": "hitung",
       "rumus": {"jenis": "jumlah", "kolom": ["lulus_akhir_ts2", "lulus_akhir_ts1", "lulus_akhir_ts"]}}
    ],
    "baris_tetap": {"label_kolom": "Tahun Masuk",
                    "baris": [{"kunci": "TS-2", "label": "TS-2"}, {"kunci": "TS-1", "label": "TS-1"}, {"kunci": "TS", "label": "TS"}]},
    "kolom_nonaktif": {"TS-1": ["lulus_akhir_ts2"], "TS": ["lulus_akhir_ts2", "lulus_akhir_ts1"]}
  }]
}
```

Kosakata:

- **Tipe kolom**: `teks`, `angka` (bulat ≥ 0), `desimal`, `tanggal`
  (disimpan `Y-m-d`; menerima `dd/mm/yyyy` dan ISO), `tahun` (4 digit),
  `pilihan` (dengan `pilihan: [...]`), `centang` (disimpan `true/false`,
  ditampilkan `V`), `tautan` (URL), `hitung` (tidak disimpan; dihitung saat
  tampil dan ekspor).
- **`rumus` kolom hitung**: `selisih_tahun{dari,sampai}`, `jumlah{kolom}`,
  `rata{kolom}`, `bagi{kolom,pembagi}`, `masa_studi_rata` (khusus Tabel 22:
  bobot 1, 2, 3 menurut jarak tahun lulus dari tahun masuk).
- **`baris_tetap`**: lembar yang barisnya sudah ditentukan instrumen (Tabel 2,
  5, 11, 14, 21–26, 29). Baris tidak bisa ditambah/dihapus; `kunci` baris
  menjadi kunci duplikasi. Tabel 14 punya `kelompok` per baris dan `subtotal`.
- **`tahun_dari`**: cara menurunkan `dkps_baris.tahun_acuan` —
  `TS` (tetap), `baris_tetap` (label barisnya: `TS-2`, `TS-4`, …), atau
  `kolom:<kunci>` (tahun dari kolom tanggal/tahun, dipetakan ke label relatif
  lewat `JendelaTs`; **ditolak** bila di luar jendela tabel).
- **`kunci`**: kolom pembentuk kunci duplikasi (`kunci_normal`) untuk lembar
  berbaris bebas.
- **`aturan`** tingkat baris: `tepat_satu{kolom}` (tepat satu centang),
  `minimal_satu{kolom}`, `jumlah_persen_100{kolom}` (toleransi ±0,05),
  `tidak_melebihi{kolom,batas}`.
- **`kolom_nonaktif`**: per kunci baris tetap, kolom yang diabaikan (sel
  diarsir, tidak wajib).
- **`catatan`**: teks catatan dari lembar Excel (mis. "minimal 32 JP").

Lampiran A memuat ke-35 lembar. Berkas siap pakai hasil pembangkitan ada di
`/tmp/claude-1000/-home-satya-code-sigap/d364a834-3355-49ce-8ae2-5167df1e30eb/scratchpad/gen/dkps-tabel.json`
dan skripnya di
`/tmp/claude-1000/-home-satya-code-sigap/d364a834-3355-49ce-8ae2-5167df1e30eb/scratchpad/gen/dkps_skema_dari_xlsx.py`;
keduanya disalin ke repo pada Tugas 1 rencana.

Pemilik pokja (kolom `pokja`) diturunkan dari elemen yang membaca tabel itu
(`pokja.json`): Tabel 1 → MUTU; 2–5 → MAWA; 6–13 dan 25–29 → SDM; 14–17 →
SARPRAS; 18–24 → DIK. **POKJA-DATA tidak lagi memiliki tabel**; perannya
tinggal penetapan TS, Nilai Rumus, dan verifikasi lintas pokja (anggotanya yang
perlu menyunting tabel pokja lain didaftarkan ke pokja itu — satu pengguna boleh
banyak pokja).

### B.3 Perubahan basis data

| Tabel | Perubahan |
|---|---|
| `dkps_butir` | tambah `kode` varchar(8) **unik** (`"1"` … `"29"`), `pokja_kode` varchar(20), `elemen` json, `lembar` json. `no` tetap 1..29 (urutan). Migrasi mengisi `kode` baris lama dari `label_tabel` ("Tabel 14" → "14") sebelum indeks unik dipasang, lalu seeder mencocokkan **lewat `kode`**, menomori ulang dua tahap (no + 100, lalu urutan baru) agar indeks unik `no` tidak bertabrakan. |
| `dkps_baris` | tambah `lembar_kode` varchar(8) (indeks), `pokja_id` foreignUuid nullable → `pokja`. Migrasi mengisi `lembar_kode` baris lama = kode butirnya (`"1"` → `"1.1"`). Model mengisi `pokja_id` saat `creating` dari `pokja` berkode `butir.pokja_kode` pada periode baris itu; perintah `dkps:selaraskan` mengisi baris lama. |

`tahun_acuan` tetap enum `TS..TS-4` dan tetap relatif (aturan proyek 3), hanya
cara pengisiannya yang kini otomatis dari skema.

Kolom `data` tetap json; kuncinya kini **persis kunci kolom skema**. Baris tetap
menyimpan kunci barisnya di `data._baris` dan `kunci_normal`.

### B.4 Wewenang (`data/izin.json`, `data/menu.json`)

| Aksi | Sebelum | Sesudah |
|---|---|---|
| `dkps.isi` | koordinator `pokja_data`, anggota `pokja_data` | koordinator **`pokjanya`**, anggota **`pokjanya`** |
| `dkps.verifikasi` | koordinator `pokja_data` | koordinator **`pokjanya`** |
| ketua | `ya` | tetap `ya` |

Jumlah sel tetap 150; lingkup `pokjanya` membaca `pokja_id` pada `dkps_baris`
(itulah sebabnya kolom itu ditambahkan). Untuk baris yang **belum ada**
(membuat baris baru), pemeriksaan memakai model `DkpsBaris` belum tersimpan yang
`pokja_id`-nya sudah diisi — `Izin::bolehSebagai` membaca `getAttribute`, jadi
tidak perlu jalur khusus.

Menu baru `dkps_lembar` "Tabel DKPS" (grup Data): admin tidak, ketua ya,
pimpinan ya, koordinator ya, anggota ya, auditor ya. Menu `dkps_baris` lama
dilabeli ulang "Verifikasi DKPS": ketua ya, koordinator ya, sisanya tidak.
Total menu 21 × 6 = 126 sel.

### B.5 Layar

**1. `/panel/dkps` — Tabel DKPS (halaman `DkpsLayar`).** Daftar tabel yang
dikelompokkan per pokja. Pengguna berlingkup `pokjanya` melihat pokjanya saja;
ketua, pimpinan, auditor melihat semua. Tiap baris: kode dan nama tabel,
lembar-lembarnya, cacah baris, lengkap, terverifikasi, status, tenggat dan
penanggung jawab dari tagihan `data_dkps` tabel itu. Di atas tiap kelompok:
"x / n lembar terverifikasi". Inilah layar progres DKPS ketua.

**2. `/panel/dkps/tabel/{kode}?lembar=` — satu tabel (halaman
`TabelDkpsLayar`).** Kepala: judul, jendela data dengan tahun nyata
(`JendelaTs::keterangan`), pokja pemilik, elemen yang membacanya, catatan
lembar. Tab per lembar (Tabel 1 punya tiga, Tabel 4 empat, Tabel 24 dua).
Isinya komponen Livewire `LembarDkps`:

- kepala kolom bertingkat (rowspan/colspan dari `kelompok`), baris nomor kolom
  Excel, kolom `No.` otomatis;
- sel isian kuning (`sigap-sel-isian`), kolom hitung abu-abu hanya-baca, kolom
  nonaktif diarsir;
- tiap tipe punya kontrol sendiri: teks → `input`/`textarea` otomatis melebar,
  angka/desimal → `input type=number`, tanggal → `input type=date`, pilihan →
  `select`, centang → kotak centang yang tampil `V`, tautan → `input type=url`;
- simpan **per baris** saat sel kehilangan fokus (`wire:model.blur` +
  `simpan`), galat ditampilkan di bawah sel yang salah, baris yang tersimpan
  ditandai sesaat; tidak ada tombol simpan besar;
- lembar berbaris bebas: tombol **Tambah baris**, hapus baris (ditolak bila
  baris sudah diverifikasi), penomoran ulang otomatis, 50 baris per halaman;
- lembar berbaris tetap: seluruh baris selalu tampil, tidak bisa
  ditambah/dihapus, baris subtotal/jumlah/rata-rata dihitung di bawahnya;
- kolom tambahan di kanan (di luar format Excel, dibedakan warnanya):
  **Bukti** (cacah bukti tertaut + tombol "Tautkan" yang membuka modal: URL,
  judul, tanggal kejadian, sumber → `PengelolaBukti::simpanTautan` lalu
  `attach`), **Sumber** (lencana `siakad/manual/...`; sumber bawaan baris baru
  dipilih satu kali di kepala lembar), **Verifikasi** (centang hijau, nama
  verifikator; tombol verifikasi bagi yang berwenang memakai `VerifikatorDkps`);
- tombol **Impor tempel** membuka `ImporTempel` dengan profil
  `ImporLembarDkps` yang medannya = kolom isian lembar;
- pengguna tanpa `dkps.isi` untuk pokja itu melihat lembar yang sama dalam
  keadaan hanya-baca.

**3. Dasbor K6** menampilkan "x / 35 lembar terverifikasi" dan bilah mini per
pokja; tautan ke `/panel/dkps`. K7 untuk POKJA-DATA tetap memakai ukuran DKPS.

**4. Tagihan `data_dkps`** kini milik pokja pemilik tabel (bukan POKJA-DATA),
berjudul "Isi Tabel N — nama", dan halaman tagihannya punya tombol **Buka
tabel DKPS**. Tagihan tetap wadah tenggat, penanggung jawab, dan alur status;
isian datanya di layar tabel.

### B.6 Status dan progres lembar

Per lembar pada satu periode (`RingkasanProgres::statusLembar`):

| Status | Syarat |
|---|---|
| `kosong` | belum ada baris (baris tetap: tidak satu pun terisi) |
| `terisi` | ada baris, tetapi ada yang belum lengkap atau belum semua baris tetap terisi |
| `lengkap` | semua baris lengkap (kolom wajib terisi, aturan baris lolos) dan untuk baris tetap seluruh barisnya ada |
| `terverifikasi` | `lengkap` dan semua baris `diverifikasi_pada` terisi |

`progresDkps()` menjadi `lembar terverifikasi / 35`;
`progresDkpsPerPokja()` mengelompokkan per `pokja_kode`. Tetap **tidak** ikut
progres bobot (K2).

### B.7 Impor tempel

`ImporLembarDkps(periodeId, prodiId, penggunaId, kodeTabel, kodeLembar)`
menggantikan `ImporBarisDkps` yang medannya generik. `medan()` = kolom isian
skema (label, wajib, tipe, contoh dari `nomor_excel`), ditambah `sumber`.
Untuk lembar berbaris tetap, medan pertama adalah label baris
(`baris_tetap.label_kolom`) dan dicocokkan longgar ke kunci baris. Validasi,
normalisasi, dan kunci duplikasi memakai `SkemaLembar`, jadi aturannya sama
dengan pengisian manual. `ImporDtps` → tabel 7, `ImporKerjaSama` → tabel 1
dengan medan `bidang` (pendidikan/penelitian/pkm) menentukan lembar,
`ImporPublikasiDtps` → tabel 27 bawaan.

### B.8 Migrasi data yang sudah ada

Baris `dkps_baris` lama (isian `KeyValue` bebas) **tidak dihapus**: tetap
tampil di Resource "Verifikasi DKPS", `lembar_kode` diisi, `pokja_id` diisi
oleh `php artisan dkps:selaraskan`. Di layar lembar, baris lama yang kunci
`data`-nya tidak cocok skema ditampilkan dalam bagian "Baris lama yang perlu
dipindahkan" dengan isi mentahnya, agar bisa disalin ke kolom yang benar lalu
dihapus. `sigap:cek-kesiapan` memeriksa 29 tabel, 35 lembar, dan tidak ada
baris DKPS tanpa `pokja_id`.

### B.9 Di luar lingkup rencana ini

- Rumus kuantitatif yang hilang (A.3) — rencana terpisah berikutnya.
- Ekspor kembali ke `DKPS PPG.xlsx` (mengisi templat Excel). Berguna saat
  unggah ke SIMALAMDIK; perlu pustaka penulis xlsx. Rencana berikutnya.
- Tangkapan layar manual pengguna (`docs/manual/`) — diperbarui setelah fitur
  jadi, dengan prosedur yang sudah ada di README.
- Mengubah jenis elemen di `elemen.json`.

---

## Lampiran A — 35 lembar DKPS v3.0

| Kode | Judul lembar | Pokja | Elemen | Tahun acuan | Kolom isian (kunci; * = wajib) | Baris tetap | Kunci duplikasi |
|---|---|---|---|---|---|---|---|
| 1.1 | Tabel 1 Bagian 1 - Kerjasama Bidang Pendidikan | MUTU | E7 | TS | *lembaga_mitra, wilayah_lokal(c), nasional(c), internasional(c), *judul_kegiatan, manfaat, *tanggal_awal(t), tanggal_akhir(t), bukti_kerjasama; hitung: durasi_tahun | — | lembaga_mitra, judul_kegiatan |
| 1.2 | Tabel 1 Bagian 2 - Kerjasama Bidang Penelitian | MUTU | E7 | TS | *lembaga_mitra, wilayah_lokal(c), nasional(c), internasional(c), *judul_kegiatan, manfaat, *tanggal_awal(t), tanggal_akhir(t), bukti_kerjasama; hitung: durasi_tahun | — | lembaga_mitra, judul_kegiatan |
| 1.3 | Tabel 1 Bagian 3 - Kerjasama Bidang PkM | MUTU | E7 | TS | *lembaga_mitra, wilayah_lokal(c), nasional(c), internasional(c), *judul_kegiatan, manfaat, *tanggal_awal(t), tanggal_akhir(t), bukti_kerjasama; hitung: durasi_tahun | — | lembaga_mitra, judul_kegiatan |
| 2 | Tabel 2 Mahasiswa PPG | MAWA | E9, E12, E13, E25 | baris_tetap | kuota(a), lulus_seleksi_calon_guru(a), lulus_seleksi_guru_tertentu(a), lapor_diri_calon_guru(a), lapor_diri_guru_tertentu(a), aktif_calon_guru(a), aktif_guru_tertentu(a), jumlah_pddikti_ts(a) | 3 (Tahun Akademik) | — |
| 3 | Tabel 3 Prestasi Akademik dan Non-akademik Mahasiswa | MAWA | E12 | kolom:tanggal_perolehan | *nama_kegiatan, jenis_prestasi(p), *tanggal_perolehan(t), wilayah_lokal(c), nasional(c), internasional(c), *prestasi_dicapai | — | nama_kegiatan, prestasi_dicapai |
| 4.1 | Tabel 4 Bagian 1 - Paten dan Paten Sederhana | MAWA | E13 | kolom:tahun | *nim, *nama_mahasiswa, *judul_karya, *tahun(t), no_paten | — | nim, judul_karya |
| 4.2 | Tabel 4 Bagian 2 - Buku ber-ISBN, Book Chapter | MAWA | E13 | kolom:tahun | *nim, *nama_mahasiswa, *judul_karya, *tahun(t), keterangan_isbn | — | nim, judul_karya |
| 4.3 | Tabel 4 Bagian 3 - Karya seni yang dipamerkan/dipagelarkan | MAWA | E13 | kolom:tahun | *nim, *nama_mahasiswa, *judul_karya, *tahun(t), bukti | — | nim, judul_karya |
| 4.4 | Tabel 4 Bagian 4 - Publikasi Ilmiah pada Jurnal Nasional Terakreditasi Minimal Sinta 5 | MAWA | E13 | kolom:tahun_terbit | *nim, *nama_mahasiswa, *judul_artikel, keterangan_jurnal, peringkat_jurnal(p), *tahun_terbit(t), tautan_artikel(t) | — | nim, judul_artikel |
| 5 | Tabel 5 Kepuasan Mahasiswa | MAWA | E14 | TS | sangat_baik(d), baik(d), cukup(d), kurang_baik(d), rencana_tindak_lanjut | 5 (Aspek yang Diukur) | — |
| 6 | Tabel 6 Dosen Pengelola Program Studi PPG | SDM | E17 | TS | *nama_dtps, *nidn_nidk, nuptk, magister, doktor, bidang_keahlian, *jabatan_akademik(p), sertifikat_pendidik, mk_diampu_ps, mk_diampu_ps_lain | — | nidn_nidk |
| 7 | Tabel 7 Dosen Bidang Studi dan Dosen Pengampu | SDM | E17, E19, E20 | TS | *nama_bidang_studi, *nama_dosen, *nidn_nidk, nuptk, magister, doktor, bidang_keahlian, *jabatan_akademik(p), sertifikat_pendidik, mk_diampu_ps, kesesuaian_bidang(c), mk_diampu_ps_lain, prodi_homebase | — | nama_bidang_studi, nidn_nidk |
| 8 | Tabel 8 Beban Kerja DTPS | SDM | E18 | TS | *nama_dtps, sks_ps_diakreditasi(d), sks_ps_lain_dalam_pt(d), sks_ps_lain_luar_pt(d), sks_penelitian(d), sks_pkm(d), sks_tugas_tambahan(d), bukti; hitung: jumlah_sks, rata_per_semester | — | nama_dtps |
| 9 | Tabel 9 Pengakuan/Rekognisi Kepakaran/Prestasi DTPS | SDM | E19 | kolom:tahun | *nama_dtps, bidang_keahlian, *deskripsi_rekognisi, *jenis_rekognisi(p), *tahun(t), *tingkat(p), bukti_pendukung | — | nama_dtps, deskripsi_rekognisi |
| 10 | Tabel 10 Pengembangan Kompetensi DTPS | SDM | E20 | kolom:waktu_pelaksanaan | *nama_dtps, *deskripsi_kegiatan, tempat, *waktu_pelaksanaan(t), manfaat_kegiatan, bukti_kegiatan | — | nama_dtps, deskripsi_kegiatan |
| 11 | Tabel 11 Tenaga Kependidikan | SDM | E21 | TS | s3(a), s2(a), s1(a), d4(a), d3(a), sma_smk(a), unit_kerja; hitung: jumlah | 4 (Jenis Tenaga Kependidikan) | — |
| 12 | Tabel 12 Pengembangan Kompetensi Tenaga Kependidikan (Tendik) | SDM | E21 | kolom:waktu_pelaksanaan | *nama_tendik, *deskripsi_kegiatan, tempat, *waktu_pelaksanaan(t), manfaat_kegiatan, bukti_kegiatan | — | nama_tendik, deskripsi_kegiatan |
| 13 | Tabel 13 Guru Pamong | SDM | E22 | TS | *bidang_studi, *nama_guru_pamong, jabatan_fungsional(p), pendidikan_tertinggi(p), no_serdik, *nama_sekolah, akreditasi_sekolah(p), bukti_kerjasama, st_guru_pamong | — | bidang_studi, nama_guru_pamong |
| 14 | Tabel 14 Penggunaan Dana | SARPRAS | E25 | TS | upps_ts2(a), upps_ts1(a), upps_ts(a), ps_ts2(a), ps_ts1(a), ps_ts(a); hitung: upps_rata, ps_rata | 10 (Jenis Penggunaan) | — |
| 15 | Tabel 15 Sarana Laboratorium dan Pembelajaran | SARPRAS | E26 | TS | *nama_ruang, *nama_alat, kualitas(p), jumlah(a), kepemilikan(p), kondisi(p), jam_per_minggu(d) | — | nama_ruang, nama_alat |
| 16 | Tabel 16 Prasarana Pendidikan | SARPRAS | E26 | TS | *nama_prasarana, fungsi, jumlah_unit(a), total_luas_m2(d), kualitas(p), kepemilikan(p), kondisi(p) | — | nama_prasarana |
| 17 | Tabel 17 Teknologi Informasi dan Komunikasi | SARPRAS | E27 | TS | *nama_sistem, deskripsi, jumlah(a), terintegrasi(p), mutakhir(p), ketersediaan_panduan(c), kepemilikan(p), kondisi(p) | — | nama_sistem |
| 18 | Tabel 18 Kurikulum | DIK | E30, E45 | TS | semester(a), *kode_mk, *nama_mk, mk_inti(c), sks_kuliah(d), sks_seminar(d), sks_praktikum(d), tautan_rps(t), tautan_asesmen_cpl(t) | — | kode_mk |
| 19 | Tabel 19 Integrasi Penelitian dan PkM dalam Pembelajaran | DIK | E32 | TS | *nama_dtps, *judul_penelitian_pkm, *mata_kuliah, bentuk_integrasi(p), ts2(c), ts1(c), ts(c), bukti | — | nama_dtps, judul_penelitian_pkm |
| 20 | Tabel 20 Pembimbingan Praktik Pengalaman Lapangan | DIK | E36 | TS | *nama_dosen_pembimbing, bimbingan_ts2(a), bimbingan_ts1(a), bimbingan_ts(a), pertemuan_ts2(a), pertemuan_ts1(a), pertemuan_ts(a), lama_ppl_bulan(d) | — | nama_dosen_pembimbing |
| 21 | Tabel 21 IPK Lulusan | DIK | E38 | baris_tetap | jumlah_lulusan(a), ipk_min(d), ipk_rata(d), ipk_maks(d) | 3 (Tahun Lulus) | — |
| 22 | Tabel 22 Masa Studi Lulusan | DIK | E39, E40, E41 | baris_tetap | diterima(a), lulus_akhir_ts2(a), lulus_akhir_ts1(a), lulus_akhir_ts(a); hitung: jumlah_lulusan, rata_masa_studi | 3 (Tahun Masuk) | — |
| 23 | Tabel 23 Waktu Tunggu Mendapatkan Pekerjaan Pertama | DIK | E43 | baris_tetap | jumlah_lulusan(a), terlacak(a), wt_kurang_6(a), wt_6_12(a), wt_lebih_12(a) | 3 (Tahun Lulus) | — |
| 24.1 | Tabel 24 Kepuasan Pengguna Lulusan | DIK | E44 | TS | sangat_baik(d), baik(d), cukup(d), kurang(d), rencana_tindak_lanjut | 9 (Jenis Kemampuan) | — |
| 24.2 | Tabel 24 Referensi - Jumlah Tanggapan Pengguna Lulusan | DIK | E44 | baris_tetap | jumlah_lulusan(a), jumlah_tanggapan(a) | 3 (Tahun Lulus) | — |
| 25 | Tabel 25 Penelitian DTPS | SDM | E49 | TS | ts2(a), ts1(a), ts(a); hitung: jumlah | 3 (Sumber Pembiayaan) | — |
| 26 | Tabel 26 Publikasi Ilmiah DTPS | SDM | E50 | TS | ts2(a), ts1(a), ts(a); hitung: jumlah | 12 (Media Publikasi) | — |
| 27 | Tabel 27 Publikasi DTPS pada Jurnal Nasional Minimal Sinta 2 dan/atau Internasional Bereputasi | SDM | E51 | kolom:tanggal_terbit | *nama_dtps, *judul_artikel, nama_penulis, *peran_penulis(p), *jenis_jurnal(p), *terindeks(p), *tanggal_terbit(t) | — | nama_dtps, judul_artikel |
| 28 | Tabel 28 Karya Ilmiah DTPS yang Disitasi | SDM | E52 | TS | *nama_dtps, *judul_artikel, *jumlah_sitasi(a) | — | nama_dtps, judul_artikel |
| 29 | Tabel 29 PkM DTPS | SDM | E54 | TS | ts2(a), ts1(a), ts(a); hitung: jumlah | 3 (Sumber Pembiayaan) | — |

Singkatan tipe: (a) angka, (d) desimal, (t) tanggal, (p) pilihan, (c) centang, (u) tautan, (h) tahun; tanpa tanda = teks.
