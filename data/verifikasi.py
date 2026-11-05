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

chk("28 rumus", len(rm) == 28, len(rm))
chk("rumus inti ada", {"PDS3", "PGBLKL", "PPDTPS", "RSA", "RK", "NA"} <= {r["kode"] for r in rm})
chk("29 tabel DKPS bernomor 1..29", [d["no"] for d in dk] == list(range(1, 30)))
chk("kode tabel DKPS unik dan sama dengan nomornya", [d["kode"] for d in dk] == [str(i) for i in range(1, 30)])
lembar = [l for d in dk for l in d["lembar"]]
chk("35 lembar DKPS", len(lembar) == 35, len(lembar))
chk("kode lembar unik", len({l["kode"] for l in lembar}) == 35)
chk("Tabel 1 tiga lembar, Tabel 4 empat lembar, Tabel 24 dua lembar",
    [len(d["lembar"]) for d in dk if d["kode"] in ("1", "4", "24")] == [3, 4, 2])
kodepok = {p["kode"] for p in pk}
chk("pokja pemilik tiap tabel ada di pokja.json", all(d["pokja"] in kodepok for d in dk))
chk("POKJA-DATA tidak memiliki tabel", not [d for d in dk if d["pokja"] == "POKJA-DATA"])
chk("elemen rujukan tabel ada di 1..59", all(1 <= n <= 59 for d in dk for n in d["elemen"]))
chk("jendela tiap tabel dikenal",
    all(d["jendela_data"] in ("saat TS", "TS-2 s.d. TS", "TS-4 s.d. TS-2", "5 tahun terakhir") for d in dk))
TIPE = {"teks", "angka", "desimal", "tanggal", "tahun", "pilihan", "centang", "tautan", "hitung"}
chk("tipe kolom dikenal", all(c["tipe"] in TIPE for l in lembar for c in l["kolom"]))
chk("kunci kolom unik per lembar",
    all(len({c["kunci"] for c in l["kolom"]}) == len(l["kolom"]) for l in lembar))
chk("tiap lembar punya kolom isian", all(any(c["tipe"] != "hitung" for c in l["kolom"]) for l in lembar))
chk("kolom pilihan punya daftar pilihan", all(c.get("pilihan") for l in lembar for c in l["kolom"] if c["tipe"] == "pilihan"))
chk("tahun_dari sah", all(l["tahun_dari"] in ("TS", "baris_tetap") or (
    l["tahun_dari"].startswith("kolom:") and l["tahun_dari"][6:] in {c["kunci"] for c in l["kolom"] if c["tipe"] in ("tanggal", "tahun")})
    for l in lembar))
chk("lembar berbaris bebas punya kunci duplikasi",
    all(l.get("kunci") or l.get("baris_tetap") for l in lembar))
chk("tahun_dari baris_tetap hanya pada lembar berbaris tetap",
    all(l.get("baris_tetap") for l in lembar if l["tahun_dari"] == "baris_tetap"))

# --- katalog variabel DKPS ---
vd = L("variabel-dkps.json")
kodevd = [v["kode"] for v in vd]
kodelembar = {l["kode"] for l in lembar}
CARA = {"cacah_baris", "cacah_centang", "cacah_pilihan", "cacah_terisi", "cacah_unik",
        "jumlah_kolom", "rata_kolom", "nilai_sel", "jumlah_sel", "khusus"}
chk("kode variabel DKPS unik", len(set(kodevd)) == len(kodevd))
chk("setiap rumus punya variabel_dkps", all("variabel_dkps" in r for r in rm))
chk("NA tidak membaca DKPS", [r["variabel_dkps"] for r in rm if r["kode"] == "NA"] == [[]])
chk("variabel_dkps rumus ada di katalog",
    not [(r["kode"], k) for r in rm for k in r["variabel_dkps"] if k not in kodevd],
    [(r["kode"], k) for r in rm for k in r["variabel_dkps"] if k not in kodevd])
kodermm = {r["kode"] for r in rm}
chk("rumus di katalog ada di rumus.json",
    not [(v["kode"], k) for v in vd for k in v["rumus"] if k not in kodermm])
chk("tautan rumus <-> variabel searah bolak-balik",
    sorted((r["kode"], k) for r in rm for k in r["variabel_dkps"])
    == sorted((k, v["kode"]) for v in vd for k in v["rumus"]))
chk("lembar variabel ada di DKPS", not [(v["kode"], l) for v in vd for l in v["lembar"] if l not in kodelembar])
chk("cara variabel dikenal", not [v["kode"] for v in vd if v["cara"] not in CARA])

chk("6 peran", len(pr) == 6)
chk("6 status tagihan", len(st) == 6)

# --- matriks izin ---
peran = iz["peran"]
aksi = iz["aksi"]
chk("6 peran di izin.json", peran == ["admin", "ketua", "pimpinan", "koordinator", "anggota", "auditor"])
chk("26 aksi izin", len(aksi) == 26, len(aksi))
chk("156 sel izin (26 x 6)", len(aksi) * len(peran) == 156)
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
chk("hitung rumus dari DKPS: ketua ya, koordinator pokja_data, sisanya tidak",
    byk["nilai_rumus.hitung"]["ketua"] == "ya" and byk["nilai_rumus.hitung"]["koordinator"] == "pokja_data"
    and all(byk["nilai_rumus.hitung"][p] == "tidak" for p in ("admin", "pimpinan", "anggota", "auditor")))

# --- matriks menu ---
mn = L("menu.json")
menu = mn["menu"]
chk("22 menu", len(menu) == 22, len(menu))
chk("kode menu unik", len({m["kode"] for m in menu}) == len(menu))
chk("peran di menu.json sama dengan izin.json", mn["peran"] == peran)
chk("semua sel menu memakai kosakata yang sah",
    not [m["kode"] for m in menu for p in mn["peran"] if m[p] not in mn["lingkup"]])
bym = {m["kode"]: m for m in menu}
chk("menu Variabel DKPS bersel sama dengan Nilai Rumus",
    all(bym["variabel_dkps"][p] == bym["nilai_rumus"][p] for p in mn["peran"]))

# Patokan yang dipakai uji otomatis di aplikasi
chk("patokan NA: rubrik+refleksi skor 4, data skor 3 = 363,25",
    round(4 * jb["rubrik"] + 4 * jb["refleksi"] + 3 * jb["data"], 2) == 363.25)
b58 = [e["bobot"] for e in el if e["no"] == 58][0]
chk("E58 berbobot 3,00 dan merupakan bobot tunggal terbesar",
    b58 == 3.0 and b58 == max(e["bobot"] for e in el))

# --- demo-dkps ---
# Data demo DKPS dibangkitkan tools/bangkitkan-demo-dkps.py; di sini hanya
# diperiksa terhadap skema lembar supaya PengisiDkps tidak menolaknya saat seeding.
import re
lembar = {l["kode"]: l for t in dk for l in t["lembar"]}
ada_demo = os.path.exists(os.path.join(D, "demo-dkps.json"))
chk("demo: berkas demo-dkps.json ada", ada_demo)
if ada_demo:
    demo = L("demo-dkps.json")
    chk("demo: 35 lembar", set(demo) == set(lembar), sorted(set(demo) ^ set(lembar)))
    chk("demo: setiap lembar berisi baris",
        all(demo[k].get("baris") for k in demo), [k for k in demo if not demo[k].get("baris")])
    pola_token = re.compile(r"^\{TS(-[1-4])?\}(-\d{2}-\d{2})?$")
    asing, hitung, baris_salah, pilihan_salah, token_salah = [], [], [], [], []
    for kode, isi in demo.items():
        if kode not in lembar:
            continue
        skema = lembar[kode]
        kolom = {k["kunci"]: k for k in skema["kolom"]}
        tetap = [b["kunci"] for b in skema.get("baris_tetap", {}).get("baris", [])]
        for i, b in enumerate(isi.get("baris", [])):
            tempat = f"{kode}#{i}"
            if tetap and b.get("_baris") not in tetap:
                baris_salah.append(f"{tempat}:{b.get('_baris')}")
            if not tetap and "_baris" in b:
                baris_salah.append(f"{tempat}:_baris pada lembar bebas")
            for k, v in b.items():
                if k == "_baris":
                    continue
                if k not in kolom:
                    asing.append(f"{tempat}.{k}")
                    continue
                tipe = kolom[k]["tipe"]
                if tipe == "hitung":
                    hitung.append(f"{tempat}.{k}")
                elif tipe == "pilihan" and v not in kolom[k].get("pilihan", []):
                    pilihan_salah.append(f"{tempat}.{k}={v}")
                elif tipe in ("tahun", "tanggal"):
                    m = pola_token.match(v) if isinstance(v, str) else None
                    if not m or (tipe == "tanggal") != (m.group(2) is not None):
                        token_salah.append(f"{tempat}.{k}={v}")
    chk("demo: semua kolom ada di skema lembar", not asing, asing[:10])
    chk("demo: kolom hitung tidak ditulis", not hitung, hitung[:10])
    chk("demo: _baris sah dan wajib pada lembar berbaris tetap", not baris_salah, baris_salah[:10])
    chk("demo: nilai pilihan ada di daftar skema", not pilihan_salah, pilihan_salah[:10])
    chk("demo: tahun/tanggal berbentuk token {TS…} (tanggal ber -MM-DD, tahun tanpa)",
        not token_salah, token_salah[:10])

    def baris_demo(kode): return demo.get(kode, {}).get("baris", [])
    tingkat = ["wilayah_lokal", "nasional", "internasional"]
    salah_tingkat = [f"{k}#{i}" for k in ("1.1", "1.2", "1.3", "3")
                     for i, b in enumerate(baris_demo(k))
                     if sum(b.get(t) is True for t in tingkat) != 1]
    chk("demo: Tabel 1.1–1.3 dan 3 tepat satu tingkat tercentang", not salah_tingkat, salah_tingkat)

    persen = {"5": ["sangat_baik", "baik", "cukup", "kurang_baik"],
              "24.1": ["sangat_baik", "baik", "cukup", "kurang"]}
    salah_persen = [f"{k}:{b.get('_baris')}={sum(b.get(c, 0) for c in kol)}"
                    for k, kol in persen.items() for b in baris_demo(k)
                    if abs(sum(b.get(c, 0) for c in kol) - 100) > 0.01]
    chk("demo: Tabel 5 dan 24.1 persen berjumlah 100", not salah_persen, salah_persen)

    diagonal = {"TS-2": "lulus_akhir_ts2", "TS-1": "lulus_akhir_ts1", "TS": "lulus_akhir_ts"}
    salah_diag = [f"{b.get('_baris')}:{k}" for b in baris_demo("22") for k in b
                  if k.startswith("lulus_akhir_") and k != diagonal.get(b.get("_baris"))]
    chk("demo: Tabel 22 hanya sel diagonal lulus_akhir_* terisi", not salah_diag, salah_diag)

    salah_23 = [b.get("_baris") for b in baris_demo("23")
                if not (b.get("terlacak", 0) <= b.get("jumlah_lulusan", 0)
                        and b.get("wt_kurang_6", 0) + b.get("wt_6_12", 0) + b.get("wt_lebih_12", 0)
                        == b.get("terlacak", 0))]
    chk("demo: Tabel 23 terlacak ≤ lulusan dan waktu tunggu berjumlah terlacak", not salah_23, salah_23)

print("\nHASIL:", "SEMUA KONSISTEN" if ok else "ADA YANG GAGAL — jangan lanjutkan")
sys.exit(0 if ok else 1)
