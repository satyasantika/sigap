<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengguna demo: hidup dan mati bersama periode demonya.
 *
 * `demo` menandai akun yang lahir dari demonstrasi, bukan dari daftar pengguna
 * sungguhan. `periode_demo_id` mengurungnya pada satu periode — lihat
 * App\Support\LingkupPeriode. Keduanya terpisah dengan sengaja: akun tanpa
 * periode demo adalah akun yatim, dan scope-nya harus menolaknya, bukan
 * menganggapnya pengguna biasa.
 *
 * Kolomnya nullable dan bawaannya false, jadi seluruh pengguna yang sudah ada
 * tetap pengguna biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('demo')->default(false)->after('aktif');
            $table->foreignUuid('periode_demo_id')->nullable()->after('demo')
                ->constrained('periode')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('periode_demo_id');
            $table->dropColumn('demo');
        });
    }
};
