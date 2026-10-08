<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * impor_batch.periode_id dibuat nullable: profil impor pengguna tidak
 * terikat satu periode akreditasi (users hanya terikat prodi_id).
 *
 * Diubah lewat SQL mentah, bukan `->nullable()->change()`, karena
 * doctrine/dbal tidak terpasang di proyek ini dan MariaDB tidak
 * membutuhkannya untuk MODIFY COLUMN sederhana.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fk = $this->namaForeignKey();

        if ($fk !== null) {
            DB::statement("ALTER TABLE impor_batch DROP FOREIGN KEY `{$fk}`");
        }

        DB::statement('ALTER TABLE impor_batch MODIFY periode_id CHAR(36) NULL');
        DB::statement(
            'ALTER TABLE impor_batch ADD CONSTRAINT impor_batch_periode_id_foreign '
            .'FOREIGN KEY (periode_id) REFERENCES periode (id) ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE impor_batch DROP FOREIGN KEY impor_batch_periode_id_foreign');
        DB::statement('ALTER TABLE impor_batch MODIFY periode_id CHAR(36) NOT NULL');
        DB::statement(
            'ALTER TABLE impor_batch ADD CONSTRAINT impor_batch_periode_id_foreign '
            .'FOREIGN KEY (periode_id) REFERENCES periode (id) ON DELETE CASCADE'
        );
    }

    private function namaForeignKey(): ?string
    {
        $baris = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS nama FROM information_schema.KEY_COLUMN_USAGE '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            .'AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['impor_batch', 'periode_id'],
        );

        return $baris?->nama;
    }
};
