<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic ke tagihan, bukti, dan narasi.
 *
 * `commentable_id` sengaja uuid, bukan morphs() bawaan yang menghasilkan
 * bigint — AGENTS.md aturan 4b tidak mengecualikan kolom polymorphic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komentar', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('commentable_type');
            $table->uuid('commentable_id');
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->text('isi');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['commentable_type', 'commentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komentar');
    }
};
