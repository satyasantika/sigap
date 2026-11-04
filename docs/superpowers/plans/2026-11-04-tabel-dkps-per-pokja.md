# Tabel DKPS per Pokja — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setiap pokja mengisi tabel DKPS miliknya dalam tampilan yang sama dengan lembar `DKPS PPG.xlsx` v3.0 (29 tabel, 35 lembar), dan ketua task force melihat progres DKPS per pokja dan per lembar.

**Architecture:** Skema setiap lembar (kolom, tipe, kepala bertingkat, baris tetap, kolom hitung, aturan) hidup di `data/dkps-tabel.json` yang dibangkitkan dari Excel resmi dan disemai ke `dkps_butir.lembar`. Kelas `SkemaLembar` membaca skema itu dan menjadi satu-satunya penafsir untuk validasi, normalisasi, kunci duplikasi, tahun acuan, dan kolom hitung; layanan `PengisiDkps` menulis `dkps_baris`; komponen Livewire `LembarDkps` menggambar lembar seperti Excel; `RingkasanProgres` menghitung status per lembar dan per pokja. Kepemilikan pokja dipasang di `dkps_baris.pokja_id` supaya lingkup `pokjanya` pada `Izin` bekerja tanpa cabang baru.

**Tech Stack:** Laravel 13 / PHP 8.5, Filament 5, Livewire 3, Tailwind (tema panel Vite), MariaDB 10.11, PHPUnit (uji di MariaDB `db_sigap_test`), Python 3 + openpyxl untuk pembangkit skema.

**Spec:** `docs/superpowers/specs/2026-11-04-tabel-dkps-per-pokja-design.md` (Bagian B; Lampiran A memuat ke-35 lembar).

## Global Constraints

- Seluruh perintah `php`, `artisan`, `composer` dijalankan di container: `docker exec sigap-php …`; `npm` di `docker exec -w /var/www/html/sigap laravel-node22 …`.
- Setelah tiap tugas: `docker exec sigap-php vendor/bin/pint --dirty` lalu uji yang disebut. Sebelum tugas terakhir selesai: `docker exec sigap-php php artisan test` seluruhnya hijau dan `python3 data/verifikasi.py` keluar 0.
- Tidak ada angka tahun mati di `app/` dan `database/migrations/`; aritmetika tahun hanya di `App\Services\JendelaTs` (dijaga `JendelaTsTest`).
- Tidak ada perbandingan peran di luar `App\Support\Izin`; tidak ada `whereJsonContains`.
- Jumlah sel `data/izin.json` tetap 25 × 6 = 150. Menu menjadi 21 × 6 = 126.
- Bahasa Indonesia untuk label, pesan, dan tombol; tanggal `d F Y`; desimal dengan koma.
- Nama parameter closure Filament harus `$query`, `$state`, `$record` (lihat `docs/KEPUTUSAN.md` §4.1); setiap saringan tabel baru ditambahkan ke `SaringanTabelTest::saringan()`.
- `kunci utama` UUID; kolom ENUM baru wajib diisi eksplisit; tabel riwayat baru memakai trait `MencatatImpersonasi`.
- Pesan commit berbahasa Indonesia berformat `feat(dkps): …`, `data: …`, `fix(dkps): …`, diakhiri `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- Berkas siap pakai (hasil pembangkitan dari Excel resmi, dipakai Tugas 1): `/tmp/claude-1000/-home-satya-code-sigap/d364a834-3355-49ce-8ae2-5167df1e30eb/scratchpad/gen/dkps-tabel.json` dan `/tmp/claude-1000/-home-satya-code-sigap/d364a834-3355-49ce-8ae2-5167df1e30eb/scratchpad/gen/dkps_skema_dari_xlsx.py`. Excel sumber: `/mnt/c/Users/Lenovo/Documents/projects/akreditasi PPG 2027/01-Instrumen-IAPSK-3.0/DKPS PPG.xlsx`.

## Review Focus

1. **Tanggal tempelan Excel berbentuk `20/06/2022`, `2024-06-08 00:00:00`, atau `2024-06-08`** — ketiganya harus menjadi `2024-06-08`; tanggal yang tidak terurai ditolak dengan pesan yang menyebut kolomnya. (Uji di Tugas 4: `tanggal_dinormalkan_dari_tiga_bentuk`.)
2. **Persentase kepuasan yang jumlahnya bukan 100** (Tabel 5, 24.1) atau nilai > 100 — ditolak per baris, bukan diam-diam disimpan. (Tugas 4: `jumlah_persen_harus_100`.)
3. **Tahun di luar jendela** — prestasi tahun 2019 pada TS = 2027 (jendela TS-2 s.d. TS) ditolak dengan pesan yang menyebut rentang tahun nyata; kerja sama berawal 2019 tetap diterima karena Tabel 1 memakai `tahun_dari: TS`. (Tugas 5: `baris_dengan_tahun_di_luar_jendela_ditolak`, `kerja_sama_lama_tetap_diterima`.)
4. **Koordinator pokja lain membuka `/panel/dkps/tabel/7` lewat URL langsung** — halaman tetap terbuka hanya-baca (izin `tagihan.lihat`), tetapi `simpan`, `tambahBaris`, `hapusBaris` pada komponen dilempar 403. (Tugas 7: `koordinator_pokja_lain_tidak_bisa_menyimpan`.)
5. **Menyunting baris yang sudah diverifikasi** — ditolak bagi pengisi biasa; bagi pemegang `dkps.verifikasi` suntingan diterima dan verifikasinya dicabut (`diverifikasi_pada = null`) supaya angka yang berubah tidak tampak tetap terverifikasi. (Tugas 5: `sunting_baris_terverifikasi_mencabut_verifikasi`.)

---

### Task 1: Data referensi DKPS v3.0

**Files:**
- Create: `tools/dkps-skema-dari-xlsx.py` (salin dari scratchpad; tambahkan docstring pemakaian yang sudah ada)
- Modify: `data/dkps-tabel.json` (ganti seluruh isi dengan hasil pembangkitan)
- Modify: `data/verifikasi.py:49` (baris `chk("28 butir DKPS …")`)
- Modify: `data/pokja.json` (catatan POKJA-DATA)

**Interfaces:**
- Produces: `data/dkps-tabel.json` berbentuk seperti spesifikasi B.2 — larik 29 objek `{no, kode, label_tabel, nama, pokja, elemen[], jendela_data, keterangan, lembar[]}`; tiap lembar `{kode, judul, tahun_dari, kolom[], kunci?, aturan?, baris_tetap?, kolom_nonaktif?, catatan?, footer?}`; tiap kolom `{kunci, label, tipe, wajib?, kelompok?, pilihan?, rumus?, nomor_excel?}`.

- [ ] **Step 1: Salin pembangkit dan jalankan**

```bash
cp /tmp/claude-1000/-home-satya-code-sigap/d364a834-3355-49ce-8ae2-5167df1e30eb/scratchpad/gen/dkps_skema_dari_xlsx.py tools/dkps-skema-dari-xlsx.py
python3 -P tools/dkps-skema-dari-xlsx.py "/mnt/c/Users/Lenovo/Documents/projects/akreditasi PPG 2027/01-Instrumen-IAPSK-3.0/DKPS PPG.xlsx" > data/dkps-tabel.json
```

Expected: keluar 0; `python3 -c "import json;d=json.load(open('data/dkps-tabel.json'));print(len(d), sum(len(t['lembar']) for t in d))"` mencetak `29 35`. Bila openpyxl tidak ada: `pip3 install --user --break-system-packages openpyxl`. Bila Excel tidak terjangkau, salin `…/scratchpad/gen/dkps-tabel.json` langsung.

- [ ] **Step 2: Ganti pemeriksaan DKPS di `data/verifikasi.py`**

Ganti baris `chk("28 butir DKPS bernomor 1..28", …)` dengan pemeriksaan berikut (nilai persis):

```python
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
```

- [ ] **Step 3: Jalankan verifikasi**

Run: `python3 data/verifikasi.py`
Expected: semua `OK`, `HASIL: SEMUA KONSISTEN`, keluar 0.

- [ ] **Step 4: Perbarui catatan POKJA-DATA di `data/pokja.json`**

Ganti `catatan` POKJA-DATA menjadi: `"Tidak memiliki elemen maupun tabel DKPS. Memegang penetapan TS, seluruh perhitungan rumus, dan verifikasi DKPS lintas pokja. Sejak DKPS 3.0, tiap tabel DKPS dimiliki pokja yang elemennya membaca tabel itu (lihat data/dkps-tabel.json)."`

- [ ] **Step 5: Commit**

```bash
git add tools/dkps-skema-dari-xlsx.py data/dkps-tabel.json data/verifikasi.py data/pokja.json
git commit -m "data: DKPS 3.0 — 29 tabel, 35 lembar berskema kolom, dibangkitkan dari DKPS PPG.xlsx"
```

---

### Task 2: Skema basis data, model, dan seeder untuk DKPS 3.0

**Files:**
- Create: `database/migrations/2026_11_05_000001_dkps_v3_kode_lembar_dan_pokja.php`
- Modify: `app/Models/DkpsButir.php`, `app/Models/DkpsBaris.php`
- Modify: `database/seeders/DkpsButirSeeder.php`, `database/seeders/DemoSeeder.php:270-296`
- Modify: `app/Support/Impor/ProfilDkpsDasar.php`, `app/Support/Impor/ImporDtps.php`, `app/Support/Impor/ImporKerjaSama.php`, `app/Support/Impor/ImporPublikasiDtps.php`, `app/Support/Impor/ImporBarisDkps.php`
- Modify: `app/Console/Commands/CekKesiapan.php:170`
- Modify: `app/Filament/Resources/DkpsBaris/Tables/DkpsBarisTable.php:60`
- Test: `tests/Feature/ReferensiInstrumenTest.php:140-160`, `tests/Feature/DkpsTest.php`

**Interfaces:**
- Produces: `DkpsButir::dariKode(string $kode): static` (firstOrFail); atribut `kode`, `pokja_kode`, `elemen` (array), `lembar` (array); `DkpsButir::kodeLembar(): array<int,string>` (urut sesuai json); `DkpsButir::lembarMentah(string $kodeLembar): array` (melempar `InvalidArgumentException` bila tak ada).
- Produces: `DkpsBaris` kolom `lembar_kode` (string) dan `pokja_id` (uuid|null), konstanta `DkpsBaris::KODE_DARI_SIAKAD = ['2','3','6','7','8','11','18','20','21','22']` (pemetaan satu-satu dari `BUTIR_DARI_SIAKAD` lama `[2,3,5,6,7,10,17,19,20,21]` ke penomoran 3.0), relasi `pokja(): BelongsTo`. Saat `creating`: `lembar_kode` kosong → kode lembar pertama butirnya; `pokja_id` kosong → id `Pokja` berkode `butir.pokja_kode` pada `periode_id` baris (null bila tidak ada).
- Produces: `ProfilDkpsDasar::kodeButir(): string` (abstrak) dan `ProfilDkpsDasar::kodeLembar(array $baris): string` (bawaan = `kodeButir()`); `idButir()` mencari lewat `kode`.

- [ ] **Step 1: Tulis uji referensi yang gagal**

Di `ReferensiInstrumenTest`, ganti uji yang menyebut 28 dan "Tabel 14" dengan:

```php
#[Test]
public function dkps_versi_3_punya_29_tabel_berkode_dan_35_lembar(): void
{
    $this->assertSame(29, DkpsButir::count());
    $this->assertSame(range(1, 29), DkpsButir::orderBy('no')->pluck('no')->all());
    $this->assertSame(array_map('strval', range(1, 29)), DkpsButir::orderBy('no')->pluck('kode')->all());
    $this->assertSame('Guru Pamong', DkpsButir::dariKode('13')->nama);
    $this->assertSame('Tabel 29', DkpsButir::dariKode('29')->label_tabel);
    $this->assertSame(['1.1', '1.2', '1.3'], DkpsButir::dariKode('1')->kodeLembar());
    $this->assertSame(['4.1', '4.2', '4.3', '4.4'], DkpsButir::dariKode('4')->kodeLembar());
    $this->assertSame(35, DkpsButir::all()->sum(fn (DkpsButir $b) => count($b->lembar)));
    $this->assertSame('POKJA-SDM', DkpsButir::dariKode('7')->pokja_kode);
    $this->assertSame([39, 40, 41], DkpsButir::dariKode('22')->elemen);
}

#[Test]
public function seeder_dkps_mencocokkan_lewat_kode_bukan_nomor(): void
{
    // Simulasi basis data lama: butir berkode "14" sempat bernomor 13.
    DkpsButir::dariKode('14')->forceFill(['no' => 113])->save();
    DkpsButir::dariKode('13')->forceFill(['no' => 13])->save();
    $idLama = DkpsButir::dariKode('14')->id;

    $this->seed(\Database\Seeders\DkpsButirSeeder::class);

    $this->assertSame($idLama, DkpsButir::dariKode('14')->id, 'Baris lama dipakai ulang lewat kode.');
    $this->assertSame(14, DkpsButir::dariKode('14')->no);
}
```

Di `DkpsTest` tambahkan:

```php
#[Test]
public function baris_baru_mewarisi_pokja_dan_lembar_dari_butirnya(): void
{
    $baris = $this->baris(7);   // Tabel 7 milik POKJA-SDM

    $this->assertSame('7', $baris->lembar_kode);
    $this->assertSame(Pokja::where('kode', 'POKJA-SDM')->firstOrFail()->id, $baris->pokja_id);
    $this->assertSame('1.1', $this->baris(1)->lembar_kode);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter='ReferensiInstrumenTest|DkpsTest'`
Expected: FAIL (kolom `kode` tidak ada / metode `dariKode` tidak ada).

- [ ] **Step 3: Tulis migrasi `2026_11_05_000001_dkps_v3_kode_lembar_dan_pokja.php`**

`up()`: (a) `dkps_butir`: tambah `kode` string(8) nullable, `pokja_kode` string(20) nullable, `elemen` json nullable, `lembar` json nullable; (b) isi `kode` baris lama dari `label_tabel` dengan `UPDATE dkps_butir SET kode = TRIM(REPLACE(label_tabel, 'Tabel', ''))` dan `kode = CAST(no AS CHAR)` bila `label_tabel` NULL; (c) pasang `unique('kode')`; (d) `dkps_baris`: tambah `lembar_kode` string(8) nullable index, `foreignUuid('pokja_id')->nullable()->constrained('pokja')->nullOnDelete()`; (e) isi `lembar_kode` baris lama = `kode` butirnya, lalu `UPDATE … SET lembar_kode = '1.1' WHERE lembar_kode = '1'`. `down()` membalik keempat kolom. Komentar kepala migrasi menyebut alasan (penomoran DKPS 3.0 bergeser; pencocokan lewat kode supaya baris lama tidak tertukar).

- [ ] **Step 4: Perbarui model dan seeder**

`DkpsButir`: fillable + `kode, pokja_kode, elemen, lembar`; casts `elemen` dan `lembar` → `array`; `dariKode()`, `kodeLembar()`, `lembarMentah()` sesuai Interfaces. `DkpsBaris`: fillable + `lembar_kode, pokja_id`; `pokja()`; ganti `BUTIR_DARI_SIAKAD` → `KODE_DARI_SIAKAD` dan `calonSelisih()` membaca `$this->butir?->kode`; tambahkan `booted()` dengan `creating` sesuai Interfaces (pencarian pokja: `Pokja::withoutGlobalScope('lingkup_periode')->where('periode_id', $baris->periode_id)->where('kode', $kode)->value('id')`). `DkpsBarisTable` saringan `calon_selisih` memakai `whereIn('kode', DkpsBaris::KODE_DARI_SIAKAD)`.

`DkpsButirSeeder`: `bacaJson('dkps-tabel.json', 29)`; dua tahap — pertama `DkpsButir::query()->update(['no' => DB::raw('no + 100')])`, lalu untuk tiap entri `updateOrCreate(['kode' => $b['kode']], ['no' => $b['no'], 'nama', 'label_tabel', 'jendela_data', 'keterangan', 'pokja_kode' => $b['pokja'], 'elemen' => $b['elemen'], 'lembar' => $b['lembar']])`; akhirnya lempar `RuntimeException` bila `pluck('no') !== range(1, 29)` dan tulis info `Butir DKPS: 29 tabel, 35 lembar`. Perbarui komentar "melompati Tabel 13" — tidak lagi benar.

`CekKesiapan:170` → `'29 tabel DKPS', DkpsButir::count() === 29`.

Importer: `ProfilDkpsDasar` ganti `nomorButir(): int` → `kodeButir(): string`, tambah `kodeLembar(array $baris): string`, `simpan()` mengisi `'lembar_kode' => $this->kodeLembar($baris)`; `ImporDtps::kodeButir()` → `'7'`; `ImporKerjaSama::kodeButir()` → `'1'` dan `kodeLembar()` → `'1.1'` (medan `bidang` menyusul di Tugas 6); `ImporPublikasiDtps` konstruktor `string $butir = '27'`; `ImporBarisDkps` konstruktor `string $butir` (dihapus di Tugas 6). `DemoSeeder` baris DKPS: untuk tiap butir pakai lembar pertama, `data` = kunci kolom isian pertama bertipe `teks` (atau kolom pertama) → `'Contoh demonstrasi '.$i`, dan `lembar_kode` kode lembar pertama.

- [ ] **Step 5: Migrasi, seed, uji**

Run:
```bash
docker exec sigap-php php artisan migrate
docker exec sigap-php php artisan db:seed --class=DkpsButirSeeder
docker exec sigap-php php artisan test --filter='ReferensiInstrumenTest|DkpsTest|KriteriaTerimaTest|ImporTempelTest|CekKesiapanTest|PenghapusanDataTest|DemoTest'
```
Expected: PASS seluruhnya; seeder mencetak `Butir DKPS: 29 tabel, 35 lembar`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_11_05_000001_dkps_v3_kode_lembar_dan_pokja.php app/Models/DkpsButir.php app/Models/DkpsBaris.php database/seeders app/Support/Impor app/Console/Commands/CekKesiapan.php app/Filament/Resources/DkpsBaris/Tables/DkpsBarisTable.php tests/Feature/ReferensiInstrumenTest.php tests/Feature/DkpsTest.php
git commit -m "feat(dkps): kode tabel, skema lembar, dan pokja pemilik pada butir dan baris DKPS"
```

---

### Task 3: Wewenang per pokja dan tagihan DKPS milik pokja pemilik

**Files:**
- Modify: `data/izin.json` (aksi `dkps.isi`, `dkps.verifikasi`)
- Modify: `app/Services/PembangkitTagihan.php:44-80`, `app/Enums/JenisTagihan.php:10`
- Test: `tests/Feature/DkpsTest.php:88-115`, `tests/Feature/PembangkitTagihanTest.php:83-101,180`

**Interfaces:**
- Consumes: `DkpsBaris.pokja_id` (Tugas 2).
- Produces: `izin.json`: `dkps.isi` koordinator `pokjanya`, anggota `pokjanya`; `dkps.verifikasi` koordinator `pokjanya`. Tagihan `data_dkps` dibuat satu per tabel (29) pada pokja `butir.pokja_kode`, judul `"Isi {label_tabel} — {nama}"`, total tagihan periode baru 138.

- [ ] **Step 1: Tulis uji yang gagal**

Ganti tiga uji wewenang di `DkpsTest` (`anggota_pokja_data_boleh_mengisi_dkps`, `koordinator_di_luar_pokja_data_tidak_boleh_mengisi_dkps`, `anggota_pokja_data_tidak_boleh_memverifikasi`) dengan:

```php
#[Test]
public function anggota_pokja_pemilik_boleh_mengisi_tabelnya_dan_bukan_tabel_pokja_lain(): void
{
    $sdm = $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM');
    $barisSdm = $this->baris(7);     // Tabel 7 milik POKJA-SDM
    $barisDik = $this->baris(18);    // Tabel 18 milik POKJA-DIK

    $this->assertTrue(Izin::boleh($sdm, 'dkps.isi', $barisSdm));
    $this->assertFalse(Izin::boleh($sdm, 'dkps.isi', $barisDik));
    $this->assertTrue(Izin::boleh($sdm, 'dkps.isi'), 'Tanpa obyek: boleh membuka halaman daftar.');
}

#[Test]
public function anggota_pokja_data_tidak_lagi_otomatis_boleh_mengisi_semua_tabel(): void
{
    $data = $this->pengguna(PeranPengguna::Anggota, 'POKJA-DATA');

    $this->assertFalse(Izin::boleh($data, 'dkps.isi', $this->baris(7)));
}

#[Test]
public function verifikasi_milik_koordinator_pokja_pemilik_dan_ketua(): void
{
    $baris = $this->baris(7);

    $this->assertTrue(Izin::boleh($this->pengguna(PeranPengguna::Koordinator, 'POKJA-SDM'), 'dkps.verifikasi', $baris));
    $this->assertFalse(Izin::boleh($this->pengguna(PeranPengguna::Koordinator, 'POKJA-DIK'), 'dkps.verifikasi', $baris));
    $this->assertFalse(Izin::boleh($this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'), 'dkps.verifikasi', $baris));
    $this->assertTrue(Izin::boleh($this->pengguna(PeranPengguna::Ketua), 'dkps.verifikasi', $baris));
}
```

Di `PembangkitTagihanTest` ganti `setiap_butir_dkps_punya_satu_tagihan_di_pokja_data` dengan:

```php
#[Test]
public function setiap_tabel_dkps_punya_satu_tagihan_di_pokja_pemiliknya(): void
{
    $this->assertSame(29, Tagihan::where('jenis', JenisTagihan::DataDkps)->count());

    $t7 = Tagihan::where('jenis', JenisTagihan::DataDkps)
        ->whereHas('dkpsButir', fn ($q) => $q->where('kode', '7'))->firstOrFail();
    $this->assertSame('POKJA-SDM', $t7->pokja->kode);
    $this->assertSame('Isi Tabel 7 — Dosen Bidang Studi dan Dosen Pengampu', $t7->judul);
    $this->assertSame(0, Tagihan::where('jenis', JenisTagihan::DataDkps)
        ->whereHas('pokja', fn ($q) => $q->where('kode', 'POKJA-DATA'))->count());
}
```

dan ubah `assertSame(137, Tagihan::count())` menjadi `138` (59 narasi + 50 bukti + 29 DKPS).

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter='DkpsTest|PembangkitTagihanTest'`
Expected: FAIL pada ketiga uji baru dan uji tagihan.

- [ ] **Step 3: Ubah `izin.json` dan pembangkit**

`izin.json`: sel `dkps.isi.koordinator`, `dkps.isi.anggota`, `dkps.verifikasi.koordinator` → `"pokjanya"`. Jalankan `docker exec sigap-php php artisan db:seed --class=IzinSeeder` (tabel `izin` dibaca `Izin::matriks()`). `PembangkitTagihan`: pokja tagihan DKPS = `$pokja[$butir->pokja_kode] ?? $pokjaData`; judul `"Isi {$butir->label_tabel} — {$butir->nama}"`; pesan `RuntimeException` POKJA-DATA tetap sebagai cadangan. `JenisTagihan` komentar baris 10 → `data_dkps  satu per tabel DKPS (29), dipegang pokja pemilik tabel`.

- [ ] **Step 4: Jalankan uji**

Run: `docker exec sigap-php php artisan test --filter='DkpsTest|PembangkitTagihanTest|MatriksIzinTest|RingkasanProgresTest|DasborTest|LayarPengerjaanTest'`
Expected: PASS. (`MatriksIzinTest` membaca `izin.json`, jadi 150 sel tetap.) `python3 data/verifikasi.py` keluar 0.

- [ ] **Step 5: Commit**

```bash
git add data/izin.json app/Services/PembangkitTagihan.php app/Enums/JenisTagihan.php tests/Feature/DkpsTest.php tests/Feature/PembangkitTagihanTest.php
git commit -m "feat(dkps): isi dan verifikasi DKPS berlingkup pokja pemilik tabel; tagihan DKPS per tabel"
```

---

### Task 4: `SkemaLembar` — penafsir tunggal skema lembar

**Files:**
- Create: `app/Support/Dkps/TipeKolom.php` (enum), `app/Support/Dkps/SkemaLembar.php`, `app/Exceptions/TahunDiLuarJendela.php`
- Modify: `app/Services/JendelaTs.php`, `app/Models/DkpsButir.php`
- Test: `tests/Feature/SkemaLembarTest.php`, `tests/Feature/JendelaTsTest.php`

**Interfaces:**
- Produces: `JendelaTs::labelDariTahun(Periode $periode, int $tahun): ?string` — `2027→'TS'`, `2025→'TS-2'`, `2023→'TS-4'` pada TS 2027; di luar `[TS-4, TS]` → `null`. `JendelaTs::tahunDalamJendela(Periode, string $jendela, int $tahun): bool`.
- Produces: `enum TipeKolom: string { Teks='teks', Angka='angka', Desimal='desimal', Tanggal='tanggal', Tahun='tahun', Pilihan='pilihan', Centang='centang', Tautan='tautan', Hitung='hitung' }` dengan `label(): string` dan `isian(): bool` (`false` hanya untuk `Hitung`).
- Produces: `final class SkemaLembar` dengan:
  - `public static function dari(DkpsButir $butir, string $kodeLembar): self`
  - `kode(): string`, `judul(): string`, `catatan(): ?string`, `tahunDari(): string`, `butir(): DkpsButir`
  - `kolom(): array<int, array>` (semua, urutan json), `kolomInput(): array`, `kolomHitung(): array`, `kunciWajib(): array<int,string>`, `pilihan(string $kunci): array`
  - `kepala(): array<int, array<int, array{label:string, colspan:int, rowspan:int}>>` — baris kepala untuk render; jumlah baris = 1 + kedalaman `kelompok` terdalam; kolom tanpa kelompok ber-`rowspan` penuh
  - `adaBarisTetap(): bool`, `barisTetap(): array<int, array{kunci:string,label:string,kelompok?:string}>`, `subtotal(): array`, `labelKolomBaris(): ?string`, `kolomNonaktif(string $kunciBaris): array<int,string>`
  - `normalkan(array $data, ?string $kunciBaris = null): array` — trim teks; `angka` → int, `desimal` → float (koma desimal diterima), `centang` → bool (`'V'`, `'v'`, `'1'`, `true` → true), `tanggal` → `'Y-m-d'` dari `d/m/Y`, `Y-m-d`, `Y-m-d H:i:s`; nilai kosong → `null`; kolom di luar skema dibuang; kolom nonaktif → `null`; mengembalikan `data` + `['_baris' => $kunciBaris]` bila baris tetap
  - `validasi(array $dataNormal, ?string $kunciBaris = null): array<string,string>` — kunci kolom ⇒ pesan; `'_baris'` ⇒ pesan aturan baris. Pesan persis: wajib kosong `"{label} wajib diisi."`; angka negatif/bukan bilangan `"{label} harus bilangan bulat ≥ 0."`; desimal `"{label} harus angka."`; tanggal tak terurai `"{label} harus tanggal (dd/mm/yyyy)."`; tahun `"{label} harus tahun empat digit."`; pilihan `"{label} harus salah satu dari: a, b, c."`; tautan `"{label} harus URL yang sah."`; `tepat_satu` `"Pilih tepat satu: {label1}, {label2}, {label3}."`; `minimal_satu` `"Centang minimal satu: …"`; `jumlah_persen_100` `"Jumlah persentase harus 100 (sekarang {jumlah})."`; `tidak_melebihi` `"{label kolom} tidak boleh melebihi {label batas}."`
  - `hitung(array $dataNormal, ?string $kunciBaris = null): array<string, int|float|null>` — nilai kolom hitung: `selisih_tahun` (tahun penuh antara dua tanggal, 0 bila salah satu kosong), `jumlah`, `rata` (abaikan null), `bagi`, `masa_studi_rata` (Tabel 22: bobot 1,2,3 menurut urutan kolom `lulus_akhir_*` relatif terhadap baris `TS-2`/`TS-1`/`TS`, dibagi jumlah lulusan; 0 bila nol)
  - `lengkap(array $dataNormal, ?string $kunciBaris = null): bool` — `validasi()` kosong dan semua kolom wajib terisi; pada baris tetap semua kolom isian non-nonaktif terisi
  - `kunciBaris(array $dataNormal, ?string $kunciBaris = null): string` — baris tetap: `NormalisasiKunci::dari($kodeTabel, $kodeLembar, $kunciBaris)`; bebas: `NormalisasiKunci::dari($kodeTabel, $kodeLembar, ...nilai kolom kunci)`
  - `tahunAcuan(Periode $periode, array $dataNormal, ?string $kunciBaris = null): string` — `TS` → `'TS'`; `baris_tetap` → kunci baris; `kolom:x` → tahun dari nilai kolom (`Y` dari tanggal atau tahun) → `JendelaTs::labelDariTahun`; lempar `TahunDiLuarJendela` (pesan `"Tahun {tahun} di luar jendela data {keterangan jendela tabel}."`) bila `null` atau bila tahun tidak masuk `JendelaTs::tahunDalamJendela($periode, $butir->jendela_data, $tahun)`
- Produces: `DkpsButir::skema(string $kodeLembar): SkemaLembar`.

- [ ] **Step 1: Tulis uji yang gagal** (`tests/Feature/SkemaLembarTest.php`, `RefreshDatabase`, seed `DatabaseSeeder`; periode TS diset 2027 lewat `Periode::firstOrFail()->forceFill(['ts_tahun' => 2027])->save()`)

```php
#[Test] public function kepala_tabel_8_berkedalaman_tiga_baris(): void
{   $k = DkpsButir::dariKode('8')->skema('8')->kepala();
    $this->assertCount(3, $k);
    $this->assertSame(['label' => 'Nama DTPS', 'colspan' => 1, 'rowspan' => 3], $k[0][1]);  // indeks 0 = kolom "No."
    $this->assertSame(6, $k[0][2]['colspan']);   // "Beban Kerja Mengajar …" membentang C:H
    $this->assertSame('Pendidikan: Pembelajaran dan Pembimbingan', $k[1][0]['label']);
    $this->assertSame(3, $k[1][0]['colspan']); }

#[Test] public function tanggal_dinormalkan_dari_tiga_bentuk(): void
{   $s = DkpsButir::dariKode('3')->skema('3');
    foreach (['20/06/2026', '2026-06-20', '2026-06-20 00:00:00'] as $masukan) {
        $this->assertSame('2026-06-20', $s->normalkan(['tanggal_perolehan' => $masukan])['tanggal_perolehan']); }
    $this->assertArrayHasKey('tanggal_perolehan', $s->validasi($s->normalkan(['tanggal_perolehan' => 'Juni 2026'])));
}

#[Test] public function jumlah_persen_harus_100(): void
{   $s = DkpsButir::dariKode('5')->skema('5');
    $galat = $s->validasi($s->normalkan(['sangat_baik' => '94,32', 'baik' => '5.68', 'cukup' => 0, 'kurang_baik' => 1], 'reliability'), 'reliability');
    $this->assertSame('Jumlah persentase harus 100 (sekarang 101).', $galat['_baris']);
    $this->assertSame([], $s->validasi($s->normalkan(['sangat_baik' => '94,32', 'baik' => '5.68', 'cukup' => 0, 'kurang_baik' => 0], 'reliability'), 'reliability')); }

#[Test] public function tepat_satu_tingkat_pada_kerja_sama(): void
{   $s = DkpsButir::dariKode('1')->skema('1.2');
    $d = $s->normalkan(['lembaga_mitra' => 'UiTM', 'judul_kegiatan' => 'Riset', 'tanggal_awal' => '19/03/2023', 'nasional' => 'V', 'internasional' => 'V']);
    $this->assertSame('Pilih tepat satu: Wilayah/Lokal, Nasional, Internasional.', $s->validasi($d)['_baris']);
    $this->assertSame(5, $s->hitung($d + ['tanggal_akhir' => '2028-03-19'])['durasi_tahun']); }

#[Test] public function kolom_nonaktif_tabel_22_dan_rata_masa_studi(): void
{   $s = DkpsButir::dariKode('22')->skema('22');
    $this->assertSame(['lulus_akhir_ts2', 'lulus_akhir_ts1'], $s->kolomNonaktif('TS'));
    $d = $s->normalkan(['diterima' => 100, 'lulus_akhir_ts2' => 60, 'lulus_akhir_ts1' => 30, 'lulus_akhir_ts' => 10], 'TS-2');
    $this->assertSame(100, $s->hitung($d, 'TS-2')['jumlah_lulusan']);
    $this->assertSame(1.5, $s->hitung($d, 'TS-2')['rata_masa_studi']);   // (60·1 + 30·2 + 10·3)/100
    $this->assertSame('TS-2', $s->tahunAcuan(Periode::firstOrFail(), $d, 'TS-2')); }

#[Test] public function tahun_acuan_dari_kolom_dan_penolakan_di_luar_jendela(): void
{   $p = Periode::firstOrFail(); $s = DkpsButir::dariKode('9')->skema('9');
    $this->assertSame('TS-1', $s->tahunAcuan($p, ['tahun' => 2026]));
    $this->expectException(TahunDiLuarJendela::class);
    $this->expectExceptionMessage('Tahun 2019 di luar jendela data TS-2 s.d. TS (2025–2027).');
    $s->tahunAcuan($p, ['tahun' => 2019]); }

#[Test] public function kunci_baris_dan_kelengkapan(): void
{   $s = DkpsButir::dariKode('28')->skema('28');
    $d = $s->normalkan(['nama_dtps' => ' Prof. Firmansyah ', 'judul_artikel' => 'Judul', 'jumlah_sitasi' => '65']);
    $this->assertSame(NormalisasiKunci::dari('28', '28', 'Prof. Firmansyah', 'Judul'), $s->kunciBaris($d));
    $this->assertTrue($s->lengkap($d));
    $this->assertFalse($s->lengkap($s->normalkan(['nama_dtps' => 'X']))); }
```

Di `JendelaTsTest` tambahkan `label_dari_tahun_dan_tahun_dalam_jendela()` yang menguji nilai pada Interfaces (TS 2027: 2027→TS, 2025→TS-2, 2023→TS-4, 2022→null, 2028→null; `tahunDalamJendela($p, 'TS-4 s.d. TS-2', 2024)` true, `(…, 2026)` false).

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter='SkemaLembarTest|JendelaTsTest'`
Expected: FAIL (kelas tidak ditemukan).

- [ ] **Step 3: Implementasikan `TipeKolom`, `TahunDiLuarJendela extends InvalidArgumentException`, `JendelaTs` dua metode baru, `SkemaLembar`, `DkpsButir::skema()`**

`kepala()`: bangun pohon kelompok dari `kelompok` tiap kolom (jalur `[]` untuk kolom tanpa kelompok); kedalaman = 1 + maks panjang jalur; kolom "No." (atau `labelKolomBaris()` pada baris tetap) selalu sel pertama ber-`rowspan` penuh. Kolom berjalur lebih pendek dari kedalaman mendapat `rowspan` = kedalaman − panjang jalur pada baris terakhirnya. Penghitung `selisih_tahun` memakai `Carbon::parse($dari)->diffInYears($sampai)` dibulatkan ke bawah. Tidak ada tahun mati: semua lewat `JendelaTs`.

- [ ] **Step 4: Jalankan uji**

Run: `docker exec sigap-php php artisan test --filter='SkemaLembarTest|JendelaTsTest'`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Dkps app/Exceptions/TahunDiLuarJendela.php app/Services/JendelaTs.php app/Models/DkpsButir.php tests/Feature/SkemaLembarTest.php tests/Feature/JendelaTsTest.php
git commit -m "feat(dkps): SkemaLembar — penafsir skema lembar DKPS (kepala, normalisasi, validasi, hitung, tahun acuan)"
```

---

### Task 5: `PengisiDkps` — layanan tulis baris DKPS

**Files:**
- Create: `app/Services/PengisiDkps.php`, `app/Exceptions/IsianDkpsTidakSah.php`
- Test: `tests/Feature/PengisiDkpsTest.php`

**Interfaces:**
- Consumes: `SkemaLembar` (Tugas 4), `Izin`, `PengelolaBukti::simpanTautan(string $url, array $atribut, User $oleh): Bukti`, `VerifikatorDkps::batalkan()`.
- Produces: `IsianDkpsTidakSah extends RuntimeException` dengan `public readonly array $galat` (kunci ⇒ pesan) dan pesan `"Isian belum sah: "` + gabungan pesan.
- Produces: `class PengisiDkps` dengan:
  - `simpan(DkpsButir $butir, string $kodeLembar, Periode $periode, array $data, SumberData $sumber, User $oleh, ?DkpsBaris $baris = null, ?string $kunciBaris = null): DkpsBaris` — normalkan → validasi (lempar `IsianDkpsTidakSah`) → `tahunAcuan` (lempar `TahunDiLuarJendela`) → otorisasi `Izin::boleh($oleh, 'dkps.isi', $calon)` (lempar `AuthorizationException`; `$calon` = baris yang ada, atau `new DkpsBaris` dengan `pokja_id` dari `Pokja` berkode `butir.pokja_kode` pada periode) → tulis `prodi_id = $periode->prodi_id`, `periode_id`, `dkps_butir_id`, `lembar_kode`, `tahun_acuan`, `data`, `sumber`, `kunci_normal` → bila `$baris` sudah `terverifikasi()`: pengisi tanpa `dkps.verifikasi` ditolak (`AuthorizationException` pesan `"Baris sudah diverifikasi; hanya verifikator yang boleh mengubahnya."`), pemegang `dkps.verifikasi` boleh dan verifikasi dicabut lewat `VerifikatorDkps::batalkan()`. Baris tetap: cari baris yang ada lewat `kunci_normal` bila `$baris` null (upsert).
  - `hapus(DkpsBaris $baris, User $oleh): void` — `dkps.isi` wajib; baris terverifikasi ditolak (`RuntimeException` `"Baris terverifikasi tidak bisa dihapus; cabut verifikasinya lebih dulu."`); baris tetap tidak dihapus melainkan `data` dikosongkan? **Tidak**: baris tetap boleh di-soft-delete (sama dengan mengosongkan).
  - `barisLembar(DkpsButir $butir, string $kodeLembar, Periode $periode): Collection<int, DkpsBaris>` urut `created_at`, eager `bukti`, `verifikator`.
  - `tautkanBuktiTautan(DkpsBaris $baris, string $url, array $atribut, User $oleh): Bukti` — `bukti.unggah` wajib; `simpanTautan($url, $atribut + ['prodi_id','periode_id' dari baris], $oleh)`; `$baris->bukti()->syncWithoutDetaching([$bukti->id])`.

- [ ] **Step 1: Tulis uji yang gagal** (`PengisiDkpsTest`, seed `DatabaseSeeder`, TS 2027, helper `pengguna()` seperti `DkpsTest`)

```php
#[Test] public function menyimpan_baris_bebas_mengisi_tahun_kunci_dan_pokja(): void
{   $b = $this->pengisi->simpan(DkpsButir::dariKode('9'), '9', $this->periode,
        ['nama_dtps' => 'Dr. Ani', 'deskripsi_rekognisi' => 'Reviewer', 'jenis_rekognisi' => 'Editor atau mitra bestari', 'tahun' => '2026', 'tingkat' => 'Nasional'],
        SumberData::Manual, $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM'));
    $this->assertSame('TS-1', $b->tahun_acuan); $this->assertSame('9', $b->lembar_kode);
    $this->assertSame(Pokja::where('kode', 'POKJA-SDM')->firstOrFail()->id, $b->pokja_id);
    $this->assertSame(2026, $b->nilai('tahun')); $this->assertNotNull($b->kunci_normal); }

#[Test] public function baris_dengan_tahun_di_luar_jendela_ditolak(): void
{   $this->expectException(TahunDiLuarJendela::class);
    $this->pengisi->simpan(DkpsButir::dariKode('3'), '3', $this->periode,
        ['nama_kegiatan' => 'LKTI', 'tanggal_perolehan' => '01/05/2019', 'nasional' => 'V', 'prestasi_dicapai' => 'Juara 1'],
        SumberData::Manual, $this->pengguna(PeranPengguna::Ketua)); }

#[Test] public function kerja_sama_lama_tetap_diterima(): void
{   $b = $this->pengisi->simpan(DkpsButir::dariKode('1'), '1.1', $this->periode,
        ['lembaga_mitra' => 'UniSZA', 'internasional' => 'V', 'judul_kegiatan' => 'Seminar', 'tanggal_awal' => '25/07/2019', 'tanggal_akhir' => '25/07/2028'],
        SumberData::Manual, $this->pengguna(PeranPengguna::Ketua));
    $this->assertSame('TS', $b->tahun_acuan); }

#[Test] public function isian_tidak_sah_melempar_galat_per_kolom(): void
{   try { $this->pengisi->simpan(DkpsButir::dariKode('28'), '28', $this->periode, ['nama_dtps' => '', 'jumlah_sitasi' => -1], SumberData::Manual, $this->pengguna(PeranPengguna::Ketua)); $this->fail(); }
    catch (IsianDkpsTidakSah $e) { $this->assertSame(['nama_dtps', 'judul_artikel', 'jumlah_sitasi'], array_keys($e->galat)); } }

#[Test] public function anggota_pokja_lain_ditolak(): void
{   $this->expectException(AuthorizationException::class);
    $this->pengisi->simpan(DkpsButir::dariKode('18'), '18', $this->periode, ['kode_mk' => 'PG1', 'nama_mk' => 'X'], SumberData::Manual, $this->pengguna(PeranPengguna::Anggota, 'POKJA-SDM')); }

#[Test] public function baris_tetap_diperbarui_bukan_digandakan(): void
{   $butir = DkpsButir::dariKode('21'); $ketua = $this->pengguna(PeranPengguna::Ketua);
    $this->pengisi->simpan($butir, '21', $this->periode, ['jumlah_lulusan' => 100, 'ipk_rata' => '3,8'], SumberData::Siakad, $ketua, null, 'TS-2');
    $this->pengisi->simpan($butir, '21', $this->periode, ['jumlah_lulusan' => 104, 'ipk_rata' => '3,84'], SumberData::Siakad, $ketua, null, 'TS-2');
    $this->assertSame(1, DkpsBaris::where('lembar_kode', '21')->count());
    $this->assertSame(104, DkpsBaris::where('lembar_kode', '21')->first()->nilai('jumlah_lulusan'));
    $this->assertSame('TS-2', DkpsBaris::where('lembar_kode', '21')->first()->tahun_acuan); }

#[Test] public function sunting_baris_terverifikasi_mencabut_verifikasi(): void
{   // siapkan baris Tabel 28 + bukti layak + verifikasi oleh ketua (lihat DkpsTest::buktiLayak)
    // anggota POKJA-SDM: AuthorizationException "Baris sudah diverifikasi; hanya verifikator yang boleh mengubahnya."
    // koordinator POKJA-SDM: berhasil, $baris->fresh()->terverifikasi() === false
}

#[Test] public function hapus_baris_terverifikasi_ditolak_dan_tautkan_bukti_menautkan(): void
{   // hapus() pada baris terverifikasi → RuntimeException; tautkanBuktiTautan() dengan URL Drive sah → $baris->bukti()->count() === 1
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter=PengisiDkpsTest`
Expected: FAIL (kelas tidak ditemukan).

- [ ] **Step 3: Implementasikan `PengisiDkps` dan `IsianDkpsTidakSah` sesuai Interfaces**

Seluruh penulisan dalam `DB::transaction`. Catat ke `LogAktivitas` bila layanan lain di proyek melakukannya (ikuti pola `VerifikatorDkps`; bila tidak ada pola, lewati).

- [ ] **Step 4: Jalankan uji**

Run: `docker exec sigap-php php artisan test --filter='PengisiDkpsTest|DkpsTest'`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PengisiDkps.php app/Exceptions/IsianDkpsTidakSah.php tests/Feature/PengisiDkpsTest.php
git commit -m "feat(dkps): PengisiDkps — simpan, hapus, dan tautkan bukti baris DKPS berdasarkan skema lembar"
```

---

### Task 6: Impor tempel per lembar (`ImporLembarDkps`)

**Files:**
- Create: `app/Support/Impor/ImporLembarDkps.php`
- Delete: `app/Support/Impor/ImporBarisDkps.php`
- Modify: `app/Support/Impor/ImporKerjaSama.php` (medan `bidang` → lembar), `app/Support/Impor/ProfilDkpsDasar.php` (`simpan()` memakai `PengisiDkps` bila subkelas menyediakan skema? **Tidak** — tetap `DkpsBaris::create`, cukup isi `lembar_kode`)
- Test: `tests/Feature/ImporLembarDkpsTest.php`, `tests/Feature/ImporTempelTest.php` (hapus rujukan `ImporBarisDkps` bila ada)

**Interfaces:**
- Consumes: `ProfilImpor` (lihat `app/Support/Impor/ProfilImpor.php`), `SkemaLembar`, `PengisiDkps`.
- Produces: `ImporLembarDkps(string $periodeId, string $prodiId, string $penggunaId, string $kodeTabel, string $kodeLembar) implements ProfilImpor`:
  - `nama()` → `"{judul lembar}"`; `medan()` → `['sumber' => [...]]` + (bila baris tetap) `['_baris' => ['label' => labelKolomBaris, 'wajib' => true, 'tipe' => 'pilihan', 'contoh' => kunci baris pertama]]` + satu medan per kolom isian (`label`, `wajib`, `tipe` = nilai `TipeKolom`, `contoh` = `''`).
  - `normalkan()` → `SkemaLembar::normalkan()` + `sumber` huruf kecil (bawaan `manual`) + `_kunci` = `SkemaLembar::kunciBaris()`; `_baris` dicocokkan longgar ke label/kunci baris tetap (`NormalisasiKunci` pada label).
  - `validasi()` → pesan `SkemaLembar::validasi()` + pesan sumber + pesan `TahunDiLuarJendela` bila ada.
  - `simpan()`/`perbarui()` → `PengisiDkps::simpan()`; `cariYangAda()` → `DkpsBaris` dengan `periode_id`, `dkps_butir_id`, `lembar_kode`, `kunci_normal`.
- Produces: `ImporKerjaSama::medan()` bertambah `bidang` (`pilihan`, contoh `pendidikan`), `kodeLembar()` memetakan `pendidikan→'1.1'`, `penelitian→'1.2'`, `pkm→'1.3'` (bawaan `'1.1'`).

- [ ] **Step 1: Tulis uji yang gagal** (`ImporLembarDkpsTest`)

```php
#[Test] public function medan_mengikuti_kolom_isian_lembar(): void
{   $p = new ImporLembarDkps($this->periode->id, $this->prodi->id, $this->ketua->id, '28', '28');
    $this->assertSame(['sumber', 'nama_dtps', 'judul_artikel', 'jumlah_sitasi'], array_keys($p->medan()));
    $this->assertTrue($p->medan()['jumlah_sitasi']['wajib']); }

#[Test] public function tempelan_tabel_28_masuk_dengan_deteksi_duplikat(): void
{   $tempel = "Nama DTPS\tJudul\tJumlah Sitasi\nProf. Firmansyah\tFactors affecting\t65\nProf. Firmansyah\tFactors affecting\t65\nProf. Firmansyah\tSEIR model\t52";
    // urai → tebakPemetaan → PratinjauImpor::susun → PelaksanaImpor::jalankan (pola ImporTempelTest)
    // ringkas: 3 baris — 2 baru, 1 kembar di tempelan; DkpsBaris lembar '28' berjumlah 2; nilai('jumlah_sitasi') === 65 (int)
}

#[Test] public function lembar_baris_tetap_memetakan_label_baris(): void
{   // profil '21'/'21'; tempelan "Tahun Lulus\tJumlah Lulusan\tRata-rata\nTS-2\t104\t3,84" → satu baris kunci_normal NormalisasiKunci::dari('21','21','TS-2'), tahun_acuan 'TS-2'
}

#[Test] public function kerja_sama_memilih_lembar_dari_bidang(): void
{   // ImporKerjaSama dengan bidang 'penelitian' → lembar_kode '1.2'
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter=ImporLembarDkpsTest`
Expected: FAIL.

- [ ] **Step 3: Implementasikan `ImporLembarDkps`, ubah `ImporKerjaSama`, hapus `ImporBarisDkps`**

`git rm app/Support/Impor/ImporBarisDkps.php`; bersihkan rujukan di `ImporTempelTest`/dokumen komentar.

- [ ] **Step 4: Jalankan uji**

Run: `docker exec sigap-php php artisan test --filter='ImporLembarDkpsTest|ImporTempelTest|DkpsTest'`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A app/Support/Impor tests/Feature/ImporLembarDkpsTest.php tests/Feature/ImporTempelTest.php
git commit -m "feat(dkps): impor tempel per lembar DKPS mengikuti skema kolom; ImporBarisDkps dihapus"
```

---

### Task 7: Komponen `LembarDkps` dan halaman satu tabel

**Files:**
- Create: `app/Livewire/LembarDkps.php`, `resources/views/livewire/lembar-dkps.blade.php`
- Create: `app/Filament/Pages/TabelDkpsLayar.php`, `resources/views/filament/pages/tabel-dkps.blade.php`
- Modify: `resources/css/filament/panel/theme.css` (kelas `.sigap-dkps`, `.sigap-sel-isian`, `.sigap-sel-hitung`, `.sigap-sel-nonaktif`, `.sigap-sel-galat`)
- Test: `tests/Feature/LembarDkpsTest.php`

**Interfaces:**
- Consumes: `SkemaLembar`, `PengisiDkps`, `VerifikatorDkps`, `ImporTempel` + `ImporLembarDkps`, `Izin`.
- Produces: `class LembarDkps extends Livewire\Component` — properti publik `string $butirId; string $kodeLembar; string $periodeId; int $halaman = 1; string $sumberBaru = 'manual'; array $baris = []; array $galat = []; array $baru = []; ?string $disimpanTerakhir = null; string $buktiUrl = ''; string $buktiJudul = ''; ?string $buktiTanggal = null; string $buktiSumber = 'manual'; ?string $buktiUntuk = null;` metode `mount(string $butirId, string $kodeLembar, string $periodeId)`, `muat(): void`, `skema(): SkemaLembar`, `bolehIsi(): bool` (`Izin::boleh(user, 'dkps.isi', $calonBaris)`), `bolehVerifikasi(DkpsBaris): bool`, `simpan(string $id): void` (`$id` = uuid baris, `tetap:<kunci>`, atau `baru:<indeks>`), `tambahBaris(): void`, `hapusBaris(string $id): void`, `verifikasi(string $id): void`, `bukaBukti(string $id): void`, `tautkanBukti(): void`, `render()` → `view('livewire.lembar-dkps')`. Konstanta `PER_HALAMAN = 50`. Setiap aksi tulis memanggil `PengisiDkps`; `AuthorizationException` diterjemahkan `abort(403)`; `IsianDkpsTidakSah`/`TahunDiLuarJendela` mengisi `$galat[$id]`.
- Produces: `class TabelDkpsLayar extends Filament\Pages\Page` — `$slug = 'dkps/tabel/{kode}'`, `shouldRegisterNavigation(): false`, `canAccess()` → `Izin::boleh(user, 'tagihan.lihat')`, properti `public DkpsButir $butir; public string $lembar;` `mount(string $kode)` (`DkpsButir::dariKode`, `abort(404)` bila tak ada; `$lembar = request('lembar', $butir->kodeLembar()[0])`), `getTitle()` → `"{label_tabel} — {nama}"`, metode `periode(): Periode` (aktif), `jendela(): string` (`JendelaTs::keterangan`), `tabLembar(): array<string,string>` (kode ⇒ judul), `tetangga(): array{sebelum:?DkpsButir, sesudah:?DkpsButir}` (tabel pokja yang sama berurutan `no`), `getHeaderActions()` → aksi `impor_tempel` (modal 5xl berisi `Livewire::make(ImporTempel::class, ['profilKelas' => ImporLembarDkps::class, 'profilArgs' => [periodeId, prodiId, userId, kode, lembar], 'periodeId' => …])->key("impor-{$kode}-{$lembar}")`, `visible` bila `bolehIsi`) dan aksi tautan `verifikasi_dkps` ke `DkpsBarisResource::getUrl()` bagi pemegang `dkps.verifikasi`. URL pembantu statis `TabelDkpsLayar::urlUntuk(string $kode, ?string $lembar = null): string`.

- [ ] **Step 1: Tulis uji yang gagal** (`LembarDkpsTest`, seed `DemoSeeder`, `Livewire::test`)

```php
#[Test] public function lembar_menampilkan_kepala_bertingkat_dan_nomor_kolom_excel(): void
{   $this->actingAs($this->anggota('POKJA-SDM'));
    Livewire::test(LembarDkps::class, ['butirId' => DkpsButir::dariKode('8')->id, 'kodeLembar' => '8', 'periodeId' => $this->periode->id])
        ->assertSeeHtml('Pendidikan: Pembelajaran dan Pembimbingan')->assertSee('PS yang Diakreditasi')
        ->assertSeeHtml('class="sigap-sel-isian')->assertSeeHtml('colspan="6"')->assertSee('Tambah baris'); }

#[Test] public function menyimpan_baris_baru_lewat_komponen(): void
{   $this->actingAs($this->anggota('POKJA-SDM'));
    Livewire::test(LembarDkps::class, [...tabel 28...])->call('tambahBaris')
        ->set('baru.0.nama_dtps', 'Dr. Ani')->set('baru.0.judul_artikel', 'Judul')->set('baru.0.jumlah_sitasi', '12')
        ->call('simpan', 'baru:0')->assertHasNoErrors();
    $this->assertSame(1, DkpsBaris::where('lembar_kode', '28')->count()); }

#[Test] public function galat_validasi_tampil_di_bawah_sel(): void
{   // set jumlah_sitasi '-3' → call simpan → assertSee('Jumlah Sitasi harus bilangan bulat ≥ 0.') dan tidak ada baris tersimpan
}

#[Test] public function lembar_baris_tetap_tidak_punya_tombol_tambah_dan_menampilkan_semua_baris(): void
{   // tabel 2: assertDontSee('Tambah baris'), assertSee('TS-2'), assertSee('TS-1'), assertSeeHtml('Jumlah')
}

#[Test] public function koordinator_pokja_lain_tidak_bisa_menyimpan(): void
{   $this->actingAs($this->koordinator('POKJA-DIK'));
    Livewire::test(LembarDkps::class, [...tabel 7...])->assertDontSee('Tambah baris')->call('tambahBaris')->assertForbidden(); }

#[Test] public function halaman_tabel_terbuka_dengan_tab_lembar_dan_jendela(): void
{   $this->actingAs($this->anggota('POKJA-MUTU'));
    $this->get('/panel/dkps/tabel/1?lembar=1.2')->assertOk()->assertSee('Tabel 1 — Kerjasama Tridharma Perguruan Tinggi')
        ->assertSee('Bagian 2 - Kerjasama Bidang Penelitian')->assertSee('TS-2 s.d. TS (')->assertSee('POKJA-MUTU');
    $this->get('/panel/dkps/tabel/99')->assertNotFound(); }

#[Test] public function tautkan_bukti_dari_baris(): void
{   // Http::fake tautan Drive 200; call bukaBukti($id) → set buktiUrl/buktiJudul/buktiTanggal → call tautkanBukti → $baris->bukti()->count() === 1
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter=LembarDkpsTest`
Expected: FAIL.

- [ ] **Step 3: Implementasikan komponen, Blade, halaman, dan CSS**

Blade: `<div class="overflow-x-auto"><table class="sigap-dkps">` — `<thead>` dari `skema()->kepala()` (baris kepala + baris nomor Excel dari `nomor_excel`, kolom "No." bernomor `1`), lalu kolom tambahan di kanan: Bukti, Sumber, Verifikasi, Aksi (dipisah garis tebal dan latar berbeda, tidak diberi nomor Excel). `<tbody>`: baris tetap dari `skema()->barisTetap()` (label di kolom pertama, data dari `$baris[kunci]` bila ada), atau baris bebas `$baris` (halaman `$halaman`) + `$baru`. Kontrol per tipe seperti spesifikasi B.5; `wire:model.blur="baris.{id}.{kunci}"` dan `wire:blur="simpan('{id}')"` pada tiap sel; sel hitung menampilkan `skema()->hitung()`; baris subtotal/jumlah/rata di bawah. Galat `$galat[$id][$kunci]` di bawah sel dengan kelas `sigap-sel-galat`; galat `_baris` di kolom Aksi. Hanya-baca bila `! bolehIsi()`: kontrol diganti teks. Baris lama yang kunci `data`-nya tidak satu pun cocok dengan kolom skema (isian `KeyValue` sebelum DKPS 3.0) tidak dicampur ke grid: ditampilkan di bawahnya dalam blok "Baris lama yang perlu dipindahkan" berisi pasangan kunci–nilai mentah dan tombol hapus, sesuai spesifikasi B.8. Navigasi halaman sederhana (`halaman`, total). Modal bukti memakai `<x-filament::modal>` dengan `id="bukti-{kodeLembar}"`.

CSS (`theme.css`, setelah `@source`): `.sigap-dkps{border-collapse:collapse;font-size:.8125rem}` `.sigap-dkps th,.sigap-dkps td{border:1px solid rgb(203 213 225);padding:.25rem .375rem;vertical-align:top}` `.sigap-dkps thead th{background:rgb(241 245 249);text-align:center;font-weight:600}` `.sigap-sel-isian{background:#fff9c4}` `.sigap-sel-hitung{background:rgb(229 231 235);text-align:right}` `.sigap-sel-nonaktif{background:repeating-linear-gradient(45deg,#e5e7eb 0 4px,#f3f4f6 4px 8px)}` `.sigap-sel-galat{border-color:rgb(220 38 38)!important;background:#fee2e2}` ditambah varian `dark:`. Jalankan `docker exec -w /var/www/html/sigap laravel-node22 npm run build`.

- [ ] **Step 4: Jalankan uji dan lihat di peramban**

Run: `docker exec sigap-php php artisan test --filter='LembarDkpsTest|TampilanTest'`
Expected: PASS. Buka `http://localhost:8021/panel/dkps/tabel/8` sebagai `koordinator@sigap.test` (data demo) dan pastikan kepala tiga baris, sel kuning, kolom hitung abu-abu.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/LembarDkps.php resources/views/livewire/lembar-dkps.blade.php app/Filament/Pages/TabelDkpsLayar.php resources/views/filament/pages/tabel-dkps.blade.php resources/css/filament/panel/theme.css public/build tests/Feature/LembarDkpsTest.php
git commit -m "feat(dkps): lembar DKPS bergaya Excel — komponen LembarDkps dan halaman tabel per pokja"
```

---

### Task 8: Daftar Tabel DKPS, progres per pokja, menu, dan ubin K6

**Files:**
- Create: `app/Filament/Pages/DkpsLayar.php`, `resources/views/filament/pages/dkps.blade.php`
- Modify: `app/Services/RingkasanProgres.php:170-190`, `app/Filament/Pages/Dasbor.php:167-175`, `resources/views/filament/pages/dasbor.blade.php:199-212`
- Modify: `data/menu.json` (tambah `dkps_lembar`; ubah `dkps_baris`), `app/Filament/Resources/DkpsBaris/DkpsBarisResource.php` (label "Verifikasi DKPS", `navigationSort` 20)
- Test: `tests/Feature/RingkasanProgresTest.php`, `tests/Feature/MenuPeranTest.php:36-56`, `tests/Feature/DasborTest.php:144-160`, `tests/Feature/DkpsLayarTest.php`

**Interfaces:**
- Consumes: `SkemaLembar::lengkap()`, `DkpsBaris` per `lembar_kode`.
- Produces: `RingkasanProgres::statusLembar(Periode $periode, DkpsButir $butir, string $kodeLembar): array{baris:int, lengkap:int, terverifikasi:int, target:?int, status:'kosong'|'terisi'|'lengkap'|'terverifikasi'}` (definisi spesifikasi B.6; `target` = cacah baris tetap atau null); `RingkasanProgres::progresDkps(Periode): array{terverifikasi:int, total:int, rasio:float}` kini menghitung **lembar** (total 35); `RingkasanProgres::progresDkpsPerPokja(Periode): array<int, array{kode:string, nama:string, total:int, terisi:int, lengkap:int, terverifikasi:int, rasio:float, tabel: array<int, array{butir:DkpsButir, lembar: array<string, array>}>}>` terurut kode pokja, hanya pokja yang memiliki tabel.
- Produces: `DkpsLayar` — `$slug = 'dkps'`, grup `Data`, `navigationSort 5`, label "Tabel DKPS", `canAccess()` → `Izin::boleh(user, 'tagihan.lihat')`, `shouldRegisterNavigation()` → `canAccess() && Izin::bolehMenu(user, 'dkps_lembar')`, metode `pokjaTerlihat(): array` (semua bila `in_array('ya', Izin::lingkup(user, 'tagihan.lihat'))`, selain itu `user->idPokjanya()`), `progres(): array` (hasil `progresDkpsPerPokja` tersaring), `tagihanTabel(DkpsButir): ?Tagihan` (jenis `data_dkps` periode aktif).
- Produces: `menu.json` menu `dkps_lembar` ("Tabel DKPS", Data): admin tidak, ketua ya, pimpinan ya, koordinator ya, anggota ya, auditor ya; `dkps_baris` ("Verifikasi DKPS"): admin tidak, ketua ya, pimpinan tidak, koordinator ya, anggota tidak, auditor tidak. `MenuPeranTest::KELAS` bertambah `'dkps_lembar' => DkpsLayar::class`; jumlah sel 126.

- [ ] **Step 1: Tulis uji yang gagal**

`RingkasanProgresTest`:
```php
#[Test] public function status_lembar_mengikuti_kelengkapan_dan_verifikasi(): void
{   // Tabel 28 kosong → 'kosong', baris 0; satu baris lengkap belum verifikasi → 'lengkap', lengkap 1; diverifikasi → 'terverifikasi'
    // Tabel 21 (baris tetap, target 3) dengan satu baris TS-2 terisi → 'terisi', target 3
}
#[Test] public function progres_dkps_dihitung_per_lembar_dari_35(): void
{   $this->assertSame(35, $this->ringkasan->progresDkps($this->periode)['total']); }
#[Test] public function progres_dkps_per_pokja_hanya_pokja_pemilik_tabel(): void
{   $p = collect($this->ringkasan->progresDkpsPerPokja($this->periode));
    $this->assertSame(['POKJA-DIK', 'POKJA-MAWA', 'POKJA-MUTU', 'POKJA-SARPRAS', 'POKJA-SDM'], $p->pluck('kode')->all());
    $this->assertSame(3, $p->firstWhere('kode', 'POKJA-MUTU')['total']);     // 1.1, 1.2, 1.3
    $this->assertSame(13, $p->firstWhere('kode', 'POKJA-SDM')['total']);     // 6–13 (8) + 25–29 (5)
    $this->assertSame(35, $p->sum('total')); }
```
`DkpsLayarTest`: ketua `GET /panel/dkps` 200, melihat "POKJA-SDM" dan "POKJA-DIK" dan "Tabel 7"; anggota POKJA-SDM melihat "Tabel 7" tetapi tidak "Tabel 18"; pimpinan 200 tanpa tombol tambah. `MenuPeranTest`: tambah `dkps_lembar` ke `KELAS`; sesuaikan angka 120 → 126 di komentar/penghitung bila ada. `DasborTest`: K6 menampilkan `"/ 35 lembar"`.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `docker exec sigap-php php artisan test --filter='RingkasanProgresTest|DkpsLayarTest|MenuPeranTest|DasborTest'`
Expected: FAIL.

- [ ] **Step 3: Implementasikan**

`statusLembar` memuat baris lembar sekali (`DkpsBaris::where(periode, butir, lembar_kode)->get()`) lalu memakai `skema->lengkap($b->data, $b->nilai('_baris'))`. `progresDkpsPerPokja` memuat semua baris periode sekali (`groupBy` butir+lembar) agar bukan N+1. Dasbor K6: angka `"{terverifikasi} / {total} lembar"`, daftar mini per pokja (`nama pendek`, `terverifikasi/total`), tautan "Buka Tabel DKPS" ke `DkpsLayar::getUrl()`. `dkps.blade.php`: per pokja sebuah kartu berisi kepala ringkas dan tabel baris = lembar (kode, judul, baris/target, lengkap, terverifikasi, lencana status warna `gray/warning/info/success`, tenggat & PJ dari `tagihanTabel`), tiap baris tertaut ke `TabelDkpsLayar::urlUntuk(kode, lembar)`. `data/menu.json` sesuai Interfaces, `DkpsBarisResource` label dan urutan.

- [ ] **Step 4: Jalankan uji**

Run: `docker exec sigap-php php artisan test --filter='RingkasanProgresTest|DkpsLayarTest|MenuPeranTest|DasborTest|PanelTest|SaringanTabelTest'`
Expected: PASS. `python3 data/verifikasi.py` keluar 0. `npm run build` bila Blade baru menambah kelas.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/DkpsLayar.php resources/views/filament/pages/dkps.blade.php app/Services/RingkasanProgres.php app/Filament/Pages/Dasbor.php resources/views/filament/pages/dasbor.blade.php data/menu.json app/Filament/Resources/DkpsBaris/DkpsBarisResource.php public/build tests/Feature
git commit -m "feat(dkps): halaman Tabel DKPS dengan progres per pokja dan per lembar; K6 menghitung lembar"
```

---

### Task 9: Tagihan DKPS menunjuk ke tabelnya

**Files:**
- Modify: `app/Filament/Resources/Tagihans/Pages/ViewTagihan.php:28-45`, `app/Filament/Pages/TagihanSaya.php:139-146`, `app/Filament/Resources/Tagihans/Tables/TagihansTable.php:30-36`
- Test: `tests/Feature/LayarPengerjaanTest.php`

**Interfaces:**
- Consumes: `TabelDkpsLayar::urlUntuk()`.
- Produces: pada tagihan `jenis = data_dkps` dengan `dkps_butir_id`, aksi kepala `buka_tabel_dkps` berlabel "Buka tabel DKPS" (ikon `heroicon-o-table-cells`, `->url(TabelDkpsLayar::urlUntuk($record->dkpsButir->kode))`); deskripsi baris di Tagihan Saya dan Semua Tagihan untuk tagihan DKPS = `"{label_tabel} · {pokja}"`.

- [ ] **Step 1: Tulis uji yang gagal** (`LayarPengerjaanTest`)

```php
#[Test] public function tagihan_dkps_punya_tombol_buka_tabel(): void
{   $t = Tagihan::where('jenis', JenisTagihan::DataDkps)->whereHas('dkpsButir', fn ($q) => $q->where('kode', '7'))->firstOrFail();
    $this->actingAs($this->ketua)->get(TagihanResource::getUrl('view', ['record' => $t]))
        ->assertOk()->assertSee('Buka tabel DKPS')->assertSee('/panel/dkps/tabel/7'); }
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `docker exec sigap-php php artisan test --filter=LayarPengerjaanTest` → FAIL.

- [ ] **Step 3: Implementasikan** aksi dan deskripsi sesuai Interfaces.

- [ ] **Step 4: Jalankan uji** — `docker exec sigap-php php artisan test --filter='LayarPengerjaanTest|PanelTagihanTest|TagihanTerlihatTest'` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Tagihans app/Filament/Pages/TagihanSaya.php tests/Feature/LayarPengerjaanTest.php
git commit -m "feat(tagihan): tagihan DKPS menautkan langsung ke tabel DKPS pokja"
```

---

### Task 10: Penyelarasan data lama, kesiapan, dan dokumentasi

**Files:**
- Create: `app/Console/Commands/SelaraskanDkps.php`
- Modify: `app/Console/Commands/CekKesiapan.php` (dua pemeriksaan), `app/Services/KalkulatorRumus.php` (komentar TKM), `app/Filament/Resources/NilaiRumuses/**` (kotak peringatan TKM → catatan konfirmasi), `README.md`, `docs/KEPUTUSAN.md`
- Test: `tests/Feature/CekKesiapanTest.php`, `tests/Feature/KalkulatorRumusTest.php` (hanya bila teks catatan TKM diuji)

**Interfaces:**
- Produces: perintah `dkps:selaraskan` — untuk setiap `DkpsBaris` dengan `pokja_id` null atau `lembar_kode` null: isi `lembar_kode` (kode lembar pertama butir) dan `pokja_id` (pokja berkode `butir.pokja_kode` pada periode baris); cetak jumlah yang diselaraskan; idempoten. `CekKesiapan` menambah `'35 lembar DKPS'` (jumlah lembar dari `DkpsButir::all()`) dan `'Tidak ada baris DKPS tanpa pokja'` (`DkpsBaris::whereNull('pokja_id')->count() === 0`, saran `php artisan dkps:selaraskan`).

- [ ] **Step 1: Tulis uji yang gagal** (`CekKesiapanTest`): baris DKPS dengan `pokja_id` dipaksa null → `sigap:cek-kesiapan` gagal dengan teks `dkps:selaraskan`; setelah `artisan dkps:selaraskan` → lulus dan `pokja_id` terisi.

- [ ] **Step 2: Jalankan, pastikan gagal** — `docker exec sigap-php php artisan test --filter=CekKesiapanTest` → FAIL.

- [ ] **Step 3: Implementasikan perintah dan pemeriksaan; perbarui dokumen**

`README.md`: ganti "28 butir DKPS" → "29 tabel DKPS (35 lembar)"; tambah langkah pasca-penggelaran `php artisan migrate && php artisan db:seed --class=DkpsButirSeeder && php artisan dkps:selaraskan`; tambah paragraf "Tabel DKPS per pokja" di bagian "Yang perlu diketahui" (skema di `data/dkps-tabel.json`, dibangkitkan `tools/dkps-skema-dari-xlsx.py`; baris lama tampil di Verifikasi DKPS). `docs/KEPUTUSAN.md`: §2 TKM → **terkonfirmasi** oleh `AK Kosong PPG.xlsx` sel E207 (rumus `/4`, ambang 0,25/0,5/0,75), pindahkan ke §1; tambah dua pertanyaan baru ke LAMDIK (rujukan `F264` pada PGBLKL; RMS yang memberi skor 1 untuk masa studi satu tahun); tambah butir §4 "Penomoran DKPS 3.0 bergeser dari Buku 3 — pencocokan butir lewat `kode`, bukan `no`"; catat bahwa rumus kuantitatif yang hilang (spesifikasi A.3) menunggu rencana berikutnya. `KalkulatorRumus::tkm()` komentar dan `catatan` baris `nilai_rumus`: "Normalisasi /4 sesuai kertas kerja AK IAPSK 3.0 (sel E207)". Kotak peringatan di layar Nilai Rumus menjadi catatan informatif.

- [ ] **Step 4: Uji penuh**

Run:
```bash
docker exec sigap-php vendor/bin/pint --dirty
docker exec sigap-php php artisan test
python3 data/verifikasi.py
docker exec sigap-php php artisan sigap:cek-kesiapan
```
Expected: seluruh uji PASS; verifikasi `SEMUA KONSISTEN`; cek kesiapan tanpa `GAGAL` (baris `PERIKSA` boleh ada).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands README.md docs/KEPUTUSAN.md app/Services/KalkulatorRumus.php app/Filament/Resources/NilaiRumuses tests/Feature/CekKesiapanTest.php
git commit -m "feat(dkps): perintah dkps:selaraskan, pemeriksaan kesiapan DKPS 3.0, dan konfirmasi tafsir TKM"
```

---

## Tindak lanjut di luar rencana ini

1. **Rencana "Rumus kuantitatif AK 3.0"**: E7 RK dua bagian, E12, E13, E18, E22, E25, E32, E36, E40, E45, E49, E50, E54, aturan responden minimum E43/E44 — tabel ambang ada di spesifikasi A.3. Setelah DKPS terisi, `KalkulatorRumus` dapat membaca variabelnya langsung dari `dkps_baris` per lembar.
2. **Ekspor ke templat `DKPS PPG.xlsx`** untuk unggah ke SIMALAMDIK.
3. **Tangkapan layar manual pengguna** untuk halaman Tabel DKPS (prosedur README).
