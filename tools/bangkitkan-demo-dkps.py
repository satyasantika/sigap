#!/usr/bin/env python3
"""Pembangkit data DKPS demo: menulis data/demo-dkps.json.

Jalankan:  python3 -P tools/bangkitkan-demo-dkps.py
Lalu:      python3 -P data/verifikasi.py   (harus "SEMUA KONSISTEN")

Skrip ini sumber kebenaran angka demo; JSON adalah keluarannya dan keduanya
dikomit. Tidak ada unsur acak: menjalankan ulang menghasilkan berkas yang sama
persis. Nama kolom, pilihan, dan baris tetap dibaca dari data/dkps-tabel.json;
skrip gagal keras bila ada kolom/pilihan/baris yang tidak dikenal skema.

Cerita demo: prodi PPG berukuran sedang yang hampir Unggul tetapi tersandung
satu syarat perlu (PDS3 41,67: skor 4, syarat 3 tahun ya, syarat 5 tahun belum).

Token tahun relatif (diganti seeder lewat JendelaTs::tahunDariLabel):
  kolom `tahun`   → "{TS}", "{TS-1}", … "{TS-4}"
  kolom `tanggal` → "{TS-n}-MM-DD"  (Tabel 4.x `tahun`/`tahun_terbit` bertipe tanggal)
Kolom `hitung` tidak ditulis; centang hanya ditulis bila true.

ANGKA SASARAN (salinan spec §4 — mengubah angka di bawah mengubah Skor A ini):

| Elemen / rumus      | Data                                                              | Skor A sasaran                      |
|---------------------|-------------------------------------------------------------------|-------------------------------------|
| E7 RK               | 1.1: 20 kerja sama (2 int, 8 nas, 10 wil); 1.2: 8 (0/4/4);        | RK 7,17 → A.1 4; NI 2 → A.2 4 → 4   |
|                     | 1.3: 10 (0/3/7)                                                   |                                     |
| E12 PRESMA          | Tabel 3: 1 internasional, 4 nasional, 6 lokal                     | RI 0,0067 → 4                       |
| E13 PKIM            | 4.1: 2 mhs; 4.2: 8; 4.3: 6; 4.4: 9 (SINTA 3–5, satu internasional)| 25/150 = 0,167 → 3                  |
| E14 TKM             | Tabel 5: tiap aspek SB 60, B 30, C 8, KB 2                        | 87 % → 4                            |
| E17 PDS3/PGBLKL     | doktor D01,D02,D03,D07,D09; GB D01; LK D02,D03,D07;               | PDS3 41,67 → 4, syarat 5 th TIDAK;  |
|                     | Lektor D04,D05,D08,D10; sisanya Asisten Ahli                      | PGBLKL 66,67 → 3,90                 |
| E18 BKD             | Tabel 8: 12 baris, rata/semester 12,5–15                          | 4                                   |
| E19 RRD             | Tabel 9: 10 rekognisi                                             | 0,83 → 3,66                         |
| E20 NDTPSPK         | Tabel 10: 9 DTPS unik                                             | 75 % → 3                            |
| E21 NTENDIKPK       | Tabel 11: 2/3/6/1 = 12 tendik; Tabel 12: 4 unik                   | 33 % → 3                            |
| E22 RGP             | Tabel 13: 9 guru pamong, 3 per BS; 6 magister, 4 madya/utama,     | 4                                   |
|                     | 9 serdik                                                          |                                     |
| E25 DANA            | Tabel 14 (PS, rata 3 th): operasional 2,4 M; penelitian 96 jt;    | 16 jt/mhs → 3; 8 jt → 3; 4 jt → 3   |
|                     | PkM 48 jt                                                         | → 3,0                               |
| E32 PDIPPKM/PMKI    | Tabel 18: 16 MK, 10 inti; Tabel 19: 7 DTPS unik, 4 MK             | 58 % → 4; 0,4 → 4                   |
| E36 RPPL            | Tabel 20: 6 pembimbing, pertemuan 4–6 per tahun                   | 4                                   |
| E38 RIPK            | Tabel 21: IPK 3,62 / 3,70 / 3,75                                  | ≈ 3,69 → 4                          |
| E39–41 RMS/PMTK/PKMS| Tabel 22: diterima 130/140/150; lulus akhir TS-2 125; TS-1 135;   | RMS 1,0 tanpa skor; PMTK 0,966 → 4; |
|                     | TS 145                                                            | PKMS 0,966 → 4                      |
| E43 WTMP            | Tabel 23: lulusan 100/110/125, terlacak 60/70/85,                 | NL 335 → Prmin 0,3; PJ 0,64;        |
|                     | WT (<6: 50/60/75; 6–12: 8/8/8; >12: 2/2/2)                        | WTMP 4,0 bln → 4                    |
| E44 TKi             | 24.1: tiap aspek SB 55, B 35, C 8, K 2;                           | 3,43 → 3,43                         |
|                     | 24.2: lulusan 100/110/125, tanggapan 60/70/85                     |                                     |
| E45 RMKCPL          | Tabel 18: 8 dari 10 MK inti punya tautan asesmen CPL              | 0,8 → 4                             |
| E49 RPEN            | Tabel 25: LN 1, DN 12, PT 20                                      | RI 0,028, RN 0,333 → 3,55           |
| E50 RPUB            | Tabel 26: NA2 10, NA3 3, NA4 2, NB2 6, NB3 2, NC1 4, NC2 2, ND1 5 | RI 0,33 → 4                         |
| E51 PPDTPS          | Tabel 27: 20 artikel oleh 3 DTPS unik                             | 25 % → 4, syarat 3 th ya, 5 th tidak|
| E52 RSA             | Tabel 28: 36 artikel disitasi                                     | 3,0 → 2                             |
| E54 RPKM            | Tabel 29: LN 0, DN 6, PT 24                                       | RN 0,167 → 2,55                     |
| Tabel 15, 16, 17    | 3–5 baris sarpras masing-masing (tanpa rumus)                     | —                                   |

Pembilang bersama: NDTPS = 12 (NIDN unik Tabel 6 D01–D08 ∪ Tabel 7 D07–D12),
NM = 150 (Tabel 2 baris TS: 120 calon guru + 30 guru tertentu), NBS = 3.
"""
import io
import json
import os
import sys

AKAR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKEMA = os.path.join(AKAR, "data", "dkps-tabel.json")
KELUARAN = os.path.join(AKAR, "data", "demo-dkps.json")

URL = "https://ppg.example.org"


def tgl(ts, bulan, hari):
    """Token tanggal: tgl(-1, 5, 20) → "{TS-1}-05-20"; tgl(0, …) → "{TS}-…"."""
    return ("{TS}" if ts == 0 else "{TS%d}" % ts) + "-%02d-%02d" % (bulan, hari)


def thn(ts):
    """Token tahun: thn(-2) → "{TS-2}"."""
    return "{TS}" if ts == 0 else "{TS%d}" % ts


# ── Dosen tetap (DTPS) ──────────────────────────────────────────────────
# (id, nama, NIDN, magister, doktor | None, jabatan, bidang keahlian)
DOSEN = [
    ("D01", "Prof. Dr. Hendarsih Wiradinata, M.Pd.", "0410126501", "Pendidikan Dasar", "Pendidikan Dasar", "Guru Besar", "Kurikulum dan Pembelajaran"),
    ("D02", "Dr. Ani Lestari, M.Pd.", "0415037202", "Pendidikan Matematika", "Pendidikan Matematika", "Lektor Kepala", "Asesmen Pembelajaran"),
    ("D03", "Dr. Rudi Kartawijaya, M.Si.", "0402087403", "Psikologi Pendidikan", "Psikologi Pendidikan", "Lektor Kepala", "Psikologi Perkembangan"),
    ("D04", "Siti Nurhalimah, M.Pd.", "0421058104", "Teknologi Pendidikan", None, "Lektor", "Teknologi Pembelajaran"),
    ("D05", "Agus Permana, M.Pd.", "0408118205", "Administrasi Pendidikan", None, "Lektor", "Manajemen Pendidikan"),
    ("D06", "Dewi Kurniasih, M.Pd.", "0419098806", "Bimbingan dan Konseling", None, "Asisten Ahli", "Bimbingan dan Konseling"),
    ("D07", "Dr. Yayan Sopyan Hidayat, M.Pd.", "0405067507", "Pendidikan Dasar", "Pendidikan Dasar", "Lektor Kepala", "Pembelajaran IPA SD"),
    ("D08", "Rina Marlina, M.Pd.", "0427028308", "Pendidikan Dasar", None, "Lektor", "Literasi Awal"),
    ("D09", "Dr. Taufik Rahmansyah, M.Pd.", "0413108609", "Pendidikan Matematika", "Pendidikan Matematika", "Asisten Ahli", "Pemecahan Masalah Matematis"),
    ("D10", "Euis Komalasari, M.Pd.", "0430048410", "Pendidikan Matematika", None, "Lektor", "Etnomatematika"),
    ("D11", "Dadan Ramdani, M.Pd.", "0411078911", "Pendidikan Bahasa Indonesia", None, "Asisten Ahli", "Keterampilan Menulis"),
    ("D12", "Neng Sri Wulandari, M.Pd.", "0424129012", "Pendidikan Bahasa Indonesia", None, "Asisten Ahli", "Sastra Anak"),
]
NAMA = {d[0]: d[1] for d in DOSEN}
BS_PGSD, BS_MAT, BS_BIN = "PGSD", "Pendidikan Matematika", "Pendidikan Bahasa Indonesia"
BIDANG_STUDI = {"D07": BS_PGSD, "D08": BS_PGSD, "D09": BS_MAT, "D10": BS_MAT, "D11": BS_BIN, "D12": BS_BIN}

# ── Mata kuliah (Tabel 18): 16 MK, 10 inti, 8 inti bertautan asesmen CPL ──
# (kode, nama, semester, inti, sks kuliah, sks seminar, sks praktikum)
MK = [
    ("PPG101", "Filosofi Pendidikan Indonesia", 1, True, 2, 0, 0),
    ("PPG102", "Pemahaman tentang Peserta Didik dan Pembelajarannya", 1, True, 2, 0, 0),
    ("PPG103", "Prinsip Pengajaran dan Asesmen I", 1, True, 2, 0, 1),
    ("PPG104", "Pendidikan Inklusif", 1, True, 2, 0, 0),
    ("PPG105", "Praktik Pengalaman Lapangan I", 1, True, 0, 0, 4),
    ("PPG201", "Prinsip Pengajaran dan Asesmen II", 2, True, 2, 0, 1),
    ("PPG202", "Pengembangan Perangkat Pembelajaran Bidang Studi", 2, True, 2, 0, 1),
    ("PPG203", "Praktik Pengalaman Lapangan II", 2, True, 0, 0, 6),
    ("PPG204", "Seminar Pendidikan Profesi Guru", 2, True, 0, 2, 0),
    ("PPG205", "Proyek Kepemimpinan", 2, True, 1, 0, 1),
    ("PPG106", "Pembelajaran Sosial Emosional", 1, False, 2, 0, 0),
    ("PPG107", "Literasi Digital dalam Pembelajaran", 1, False, 1, 0, 1),
    ("PPG108", "Etika Profesi Guru", 1, False, 2, 0, 0),
    ("PPG206", "Bimbingan dan Konseling di Sekolah", 2, False, 2, 0, 0),
    ("PPG207", "Teknologi Baru dalam Pembelajaran", 2, False, 1, 0, 1),
    ("PPG208", "Kepemimpinan Sekolah", 2, False, 2, 0, 0),
]
MK_CPL = {"PPG101", "PPG102", "PPG103", "PPG104", "PPG105", "PPG201", "PPG202", "PPG203"}
NAMA_MK = {m[0]: m[1] for m in MK}

# Nama fiktif mahasiswa, disusun dari dua daftar tanpa acak.
DEPAN = ["Aulia", "Bayu", "Citra", "Dimas", "Elsa", "Fajar", "Gita", "Hanif", "Intan", "Jaka",
         "Kirana", "Luthfi", "Mega", "Nanda", "Oki", "Putri", "Raka", "Salma", "Tegar", "Ulfa",
         "Vina", "Wahyu", "Yuni", "Zaki", "Anisa"]
BELAKANG = ["Pratama", "Maharani", "Saputra", "Anggraeni", "Nugraha", "Permatasari", "Ramadhan",
            "Fitriani", "Kusuma", "Wijayanti", "Firmansyah", "Rahayu", "Gunawan"]


def mahasiswa(i):
    return "2401%06d" % (101 + i), DEPAN[i % len(DEPAN)] + " " + BELAKANG[(i * 5) % len(BELAKANG)]


# ── Pembangun per lembar ────────────────────────────────────────────────

def tabel_1():
    """1.1 pendidikan 20 (2/8/10); 1.2 penelitian 8 (0/4/4); 1.3 PkM 10 (0/3/7)."""
    mitra = {
        "internasional": ["Universiti Pendidikan Seri Mahkota (Malaysia)", "Nusantara Teachers College (Brunei)"],
        "nasional": ["Universitas Cahaya Bangsa", "Institut Keguruan Lembah Hijau", "Universitas Pelita Timur",
                     "Universitas Samudra Raya", "Balai Guru Penggerak Wilayah Tengah", "Yayasan Literasi Anak Negeri",
                     "Universitas Tanjung Harapan", "Pusat Kurikulum Mandiri"],
        "wilayah_lokal": ["SD Negeri 1 Sukamaju", "SD Negeri 4 Cibeureum", "SMP Negeri 2 Mekarjaya", "SMP Negeri 5 Tawangsari",
                          "SMA Negeri 3 Sindangkasih", "Dinas Pendidikan Kota Cemerlang", "MGMP Matematika Kota Cemerlang",
                          "KKG Gugus 7 Kecamatan Indihiang Baru", "SD Islam Terpadu Al-Hikmah Sukasari", "SMP Plus Bina Insani"],
    }
    rencana = {
        "1.1": ("pendidikan", {"internasional": 2, "nasional": 8, "wilayah_lokal": 10},
                ["Program pertukaran dosen pembimbing PPL", "Penempatan mahasiswa PPL", "Pengembangan kurikulum PPG bersama",
                 "Kuliah tamu praktisi pendidikan", "Lokakarya perangkat ajar"]),
        "1.2": ("penelitian", {"internasional": 0, "nasional": 4, "wilayah_lokal": 4},
                ["Penelitian tindakan kelas kolaboratif", "Riset asesmen diagnostik", "Kajian implementasi Kurikulum Merdeka"]),
        "1.3": ("pkm", {"internasional": 0, "nasional": 3, "wilayah_lokal": 7},
                ["Pendampingan guru menyusun modul ajar", "Pelatihan literasi numerasi", "Sekolah ramah anak"]),
    }
    hasil = {}
    for kode, (bidang, cacah, judul) in rencana.items():
        baris, n = [], 0
        for tingkat in ("internasional", "nasional", "wilayah_lokal"):
            for j in range(cacah[tingkat]):
                # tanggal awal tersebar di TS-2..TS
                awal = tgl(-2 + (n % 3), 1 + (n * 2) % 11, 1 + (n * 3) % 27)
                b = {
                    "lembaga_mitra": mitra[tingkat][(j + {"1.1": 0, "1.2": 3, "1.3": 5}[kode]) % len(mitra[tingkat])],
                    tingkat: True,
                    "judul_kegiatan": "%s %d" % (judul[n % len(judul)], n + 1),
                    "manfaat": "Penguatan %s calon guru dan mitra" % {"pendidikan": "kompetensi pedagogik",
                                                                       "penelitian": "budaya riset",
                                                                       "pkm": "layanan kepada sekolah"}[bidang],
                    "tanggal_awal": awal,
                    "tanggal_akhir": tgl(0, 12, 31),
                    "bukti_kerjasama": "%s/MoA/%s/%03d" % (URL, kode, n + 1),
                }
                baris.append(b)
                n += 1
        hasil[kode] = baris
    return hasil


def tabel_2():
    return [
        {"_baris": "TS-2", "kuota": 150, "lulus_seleksi_calon_guru": 110, "lulus_seleksi_guru_tertentu": 30,
         "lapor_diri_calon_guru": 105, "lapor_diri_guru_tertentu": 28, "aktif_calon_guru": 102,
         "aktif_guru_tertentu": 28, "jumlah_pddikti_ts": 130},
        {"_baris": "TS-1", "kuota": 160, "lulus_seleksi_calon_guru": 118, "lulus_seleksi_guru_tertentu": 32,
         "lapor_diri_calon_guru": 114, "lapor_diri_guru_tertentu": 29, "aktif_calon_guru": 111,
         "aktif_guru_tertentu": 29, "jumlah_pddikti_ts": 140},
        {"_baris": "TS", "kuota": 170, "lulus_seleksi_calon_guru": 128, "lulus_seleksi_guru_tertentu": 34,
         "lapor_diri_calon_guru": 123, "lapor_diri_guru_tertentu": 31, "aktif_calon_guru": 120,
         "aktif_guru_tertentu": 30, "jumlah_pddikti_ts": 150},
    ]


def tabel_3():
    """1 internasional, 4 nasional, 6 lokal."""
    data = [
        ("internasional", "Olimpiade Inovasi Pembelajaran ASEAN", "Akademik", "Medali Perak"),
        ("nasional", "Lomba Inovasi Pembelajaran Nasional", "Akademik", "Juara 2"),
        ("nasional", "Kompetisi Media Ajar Digital Nasional", "Akademik", "Juara 3"),
        ("nasional", "Festival Seni Guru Muda Nasional", "Non-akademik", "Penyaji Terbaik"),
        ("nasional", "Lomba Esai Pendidikan Nasional", "Akademik", "Juara Harapan 1"),
        ("wilayah_lokal", "Lomba Karya Tulis Pendidikan Provinsi", "Akademik", "Juara 1"),
        ("wilayah_lokal", "Pekan Olahraga Mahasiswa Provinsi (Bulu Tangkis)", "Non-akademik", "Juara 2"),
        ("wilayah_lokal", "Lomba Bercerita Dongeng Daerah", "Non-akademik", "Juara 1"),
        ("wilayah_lokal", "Kompetisi Modul Ajar Kota", "Akademik", "Juara 3"),
        ("wilayah_lokal", "Lomba Paduan Suara Antarkampus Kota", "Non-akademik", "Juara 2"),
        ("wilayah_lokal", "Lomba Video Pembelajaran Kota", "Akademik", "Juara 1"),
    ]
    return [{"nama_kegiatan": n, "jenis_prestasi": j, "tanggal_perolehan": tgl(-2 + i % 3, 3 + i % 9, 10 + i % 15),
             t: True, "prestasi_dicapai": p} for i, (t, n, j, p) in enumerate(data)]


def tabel_4():
    """PKIM: 4.1 2 mhs; 4.2 8; 4.3 6; 4.4 9 (NIM unik per lembar)."""
    i = 0

    def mhs():
        nonlocal i
        r = mahasiswa(i)
        i += 1
        return r

    t41 = []
    for k, judul in enumerate(["Alat peraga pecahan magnetik untuk SD", "Aplikasi kartu kosakata bahasa daerah"]):
        nim, nama = mhs()
        t41.append({"nim": nim, "nama_mahasiswa": nama, "judul_karya": judul, "tahun": tgl(-1 + k, 6, 15),
                    "no_paten": "S00%d02%05d" % (2 + k, 1201 + k)})
    t42 = []
    for k in range(8):
        nim, nama = mhs()
        t42.append({"nim": nim, "nama_mahasiswa": nama,
                    "judul_karya": ["Antologi Cerita Anak Nusantara", "Buku Saku Asesmen Formatif", "Bunga Rampai Praktik Baik PPL",
                                    "Modul Numerasi Kontekstual", "Panduan Kelas Inklusif", "Kumpulan Puisi Guru Muda",
                                    "Buku Ajar Literasi Sains SD", "Book Chapter: Refleksi Pembelajaran Berdiferensiasi"][k],
                    "tahun": tgl(-4 + k % 5, 2 + k, 10 + k), "keterangan_isbn": "ISBN 978-623-0000-%02d-%d" % (10 + k, k % 10)})
    t43 = []
    for k in range(6):
        nim, nama = mhs()
        t43.append({"nim": nim, "nama_mahasiswa": nama,
                    "judul_karya": ["Pagelaran Tari Kreasi Ronggeng Guru", "Pameran Lukis Ruang Kelas Impian",
                                    "Pentas Drama Musikal Anak Negeri", "Pameran Fotografi Sekolah Pinggiran",
                                    "Konser Angklung Kolaboratif", "Pameran Kriya Media Ajar Daur Ulang"][k],
                    "tahun": tgl(-3 + k % 4, 4 + k, 5 + k), "bukti": "%s/karya-seni/%d" % (URL, k + 1)})
    peringkat = ["Jurnal Nasional (SINTA 3)", "Jurnal Nasional (SINTA 4)", "Jurnal Nasional (SINTA 5)",
                 "Jurnal Nasional (SINTA 4)", "Jurnal Nasional (SINTA 3)", "Jurnal Nasional (SINTA 5)",
                 "Jurnal Nasional (SINTA 4)", "Jurnal Internasional", "Jurnal Nasional (SINTA 3)"]
    t44 = []
    for k in range(9):
        nim, nama = mhs()
        t44.append({"nim": nim, "nama_mahasiswa": nama,
                    "judul_artikel": ["Penerapan asesmen diagnostik pada pembelajaran pecahan",
                                      "Media cerita bergambar untuk literasi awal",
                                      "Pembelajaran berdiferensiasi di kelas rangkap",
                                      "Refleksi calon guru tentang PPL daring",
                                      "Etnomatematika permainan tradisional",
                                      "Strategi umpan balik dalam menulis teks deskripsi",
                                      "Kesiapan calon guru menghadapi kelas inklusif",
                                      "Pre-service teachers' noticing in mathematics lessons",
                                      "Penggunaan kuis daring untuk asesmen formatif"][k],
                    "keterangan_jurnal": "Jurnal Pendidikan Contoh Vol. %d No. %d" % (5 + k % 3, 1 + k % 2),
                    "peringkat_jurnal": peringkat[k], "tahun_terbit": tgl(-4 + k % 5, 3 + k, 12 + k),
                    "tautan_artikel": "%s/artikel-mahasiswa/%d" % (URL, k + 1)})
    return {"4.1": t41, "4.2": t42, "4.3": t43, "4.4": t44}


def tabel_5():
    aspek = ["reliability", "responsiveness", "assurance", "empathy", "tangible"]
    return [{"_baris": a, "sangat_baik": 60, "baik": 30, "cukup": 8, "kurang_baik": 2,
             "rencana_tindak_lanjut": "Pertahankan; tindak lanjuti 2% responden kurang puas lewat forum mahasiswa"}
            for a in aspek]


def baris_dosen(d, nama_kunci):
    id_, nama, nidn, mag, dok, jab, keahlian = d
    b = {nama_kunci: nama, "nidn_nidk": nidn, "nuptk": "77%s%04d" % (nidn[4:8], int(id_[1:]) * 7),
         "magister": mag}
    if dok:
        b["doktor"] = dok
    b.update({"bidang_keahlian": keahlian, "jabatan_akademik": jab,
              "sertifikat_pendidik": "SD-%s" % nidn,
              "mk_diampu_ps": "; ".join(NAMA_MK[MK[(int(id_[1:]) + j) % 10][0]] for j in range(2))})
    return b


def tabel_6():
    return [baris_dosen(d, "nama_dtps") for d in DOSEN[:8]]


def tabel_7():
    hasil = []
    for d in DOSEN[6:]:
        b = {"nama_bidang_studi": BIDANG_STUDI[d[0]]}
        b.update(baris_dosen(d, "nama_dosen"))
        b["kesesuaian_bidang"] = True
        b["prodi_homebase"] = "S1 " + ("PGSD" if BIDANG_STUDI[d[0]] == BS_PGSD else BIDANG_STUDI[d[0]])
        hasil.append(b)
    return hasil


def tabel_8():
    hasil = []
    for k, d in enumerate(DOSEN):
        ps = 10 + k % 3          # 10..12
        lain = 2 + k % 2         # 2..3
        pen = 4 + (k % 4) * 0.5  # 4..5,5
        pkm = 3
        # jumlah 25..29 sks → rata per semester 12,5..14,5; sisa menjadi tugas tambahan
        tugas = 25 + (k * 2) % 6 - (ps + lain + pen + pkm)
        hasil.append({"nama_dtps": d[1], "sks_ps_diakreditasi": ps, "sks_ps_lain_dalam_pt": lain,
                      "sks_ps_lain_luar_pt": 0, "sks_penelitian": pen, "sks_pkm": pkm,
                      "sks_tugas_tambahan": tugas, "bukti": "%s/bkd/%s" % (URL, d[0])})
    return hasil


def tabel_9():
    data = [
        ("D01", "Keynote speaker Konferensi Internasional Pendidikan Guru", "Keynote speaker/invited speaker", -1, "Internasional"),
        ("D01", "Staf ahli penyusunan standar PPG", "Staf ahli/narasumber", -2, "Nasional"),
        ("D02", "Mitra bestari Jurnal Pendidikan Matematika Nusantara", "Editor atau mitra bestari", 0, "Nasional"),
        ("D02", "Visiting scholar di universitas mitra Malaysia", "Visiting lecturer atau visiting scholar", -1, "Internasional"),
        ("D03", "Narasumber pelatihan guru BK provinsi", "Staf ahli/narasumber", -2, "Lokal/Wilayah"),
        ("D07", "Penghargaan dosen berprestasi tingkat universitas", "Penghargaan atas prestasi dan kinerja", -1, "Lokal/Wilayah"),
        ("D07", "Invited speaker seminar nasional pendidikan dasar", "Keynote speaker/invited speaker", 0, "Nasional"),
        ("D09", "Editor jurnal pemecahan masalah matematis", "Editor atau mitra bestari", 0, "Nasional"),
        ("D04", "Narasumber teknologi pembelajaran dinas pendidikan", "Staf ahli/narasumber", -1, "Lokal/Wilayah"),
        ("D10", "Narasumber etnomatematika pada MGMP kota", "Staf ahli/narasumber", -2, "Lokal/Wilayah"),
    ]
    keahlian = {d[0]: d[6] for d in DOSEN}
    return [{"nama_dtps": NAMA[i], "bidang_keahlian": keahlian[i], "deskripsi_rekognisi": desk,
             "jenis_rekognisi": jenis, "tahun": thn(ts), "tingkat": tingkat,
             "bukti_pendukung": "%s/rekognisi/%d" % (URL, k + 1)}
            for k, (i, desk, jenis, ts, tingkat) in enumerate(data)]


def tabel_10():
    """9 DTPS unik (D01–D09), 11 baris."""
    kegiatan = ["Pelatihan asesmen autentik", "Lokakarya pembelajaran berdiferensiasi", "Sertifikasi asesor PPG",
                "Pelatihan penulisan artikel bereputasi", "Bimtek pembimbingan PPL", "Pelatihan pembelajaran sosial emosional",
                "Kursus singkat literasi digital", "Magang dosen di sekolah mitra", "Pelatihan etnomatematika"]
    hasil = []
    urut = ["D01", "D02", "D03", "D04", "D05", "D06", "D07", "D08", "D09", "D02", "D07"]
    for k, i in enumerate(urut):
        hasil.append({"nama_dtps": NAMA[i], "deskripsi_kegiatan": "%s (angkatan %d)" % (kegiatan[k % 9], k + 1),
                      "tempat": ["Jakarta", "Bandung", "Yogyakarta", "Surabaya", "Kampus Utama"][k % 5],
                      "waktu_pelaksanaan": tgl(-2 + k % 3, 2 + k % 10, 3 + k * 2),
                      "manfaat_kegiatan": "Peningkatan kompetensi pembimbingan calon guru",
                      "bukti_kegiatan": "%s/sertifikat-dosen/%d" % (URL, k + 1)})
    return hasil


def tabel_11():
    return [
        {"_baris": "pustakawan", "s1": 1, "d3": 1, "unit_kerja": "Perpustakaan Fakultas"},
        {"_baris": "laboran_teknisi", "s1": 1, "d3": 2, "unit_kerja": "Laboratorium Microteaching"},
        {"_baris": "administrasi", "s2": 1, "s1": 4, "sma_smk": 1, "unit_kerja": "Tata Usaha Prodi PPG"},
        {"_baris": "lainnya", "sma_smk": 1, "unit_kerja": "Pengemudi dan Kurir Dokumen PPL"},
    ]


def tabel_12():
    tendik = ["Lina Herlina, A.Md.", "Asep Saepudin, S.Kom.", "Yuliani Sari, S.E.", "Iwan Setiawan, S.Pd."]
    data = [(0, "Pelatihan otomasi perpustakaan"), (1, "Pelatihan administrator LMS"),
            (2, "Pelatihan pengelolaan keuangan BLU"), (3, "Pelatihan tata kelola data PDDikti"),
            (1, "Sertifikasi teknisi jaringan dasar")]
    return [{"nama_tendik": tendik[t], "deskripsi_kegiatan": desk, "tempat": "Kampus Utama",
             "waktu_pelaksanaan": tgl(-2 + k % 3, 4 + k, 8 + k), "manfaat_kegiatan": "Peningkatan layanan tendik",
             "bukti_kegiatan": "%s/sertifikat-tendik/%d" % (URL, k + 1)} for k, (t, desk) in enumerate(data)]


def tabel_13():
    """9 guru pamong, 3 per bidang studi; 6 magister; 4 madya/utama; 9 serdik."""
    data = [
        (BS_PGSD, "Ratna Juwita, M.Pd.", "Guru Madya", "Magister/Magister Terapan", "SD Negeri 1 Sukamaju", "A (Unggul)"),
        (BS_PGSD, "Ujang Sukmana, S.Pd.", "Guru Muda", "Sarjana/Sarjana Terapan", "SD Negeri 4 Cibeureum", "A (Unggul)"),
        (BS_PGSD, "Heni Rohaeni, M.Pd.", "Guru Muda", "Magister/Magister Terapan", "SD Islam Terpadu Al-Hikmah Sukasari", "B (Baik)"),
        (BS_MAT, "Dedi Supriadi, M.Pd.", "Guru Utama", "Magister/Magister Terapan", "SMP Negeri 2 Mekarjaya", "A (Unggul)"),
        (BS_MAT, "Nurlaela, S.Pd.", "Guru Pertama", "Sarjana/Sarjana Terapan", "SMP Negeri 5 Tawangsari", "A (Unggul)"),
        (BS_MAT, "Wawan Kurniawan, M.Pd.", "Guru Madya", "Magister/Magister Terapan", "SMA Negeri 3 Sindangkasih", "A (Unggul)"),
        (BS_BIN, "Sri Mulyati, M.Pd.", "Guru Madya", "Magister/Magister Terapan", "SMP Plus Bina Insani", "B (Baik)"),
        (BS_BIN, "Tatang Sutarman, S.Pd.", "Guru Muda", "Sarjana/Sarjana Terapan", "SMA Negeri 3 Sindangkasih", "A (Unggul)"),
        (BS_BIN, "Imas Masitoh, M.Pd.", "Guru Muda", "Magister/Magister Terapan", "SMP Negeri 2 Mekarjaya", "A (Unggul)"),
    ]
    return [{"bidang_studi": bs, "nama_guru_pamong": n, "jabatan_fungsional": jf, "pendidikan_tertinggi": pt,
             "no_serdik": "1810%08d" % (20231 + k * 37), "nama_sekolah": s, "akreditasi_sekolah": a,
             "bukti_kerjasama": "%s/pks-sekolah/%d" % (URL, k + 1), "st_guru_pamong": "%s/st-gp/%d" % (URL, k + 1)}
            for k, (bs, n, jf, pt, s, a) in enumerate(data)]


def tabel_14():
    """Rata PS 3 tahun: operasional (a–e) 2.400 jt; penelitian 96 jt; PkM 48 jt. Selisih ±5% per tahun."""
    jt = 1_000_000
    rata_ps = {  # dalam juta rupiah
        "biaya_dosen": 1200, "biaya_tendik": 300, "biaya_operasional_pembelajaran": 540,
        "biaya_operasional_tidak_langsung": 240, "biaya_kemahasiswaan": 120,
        "biaya_penelitian": 96, "biaya_pkm": 48,
        "investasi_sdm": 60, "investasi_sarana": 180, "investasi_prasarana": 300,
    }
    hasil = []
    for baris, r in rata_ps.items():
        ps = [round(r * 0.95 * jt), r * jt, round(r * 1.05 * jt)]  # rata tepat r juta
        upps = [v * 4 for v in ps]
        hasil.append({"_baris": baris, "upps_ts2": upps[0], "upps_ts1": upps[1], "upps_ts": upps[2],
                      "ps_ts2": ps[0], "ps_ts1": ps[1], "ps_ts": ps[2]})
    return hasil


def tabel_15():
    data = [
        ("Laboratorium Microteaching", "Kamera perekam dan monitor", "Sangat Baik", 6, 30),
        ("Laboratorium Microteaching", "Papan interaktif", "Baik", 2, 25),
        ("Laboratorium Komputer PPG", "Komputer meja", "Baik", 40, 36),
        ("Laboratorium Matematika", "Alat peraga geometri", "Baik", 15, 12),
        ("Ruang Sumber Belajar", "Koleksi buku bacaan anak", "Sangat Baik", 850, 20),
    ]
    return [{"nama_ruang": r, "nama_alat": a, "kualitas": q, "jumlah": n, "kepemilikan": "Milik Sendiri",
             "kondisi": "Terawat", "jam_per_minggu": j} for r, a, q, n, j in data]


def tabel_16():
    data = [
        ("Ruang kuliah PPG", "Perkuliahan tatap muka dan lokakarya", 8, 560.0, "Sangat Baik"),
        ("Ruang microteaching", "Praktik mengajar terbimbing", 3, 180.0, "Baik"),
        ("Ruang dosen", "Kerja dan konsultasi dosen", 1, 120.0, "Baik"),
        ("Aula seminar", "Seminar PPG dan ujian kinerja", 1, 300.0, "Baik"),
    ]
    return [{"nama_prasarana": n, "fungsi": f, "jumlah_unit": u, "total_luas_m2": l, "kualitas": q,
             "kepemilikan": "Milik Sendiri", "kondisi": "Terawat"} for n, f, u, l, q in data]


def tabel_17():
    data = [
        ("Sistem Informasi Akademik", "Pengelolaan KRS, nilai, dan transkrip", "Terintegrasi Penuh", "Milik Sendiri"),
        ("Learning Management System", "Perkuliahan daring dan penugasan", "Terintegrasi Penuh", "Milik Sendiri"),
        ("Sistem Informasi PPL", "Penempatan dan jurnal PPL", "Terintegrasi Sebagian", "Milik Sendiri"),
        ("Perpustakaan Digital", "Akses jurnal dan e-book", "Terintegrasi Sebagian", "Sewa"),
    ]
    return [{"nama_sistem": n, "deskripsi": d, "jumlah": 1, "terintegrasi": t, "mutakhir": "Mutahir",
             "ketersediaan_panduan": True, "kepemilikan": k, "kondisi": "Terawat"} for n, d, t, k in data]


def tabel_18():
    hasil = []
    for kode, nama, smt, inti, kul, sem, prak in MK:
        b = {"semester": smt, "kode_mk": kode, "nama_mk": nama}
        if inti:
            b["mk_inti"] = True
        b.update({"sks_kuliah": kul, "sks_seminar": sem, "sks_praktikum": prak,
                  "tautan_rps": "%s/rps/%s" % (URL, kode)})
        if kode in MK_CPL:
            b["tautan_asesmen_cpl"] = "%s/asesmen-cpl/%s" % (URL, kode)
        hasil.append(b)
    return hasil


def tabel_19():
    """7 DTPS unik, 4 MK unik (nama persis Tabel 18), 8 baris."""
    data = [
        ("D01", "Model kurikulum operasional sekolah penggerak", "PPG101", "Bab/Subab dalam Buku Ajar", ["ts2"]),
        ("D02", "Asesmen diagnostik numerasi calon guru", "PPG103", "Studi Kasus", ["ts1"]),
        ("D02", "Umpan balik formatif pada praktik mengajar", "PPG103", "Tambahan Materi Perkuliahan", ["ts"]),
        ("D03", "Profil perkembangan sosial emosional siswa SD", "PPG102", "Bahan Ajar", ["ts2", "ts1"]),
        ("D04", "Pemanfaatan video refleksi dalam microteaching", "PPG202", "Tambahan Materi Perkuliahan", ["ts1"]),
        ("D07", "Pendampingan guru IPA SD dalam inkuiri terbimbing", "PPG202", "Studi Kasus", ["ts"]),
        ("D09", "Pemecahan masalah matematis berbasis konteks lokal", "PPG103", "Bahan Ajar", ["ts1", "ts"]),
        ("D10", "Etnomatematika permainan tradisional Sunda", "PPG102", "Bentuk Lain yang Relevan", ["ts2"]),
    ]
    hasil = []
    for k, (i, judul, mk, bentuk, tahun) in enumerate(data):
        b = {"nama_dtps": NAMA[i], "judul_penelitian_pkm": judul, "mata_kuliah": NAMA_MK[mk], "bentuk_integrasi": bentuk}
        for t in tahun:
            b[t] = True
        b["bukti"] = "%s/integrasi/%d" % (URL, k + 1)
        hasil.append(b)
    return hasil


def tabel_20():
    """6 pembimbing, pertemuan 4–6 per tahun."""
    data = [("D04", 24, 26, 28, 5, 5, 6), ("D05", 22, 24, 25, 4, 5, 5), ("D06", 20, 22, 24, 4, 4, 5),
            ("D08", 23, 25, 26, 5, 6, 6), ("D11", 21, 22, 24, 4, 5, 4), ("D12", 20, 21, 23, 5, 4, 5)]
    return [{"nama_dosen_pembimbing": NAMA[i], "bimbingan_ts2": b2, "bimbingan_ts1": b1, "bimbingan_ts": b0,
             "pertemuan_ts2": p2, "pertemuan_ts1": p1, "pertemuan_ts": p0, "lama_ppl_bulan": 4.0}
            for i, b2, b1, b0, p2, p1, p0 in data]


def tabel_21():
    return [
        {"_baris": "TS-2", "jumlah_lulusan": 125, "ipk_min": 3.21, "ipk_rata": 3.62, "ipk_maks": 3.96},
        {"_baris": "TS-1", "jumlah_lulusan": 135, "ipk_min": 3.30, "ipk_rata": 3.70, "ipk_maks": 3.98},
        {"_baris": "TS", "jumlah_lulusan": 145, "ipk_min": 3.34, "ipk_rata": 3.75, "ipk_maks": 4.00},
    ]


def tabel_22():
    """Hanya sel diagonal: angkatan baris itu lulus pada akhir tahunnya sendiri (PPG 1 tahun)."""
    return [
        {"_baris": "TS-2", "diterima": 130, "lulus_akhir_ts2": 125},
        {"_baris": "TS-1", "diterima": 140, "lulus_akhir_ts1": 135},
        {"_baris": "TS", "diterima": 150, "lulus_akhir_ts": 145},
    ]


def tabel_23():
    return [
        {"_baris": "TS-4", "jumlah_lulusan": 100, "terlacak": 60, "wt_kurang_6": 50, "wt_6_12": 8, "wt_lebih_12": 2},
        {"_baris": "TS-3", "jumlah_lulusan": 110, "terlacak": 70, "wt_kurang_6": 60, "wt_6_12": 8, "wt_lebih_12": 2},
        {"_baris": "TS-2", "jumlah_lulusan": 125, "terlacak": 85, "wt_kurang_6": 75, "wt_6_12": 8, "wt_lebih_12": 2},
    ]


def tabel_24():
    aspek = ["etika", "keahlian_bidang_ilmu", "bahasa_asing", "teknologi_informasi", "komunikasi",
             "kerjasama_tim", "pengembangan_diri", "berpikir_kritis", "kreativitas"]
    t241 = [{"_baris": a, "sangat_baik": 55, "baik": 35, "cukup": 8, "kurang": 2,
             "rencana_tindak_lanjut": "Masukan pengguna dibahas dalam rapat tinjauan kurikulum tahunan"} for a in aspek]
    t242 = [{"_baris": "TS-4", "jumlah_lulusan": 100, "jumlah_tanggapan": 60},
            {"_baris": "TS-3", "jumlah_lulusan": 110, "jumlah_tanggapan": 70},
            {"_baris": "TS-2", "jumlah_lulusan": 125, "jumlah_tanggapan": 85}]
    return {"24.1": t241, "24.2": t242}


def tiga_tahun(baris, nilai):
    return {"_baris": baris, "ts2": nilai[0], "ts1": nilai[1], "ts": nilai[2]}


def tabel_25():
    """Penelitian: LN 1, DN 12, PT 20."""
    return [tiga_tahun("pt_mandiri", [6, 7, 7]), tiga_tahun("dalam_negeri", [4, 4, 4]),
            tiga_tahun("luar_negeri", [0, 0, 1])]


def tabel_26():
    """NA2 10, NA3 3, NA4 2, NB2 6, NB3 2, NC1 4, NC2 2, ND1 5; sisanya 0 (baris tetap ada)."""
    nilai = {"na1": [0, 0, 0], "na2": [3, 3, 4], "na3": [1, 1, 1], "na4": [0, 1, 1], "nb1": [0, 0, 0],
             "nb2": [2, 2, 2], "nb3": [0, 1, 1], "nc1": [1, 1, 2], "nc2": [0, 1, 1], "nc3": [0, 0, 0],
             "nd1": [1, 2, 2], "nd2": [0, 0, 0]}
    return [tiga_tahun(b, v) for b, v in nilai.items()]


def tabel_27():
    """20 artikel oleh tepat 3 DTPS (D01 8, D02 7, D03 5)."""
    topik = ["asesmen formatif", "pembelajaran berdiferensiasi", "literasi numerasi", "kesiapan mengajar calon guru",
             "refleksi praktik mengajar", "pendidikan inklusif", "kurikulum operasional sekolah", "efikasi diri guru",
             "umpan balik sejawat", "komunitas belajar guru"]
    pola = ["Kajian %s pada program PPG", "Model %s berbasis kelas nyata", "Analisis %s di sekolah mitra",
            "Pengaruh %s terhadap hasil PPL"]
    indeks = [("SINTA 2", "Jurnal Nasional"), ("Scopus Q3", "Jurnal Internasional"), ("SINTA 2", "Jurnal Nasional"),
              ("SINTA 1", "Jurnal Nasional"), ("Scopus Q4", "Jurnal Internasional")]
    hasil, k = [], 0
    for i, n in (("D01", 8), ("D02", 7), ("D03", 5)):
        for _ in range(n):
            ter, jenis = indeks[k % len(indeks)]
            hasil.append({"nama_dtps": NAMA[i],
                          "judul_artikel": (pola[k % 4] % topik[k % 10]).capitalize() + " (%d)" % (k + 1),
                          "nama_penulis": NAMA[i] + (", tim PPG" if k % 2 else ""),
                          "peran_penulis": "Penulis Pertama" if k % 3 else "Corresponding author",
                          "jenis_jurnal": jenis, "terindeks": ter,
                          "tanggal_terbit": tgl(-2 + k % 3, 1 + k % 12, 1 + (k * 3) % 28)})
            k += 1
    return hasil


def tabel_28():
    """36 artikel disitasi: 3 per DTPS."""
    topik = ["asesmen", "literasi", "numerasi", "inklusi", "kurikulum", "refleksi", "microteaching", "PPL daring",
             "etnomatematika", "sastra anak", "psikologi belajar", "kepemimpinan sekolah"]
    hasil = []
    for k, d in enumerate(DOSEN):
        for j in range(3):
            hasil.append({"nama_dtps": d[1],
                          "judul_artikel": "Studi %s untuk penguatan profesi guru, seri %d" % (topik[(k + j * 5) % 12], j + 1),
                          "jumlah_sitasi": 3 + (k * 7 + j * 4) % 25})
    return hasil


def tabel_29():
    """PkM: LN 0, DN 6, PT 24."""
    return [tiga_tahun("pt_mandiri", [8, 8, 8]), tiga_tahun("dalam_negeri", [2, 2, 2]),
            tiga_tahun("luar_negeri", [0, 0, 0])]


# ── Perakitan dan pemeriksaan terhadap skema ────────────────────────────

def rakit():
    isi = {}
    isi.update(tabel_1())
    isi["2"] = tabel_2()
    isi["3"] = tabel_3()
    isi.update(tabel_4())
    isi["5"] = tabel_5()
    isi["6"] = tabel_6()
    isi["7"] = tabel_7()
    isi["8"] = tabel_8()
    isi["9"] = tabel_9()
    isi["10"] = tabel_10()
    isi["11"] = tabel_11()
    isi["12"] = tabel_12()
    isi["13"] = tabel_13()
    isi["14"] = tabel_14()
    isi["15"] = tabel_15()
    isi["16"] = tabel_16()
    isi["17"] = tabel_17()
    isi["18"] = tabel_18()
    isi["19"] = tabel_19()
    isi["20"] = tabel_20()
    isi["21"] = tabel_21()
    isi["22"] = tabel_22()
    isi["23"] = tabel_23()
    isi.update(tabel_24())
    isi["25"] = tabel_25()
    isi["26"] = tabel_26()
    isi["27"] = tabel_27()
    isi["28"] = tabel_28()
    isi["29"] = tabel_29()
    return isi


def main():
    skema = json.load(io.open(SKEMA, encoding="utf-8"))
    lembar = [l for t in skema for l in t["lembar"]]
    isi = rakit()

    galat = []
    if set(isi) != {l["kode"] for l in lembar}:
        galat.append("lembar tidak cocok: %s" % sorted(set(isi) ^ {l["kode"] for l in lembar}))
    for l in lembar:
        kolom = {k["kunci"]: k for k in l["kolom"]}
        tetap = [b["kunci"] for b in l.get("baris_tetap", {}).get("baris", [])]
        for b in isi.get(l["kode"], []):
            if tetap and b.get("_baris") not in tetap:
                galat.append("%s: _baris %r asing" % (l["kode"], b.get("_baris")))
            for k, v in b.items():
                if k == "_baris":
                    continue
                if k not in kolom or kolom[k]["tipe"] == "hitung":
                    galat.append("%s: kolom %s tidak boleh ditulis" % (l["kode"], k))
                elif kolom[k]["tipe"] == "pilihan" and v not in kolom[k]["pilihan"]:
                    galat.append("%s: %s=%r bukan pilihan" % (l["kode"], k, v))
            for k in kolom.values():
                if k.get("wajib") and b.get(k["kunci"]) in (None, ""):
                    galat.append("%s: kolom wajib %s kosong" % (l["kode"], k["kunci"]))
    if galat:
        print("\n".join(galat), file=sys.stderr)
        sys.exit(1)

    # Urut lembar mengikuti dkps-tabel.json supaya diff stabil.
    keluaran = {l["kode"]: {"baris": isi[l["kode"]]} for l in lembar}
    with io.open(KELUARAN, "w", encoding="utf-8") as f:
        json.dump(keluaran, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print("Ditulis %s: %d lembar, %d baris." % (os.path.relpath(KELUARAN, AKAR), len(keluaran),
                                               sum(len(v["baris"]) for v in keluaran.values())))


if __name__ == "__main__":
    main()
