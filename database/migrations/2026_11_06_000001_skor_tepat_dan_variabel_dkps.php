<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rumus kuantitatif AK 3.0: skor pecahan dan katalog variabel DKPS.
 *
 * `skor_tepat` menyimpan Skor A apa adanya (3,4; 2,75) karena pecahan itulah
 * yang dipakai asesor dalam (3A + B)/4; `skor` bulat tetap ada untuk lencana.
 * `variabel_dkps` adalah katalog rujukan dari data/variabel-dkps.json, dan
 * `rumus.variabel_dkps` menautkan tiap rumus ke variabel yang dibacanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nilai_rumus', function (Blueprint $table) {
            $table->decimal('skor_tepat', 5, 2)->nullable()->after('skor');
        });

        Schema::table('rumus', function (Blueprint $table) {
            $table->json('variabel_dkps')->nullable()->after('variabel');
        });

        Schema::create('variabel_dkps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 40)->unique();
            $table->string('label');
            $table->json('lembar');
            $table->string('cara', 20);
            $table->string('kolom')->nullable();
            $table->json('parameter')->nullable();
            $table->json('rumus');
            $table->unsignedInteger('urutan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variabel_dkps');

        Schema::table('rumus', function (Blueprint $table) {
            $table->dropColumn('variabel_dkps');
        });

        Schema::table('nilai_rumus', function (Blueprint $table) {
            $table->dropColumn('skor_tepat');
        });
    }
};
