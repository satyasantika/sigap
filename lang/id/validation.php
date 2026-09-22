<?php

/**
 * Pesan validasi berbahasa Indonesia.
 *
 * Laravel 11 ke atas tidak menyertakan berkas terjemahan apa pun; tanpa
 * berkas ini, pesan galat muncul dalam bahasa Inggris atau bahkan sebagai
 * kunci mentah ("validation.required"). Seluruh antarmuka SIGAP berbahasa
 * Indonesia — termasuk saat pengguna salah mengisi, yaitu justru saat kata-kata
 * paling perlu dimengerti.
 *
 * Hanya aturan yang benar-benar dipakai aplikasi ini yang diterjemahkan.
 * Menyalin seluruh berkas bawaan Laravel akan menghasilkan ratusan baris yang
 * tidak pernah muncul dan tidak pernah diperiksa siapa pun.
 */
return [
    'accepted' => 'Kolom :attribute harus disetujui.',
    'after' => 'Kolom :attribute harus berisi tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus berisi tanggal setelah atau sama dengan :date.',
    'array' => 'Kolom :attribute harus berupa larik.',
    'before' => 'Kolom :attribute harus berisi tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus berisi tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Kolom :attribute harus berisi antara :min sampai :max butir.',
        'file' => 'Berkas :attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai antara :min sampai :max.',
        'string' => 'Kolom :attribute harus berisi antara :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => 'Kolom :attribute bukan tanggal yang sah.',
    'date_format' => 'Kolom :attribute tidak sesuai format :format.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'digits' => 'Kolom :attribute harus terdiri dari :digits digit.',
    'digits_between' => 'Kolom :attribute harus terdiri dari :min sampai :max digit.',
    'email' => 'Kolom :attribute harus berupa alamat surel yang sah.',
    'exists' => 'Pilihan pada :attribute tidak sah.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'filled' => 'Kolom :attribute wajib diisi.',
    'gt' => [
        'numeric' => 'Kolom :attribute harus lebih besar dari :value.',
        'string' => 'Kolom :attribute harus lebih panjang dari :value karakter.',
    ],
    'gte' => [
        'numeric' => 'Kolom :attribute harus lebih besar dari atau sama dengan :value.',
    ],
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => 'Pilihan pada :attribute tidak sah.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'lt' => [
        'numeric' => 'Kolom :attribute harus lebih kecil dari :value.',
    ],
    'lte' => [
        'numeric' => 'Kolom :attribute harus lebih kecil dari atau sama dengan :value.',
    ],
    'max' => [
        'array' => 'Kolom :attribute tidak boleh berisi lebih dari :max butir.',
        'file' => 'Berkas :attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => 'Kolom :attribute tidak boleh lebih besar dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih panjang dari :max karakter.',
    ],
    'mimes' => 'Kolom :attribute harus berupa berkas berjenis: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus berisi sekurangnya :min butir.',
        'file' => 'Berkas :attribute harus berukuran sekurangnya :min kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai sekurangnya :min.',
        'string' => 'Kolom :attribute harus berisi sekurangnya :min karakter.',
    ],
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'present' => 'Kolom :attribute harus ada.',
    'prohibited' => 'Kolom :attribute tidak boleh diisi.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_if' => 'Kolom :attribute wajib diisi bila :other bernilai :value.',
    'required_with' => 'Kolom :attribute wajib diisi bila terdapat :values.',
    'same' => 'Kolom :attribute dan :other harus sama.',
    'size' => [
        'file' => 'Berkas :attribute harus berukuran :size kilobyte.',
        'numeric' => 'Kolom :attribute harus bernilai :size.',
        'string' => 'Kolom :attribute harus berisi :size karakter.',
    ],
    'string' => 'Kolom :attribute harus berupa teks.',
    'unique' => 'Kolom :attribute sudah dipakai.',
    'uploaded' => 'Berkas :attribute gagal diunggah.',
    'url' => 'Kolom :attribute harus berupa tautan yang sah.',

    /**
     * Nama kolom yang dibaca manusia.
     *
     * Tanpa ini pesannya berbunyi "Kolom tanggal_kejadian wajib diisi" —
     * benar, tetapi menuntut pembaca menerjemahkan nama kolom basis data
     * menjadi nama yang ia lihat di layar.
     */
    'attributes' => [
        'ambang_3_tahun' => 'ambang 3 tahun',
        'ambang_5_tahun' => 'ambang 5 tahun',
        'bobot' => 'bobot',
        'bobot_terkait' => 'bobot terkait',
        'catatan' => 'catatan',
        'catatan_validasi' => 'catatan validasi',
        'data' => 'isi baris',
        'deskripsi' => 'deskripsi',
        'dkps_butir_id' => 'butir DKPS',
        'elemen_id' => 'elemen',
        'email' => 'alamat surel',
        'isi' => 'naskah',
        'jabatan' => 'jabatan',
        'jenis' => 'jenis',
        'judul' => 'judul',
        'keterangan' => 'keterangan',
        'kode' => 'kode',
        'level' => 'level pemenuhan',
        'nama' => 'nama',
        'nama_lengkap' => 'nama lengkap',
        'nidn' => 'NIDN',
        'nilai_terukur' => 'nilai terukur',
        'password' => 'kata sandi',
        'penanggung_jawab_id' => 'penanggung jawab',
        'peran' => 'peran',
        'periode_id' => 'periode',
        'pokja_id' => 'pokja',
        'prodi_id' => 'prodi',
        'skor' => 'skor',
        'sumber' => 'sumber data',
        'tahun_acuan' => 'tahun acuan',
        'tanggal' => 'tanggal',
        'tanggal_kejadian' => 'tanggal kejadian',
        'tanggal_target_unggah' => 'tanggal target unggah',
        'tenggat' => 'tenggat',
        'ts_tahun' => 'tahun acuan (TS)',
        'url' => 'tautan',
        'versi_instrumen' => 'versi instrumen',
    ],
];
