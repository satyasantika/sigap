#!/usr/bin/env python3
"""Menyusun manual pengguna SIGAP dalam bentuk HTML.

Naskahnya ada di berkas ini, gambarnya di docs/manual/gambar/ (hasil
tools/tangkap-layar.py). Dipisah begitu supaya tampilan dan tata letak manual
bisa diperbaiki tanpa menyentuh naskah, dan sebaliknya.

Menjalankan:
    python3 tools/tangkap-layar.py     # perbarui gambar bila tampilan berubah
    python3 tools/susun-manual.py      # susun ulang HTML-nya
"""
from __future__ import annotations

import hashlib
import html
import json
from pathlib import Path

AKAR = Path(__file__).resolve().parent.parent
KELUARAN = AKAR / "docs" / "manual"
GAMBAR = KELUARAN / "gambar"

TAHUN = "2026"
PEMILIK = "Satya Santika"

# ---------------------------------------------------------------------------
# Naskah
# ---------------------------------------------------------------------------

PERAN = {
    "admin": {
        "judul": "Administrator Sistem",
        "ringkas": "Mengelola sistem, bukan isi akreditasi.",
        "pembuka": """
            <p>Administrator sistem memegang kendali atas <strong>siapa</strong> yang
            boleh masuk dan <strong>kapan</strong> periode dibuka atau dikunci — bukan
            atas isi akreditasinya. Anda tidak menyetujui tagihan, tidak menulis narasi,
            dan tidak mengunggah bukti. Pembagian ini disengaja: orang yang bisa
            mengubah daftar pengguna sebaiknya bukan orang yang sama dengan yang
            memutuskan sebuah bukti sah atau tidak.</p>
            <p>Bila Anda perlu melakukan sesuatu yang bukan wewenang admin, jangan
            mencari jalan pintas lewat basis data. Mintalah kepada ketua task force,
            atau — bila memang perlu memeriksa sendiri apa yang dilihat seseorang —
            gunakan fitur <em>Masuk sebagai</em> yang dijelaskan di bawah.</p>
        """,
        "bagian": [
            ("01-dasbor", "Dasbor", """
                <p>Dasbor menampilkan keadaan periode berjalan. Sebagai admin Anda
                melihat ubin yang sama dengan peran lain, tetapi tidak ada tombol
                tindakan di dalamnya.</p>
                <p>Angka <strong>Progres bobot</strong> selalu dihitung dari bobot
                elemen, bukan dari cacah tagihan. Dua puluh tagihan ringan yang selesai
                tidak menggeser angka sebanyak satu elemen berbobot besar, dan memang
                begitu seharusnya.</p>
            """),
            ("02-pengguna", "Pengguna", """
                <p>Seluruh akun dibuat di sini. Tidak ada pendaftaran mandiri, tidak
                ada lupa kata sandi lewat surel — bila seseorang kehilangan sandinya,
                Anda yang menyetel ulang.</p>
                <p>Menonaktifkan pengguna lebih baik daripada menghapusnya. Akun yang
                dinonaktifkan ditolak masuk dengan pesan yang jelas, sementara seluruh
                jejaknya di riwayat tagihan tetap utuh dan tetap bisa dibaca.</p>
            """),
            ("03-periode", "Periode", """
                <p>Satu periode mewakili satu siklus akreditasi, dan TS-nya
                (<em>tahun sekarang</em>) ditentukan di sini. Seluruh label tahun di
                DKPS — TS, TS-1, TS-2 — diturunkan dari angka itu, tidak ada satu pun
                yang ditulis mati di dalam kode.</p>
                <p><strong>Mengunci periode menutup seluruh penulisan untuk semua
                peran</strong>, termasuk ketua. Satu-satunya yang tetap bisa bergerak
                adalah admin lewat menu periode itu sendiri, supaya periode yang keliru
                dikunci masih bisa dibuka kembali.</p>
            """),
            ("04-pokja", "Pokja", """
                <p>Enam pokja membagi habis 59 elemen. Keanggotaan pokja menentukan
                apa yang dilihat koordinator dan anggota: lingkup
                <code>pokjanya</code> pada matriks izin berarti "hanya baris yang
                pokjanya sama dengan pokja pengguna".</p>
                <p>POKJA-DATA berbeda dari lima lainnya — ia tidak memegang elemen,
                melainkan mengisi dan memverifikasi DKPS.</p>
            """),
            ("05-matriks-izin", "Matriks izin", """
                <p>Halaman hanya-baca berisi 144 sel: 24 aksi &times; 6 peran. Inilah
                jawaban atas pertanyaan "kenapa tombol itu tidak muncul di layar saya".
                Siapa pun boleh membukanya sendiri tanpa bertanya.</p>
                <p>Matriks tidak bisa disunting dari layar. Mengubahnya berarti
                menyunting <code>data/izin.json</code> lalu menjalankan seeder, dan
                perubahan itu tercatat di riwayat git.</p>
            """),
            ("06-log-aktivitas", "Log aktivitas", """
                <p>Tempat seluruh jejak berkumpul dalam satu urutan waktu. Kolom
                <strong>Sebenarnya</strong> hanya terisi bila tindakan itu dilakukan
                seseorang yang sedang menyamar.</p>
                <p>Catatan ini tidak bisa diubah dan tidak bisa dihapus — penolakannya
                dipasang di tingkat model, bukan sekadar disembunyikan tombolnya. Tidak
                ada layar dan tidak ada perintah artisan yang bisa merapikannya di
                belakang.</p>
            """),
            ("09-masuk-sebagai-modal", "Masuk sebagai — meminta alasan", """
                <p>Tombol <em>Masuk sebagai</em> ada di setiap baris daftar pengguna,
                kecuali baris admin lain dan baris Anda sendiri. Menekannya membuka
                modal yang <strong>mewajibkan alasan</strong>.</p>
                <p>Alasan itu bukan formalitas. Ia satu-satunya bagian dari jejak yang
                menjelaskan <em>mengapa</em>; tanpanya, log hanya memberitahu bahwa
                admin pernah menjadi ketua selama sebelas menit tanpa ada yang bisa
                menilai apakah itu wajar.</p>
            """),
            ("10-spanduk-penyamaran", "Selama menyamar", """
                <p>Spanduk kuning menempel di atas setiap halaman dan tidak bisa
                ditutup. Selama menyamar Anda memegang <strong>seluruh wewenang peran
                yang ditiru</strong> — termasuk menyetujui tagihan. Tidak ada pagar
                yang menahan; yang menjaga pertanggungjawaban adalah jejaknya.</p>
                <p>Ada dua jalan keluar, keduanya tidak mengakhiri sesi Anda: tombol
                <em>Kembali ke akun saya</em> di spanduk, dan menu serupa di dalam menu
                pengguna di pojok kanan atas.</p>
            """),
            ("11-log-setelah-menyamar", "Jejak yang ditinggalkan", """
                <p>Setiap tindakan selama penyamaran menyimpan dua nama: pengguna yang
                ditiru dan admin di baliknya. Bukan hanya di log aktivitas —
                <code>tagihan_riwayat</code>, <code>narasi_versi</code>, dan
                <code>komentar</code> semuanya membawa kolom yang sama.</p>
                <p>Akibatnya sebuah persetujuan tagihan selalu bisa dilacak ke tangan
                yang benar-benar menekannya.</p>
            """),
            ("07-simulasi", "Simulasi", """
                <p>Dua bentuk simulasi, keduanya dibuat dan dihapus seperlunya.</p>
                <p><strong>Pengandaian skor</strong> menjawab "bagaimana bila E58 naik
                ke 4?" dengan menghitung ulang NA memakai skor yang Anda andaikan.
                Tabel penilaian tidak tersentuh sama sekali — dan itu syarat yang tidak
                bisa ditawar, karena begitu simulasi boleh menulis ke sana, tidak ada
                lagi cara membedakan skor yang dinilai dari skor yang diandaikan.</p>
                <p><strong>Periode latihan</strong> menyalin kerangka periode sungguhan
                — pokja dan seluruh tagihannya — ke periode baru bertanda
                <code>[SIMULASI]</code>. Narasi, bukti, dan penilaian tidak ikut
                disalin; periode latihan memang dimulai kosong untuk dilatih mengisinya.
                Ia tidak pernah muncul sebagai periode berjalan.</p>
                <p>Menghapus periode latihan membuang periodenya beserta seluruh isinya,
                permanen. Data periode sungguhan tidak tersentuh.</p>
            """),
            ("08-prodi", "Program studi", """
                <p>Identitas prodi, UPPS, dan perguruan tinggi. Jarang disentuh setelah
                pemasangan, tetapi ada karena sistem ini dirancang untuk bisa menaungi
                lebih dari satu prodi tanpa migrasi ulang.</p>
            """),
            ("99-konfirmasi-keluar", "Keluar", """
                <p>Keluar selalu bertanya lebih dulu. Tombolnya bertetangga dengan
                tombol profil dan penukar tema, dan satu salah klik di tengah menyusun
                narasi berarti kehilangan isian yang belum tersimpan.</p>
                <p>Bila Anda sedang menyamar, modalnya berbunyi berbeda: ia mengingatkan
                bahwa keluar mengakhiri penyamaran <em>sekaligus</em> sesi Anda, dan
                menyarankan <em>Kembali ke akun saya</em> bila itu yang Anda maksud.</p>
            """),
        ],
    },
    "ketua": {
        "judul": "Ketua Task Force",
        "ringkas": "Memegang seluruh wewenang isi akreditasi.",
        "pembuka": """
            <p>Ketua task force adalah satu-satunya peran yang memegang seluruh
            wewenang atas isi akreditasi: membuat dan menugaskan tagihan, menyetujui
            atau mengembalikannya, menulis narasi, memvalidasi bukti, mengisi
            penilaian, dan menetapkan level syarat perlu.</p>
            <p>Yang tidak Anda pegang adalah pengelolaan pengguna. Itu milik admin.
            Bila Anda butuh akun baru atau peran seseorang diubah, mintalah kepadanya.</p>
        """,
        "bagian": [
            ("01-dasbor", "Dasbor", """
                <p>Ubin <strong>Gerbang Unggul</strong> adalah yang paling penting di
                layar ini, dan letaknya paling atas karena satu alasan: NA tinggi tanpa
                syarat perlu tetap berujung "Terakreditasi", bukan "Unggul". Progres 90%
                tidak menghasilkan Unggul bila salah satu dari lima syarat perlu masih
                merah.</p>
                <p>Angka NA proyeksi selalu didampingi kalimat kejujuran: berapa elemen
                yang belum dinilai dan karenanya diandaikan berskor 3. Bacalah kalimat
                itu sebelum mengutip angkanya.</p>
                <p>Ubin <strong>Laju dan perkiraan</strong> membaca riwayat tagihan
                untuk memperkirakan kapan pengumpulan rampung dengan kecepatan sekarang.
                Perkiraan, bukan janji.</p>
            """),
            ("02-tagihan", "Semua tagihan", """
                <p>137 tagihan membagi habis bobot 100: 59 narasi, 50 bukti, 28 DKPS.
                Setiap tagihan membawa bobot terkaitnya sendiri, dan jumlah seluruhnya
                persis 100,000.</p>
                <p>Status bergerak lewat satu pintu: <em>belum &rarr; dikerjakan &rarr;
                diajukan &rarr; direviu &rarr; disetujui</em>, dengan
                <em>dikembalikan</em> sebagai jalan mundur. Setiap perpindahan menulis
                satu baris riwayat yang tidak pernah ditimpa.</p>
                <p>Tagihan tidak bisa disetujui selama masih ada buktinya yang tidak
                terbuka. Itu pagar yang disengaja — bukti yang tidak terbaca oleh mesin
                juga tidak akan terbaca oleh asesor.</p>
            """),
            ("03-narasi", "Naskah LED", """
                <p>Narasi ditulis manusia. Sistem menyediakan angka, bukti, dan
                kerangka; kalimatnya Anda yang susun.</p>
                <p>Setiap penyimpanan membuat versi baru — versi lama tidak pernah
                hilang dan tidak pernah ditimpa. Anda bisa membandingkan dan kembali ke
                versi mana pun.</p>
            """),
            ("04-bukti", "Bukti", """
                <p>Bukti dinilai pada <strong>dua sumbu yang terpisah</strong>, dan
                keduanya harus hijau:</p>
                <ul>
                    <li><strong>Keterbacaan</strong> (<code>akses_status</code>) —
                    diperiksa mesin. Tautan Drive dibuka <em>tanpa kredensial apa pun</em>,
                    persis seperti asesor akan membukanya. Memeriksanya sambil membawa
                    akun yang punya akses akan membuat pemeriksaan selalu lulus dan
                    karena itu tidak berguna.</li>
                    <li><strong>Keabsahan</strong> (<code>validasi_status</code>) —
                    dinilai manusia. Tautan bisa terbuka lebar tetapi berisi dokumen
                    yang keliru.</li>
                </ul>
                <p>Setiap bukti wajib punya tanggal kejadian dan sumber. Bukti tanpa
                keduanya ditolak saat disimpan, bukan sekadar diberi peringatan.</p>
            """),
            ("05-penilaian", "Asesmen mandiri", """
                <p>Skor 1&ndash;4 per elemen. Kolom <strong>Selisih</strong> menandai
                elemen yang dinilai berbeda oleh dua penilai — dan elemen itulah yang
                paling berguna didiskusikan sebelum asesor datang, karena beda penilaian
                hampir selalu berarti buktinya belum meyakinkan.</p>
            """),
            ("06-syarat-perlu", "Syarat perlu", """
                <p>Lima syarat perlu, masing-masing berlevel <em>belum</em>,
                <em>tiga tahun</em>, atau <em>lima tahun</em>. Yang dihitung adalah
                <strong>kelimanya</strong>, bukan sebagian: empat dari lima terpenuhi
                tetap berarti tidak terpenuhi.</p>
                <p>Inilah gerbang yang menentukan apakah NA tinggi berubah menjadi
                "Unggul" atau berhenti di "Terakreditasi".</p>
            """),
            ("07-nilai-rumus", "Nilai rumus", """
                <p>Tujuh rumus LAMDIK — PDS3, PGBLKL, PPDTPS, RK, RSA, TKM, RIPK —
                dihitung dari isian DKPS. Hasilnya menunjukkan skor sekaligus apakah
                syarat tiga atau lima tahun terpenuhi; keduanya angka yang berbeda dan
                ditampilkan terpisah.</p>
            """),
            ("08-simulasi", "Simulasi", """
                <p>Anda bisa membaca hasil simulasi, tetapi membuat dan menghapusnya
                adalah wewenang admin. Gunakan halaman ini untuk menjawab "apa yang
                harus naik supaya statusnya berubah" sebelum memutuskan ke mana tenaga
                pokja diarahkan.</p>
                <p>Ingat lencana SIMULASI di bagian atas: angka di halaman ini bukan
                nilai akreditasi.</p>
            """),
        ],
    },
    "pimpinan": {
        "judul": "Pimpinan",
        "ringkas": "Membaca keadaan, tidak mengubah isinya.",
        "pembuka": """
            <p>Peran pimpinan dirancang untuk memantau, bukan mengerjakan. Anda melihat
            seluruh angka dan seluruh tagihan, dan Anda bisa menulis komentar — tetapi
            Anda tidak menyetujui tagihan, tidak menulis narasi, dan tidak mengunggah
            bukti.</p>
            <p>Pembatasan ini bukan soal kepercayaan melainkan soal kejelasan: bila
            pimpinan ikut menyetujui, tidak ada lagi lapisan yang memeriksa keputusan
            ketua. Karena itu menu Anda pendek — hanya layar yang memang urusan
            pimpinan yang muncul.</p>
        """,
        "bagian": [
            ("01-dasbor", "Dasbor", """
                <p>Satu layar yang menjawab pertanyaan yang biasanya ditanyakan
                pimpinan: di mana posisi kita, apa yang menahan, dan kapan kira-kira
                selesai.</p>
                <p>Bacalah <strong>Gerbang Unggul</strong> lebih dulu, bukan
                <strong>Progres bobot</strong>. Progres yang tinggi tidak berarti apa
                pun bila salah satu syarat perlu masih merah.</p>
            """),
            ("02-syarat-perlu", "Syarat perlu", """
                <p>Lima baris yang menentukan status akhir. Bila ada yang masih
                <em>belum</em>, di sinilah perhatian pimpinan paling berguna — biasanya
                yang dibutuhkan bukan tenaga tambahan melainkan keputusan di tingkat
                fakultas.</p>
            """),
            ("03-tagihan", "Semua tagihan", """
                <p>137 tagihan beserta status dan tenggatnya. Hanya-baca bagi pimpinan:
                menugaskan dan menyetujui adalah urusan ketua dan koordinator.</p>
                <p>Kolom yang paling berguna bagi pimpinan adalah tenggat dan bobot —
                tagihan terlambat yang berbobot besar jauh lebih mendesak daripada
                sepuluh tagihan ringan yang juga terlambat.</p>
            """),
            ("04-nilai-rumus", "Nilai rumus", """
                <p>Tujuh rumus LAMDIK beserta hasilnya. Angka di sini datang dari isian
                DKPS, jadi rumus yang hasilnya mengejutkan biasanya menandakan isian
                DKPS yang belum lengkap, bukan kinerja yang buruk.</p>
            """),
            ("05-simulasi", "Simulasi", """
                <p>Hasil simulasi bisa Anda baca untuk menimbang keputusan — misalnya
                apakah menaikkan satu elemen berbobot besar lebih berdampak daripada
                menaikkan tiga elemen kecil.</p>
                <p>Angka di halaman ini bercampur pengandaian dan <strong>bukan</strong>
                nilai akreditasi. Jangan mengutipnya keluar tanpa lencana SIMULASI-nya.</p>
            """),
        ],
    },
    "koordinator": {
        "judul": "Koordinator Pokja",
        "ringkas": "Memimpin satu pokja, dari penugasan sampai reviu.",
        "pembuka": """
            <p>Koordinator bekerja dalam lingkup pokjanya. Hampir seluruh wewenang Anda
            bertuliskan <code>pokjanya</code> di matriks izin, yang berarti: hanya baris
            yang pokjanya sama dengan pokja tempat Anda terdaftar.</p>
            <p>Anda menugaskan, mengubah tenggat, mereviu, dan mengembalikan tagihan —
            tetapi <strong>persetujuan akhir ada pada ketua</strong>. Reviu Anda adalah
            saringan pertama, bukan keputusan terakhir.</p>
            <p>Menu <strong>Isian DKPS</strong> dan <strong>Nilai Rumus</strong> hanya
            muncul bila pokja Anda berkode POKJA-DATA. Bila Anda memimpin pokja lain,
            keduanya memang bukan urusan Anda.</p>
        """,
        "bagian": [
            ("01-dasbor", "Dasbor", """
                <p>Ubin yang Anda lihat sama dengan peran lain, tetapi angkanya sudah
                disaring ke pokja Anda. Ubin <strong>Progres per pokja</strong> berguna
                untuk membandingkan: pokja yang tertinggal biasanya bukan pokja yang
                malas, melainkan pokja yang memegang elemen paling berat.</p>
            """),
            ("02-tagihan-saya", "Tagihan Saya", """
                <p>Tagihan yang ditugaskan kepada Anda sendiri — koordinator juga
                mengerjakan, bukan hanya membagi. Tersaring yang belum selesai, terurut
                tenggat terdekat.</p>
            """),
            ("03-tagihan", "Tagihan pokja", """
                <p>Menugaskan tagihan adalah pekerjaan koordinator yang paling sering.
                Tagihan tanpa penanggung jawab tidak akan bergerak sendiri, dan ubin
                dasbor menghitungnya terang-terangan.</p>
                <p>Saat mereviu, perhatikan dua hal sebelum meneruskan ke ketua:
                buktinya terbaca, dan isinya memang menjawab elemen yang dimaksud.
                Mengembalikan lebih murah daripada menarik persetujuan.</p>
            """),
            ("04-narasi", "Narasi pokja", """
                <p>Narasi elemen di pokja Anda. Versi lama tidak pernah hilang, jadi
                jangan ragu menyimpan draf — menyimpan setengah jadi lebih baik daripada
                menunda sampai sempurna.</p>
            """),
            ("05-bukti", "Bukti pokja", """
                <p>Anda memvalidasi keabsahan bukti di pokja Anda. Keterbacaan tautannya
                diperiksa mesin secara terpisah, tanpa kredensial — jadi tautan yang
                terbuka di peramban Anda belum tentu terbuka bagi asesor. Percayai
                status mesin, bukan pengalaman Anda sendiri.</p>
            """),
            ("06-penilaian", "Asesmen mandiri", """
                <p>Koordinator ikut memberi skor 1&ndash;4 per elemen. Kolom
                <strong>Selisih</strong> menandai elemen yang dinilai berbeda oleh dua
                penilai — dan itulah yang paling berguna didiskusikan sebelum asesor
                datang.</p>
            """),
        ],
    },
    "anggota": {
        "judul": "Anggota Pokja",
        "ringkas": "Mengerjakan tagihan yang ditugaskan kepada Anda.",
        "pembuka": """
            <p>Anggota pokja melihat dan mengerjakan <strong>tagihannya sendiri</strong>.
            Anda menulis narasi, mengunggah bukti, dan mengajukan tagihan untuk direviu
            koordinator.</p>
            <p>Anda tidak menyetujui tagihan — termasuk tagihan Anda sendiri. Itu bukan
            soal kepercayaan, melainkan supaya setiap tagihan dibaca setidaknya oleh dua
            pasang mata sebelum menjadi bahan akreditasi.</p>
        """,
        "bagian": [
            ("01-tagihan-saya", "Tagihan Saya", """
                <p>Layar harian Anda, dan tempat paling masuk akal untuk memulai pagi:
                hanya tagihan milik Anda, hanya yang belum selesai, terurut tenggat
                terdekat.</p>
                <p>Halaman ini sengaja dipisahkan dari <em>Semua Tagihan</em>. Orang
                yang membuka panel pagi hari ingin melihat apa yang harus dikerjakan
                hari ini, bukan 137 baris yang harus disaring dulu setiap kali.</p>
            """),
            ("02-dasbor", "Dasbor", """
                <p>Gambaran keadaan periode. Sebagai anggota, angka tagihan pada dasbor
                sudah disaring ke milik Anda sendiri.</p>
            """),
            ("03-narasi", "Menulis narasi", """
                <p>Sistem tidak menulis narasi untuk Anda dan memang tidak dirancang
                untuk itu. Yang disediakan adalah angka, bukti, dan kerangka.</p>
                <p>Setiap simpan membuat versi baru. Simpanlah sering — tidak ada yang
                hilang, dan versi lama selalu bisa dibuka lagi.</p>
            """),
            ("04-bukti", "Mengunggah bukti", """
                <p>Dua hal yang wajib dan sering terlupa: <strong>tanggal kejadian</strong>
                dan <strong>sumber</strong>. Bukti tanpa keduanya ditolak saat disimpan.</p>
                <p>Untuk tautan Drive, pastikan tautannya bisa dibuka oleh orang yang
                <em>tidak</em> punya akun di organisasi kita. Sistem memeriksanya persis
                seperti itu — tanpa kredensial apa pun — karena begitulah asesor akan
                membukanya.</p>
            """),
            ("05-profil", "Profil dan kata sandi", """
                <p>Nama dan kata sandi Anda sendiri diubah di sini. Tidak ada
                pemulihan sandi lewat surel; bila Anda lupa, hubungi administrator
                sistem.</p>
            """),
            ("99-konfirmasi-keluar", "Keluar", """
                <p>Keluar selalu bertanya lebih dulu, supaya narasi yang sedang Anda
                tulis tidak hilang karena salah klik.</p>
            """),
        ],
    },
    "auditor": {
        "judul": "Auditor Mutu Internal",
        "ringkas": "Memeriksa tanpa mengubah.",
        "pembuka": """
            <p>Auditor membaca seluruhnya dan mengubah hampir tidak ada apa pun. Yang
            bisa Anda tulis hanya dua: skor asesmen mandiri Anda sendiri, dan komentar.</p>
            <p>Susunan ini disengaja. Pemeriksaan kehilangan artinya bila pemeriksanya
            juga ikut memperbaiki apa yang diperiksanya.</p>
        """,
        "bagian": [
            ("01-dasbor", "Dasbor", """
                <p>Mulailah dari <strong>Gerbang Unggul</strong> dan
                <strong>Bukti bermasalah</strong>. Dua ubin itu yang paling sering
                menunjukkan jarak antara apa yang diklaim dan apa yang bisa
                dibuktikan.</p>
            """),
            ("02-tagihan", "Semua tagihan", """
                <p>Seluruh 137 tagihan beserta riwayat status masing-masing. Riwayatnya
                bersifat <em>append only</em>: tidak pernah ditimpa dan tidak pernah
                dihapus, jadi urutan kejadian yang Anda baca adalah urutan yang
                sebenarnya.</p>
            """),
            ("03-bukti", "Bukti", """
                <p>Perhatikan kedua sumbu secara terpisah. Bukti yang tautannya terbuka
                tetapi validasinya merah adalah dokumen yang keliru, bukan dokumen yang
                hilang — dan keduanya menuntut tindakan yang berbeda.</p>
            """),
            ("04-penilaian", "Asesmen mandiri", """
                <p>Auditor memberi skor sendiri, terpisah dari skor ketua. Justru
                selisih antara keduanya yang berguna: elemen yang dinilai 4 oleh ketua
                dan 2 oleh auditor hampir selalu elemen yang buktinya belum
                meyakinkan.</p>
            """),
            ("05-syarat-perlu", "Syarat perlu", """
                <p>Lima baris yang menentukan status akhir. Periksalah bukti
                pendukungnya, bukan hanya levelnya: level yang ditetapkan tanpa bukti
                yang terbaca tidak akan bertahan di tangan asesor.</p>
            """),
            ("06-log-aktivitas", "Log aktivitas", """
                <p>Auditor membaca log yang sama dengan admin, dan itu disengaja: yang
                diperiksa tidak boleh menjadi satu-satunya pihak yang memegang catatan
                pemeriksaan.</p>
                <p>Kolom <strong>Sebenarnya</strong> menandai tindakan yang dilakukan
                lewat penyamaran. Bila ada persetujuan yang lahir dari penyamaran,
                periksalah alasan yang tercatat pada baris <code>impersonasi.mulai</code>
                di sesi yang sama.</p>
            """),
            ("07-simulasi", "Simulasi", """
                <p>Anda bisa membaca simulasi untuk memahami pertimbangan yang diambil
                task force. Pastikan angka simulasi tidak pernah tercampur ke dalam
                laporan audit — halamannya diberi lencana SIMULASI justru untuk itu.</p>
            """),
        ],
    },
}

BERSAMA = {
    "judul": "Masuk ke SIGAP",
    "gambar": "bersama/00-masuk.png",
    "isi": """
        <p>Semua peran masuk lewat halaman yang sama. Tidak ada pendaftaran mandiri —
        akun dibuat oleh administrator sistem — dan tidak ada pemulihan kata sandi lewat
        surel.</p>
        <p>Bila akun Anda dinonaktifkan, pesannya akan mengatakan demikian dengan jelas,
        supaya Anda berhenti menebak-nebak kata sandi sendiri. Untuk kata sandi yang
        salah, pesannya sengaja dibuat tidak membedakan akun yang ada dari akun yang
        tidak ada.</p>
    """,
}

# ---------------------------------------------------------------------------
# Penyusunan
# ---------------------------------------------------------------------------

GAYA = """
:root {
    --tinta: #1d2a24;
    --tinta-lembut: #55625b;
    --garis: #e4ddcf;
    --latar: #fbf8f2;
    --latar-lembut: #f3eee4;
    --sorot: #0a5c44;
    --sorot-lembut: #e3f2ec;
    --zamrud: #0f7a5a;
    --peringatan: #b7791f;
    --peringatan-lembut: #fbf0d9;
    --font-judul: "Fraunces", ui-serif, Georgia, "Times New Roman", serif;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: "Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    color: var(--tinta);
    background-color: var(--latar);
    background-image: radial-gradient(circle at 1px 1px, rgb(29 42 36 / 0.07) 1px, transparent 0);
    background-size: 22px 22px;
    line-height: 1.65;
}

.bungkus { max-width: 60rem; margin: 0 auto; padding: 0 1.5rem 5rem; }

header.utama {
    border-bottom: 1px solid var(--garis);
    background-color: var(--latar-lembut);
    background-image: radial-gradient(50rem 20rem at 100% -20%, rgb(15 122 90 / 0.12), transparent 60%);
    padding: 2.5rem 0 2rem;
    margin-bottom: 2.5rem;
}

header.utama .bungkus { padding-bottom: 0; }

/* Bilah atas menempel, sama dengan landing page dan tur. Tautan ke beranda,
   tur, dan halaman masuk RELATIF (`../`), bukan absolut (`/`): aplikasi juga
   dipasang di bawah subfolder — https://…/sigap — dan di sana `/panel` akan
   menunjuk ke luar aplikasi. Dari /manual/ maupun /sigap/manual/, `../panel`
   selalu benar. */
.bilah {
    position: sticky; top: 0; z-index: 20;
    border-bottom: 1px solid var(--garis);
    background: rgb(251 248 242 / 0.88);
    backdrop-filter: blur(8px);
}
.bilah .bungkus {
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; padding-top: 0.75rem; padding-bottom: 0.75rem;
}
.jenama {
    display: inline-flex; align-items: center; gap: 0.6rem;
    font-family: var(--font-judul); font-weight: 700; font-size: 1.25rem;
    letter-spacing: -0.015em; color: var(--tinta); text-decoration: none;
}
.jenama::before {
    content: "S"; display: inline-flex; align-items: center; justify-content: center;
    width: 2.25rem; height: 2.25rem; border-radius: 9999px;
    background: var(--zamrud); color: #fff; font-size: 1.1rem;
}
.bilah nav { display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; }
.bilah nav a {
    padding: 0.5rem 0.75rem; border-radius: 9999px;
    color: var(--tinta-lembut); text-decoration: none;
}
.bilah nav a:hover { background: var(--latar-lembut); }
.bilah nav a.aktif { color: var(--sorot); font-weight: 500; }
.bilah nav a.aktif:hover { background: var(--sorot-lembut); }
.bilah nav a.masuk {
    margin-left: 0.25rem; padding: 0.5rem 1.25rem;
    background: var(--zamrud); color: #fff; font-weight: 600;
}
.bilah nav a.masuk:hover { background: var(--sorot); }
@media (max-width: 40rem) { .bilah nav a.sekunder { display: none; } }

h1, h2, h3 { font-family: var(--font-judul); }
h1 { font-size: 2.3rem; line-height: 1.15; letter-spacing: -0.015em; margin: 0.75rem 0 0.35rem; }
h2 {
    font-size: 1.35rem; letter-spacing: -0.01em; margin: 3rem 0 0.75rem;
    padding-top: 2rem; border-top: 1px solid var(--garis);
}
h2:first-of-type { border-top: 0; padding-top: 0; }
h3 { font-size: 1.05rem; margin: 2rem 0 0.5rem; }

.ringkas { color: var(--tinta-lembut); font-size: 1.05rem; margin: 0; }

p { margin: 0 0 1rem; }
ul { margin: 0 0 1rem; padding-left: 1.25rem; }
li { margin-bottom: 0.4rem; }

code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.875em;
    background: var(--latar-lembut);
    border: 1px solid var(--garis);
    border-radius: 0.25rem;
    padding: 0.05rem 0.3rem;
}

figure { margin: 1.25rem 0 2rem; }

figure img {
    display: block; width: 100%; height: auto;
    border: 1px solid var(--garis); border-radius: 1rem;
    background: #fff; box-shadow: 0 12px 28px -18px rgb(10 92 68 / 0.3);
}

figcaption {
    margin-top: 0.6rem; font-size: 0.85rem; color: var(--tinta-lembut);
}

nav.daftar { margin: 2rem 0 0; }
nav.daftar ol { margin: 0; padding-left: 1.25rem; }
nav.daftar a { color: var(--sorot); }

.kartu-peran {
    display: grid; gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
    margin: 2rem 0;
}

.kartu {
    border: 1px solid var(--garis); border-left: 4px solid var(--zamrud);
    border-radius: 1rem;
    padding: 1.1rem 1.25rem; text-decoration: none; color: inherit;
    background: #fff; transition: border-color .15s, background .15s;
}
.kartu:hover { border-color: var(--zamrud); background: var(--sorot-lembut); }
.kartu strong { display: block; font-family: var(--font-judul); font-size: 1.1rem; margin-bottom: 0.2rem; }
.kartu span { color: var(--tinta-lembut); font-size: 0.9rem; }

.catatan {
    border-left: 3px solid var(--peringatan);
    background: var(--peringatan-lembut);
    padding: 0.9rem 1.1rem; border-radius: 0 0.75rem 0.75rem 0;
    margin: 1.5rem 0;
}
.catatan p:last-child { margin-bottom: 0; }

footer.utama {
    border-top: 1px solid var(--garis); margin-top: 4rem;
    padding: 1.5rem 0; color: var(--tinta-lembut); font-size: 0.85rem;
}

a.kembali { color: var(--sorot); text-decoration: none; font-size: 0.9rem; }
a.kembali:hover { text-decoration: underline; }
"""


VERSI_GAYA = hashlib.sha1(GAYA.encode()).hexdigest()[:8]


def kerangka(judul: str, isi: str) -> str:
    """Seluruh halaman manual berada di satu folder, jadi tautannya datar."""
    return f"""<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{html.escape(judul)} &middot; Manual SIGAP</title>
<meta name="color-scheme" content="light">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="manual.css?v={VERSI_GAYA}">
</head>
<body>
<div class="bilah">
  <div class="bungkus">
    <a class="jenama" href="../">SIGAP</a>
    <nav>
      <a class="sekunder" href="../tur">Tur</a>
      <a class="aktif" href="index.html">Manual</a>
      <a class="masuk" href="../panel">Masuk</a>
    </nav>
  </div>
</div>
<header class="utama">
  <div class="bungkus">
{isi.split('<!--PISAH-->')[0]}
  </div>
</header>
<main class="bungkus">
{isi.split('<!--PISAH-->')[1]}
</main>
<footer class="utama">
  <div class="bungkus">
    &copy; {TAHUN} {html.escape(PEMILIK)}. Sistem Informasi Gugus Akreditasi Program Studi.
  </div>
</footer>
</body>
</html>
"""


def halaman_peran(kunci: str, data: dict) -> str:
    kepala = f"""    <h1>{html.escape(data['judul'])}</h1>
    <p class="ringkas">{html.escape(data['ringkas'])}</p>"""

    daftar = "\n".join(
        f'      <li><a href="#{berkas}">{html.escape(tajuk)}</a></li>'
        for berkas, tajuk, _ in data["bagian"]
    )

    badan = [
        f'  <a class="kembali" href="index.html">&larr; Semua manual</a>',
        data["pembuka"],
        '  <nav class="daftar"><strong>Isi halaman ini</strong>',
        f"    <ol>\n{daftar}\n    </ol>",
        "  </nav>",
    ]

    for berkas, tajuk, prosa in data["bagian"]:
        jalur = GAMBAR / kunci / f"{berkas}.png"
        badan.append(f'  <h2 id="{berkas}">{html.escape(tajuk)}</h2>')
        badan.append(prosa)

        if jalur.is_file():
            badan.append(
                f'  <figure>\n'
                f'    <img src="gambar/{kunci}/{berkas}.png" alt="{html.escape(tajuk)} pada SIGAP" loading="lazy">\n'
                f'    <figcaption>{html.escape(tajuk)} — tangkapan layar SIGAP dengan data contoh.</figcaption>\n'
                f'  </figure>'
            )
        else:
            print(f"  PERINGATAN gambar hilang: {jalur.relative_to(AKAR)}")

    return kerangka(data["judul"], kepala + "\n<!--PISAH-->\n" + "\n".join(badan))


def halaman_indeks() -> str:
    kepala = """    <h1>Manual pengguna SIGAP</h1>
    <p class="ringkas">Satu manual untuk tiap peran. Seluruh gambar adalah tangkapan
    layar sungguhan dari sistem ini.</p>"""

    kartu = "\n".join(
        f'    <a class="kartu" href="{k}.html"><strong>{html.escape(v["judul"])}</strong>'
        f'<span>{html.escape(v["ringkas"])}</span></a>'
        for k, v in PERAN.items()
    )

    badan = f"""
  <p>SIGAP membagi wewenang ke enam peran. Setiap orang hanya melihat apa yang
  menjadi urusannya, dan pembagian itu ditentukan satu matriks 144 sel yang bisa
  dibuka siapa pun lewat menu <em>Matriks Izin</em>. Bila sebuah tombol tidak muncul
  di layar Anda, jawabannya ada di sana — bukan pada kesalahan sistem.</p>

  <div class="kartu-peran">
{kartu}
  </div>

  <h2 id="masuk">{html.escape(BERSAMA['judul'])}</h2>
{BERSAMA['isi']}
  <figure>
    <img src="gambar/{BERSAMA['gambar']}" alt="Halaman masuk SIGAP" loading="lazy">
    <figcaption>Halaman masuk — sama untuk seluruh peran.</figcaption>
  </figure>

  <h2 id="tur-dan-demo">Manual, tur, dan demo</h2>
  <p>Selain manual ini ada dua jalan lain mengenal SIGAP, dan ketiganya menjawab
  pertanyaan yang berbeda:</p>
  <ul>
    <li><strong>Manual</strong> — halaman ini. Satu halaman per peran, lengkap, bisa
    dibuka di bagian mana pun. Menjawab <em>&ldquo;layar ini apa dan mengapa
    begini&rdquo;</em>.</li>
    <li><strong><a href="/tur">Tur terpandu</a></strong> — alur kerja tiap peran,
    langkah demi langkah, dengan gambar yang sama persis dengan manual ini.
    Menjawab <em>&ldquo;saya harus mulai dari mana&rdquo;</em>. Sekitar lima menit
    per peran, tanpa masuk.</li>
    <li><strong><a href="/demo">Demo hidup</a></strong> — masuk sungguhan sebagai
    peran pilihan Anda dan kerjakan periode latihan. Perlu kode akses dari
    administrator sistem, dan peran administrator sendiri tidak tersedia di sana:
    wewenangnya menyentuh pengguna dan periode yang tidak terikat periode latihan.</li>
  </ul>

  <h2 id="menu">Menu Anda hanya memuat urusan Anda</h2>
  <p>Menu yang muncul berbeda per peran, dan perbedaannya disengaja. Anggota pokja
  tidak melihat menu Pengguna atau Prodi; administrator sistem tidak melihat menu
  Bukti atau Naskah LED. Tabelnya ada di <code>data/menu.json</code> dan diturunkan
  dari dokumen rancangan layar.</p>
  <p>Menu yang hilang <strong>menyembunyikan, bukan menolak</strong>. Penolakan tetap
  diputuskan matriks izin, dan halaman yang tidak ada di menu Anda tetap akan menolak
  bila dibuka lewat tautan langsung. Dua lapis itu memang disengaja: menyembunyikan
  tombol tidak pernah boleh menjadi satu-satunya pengaman.</p>

  <h2 id="galat">Kalau ada yang salah</h2>
  <p>SIGAP tidak menampilkan halaman galat kosong. Setiap halaman galat menyebut
  apa yang terjadi, mengapa, dan apa yang bisa Anda lakukan sekarang.</p>
  <figure>
    <img src="gambar/bersama/01-galat-403.png" alt="Halaman 403 SIGAP" loading="lazy">
    <figcaption>403 — halaman bukan wewenang Anda. Menyebut nama dan peran Anda, lalu
    menunjuk Matriks Izin supaya Anda bisa memeriksa sendiri.</figcaption>
  </figure>
  <figure>
    <img src="gambar/bersama/02-galat-404.png" alt="Halaman 404 SIGAP" loading="lazy">
    <figcaption>404 — alamat tidak dikenali.</figcaption>
  </figure>
  <p>Bila yang muncul adalah <strong>500</strong>, halamannya menampilkan
  <strong>kode rujukan</strong> seperti <code>SIGAP-7KQ3M2XA</code>. Salin kode itu
  saat melapor: kode yang sama tersimpan di berkas log, jadi administrator bisa
  menemukan baris yang tepat tanpa menebak jam kejadian. Jangan mengulang tindakan
  yang sama berkali-kali — bila tindakan itu sempat menulis sebagian data,
  pengulangan bisa menggandakannya.</p>

  <h2 id="tentang-gambar">Tentang gambar di manual ini</h2>
  <p>Setiap gambar di sini ditangkap dari SIGAP yang benar-benar berjalan, lewat
  peramban sungguhan, dengan data contoh dari <code>DemoSeeder</code>. Tidak ada
  gambar stok, tidak ada mockup, dan tidak ada tangkapan layar dari aplikasi lain.
  Nama orang yang muncul di gambar adalah nama akun contoh, bukan dosen sungguhan.</p>

  <div class="catatan">
    <p><strong>Bila tampilan berubah, gambarnya harus ikut diperbarui.</strong>
    Jalankan <code>python3 tools/tangkap-layar.py</code> lalu
    <code>python3 tools/susun-manual.py</code>. Manual yang menampilkan layar lama
    lebih menyesatkan daripada manual yang tidak bergambar sama sekali.</p>
  </div>
"""

    return kerangka("Manual pengguna", kepala + "\n<!--PISAH-->\n" + badan)


def tulis_tur() -> None:
    """Menulis docs/manual/tur.json — sumber tunggal tur terpandu di aplikasi.

    Naskah dan gambar tur SAMA PERSIS dengan manual. Dipisah menjadi berkas
    data supaya halaman tur di aplikasi tidak perlu menyalin prosanya: manual
    dan tur berubah bersama, atau tidak berubah sama sekali. Manual dan tur
    yang menceritakan dua versi sistem yang berbeda lebih buruk daripada salah
    satunya tidak ada.
    """
    import json
    import re

    def polos(html_: str) -> str:
        """HTML prosa dibiarkan apa adanya; hanya dirapikan spasinya."""
        return re.sub(r"\s+", " ", html_).strip()

    tur = {
        "catatan": "DIBANGKITKAN tools/susun-manual.py. Jangan disunting langsung.",
        "peran": {},
    }

    for kunci, data in PERAN.items():
        langkah = []

        for berkas, tajuk, prosa in data["bagian"]:
            if not (GAMBAR / kunci / f"{berkas}.png").is_file():
                continue

            langkah.append({
                "gambar": f"{kunci}/{berkas}.png",
                "judul": tajuk,
                "isi": polos(prosa),
            })

        tur["peran"][kunci] = {
            "judul": data["judul"],
            "ringkas": data["ringkas"],
            "pembuka": polos(data["pembuka"]),
            "langkah": langkah,
        }

    (KELUARAN / "tur.json").write_text(
        json.dumps(tur, ensure_ascii=False, indent=1) + "\n", encoding="utf-8"
    )

    jumlah = sum(len(p["langkah"]) for p in tur["peran"].values())
    print(f"docs/manual/tur.json ({len(tur['peran'])} peran, {jumlah} langkah)")


def main() -> int:
    KELUARAN.mkdir(parents=True, exist_ok=True)
    (KELUARAN / "manual.css").write_text(GAYA.strip() + "\n", encoding="utf-8")

    (KELUARAN / "index.html").write_text(halaman_indeks(), encoding="utf-8")
    print("docs/manual/index.html")

    for kunci, data in PERAN.items():
        (KELUARAN / f"{kunci}.html").write_text(halaman_peran(kunci, data), encoding="utf-8")
        print(f"docs/manual/{kunci}.html")

    tulis_tur()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
