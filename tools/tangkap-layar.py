#!/usr/bin/env python3
"""Menangkap layar SIGAP sungguhan untuk manual pengguna.

Dipakai ulang setiap kali tampilan berubah, sehingga gambar di manual tidak
pernah menjadi gambar sistem lain. Tidak ada satu pun gambar stok di sini:
seluruh berkas di docs/manual/gambar/ lahir dari aplikasi ini, dengan data
DemoSeeder, lewat peramban sungguhan.

Prasyarat:
    docker exec sigap-php php artisan migrate:fresh --seed --seeder=DemoSeeder
    docker exec sigap-php php artisan sigap:siapkan-manual
    aplikasi hidup di ALAMAT (bawaan http://localhost:8021)

Menjalankan:
    python3 tools/tangkap-layar.py
    python3 tools/tangkap-layar.py --peran admin ketua

Catatan pemasangan peramban (sekali saja, tanpa root):
    python3 -m pip install --user --break-system-packages playwright
    python3 -m playwright install chromium
    # bila chrome-headless-shell mengeluh libnspr4.so hilang dan Anda tidak
    # punya sudo, unduh pustakanya sebagai pengguna biasa:
    #   apt-get download libnspr4 libnss3 libasound2t64 libasound2-data fonts-liberation
    #   dpkg-deb -x <tiap .deb> ~/.local/pw-deps/root
    #   export LD_LIBRARY_PATH=~/.local/pw-deps/root/usr/lib/x86_64-linux-gnu
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

ALAMAT = "http://localhost:8021"
AKAR = Path(__file__).resolve().parent.parent
GAMBAR = AKAR / "docs" / "manual" / "gambar"
SANDI = "password"

# Lebar jendela dipilih agar sidebar Filament terbuka penuh dan tabelnya tidak
# terpotong; tinggi hanya menentukan viewport, karena semua tangkapan memakai
# full_page.
LEBAR, TINGGI = 1440, 900

# Layar yang ditangkap per peran. Urutannya urutan bercerita di manual, bukan
# urutan menu: yang dilihat pertama kali lebih dulu.
# Layar yang ditangkap per peran. Daftarnya mengikuti data/menu.json — hanya
# layar yang benar-benar muncul di menu peran itu. Urutannya urutan bercerita
# di manual, bukan urutan menu: yang dilihat pertama kali lebih dulu.
LAYAR: dict[str, list[tuple[str, str, str]]] = {
    "admin": [
        ("01-dasbor", "/panel/dasbor", "Dasbor"),
        ("02-pengguna", "/panel/users", "Daftar pengguna"),
        ("03-periode", "/panel/periodes", "Periode akreditasi"),
        ("04-pokja", "/panel/pokjas", "Pokja"),
        ("05-matriks-izin", "/panel/matriks-izin", "Matriks izin"),
        ("06-log-aktivitas", "/panel/log-aktivitas", "Log aktivitas"),
        ("07-simulasi", "/panel/simulasi", "Simulasi"),
        ("08-prodi", "/panel/prodis", "Program studi"),
    ],
    "ketua": [
        ("01-dasbor", "/panel/dasbor", "Dasbor"),
        ("02-tagihan", "/panel/tagihans", "Semua tagihan"),
        ("03-narasi", "/panel/narasis", "Narasi LED"),
        ("04-bukti", "/panel/buktis", "Bukti"),
        ("05-penilaian", "/panel/penilaians", "Penilaian"),
        ("06-syarat-perlu", "/panel/syarat-perlu", "Syarat perlu"),
        ("07-nilai-rumus", "/panel/nilai-rumuses", "Nilai rumus"),
        ("08-simulasi", "/panel/simulasi", "Simulasi"),
    ],
    "pimpinan": [
        ("01-dasbor", "/panel/dasbor", "Dasbor"),
        ("02-syarat-perlu", "/panel/syarat-perlu", "Syarat perlu"),
        ("03-tagihan", "/panel/tagihans", "Semua tagihan"),
        ("04-nilai-rumus", "/panel/nilai-rumuses", "Nilai rumus"),
        ("05-simulasi", "/panel/simulasi", "Simulasi"),
    ],
    "koordinator": [
        ("01-dasbor", "/panel/dasbor", "Dasbor"),
        ("02-tagihan-saya", "/panel/tagihan-saya", "Tagihan Saya"),
        ("03-tagihan", "/panel/tagihans", "Tagihan pokja"),
        ("04-narasi", "/panel/narasis", "Narasi pokja"),
        ("05-bukti", "/panel/buktis", "Bukti pokja"),
        ("06-penilaian", "/panel/penilaians", "Asesmen mandiri"),
    ],
    "anggota": [
        ("01-tagihan-saya", "/panel/tagihan-saya", "Tagihan Saya"),
        ("02-dasbor", "/panel/dasbor", "Dasbor"),
        ("03-narasi", "/panel/narasis", "Narasi"),
        ("04-bukti", "/panel/buktis", "Bukti"),
        ("05-profil", "/panel/profile", "Profil"),
    ],
    "auditor": [
        ("01-dasbor", "/panel/dasbor", "Dasbor"),
        ("02-tagihan", "/panel/tagihans", "Semua tagihan"),
        ("03-bukti", "/panel/buktis", "Bukti"),
        ("04-penilaian", "/panel/penilaians", "Asesmen mandiri"),
        ("05-syarat-perlu", "/panel/syarat-perlu", "Syarat perlu"),
        ("06-log-aktivitas", "/panel/log-aktivitas", "Log aktivitas"),
        ("07-simulasi", "/panel/simulasi", "Simulasi"),
    ],
}

# Layar yang sama untuk semua peran, ditangkap sekali.
BERSAMA = [
    ("00-masuk", "/panel/login", None, "Halaman masuk"),
]


def masuk(halaman, peran: str, percobaan: int = 3) -> None:
    """Masuk sebagai satu peran, sabar terhadap pembatasan laju.

    Filament membatasi percobaan masuk per alamat IP. Enam login berturut-turut
    dari satu skrip menabrak batas itu, dan yang terjadi berikutnya bukan galat
    melainkan diam: halaman tetap di /panel/login dan seluruh tangkapan layar
    berikutnya menjadi halaman masuk. Karena itu kegagalan di sini harus
    berisik, bukan dilewati.
    """
    for ke in range(1, percobaan + 1):
        halaman.goto(f"{ALAMAT}/panel/login", wait_until="networkidle")
        halaman.fill('input[type="email"]', f"{peran}@sigap.test")
        halaman.fill('input[type="password"]', SANDI)
        halaman.click('button[type="submit"]')
        halaman.wait_for_timeout(2500)

        if "/panel/login" not in halaman.url:
            return

        badan = halaman.inner_text("body")

        if "Terlalu banyak permintaan" in badan:
            jeda = 20 + ke * 20
            print(f"    dibatasi laju, menunggu {jeda} detik (percobaan {ke}/{percobaan})")
            halaman.wait_for_timeout(jeda * 1000)

            continue

        raise RuntimeError(f"gagal masuk sebagai {peran}: {badan[:200]}")

    raise RuntimeError(
        f"gagal masuk sebagai {peran} setelah {percobaan} percobaan. "
        "Jalankan: docker exec sigap-php php artisan cache:clear"
    )


def keluar(konteks) -> None:
    konteks.clear_cookies()


def tangkap(halaman, jalur: str, berkas: Path) -> bool:
    halaman.goto(f"{ALAMAT}{jalur}", wait_until="networkidle")

    if "/login" in halaman.url and "/login" not in jalur:
        print(f"    LEWAT {berkas.name}: terlempar ke halaman masuk")
        return False

    # Menunggu tabel/konten Livewire selesai; networkidle saja kadang terlalu
    # cepat untuk tabel yang memuat sendiri setelah render pertama.
    halaman.wait_for_timeout(1200)
    berkas.parent.mkdir(parents=True, exist_ok=True)
    halaman.screenshot(path=str(berkas), full_page=True)
    print(f"    {berkas.relative_to(AKAR)}")
    return True


def adegan_impersonasi(halaman) -> None:
    """Menangkap alur penyamaran: tombol, modal alasan, lalu spanduk.

    Ini bukan satu layar melainkan satu urutan, dan urutan itulah yang perlu
    dilihat orang: tombolnya ada di daftar pengguna, alasannya wajib, dan
    hasilnya adalah spanduk kuning yang tidak bisa ditutup.
    """
    halaman.goto(f"{ALAMAT}/panel/users", wait_until="networkidle")
    halaman.wait_for_timeout(1200)

    tombol = halaman.get_by_role("button", name="Masuk sebagai").first
    tombol.click()
    halaman.wait_for_timeout(1200)

    berkas = GAMBAR / "admin" / "09-masuk-sebagai-modal.png"
    berkas.parent.mkdir(parents=True, exist_ok=True)
    halaman.screenshot(path=str(berkas))
    print(f"    {berkas.relative_to(AKAR)}")

    halaman.fill("textarea", "Menelusuri laporan bahwa tombol Ajukan tidak muncul.")
    halaman.wait_for_timeout(400)
    halaman.get_by_role("button", name="Mulai menyamar").click()
    halaman.wait_for_timeout(3000)

    tangkap(halaman, "/panel/dasbor", GAMBAR / "admin" / "10-spanduk-penyamaran.png")
    tangkap(halaman, "/panel/log-aktivitas", GAMBAR / "admin" / "11-log-setelah-menyamar.png")

    # Kembali menjadi admin lewat tombol di spanduk, persis seperti pengguna.
    halaman.goto(f"{ALAMAT}/panel/dasbor", wait_until="networkidle")
    halaman.get_by_role("button", name="Kembali ke akun saya").click()
    halaman.wait_for_timeout(2500)


def adegan_konfirmasi_keluar(halaman, peran: str) -> None:
    """Modal konfirmasi keluar — diminta manusia, mudah terlewat di manual."""
    halaman.goto(f"{ALAMAT}/panel/dasbor", wait_until="networkidle")
    halaman.wait_for_timeout(1000)

    halaman.locator(".fi-user-menu-trigger").first.click()
    halaman.wait_for_timeout(600)
    halaman.get_by_role("link", name="Keluar").or_(
        halaman.get_by_role("button", name="Keluar")
    ).first.click()
    halaman.wait_for_timeout(1500)

    berkas = GAMBAR / peran / "99-konfirmasi-keluar.png"
    berkas.parent.mkdir(parents=True, exist_ok=True)
    halaman.screenshot(path=str(berkas))
    print(f"    {berkas.relative_to(AKAR)}")


def adegan_galat(halaman, konteks) -> None:
    """Halaman galat, ditangkap dari galat yang sungguh terjadi.

    403 dipicu dengan membuka daftar pengguna sebagai anggota pokja — bukan
    dengan memanggil view() dari skrip. Yang perlu dilihat orang di manual
    adalah halaman yang benar-benar muncul kepadanya, termasuk namanya sendiri
    dan perannya yang tercetak di sana.
    """
    keluar(konteks)
    masuk(halaman, "anggota")

    tangkap(halaman, "/panel/users", GAMBAR / "bersama" / "01-galat-403.png")
    tangkap(halaman, "/alamat-yang-tidak-pernah-ada", GAMBAR / "bersama" / "02-galat-404.png")


def main() -> int:
    global ALAMAT  # noqa: PLW0603 — satu alamat dipakai seluruh modul

    ap = argparse.ArgumentParser()
    ap.add_argument("--peran", nargs="*", default=list(LAYAR))
    ap.add_argument("--alamat", default=ALAMAT)
    argumen = ap.parse_args()

    ALAMAT = argumen.alamat.rstrip("/")

    with sync_playwright() as p:
        peramban = p.chromium.launch()
        konteks = peramban.new_context(
            viewport={"width": LEBAR, "height": TINGGI},
            device_scale_factor=2,  # tajam saat ditampilkan di HTML
            locale="id-ID",
            color_scheme="light",  # tema terang, sesuai bawaan sistem
        )
        halaman = konteks.new_page()

        print("bersama:")
        for nama, jalur, _, _judul in BERSAMA:
            tangkap(halaman, jalur, GAMBAR / "bersama" / f"{nama}.png")

        for peran in argumen.peran:
            if peran not in LAYAR:
                print(f"peran tidak dikenal: {peran}", file=sys.stderr)
                continue

            print(f"{peran}:")
            keluar(konteks)
            masuk(halaman, peran)

            for nama, jalur, _judul in LAYAR[peran]:
                tangkap(halaman, jalur, GAMBAR / peran / f"{nama}.png")

            if peran == "admin":
                adegan_impersonasi(halaman)

            if peran in ("admin", "anggota"):
                adegan_konfirmasi_keluar(halaman, peran)

        adegan_galat(halaman, konteks)

        peramban.close()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
