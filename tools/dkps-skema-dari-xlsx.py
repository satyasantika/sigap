#!/usr/bin/env python3
"""Bangkitkan data/dkps-tabel.json (v3.0) dari berkas resmi "DKPS PPG.xlsx".

Pemakaian: python3 -P tools/dkps-skema-dari-xlsx.py "/path/DKPS PPG.xlsx" > data/dkps-tabel.json

Label kolom, label kelompok kepala, label baris tetap, dan daftar pilihan
DIBACA dari lembar kerja — tidak diketik ulang. Yang ditetapkan di skrip ini
hanya: kunci kolom (nama mesin), tipe isian, kolom hitung, aturan, dan
pemetaan tabel -> pokja/elemen (diturunkan dari rujukan "(Tabel N)" pada
Kertas Kerja AK Kosong PPG).
"""
import json, re, sys, warnings
from openpyxl import load_workbook
from openpyxl.utils import get_column_letter, column_index_from_string as ci

warnings.filterwarnings("ignore")

JENDELA = {
    "1": "TS-2 s.d. TS", "2": "TS-2 s.d. TS", "3": "TS-2 s.d. TS", "4": "5 tahun terakhir",
    "5": "saat TS", "6": "saat TS", "7": "saat TS", "8": "saat TS", "9": "TS-2 s.d. TS",
    "10": "TS-2 s.d. TS", "11": "TS-2 s.d. TS", "12": "TS-2 s.d. TS", "13": "TS-2 s.d. TS",
    "14": "TS-2 s.d. TS", "15": "TS-2 s.d. TS", "16": "TS-2 s.d. TS", "17": "TS-2 s.d. TS",
    "18": "saat TS", "19": "TS-2 s.d. TS", "20": "TS-2 s.d. TS", "21": "TS-2 s.d. TS",
    "22": "TS-2 s.d. TS", "23": "TS-4 s.d. TS-2", "24": "TS-4 s.d. TS-2", "25": "TS-2 s.d. TS",
    "26": "TS-2 s.d. TS", "27": "TS-2 s.d. TS", "28": "TS-2 s.d. TS", "29": "TS-2 s.d. TS",
}
# Pokja pemilik dan elemen yang membaca tabel itu (rujukan "(Tabel N)" di AK Kosong PPG).
PEMILIK = {
    "1": ("POKJA-MUTU", [7]), "2": ("POKJA-MAWA", [9, 12, 13, 25]), "3": ("POKJA-MAWA", [12]),
    "4": ("POKJA-MAWA", [13]), "5": ("POKJA-MAWA", [14]), "6": ("POKJA-SDM", [17]),
    "7": ("POKJA-SDM", [17, 19, 20]), "8": ("POKJA-SDM", [18]), "9": ("POKJA-SDM", [19]),
    "10": ("POKJA-SDM", [20]), "11": ("POKJA-SDM", [21]), "12": ("POKJA-SDM", [21]),
    "13": ("POKJA-SDM", [22]), "14": ("POKJA-SARPRAS", [25]), "15": ("POKJA-SARPRAS", [26]),
    "16": ("POKJA-SARPRAS", [26]), "17": ("POKJA-SARPRAS", [27]), "18": ("POKJA-DIK", [30, 45]),
    "19": ("POKJA-DIK", [32]), "20": ("POKJA-DIK", [36]), "21": ("POKJA-DIK", [38]),
    "22": ("POKJA-DIK", [39, 40, 41]), "23": ("POKJA-DIK", [43]), "24": ("POKJA-DIK", [44]),
    "25": ("POKJA-SDM", [49]), "26": ("POKJA-SDM", [50]), "27": ("POKJA-SDM", [51]),
    "28": ("POKJA-SDM", [52]), "29": ("POKJA-SDM", [54]),
}
NAMA_TABEL = {
    "1": "Kerjasama Tridharma Perguruan Tinggi", "2": "Mahasiswa PPG",
    "3": "Prestasi Akademik dan Non-akademik Mahasiswa",
    "4": "Karya Inovatif Mahasiswa dan/atau Publikasi Ilmiah", "5": "Kepuasan Mahasiswa",
    "6": "Dosen Pengelola Program Studi PPG", "7": "Dosen Bidang Studi dan Dosen Pengampu",
    "8": "Beban Kerja DTPS", "9": "Pengakuan/Rekognisi Kepakaran/Prestasi DTPS",
    "10": "Pengembangan Kompetensi DTPS", "11": "Tenaga Kependidikan",
    "12": "Pengembangan Kompetensi Tenaga Kependidikan", "13": "Guru Pamong",
    "14": "Penggunaan Dana", "15": "Sarana Laboratorium dan Pembelajaran",
    "16": "Prasarana Pendidikan", "17": "Teknologi Informasi dan Komunikasi", "18": "Kurikulum",
    "19": "Integrasi Penelitian dan PkM dalam Pembelajaran",
    "20": "Pembimbingan Praktik Pengalaman Lapangan", "21": "IPK Lulusan", "22": "Masa Studi Lulusan",
    "23": "Waktu Tunggu Mendapatkan Pekerjaan Pertama", "24": "Kepuasan Pengguna Lulusan",
    "25": "Penelitian DTPS", "26": "Publikasi Ilmiah DTPS",
    "27": "Publikasi DTPS pada Jurnal Nasional Minimal Sinta 2 dan/atau Internasional Bereputasi",
    "28": "Karya Ilmiah DTPS yang Disitasi", "29": "PkM DTPS",
}

# K(kolom_excel, kunci, tipe, **opsi). Tipe: teks angka desimal tanggal tahun pilihan centang tautan hitung
def K(huruf, kunci, tipe, **o):
    d = {"huruf": huruf, "kunci": kunci, "tipe": tipe}
    d.update(o); return d

TINGKAT3 = [K("C", "wilayah_lokal", "centang"), K("D", "nasional", "centang"), K("E", "internasional", "centang")]
KERJASAMA = [K("B", "lembaga_mitra", "teks", wajib=True)] + TINGKAT3 + [
    K("F", "judul_kegiatan", "teks", wajib=True), K("G", "manfaat", "teks"),
    K("H", "tanggal_awal", "tanggal", wajib=True), K("I", "tanggal_akhir", "tanggal"),
    K("J", "durasi_tahun", "hitung", rumus={"jenis": "selisih_tahun", "dari": "tanggal_awal", "sampai": "tanggal_akhir"}),
    K("K", "bukti_kerjasama", "teks")]
ATURAN_TINGKAT = [{"jenis": "tepat_satu", "kolom": ["wilayah_lokal", "nasional", "internasional"]}]
PENGEMBANGAN = lambda nama: [K("B", nama, "teks", wajib=True), K("C", "deskripsi_kegiatan", "teks", wajib=True),
    K("D", "tempat", "teks"), K("E", "waktu_pelaksanaan", "tanggal", wajib=True), K("F", "manfaat_kegiatan", "teks"),
    K("G", "bukti_kegiatan", "teks")]
SUMBER_DANA = lambda: [K("C", "ts2", "angka"), K("D", "ts1", "angka"), K("E", "ts", "angka"),
    K("F", "jumlah", "hitung", rumus={"jenis": "jumlah", "kolom": ["ts2", "ts1", "ts"]})]

# Konfigurasi tiap lembar. kepala=(baris_awal, baris_akhir) kepala tabel; nomor=baris "1 2 3 ..."; data=baris data pertama.
LEMBAR = [
 dict(sheet="1.1", kode="1.1", tabel="1", kepala=(6,7), nomor=8, data=9, tahun_dari="TS",
      kunci=["lembaga_mitra","judul_kegiatan"], kolom=KERJASAMA, aturan=ATURAN_TINGKAT),
 dict(sheet="1.2", kode="1.2", tabel="1", kepala=(6,7), nomor=8, data=9, tahun_dari="TS",
      kunci=["lembaga_mitra","judul_kegiatan"], kolom=KERJASAMA, aturan=ATURAN_TINGKAT),
 dict(sheet="1.3", kode="1.3", tabel="1", kepala=(6,7), nomor=8, data=9, tahun_dari="TS",
      kunci=["lembaga_mitra","judul_kegiatan"], kolom=KERJASAMA, aturan=ATURAN_TINGKAT),
 dict(sheet="2", kode="2", tabel="2", kepala=(3,4), nomor=5, data=6, tahun_dari="baris_tetap",
      baris_tetap=("A", 6, 8), footer="jumlah",
      kolom=[K("B","kuota","angka"), K("C","lulus_seleksi_calon_guru","angka"), K("D","lulus_seleksi_guru_tertentu","angka"),
             K("E","lapor_diri_calon_guru","angka"), K("F","lapor_diri_guru_tertentu","angka"),
             K("G","aktif_calon_guru","angka"), K("H","aktif_guru_tertentu","angka"), K("I","jumlah_pddikti_ts","angka")]),
 dict(sheet="3", kode="3", tabel="3", kepala=(3,4), nomor=5, data=6, tahun_dari="kolom:tanggal_perolehan",
      kunci=["nama_kegiatan","prestasi_dicapai"], aturan=ATURAN_TINGKAT,
      kolom=[K("B","nama_kegiatan","teks",wajib=True), K("C","jenis_prestasi","pilihan",pilihan=["Akademik","Non-akademik"]),
             K("D","tanggal_perolehan","tanggal",wajib=True), K("E","wilayah_lokal","centang"), K("F","nasional","centang"),
             K("G","internasional","centang"), K("H","prestasi_dicapai","teks",wajib=True)]),
 dict(sheet="4.1", kode="4.1", tabel="4", kepala=(4,4), nomor=5, data=6, tahun_dari="kolom:tahun", kunci=["nim","judul_karya"],
      kolom=[K("B","nim","teks",wajib=True), K("C","nama_mahasiswa","teks",wajib=True), K("D","judul_karya","teks",wajib=True),
             K("E","tahun","tanggal",wajib=True), K("F","no_paten","teks")]),
 dict(sheet="4.2", kode="4.2", tabel="4", kepala=(4,4), nomor=5, data=6, tahun_dari="kolom:tahun", kunci=["nim","judul_karya"],
      kolom=[K("B","nim","teks",wajib=True), K("C","nama_mahasiswa","teks",wajib=True), K("D","judul_karya","teks",wajib=True),
             K("E","tahun","tanggal",wajib=True), K("F","keterangan_isbn","teks")]),
 dict(sheet="4.3", kode="4.3", tabel="4", kepala=(4,4), nomor=5, data=6, tahun_dari="kolom:tahun", kunci=["nim","judul_karya"],
      kolom=[K("B","nim","teks",wajib=True), K("C","nama_mahasiswa","teks",wajib=True), K("D","judul_karya","teks",wajib=True),
             K("E","tahun","tanggal",wajib=True), K("F","bukti","teks")]),
 dict(sheet="4.4", kode="4.4", tabel="4", kepala=(11,11), nomor=12, data=13, tahun_dari="kolom:tahun_terbit", kunci=["nim","judul_artikel"],
      kolom=[K("B","nim","teks",wajib=True), K("C","nama_mahasiswa","teks",wajib=True), K("D","judul_artikel","teks",wajib=True),
             K("E","keterangan_jurnal","teks"), K("F","peringkat_jurnal","pilihan",pilihan_dari="E3:E9"),
             K("G","tahun_terbit","tanggal",wajib=True), K("H","tautan_artikel","tautan")]),
 dict(sheet="5", kode="5", tabel="5", kepala=(3,4), nomor=5, data=6, tahun_dari="TS", baris_tetap=("B", 6, 10), footer="rata",
      aturan=[{"jenis": "jumlah_persen_100", "kolom": ["sangat_baik","baik","cukup","kurang_baik"]}],
      kolom=[K("C","sangat_baik","desimal"), K("D","baik","desimal"), K("E","cukup","desimal"), K("F","kurang_baik","desimal"),
             K("G","rencana_tindak_lanjut","teks")]),
 dict(sheet="6", kode="6", tabel="6", kepala=(9,10), nomor=11, data=12, tahun_dari="TS", kunci=["nidn_nidk"],
      kolom=[K("B","nama_dtps","teks",wajib=True), K("C","nidn_nidk","teks",wajib=True), K("D","nuptk","teks"),
             K("E","magister","teks"), K("F","doktor","teks"), K("G","bidang_keahlian","teks"),
             K("H","jabatan_akademik","pilihan",pilihan_dari="D3:D7",wajib=True), K("I","sertifikat_pendidik","teks"),
             K("J","mk_diampu_ps","teks"), K("K","mk_diampu_ps_lain","teks")]),
 dict(sheet="7", kode="7", tabel="7", kepala=(9,10), nomor=11, data=12, tahun_dari="TS", kunci=["nama_bidang_studi","nidn_nidk"],
      kolom=[K("B","nama_bidang_studi","teks",wajib=True), K("C","nama_dosen","teks",wajib=True), K("D","nidn_nidk","teks",wajib=True),
             K("E","nuptk","teks"), K("F","magister","teks"), K("G","doktor","teks"), K("H","bidang_keahlian","teks"),
             K("I","jabatan_akademik","pilihan",pilihan_dari="E3:E7",wajib=True), K("J","sertifikat_pendidik","teks"),
             K("K","mk_diampu_ps","teks"), K("L","kesesuaian_bidang","centang"), K("M","mk_diampu_ps_lain","teks"),
             K("N","prodi_homebase","teks")]),
 dict(sheet="8", kode="8", tabel="8", kepala=(5,7), nomor=8, data=9, tahun_dari="TS", kunci=["nama_dtps"],
      kolom=[K("B","nama_dtps","teks",wajib=True), K("C","sks_ps_diakreditasi","desimal"), K("D","sks_ps_lain_dalam_pt","desimal"),
             K("E","sks_ps_lain_luar_pt","desimal"), K("F","sks_penelitian","desimal"), K("G","sks_pkm","desimal"),
             K("H","sks_tugas_tambahan","desimal"),
             K("I","jumlah_sks","hitung",rumus={"jenis":"jumlah","kolom":["sks_ps_diakreditasi","sks_ps_lain_dalam_pt","sks_ps_lain_luar_pt","sks_penelitian","sks_pkm","sks_tugas_tambahan"]}),
             K("J","rata_per_semester","hitung",rumus={"jenis":"bagi","kolom":"jumlah_sks","pembagi":2}), K("K","bukti","teks")]),
 dict(sheet="9", kode="9", tabel="9", kepala=(10,11), nomor=12, data=13, tahun_dari="kolom:tahun", kunci=["nama_dtps","deskripsi_rekognisi"],
      kolom=[K("B","nama_dtps","teks",wajib=True), K("C","bidang_keahlian","teks"), K("D","deskripsi_rekognisi","teks",wajib=True),
             K("E","jenis_rekognisi","pilihan",pilihan_dari="E4:E8",wajib=True), K("F","tahun","tahun",wajib=True),
             K("G","tingkat","pilihan",pilihan_dari="G4:G6",wajib=True), K("H","bukti_pendukung","teks")]),
 dict(sheet="10", kode="10", tabel="10", kepala=(6,7), nomor=8, data=9, tahun_dari="kolom:waktu_pelaksanaan",
      kunci=["nama_dtps","deskripsi_kegiatan"], catatan_dari="D5", kolom=PENGEMBANGAN("nama_dtps")),
 dict(sheet="11", kode="11", tabel="11", kepala=(3,4), nomor=5, data=6, tahun_dari="TS", baris_tetap=("B", 6, 9), footer="jumlah",
      kolom=[K("C","s3","angka"), K("D","s2","angka"), K("E","s1","angka"), K("F","d4","angka"), K("G","d3","angka"),
             K("H","sma_smk","angka"), K("I","jumlah","hitung",rumus={"jenis":"jumlah","kolom":["s3","s2","s1","d4","d3","sma_smk"]}),
             K("J","unit_kerja","teks")]),
 dict(sheet="12", kode="12", tabel="12", kepala=(6,7), nomor=8, data=9, tahun_dari="kolom:waktu_pelaksanaan",
      kunci=["nama_tendik","deskripsi_kegiatan"], catatan_dari="D5", kolom=PENGEMBANGAN("nama_tendik")),
 dict(sheet="13", kode="13", tabel="13", kepala=(10,11), nomor=12, data=13, tahun_dari="TS", kunci=["bidang_studi","nama_guru_pamong"],
      kolom=[K("B","bidang_studi","teks",wajib=True), K("C","nama_guru_pamong","teks",wajib=True),
             K("D","jabatan_fungsional","pilihan",pilihan_dari="E4:E7"), K("E","pendidikan_tertinggi","pilihan",pilihan_dari="F4:F6"),
             K("F","no_serdik","teks"), K("G","nama_sekolah","teks",wajib=True), K("H","akreditasi_sekolah","pilihan",pilihan_dari="H4:H7"),
             K("I","bukti_kerjasama","teks"), K("J","st_guru_pamong","teks")]),
 dict(sheet="14", kode="14", tabel="14", kepala=(4,5), nomor=6, data=7, tahun_dari="TS", baris_tetap_bertingkat=(7, 20),
      catatan_dari="D3",
      kolom=[K("D","upps_ts2","angka"), K("E","upps_ts1","angka"), K("F","upps_ts","angka"),
             K("G","upps_rata","hitung",rumus={"jenis":"rata","kolom":["upps_ts2","upps_ts1","upps_ts"]}),
             K("H","ps_ts2","angka"), K("I","ps_ts1","angka"), K("J","ps_ts","angka"),
             K("K","ps_rata","hitung",rumus={"jenis":"rata","kolom":["ps_ts2","ps_ts1","ps_ts"]})]),
 dict(sheet="15", kode="15", tabel="15", kepala=(8,9), nomor=10, data=11, tahun_dari="TS", kunci=["nama_ruang","nama_alat"],
      kolom=[K("B","nama_ruang","teks",wajib=True), K("C","nama_alat","teks",wajib=True), K("D","kualitas","pilihan",pilihan_dari="D3:D6"),
             K("E","jumlah","angka"), K("F","kepemilikan","pilihan",pilihan_dari="E3:E4"), K("G","kondisi","pilihan",pilihan_dari="F3:F4"),
             K("H","jam_per_minggu","desimal")]),
 dict(sheet="16", kode="16", tabel="16", kepala=(8,9), nomor=10, data=11, tahun_dari="TS", kunci=["nama_prasarana"],
      kolom=[K("B","nama_prasarana","teks",wajib=True), K("C","fungsi","teks"), K("D","jumlah_unit","angka"), K("E","total_luas_m2","desimal"),
             K("F","kualitas","pilihan",pilihan_dari="F3:F6"), K("G","kepemilikan","pilihan",pilihan_dari="G3:G4"),
             K("H","kondisi","pilihan",pilihan_dari="H3:H4")]),
 dict(sheet="17", kode="17", tabel="17", kepala=(7,8), nomor=9, data=10, tahun_dari="TS", kunci=["nama_sistem"],
      kolom=[K("B","nama_sistem","teks",wajib=True), K("C","deskripsi","teks"), K("D","jumlah","angka"),
             K("E","terintegrasi","pilihan",pilihan_dari="F3:F5"), K("F","mutakhir","pilihan",pilihan_dari="H3:H4"),
             K("G","ketersediaan_panduan","centang"), K("H","kepemilikan","pilihan",pilihan_dari="D3:D4"),
             K("I","kondisi","pilihan",pilihan_dari="E3:E4")]),
 dict(sheet="18", kode="18", tabel="18", kepala=(7,8), nomor=9, data=10, tahun_dari="TS", kunci=["kode_mk"],
      kolom=[K("B","semester","angka"), K("C","kode_mk","teks",wajib=True), K("D","nama_mk","teks",wajib=True), K("E","mk_inti","centang"),
             K("F","sks_kuliah","desimal"), K("G","sks_seminar","desimal"), K("H","sks_praktikum","desimal"),
             K("I","tautan_rps","tautan"), K("J","tautan_asesmen_cpl","tautan")]),
 dict(sheet="19", kode="19", tabel="19", kepala=(11,13), nomor=14, data=15, tahun_dari="TS", kunci=["nama_dtps","judul_penelitian_pkm"],
      aturan=[{"jenis": "minimal_satu", "kolom": ["ts2","ts1","ts"]}],
      kolom=[K("B","nama_dtps","teks",wajib=True), K("C","judul_penelitian_pkm","teks",wajib=True), K("D","mata_kuliah","teks",wajib=True),
             K("E","bentuk_integrasi","pilihan",pilihan_dari="F4:F8"), K("F","ts2","centang"), K("G","ts1","centang"), K("H","ts","centang"),
             K("I","bukti","teks")]),
 dict(sheet="20", kode="20", tabel="20", kepala=(5,6), nomor=7, data=8, tahun_dari="TS", kunci=["nama_dosen_pembimbing"],
      kolom=[K("B","nama_dosen_pembimbing","teks",wajib=True), K("C","bimbingan_ts2","angka"), K("D","bimbingan_ts1","angka"), K("E","bimbingan_ts","angka"),
             K("F","pertemuan_ts2","angka"), K("G","pertemuan_ts1","angka"), K("H","pertemuan_ts","angka"), K("I","lama_ppl_bulan","desimal")]),
 dict(sheet="21", kode="21", tabel="21", kepala=(7,8), nomor=9, data=10, tahun_dari="baris_tetap", baris_tetap=("A", 10, 12),
      kolom=[K("B","jumlah_lulusan","angka"), K("C","ipk_min","desimal"), K("D","ipk_rata","desimal"), K("E","ipk_maks","desimal")]),
 dict(sheet="22", kode="22", tabel="22", kepala=(7,8), nomor=9, data=10, tahun_dari="baris_tetap", baris_tetap=("A", 10, 12),
      nonaktif={"TS-1": ["lulus_akhir_ts2"], "TS": ["lulus_akhir_ts2", "lulus_akhir_ts1"]},
      kolom=[K("B","diterima","angka"), K("C","lulus_akhir_ts2","angka"), K("D","lulus_akhir_ts1","angka"), K("E","lulus_akhir_ts","angka"),
             K("F","jumlah_lulusan","hitung",rumus={"jenis":"jumlah","kolom":["lulus_akhir_ts2","lulus_akhir_ts1","lulus_akhir_ts"]}),
             K("G","rata_masa_studi","hitung",rumus={"jenis":"masa_studi_rata"})]),
 dict(sheet="23", kode="23", tabel="23", kepala=(7,8), nomor=9, data=10, tahun_dari="baris_tetap", baris_tetap=("A", 10, 12),
      aturan=[{"jenis": "tidak_melebihi", "kolom": ["wt_kurang_6","wt_6_12","wt_lebih_12"], "batas": "terlacak"},
              {"jenis": "tidak_melebihi", "kolom": ["terlacak"], "batas": "jumlah_lulusan"}],
      kolom=[K("B","jumlah_lulusan","angka"), K("C","terlacak","angka"), K("D","wt_kurang_6","angka"), K("E","wt_6_12","angka"), K("F","wt_lebih_12","angka")]),
 dict(sheet="24", kode="24.1", tabel="24", kepala=(10,11), nomor=12, data=13, tahun_dari="TS", baris_tetap=("B", 13, 21),
      aturan=[{"jenis": "jumlah_persen_100", "kolom": ["sangat_baik","baik","cukup","kurang"]}],
      kolom=[K("C","sangat_baik","desimal"), K("D","baik","desimal"), K("E","cukup","desimal"), K("F","kurang","desimal"),
             K("G","rencana_tindak_lanjut","teks")]),
 dict(sheet="24", kode="24.2", tabel="24", judul="Tabel 24 Referensi - Jumlah Tanggapan Pengguna Lulusan", kepala=(4,4), nomor=None, data=5,
      tahun_dari="baris_tetap", baris_tetap=("E", 5, 7), kepala_kolom_manual=True,
      kolom=[K("F","jumlah_lulusan","angka",label="Jumlah Lulusan"), K("G","jumlah_tanggapan","angka",label="Jumlah Tanggapan Pengguna Lulusan yang Terlacak")]),
 dict(sheet="25", kode="25", tabel="25", kepala=(3,4), nomor=5, data=6, tahun_dari="TS", baris_tetap=("B", 6, 8), footer="jumlah", kolom=SUMBER_DANA()),
 dict(sheet="26", kode="26", tabel="26", kepala=(3,4), nomor=5, data=6, tahun_dari="TS", baris_tetap=("B", 6, 17), kunci_baris_dari="F", footer="jumlah",
      kolom=[K("C","ts2","angka"), K("D","ts1","angka"), K("E","ts","angka"),
             K("G","jumlah","hitung",rumus={"jenis":"jumlah","kolom":["ts2","ts1","ts"]})]),
 dict(sheet="27", kode="27", tabel="27", kepala=(12,13), nomor=14, data=15, tahun_dari="kolom:tanggal_terbit", kunci=["nama_dtps","judul_artikel"],
      kolom=[K("B","nama_dtps","teks",wajib=True), K("C","judul_artikel","teks",wajib=True), K("D","nama_penulis","teks"),
             K("E","peran_penulis","pilihan",pilihan_dari="G5:G6",wajib=True), K("F","jenis_jurnal","pilihan",pilihan_dari="C4:C5",wajib=True),
             K("G","terindeks","pilihan",pilihan_dari="F4:F10",wajib=True), K("H","tanggal_terbit","tanggal",wajib=True)]),
 dict(sheet="28", kode="28", tabel="28", kepala=(7,8), nomor=9, data=10, tahun_dari="TS", kunci=["nama_dtps","judul_artikel"],
      kolom=[K("B","nama_dtps","teks",wajib=True), K("C","judul_artikel","teks",wajib=True), K("D","jumlah_sitasi","angka",wajib=True)]),
 dict(sheet="29", kode="29", tabel="29", kepala=(3,4), nomor=5, data=6, tahun_dari="TS", baris_tetap=("B", 6, 8), footer="jumlah", kolom=SUMBER_DANA()),
]

KUNCI_TETAP = {
    "5": ["reliability", "responsiveness", "assurance", "empathy", "tangible"],
    "11": ["pustakawan", "laboran_teknisi", "administrasi", "lainnya"],
    "14": ["biaya_dosen", "biaya_tendik", "biaya_operasional_pembelajaran", "biaya_operasional_tidak_langsung",
           "biaya_kemahasiswaan", "biaya_penelitian", "biaya_pkm", "investasi_sdm", "investasi_sarana", "investasi_prasarana"],
    "24.1": ["etika", "keahlian_bidang_ilmu", "bahasa_asing", "teknologi_informasi", "komunikasi", "kerjasama_tim",
             "pengembangan_diri", "berpikir_kritis", "kreativitas"],
    "25": ["pt_mandiri", "dalam_negeri", "luar_negeri"], "29": ["pt_mandiri", "dalam_negeri", "luar_negeri"],
}
JUDUL_LEMBAR = {"24.1": "Tabel 24 Kepuasan Pengguna Lulusan"}

def slug(s):
    s = re.sub(r"[^a-z0-9]+", "_", str(s).lower()).strip("_")
    return s[:40] or "x"

def teks(v):
    if v is None: return ""
    return re.sub(r"\s+", " ", str(v)).strip()

def kepala_untuk(ws, cfg, huruf):
    """Label kolom dan jalur kelompoknya, dibaca dari baris kepala + merge."""
    r0, r1 = cfg["kepala"]; c = ci(huruf)
    merges = ws.merged_cells.ranges
    def nilai(r, c):
        for m in merges:
            if m.min_row <= r <= m.max_row and m.min_col <= c <= m.max_col:
                return ws.cell(m.min_row, m.min_col).value, m
        return ws.cell(r, c).value, None
    jalur = []
    for r in range(r0, r1 + 1):
        v, m = nilai(r, c)
        t = teks(v)
        if not t: continue
        if jalur and jalur[-1] == t: continue
        jalur.append(t)
    if not jalur: return "", []
    return jalur[-1], jalur[:-1]

def pilihan_dari(ws, rng):
    a, b = rng.split(":")
    out = []
    for row in ws[a:b]:
        for c in row:
            t = teks(c.value)
            if t: out.append(t)
    return out

def main(path):
    wb = load_workbook(path, data_only=False)
    tabel = {}
    for cfg in LEMBAR:
        ws = wb[cfg["sheet"]]
        t = cfg["tabel"]
        if t not in tabel:
            tabel[t] = {"no": int(t), "kode": t, "label_tabel": f"Tabel {t}", "nama": NAMA_TABEL[t],
                        "pokja": PEMILIK[t][0], "elemen": PEMILIK[t][1], "jendela_data": JENDELA[t], "lembar": []}
        judul = cfg.get("judul") or teks(ws["A1"].value if not str(ws["A1"].value or "").startswith("ta") or cfg["sheet"]!="1.1" else "Tabel 1 Bagian 1 - Kerjasama Bidang Pendidikan")
        if cfg["sheet"] in ("1.1","1.2","1.3"): judul = teks(ws["A5"].value)
        if cfg["sheet"].startswith("4."): judul = teks(ws["A3"].value if cfg["sheet"]!="4.4" else ws["A10"].value)
        kolom = []
        for k in cfg["kolom"]:
            if cfg.get("kepala_kolom_manual"):
                label, kelompok = k.get("label"), []
            else:
                label, kelompok = kepala_untuk(ws, cfg, k["huruf"])
            d = {"kunci": k["kunci"], "label": label, "tipe": k["tipe"]}
            if kelompok: d["kelompok"] = kelompok
            if k.get("wajib"): d["wajib"] = True
            if k["tipe"] == "pilihan":
                d["pilihan"] = k.get("pilihan") or pilihan_dari(ws, k["pilihan_dari"])
            if k["tipe"] == "hitung": d["rumus"] = k["rumus"]
            nomor = cfg.get("nomor")
            if nomor: d["nomor_excel"] = teks(ws[f"{k['huruf']}{nomor}"].value)
            kolom.append(d)
        judul = JUDUL_LEMBAR.get(cfg["kode"], judul)
        lembar = {"kode": cfg["kode"], "judul": judul, "tahun_dari": cfg["tahun_dari"], "kolom": kolom}
        if cfg.get("kunci"): lembar["kunci"] = cfg["kunci"]
        if cfg.get("aturan"): lembar["aturan"] = cfg["aturan"]
        if cfg.get("footer"): lembar["footer"] = cfg["footer"]
        if cfg.get("catatan_dari"):
            lembar["catatan"] = teks(ws[cfg["catatan_dari"]].value).strip("= ").strip()
        if cfg.get("nonaktif"): lembar["kolom_nonaktif"] = cfg["nonaktif"]
        if cfg.get("baris_tetap"):
            huruf, r0, r1 = cfg["baris_tetap"]
            label_kolom, _ = ("", []) if cfg.get("kepala_kolom_manual") else kepala_untuk(ws, cfg, huruf)
            baris = []
            for r in range(r0, r1 + 1):
                lab = teks(ws[f"{huruf}{r}"].value)
                if cfg.get("kunci_baris_dari"):
                    kk = slug(teks(ws[f"{cfg['kunci_baris_dari']}{r}"].value).rstrip("= ").strip())
                else:
                    kk = lab if lab.startswith("TS") else slug(lab.split(":")[0])
                baris.append({"kunci": kk, "label": lab})
            lembar["baris_tetap"] = {"label_kolom": label_kolom or ("Tahun Lulus" if cfg["kode"]=="24.2" else ""), "baris": baris}
        if cfg.get("baris_tetap_bertingkat"):
            r0, r1 = cfg["baris_tetap_bertingkat"]; baris = []; kelompok = None; subtotal = []; sejak = []
            for r in range(r0, r1 + 1):
                a, b, c = teks(ws[f"A{r}"].value), teks(ws[f"B{r}"].value), teks(ws[f"C{r}"].value)
                isi = [ws.cell(r, col).value for col in range(4, 12)]
                kosong = all(v in (None, "") for v in isi)
                if b.lower() == "jumlah":                 # baris subtotal
                    subtotal.append({"label": f"Jumlah {kelompok or ''}".strip(), "dari": sejak}); sejak = []; kelompok = None; continue
                if a and b and kosong:                    # judul kelompok: "1 | Biaya Operasional Pendidikan"
                    kelompok = f"{a}. {b}"; continue
                label = c or b
                item = {"kunci": slug(label), "label": (f"{b}. {label}" if c and b else f"{a}. {label}")}
                if kelompok: item["kelompok"] = kelompok
                baris.append(item); sejak.append(item["kunci"])
            lembar["baris_tetap"] = {"label_kolom": "Jenis Penggunaan", "baris": baris, "subtotal": subtotal}
        if cfg["kode"] in KUNCI_TETAP:
            baris = lembar["baris_tetap"]["baris"]
            assert len(baris) == len(KUNCI_TETAP[cfg["kode"]]), (cfg["kode"], len(baris))
            peta = {}
            for b, k in zip(baris, KUNCI_TETAP[cfg["kode"]]): peta[b["kunci"]] = k; b["kunci"] = k
            for sub in lembar["baris_tetap"].get("subtotal", []):
                sub["dari"] = [peta.get(x, x) for x in sub["dari"]]
        tabel[t]["lembar"].append(lembar)
    keluar = [tabel[k] for k in sorted(tabel, key=int)]
    for tb in keluar:
        tb["keterangan"] = f"Isi mengikuti format {tb['label_tabel']} pada DKPS-3.1-TU-PPG (versi 3.0, 15 April 2026); jendela data {tb['jendela_data']}."
    print(json.dumps(keluar, ensure_ascii=False, indent=1))

if __name__ == "__main__":
    main(sys.argv[1])
