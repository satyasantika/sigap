<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koordinator yang ditunjuk lewat `pokja.koordinator_id` dimasukkan ke
 * `pokja_user` bila belum ada, supaya keanggotaannya tercatat dan tampil di
 * tab Anggota. Idempoten: yang sudah terdaftar tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pokja = DB::table('pokja')->whereNotNull('koordinator_id')->get(['id', 'koordinator_id']);

        foreach ($pokja as $p) {
            $ada = DB::table('pokja_user')
                ->where('pokja_id', $p->id)
                ->where('user_id', $p->koordinator_id)
                ->exists();

            if (! $ada) {
                DB::table('pokja_user')->insert([
                    'pokja_id' => $p->id,
                    'user_id' => $p->koordinator_id,
                    'peran_dalam_pokja' => 'koordinator',
                ]);
            }
        }
    }

    public function down(): void
    {
        // Tidak ada yang dibalik: keanggotaan yang sah tidak boleh ikut terhapus.
    }
};
