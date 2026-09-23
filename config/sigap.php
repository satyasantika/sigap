<?php

/**
 * Jati diri aplikasi.
 *
 * Tahun hak cipta ada DI SINI, bukan di dalam app/, karena aturan 3 melarang
 * tahun ditulis mati di dalam kode dan uji `tidak_ada_tahun_yang_ditulis_mati`
 * menegakkannya dengan menyisir app/ dan database/migrations/.
 *
 * Larangan itu bicara tentang TS — tahun akademik yang harus diturunkan dari
 * `periode.ts_tahun` supaya SIGAP tetap benar saat periode berganti. Tahun hak
 * cipta bukan TS dan justru tidak boleh ikut bergeser. Menaruhnya sebagai
 * konfigurasi menyelesaikan keduanya: nilainya tetap, ujinya tetap galak, dan
 * tidak ada pengecualian yang perlu dihafal.
 */
return [
    'nama' => 'SIGAP',

    'nama_panjang' => 'Sistem Informasi Gugus Akreditasi Program Studi',

    'pemilik' => env('SIGAP_PEMILIK', 'Satya Santika'),

    'tahun_hak_cipta' => env('SIGAP_TAHUN_HAK_CIPTA', '2026'),
];
