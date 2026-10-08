<?php

namespace App\Support\Impor;

/**
 * Profil impor yang membuat sandi acak untuk baris baru -- hanya
 * ImporPengguna. Sistem ini sengaja tidak punya alur kirim sandi lewat
 * surel (vibecoding/docs/08-auth-dan-izin.md), jadi sandinya harus
 * ditunjukkan SEKALI di layar hasil agar admin bisa menyalinnya manual
 * ke tiap orang.
 */
interface MemilikiSandiBaru
{
    /**
     * Sandi acak yang dibuat selama penjalanan impor TERAKHIR, satu entri
     * per baris baru. Kosong sebelum jalankan() dipanggil, dan kosong lagi
     * pada instance baru -- karena itu instance yang sama harus dibaca
     * ulang setelah PelaksanaImpor::jalankan(), bukan instance baru.
     *
     * @return array<int, array{email: string, sandi: string}>
     */
    public function sandiBaruDibuat(): array;
}
