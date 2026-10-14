<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Replace both cycle indexes together to keep the composite foreign key indexed. */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE transaksis
                DROP INDEX transaksis_sikluses_idx,
                DROP INDEX transaksis_sikluses_fk,
                ADD INDEX transaksis_sikluses_export_idx (sikluses_id, usahas_id, deleted_at, arah, tanggal, id)
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE transaksis
                DROP INDEX transaksis_sikluses_export_idx,
                ADD INDEX transaksis_sikluses_idx (sikluses_id, deleted_at, arah),
                ADD INDEX transaksis_sikluses_fk (sikluses_id, usahas_id)
            SQL);
    }
};
