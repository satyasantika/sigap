#!/usr/bin/env python3
"""Verifikasi konsistensi data instrumen. Jalankan: python3 data/verifikasi.py

Dipakai dua kali: sekali oleh manusia sebelum menyerahkan paket ini ke agen,
sekali oleh agen setelah menjalankan seeder (bandingkan hasil query basis data
dengan angka di sini). Bila ada yang GAGAL, jangan lanjutkan pekerjaan.
"""
import json, io, os, sys, collections

D = os.path.dirname(os.path.abspath(__file__))
def L(n): return json.load(io.open(os.path.join(D, n), encoding='utf-8'))

ok = True
def chk(label, cond, got=""):
    global ok
    print(("  OK   " if cond else " GAGAL ") + "| " + label + (("  -> " + str(got)) if not cond else ""))
    if not cond: ok = False

el, kr, sp = L("elemen.json"), L("kriteria.json"), L("syarat-perlu.json")
pk, rm, dk = L("pokja.json"), L("rumus.json"), L("dkps-tabel.json")
pr, st, iz = L("peran.json"), L("status-tagihan.json"), L("izin.json")

chk("59 elemen", len(el) == 59, len(el))
chk("total bobot 100,00", round(sum(e["bobot"] for e in el), 2) == 100.0)
chk("nomor 1..59 berurutan", [e["no"] for e in el] == list(range(1, 60)))

j = collections.Counter(e["jenis"] for e in el)
chk("jenis: data 20 / rubrik 30 / refleksi 9",
    (j["data"], j["rubrik"], j["refleksi"]) == (20, 30, 9), dict(j))
jb = {k: round(sum(e["bobot"] for e in el if e["jenis"] == k), 2) for k in j}
chk("bobot jenis 36,75 / 49,75 / 13,50",
    (jb["data"], jb["rubrik"], jb["refleksi"]) == (36.75, 49.75, 13.50), jb)

chk("syarat perlu tepat pada elemen 17, 34, 45, 51, 58",
    sorted(e["no"] for e in el if e["syarat_perlu"]) == [17, 34, 45, 51, 58])
chk("semua elemen punya panduan", all(e["panduan"].strip() for e in el))

exp = {"K1": 5.5, "K2": 6.0, "K3": 11.75, "K4": 12.75, "K5": 8.0,
       "K6": 30.5, "K7": 13.0, "K8": 4.0, "K9": 8.5}
chk("bobot 9 kriteria sesuai", {k["kode"]: k["bobot"] for k in kr} == exp)
chk("jumlah elemen per kriteria berjumlah 59", sum(k["jumlah_elemen"] for k in kr) == 59)

chk("5 syarat perlu lengkap dengan kedua ambangnya",
    len(sp) == 5 and all(s["ambang_3_tahun"] and s["ambang_5_tahun"] for s in sp))

chk("total bobot pokja 100,00", round(sum(p["bobot"] for p in pk), 2) == 100.0)
chk("pokja membagi habis 59 elemen tanpa tumpang tindih",
    sorted(sum([p["elemen"] for p in pk], [])) == list(range(1, 60)))
elpok = {e["no"]: e["pokja"] for e in el}
chk("pokja di elemen.json konsisten dengan pokja.json",
    not [n for p in pk for n in p["elemen"] if elpok[n] != p["kode"]])

chk("15 rumus", len(rm) == 15, len(rm))
chk("rumus inti ada", {"PDS3", "PGBLKL", "PPDTPS", "RSA", "RK", "NA"} <= {r["kode"] for r in rm})
chk("28 butir DKPS bernomor 1..28", [d["no"] for d in dk] == list(range(1, 29)))
chk("6 peran", len(pr) == 6)
chk("6 status tagihan", len(st) == 6)

# --- matriks izin ---
peran = iz["peran"]
aksi = iz["aksi"]
chk("6 peran di izin.json", peran == ["admin", "ketua", "pimpinan", "koordinator", "anggota", "auditor"])
chk("24 aksi izin", len(aksi) == 24, len(aksi))
chk("144 sel izin (24 x 6)", len(aksi) * len(peran) == 144)
chk("kode aksi unik", len({a["kode"] for a in aksi}) == len(aksi))
sah = set(iz["lingkup"])
chk("semua nilai sel memakai kosakata yang sah",
    not [a["kode"] for a in aksi for p in peran if a[p] not in sah])
chk("peran di izin.json cocok dengan peran.json",
    sorted(peran) == sorted(p["kode"] for p in pr))
byk = {a["kode"]: a for a in aksi}
chk("admin tidak menyetujui isi akreditasi",
    byk["tagihan.setujui"]["admin"] == "tidak" and byk["narasi.tulis"]["admin"] == "tidak")
chk("ketua tidak mengelola pengguna", byk["pengguna.kelola"]["ketua"] == "tidak")
chk("pimpinan tidak menulis apa pun",
    all(a["pimpinan"] == "tidak" for a in aksi
        if a["kode"] not in ("dasbor.lihat", "tagihan.lihat", "komentar.tulis")))
chk("koordinator tidak bisa menyetujui final", byk["tagihan.setujui"]["koordinator"] == "tidak")
chk("tidak ada peran yang boleh semua aksi",
    not [p for p in peran if all(a[p] == "ya" for a in aksi)])
chk("validasi bukti: ketua ya, koordinator pokjanya, sisanya tidak",
    byk["bukti.validasi"]["ketua"] == "ya" and byk["bukti.validasi"]["koordinator"] == "pokjanya"
    and all(byk["bukti.validasi"][p] == "tidak" for p in ("admin", "pimpinan", "anggota", "auditor")))
chk("impor massal tunduk izin yang sama dengan membuat satu baris",
    byk["impor.jalankan"]["ketua"] == "ya" and byk["impor.jalankan"]["pimpinan"] == "tidak")

# Patokan yang dipakai uji otomatis di aplikasi
chk("patokan NA: rubrik+refleksi skor 4, data skor 3 = 363,25",
    round(4 * jb["rubrik"] + 4 * jb["refleksi"] + 3 * jb["data"], 2) == 363.25)
b58 = [e["bobot"] for e in el if e["no"] == 58][0]
chk("E58 berbobot 3,00 dan merupakan bobot tunggal terbesar",
    b58 == 3.0 and b58 == max(e["bobot"] for e in el))

print("\nHASIL:", "SEMUA KONSISTEN" if ok else "ADA YANG GAGAL — jangan lanjutkan")
sys.exit(0 if ok else 1)
