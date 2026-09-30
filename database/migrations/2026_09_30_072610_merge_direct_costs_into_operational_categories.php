<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['template_kategoris', 'kategoris'] as $table) {
            DB::table($table)->where('klasifikasi', 'langsung')->update(['klasifikasi' => 'operasional']);

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_arah_klasifikasi_chk");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_arah_klasifikasi_chk CHECK (
                (arah = 'pengeluaran' AND klasifikasi IS NOT NULL AND klasifikasi IN ('operasional', 'investasi'))
                OR (arah = 'pemasukan' AND klasifikasi IS NULL)
            )");
        }
    }

    /**
     * Restore the previous constraint. Merged classifications remain operational
     * because their original classification cannot be recovered reliably.
     */
    public function down(): void
    {
        foreach (['template_kategoris', 'kategoris'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_arah_klasifikasi_chk");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_arah_klasifikasi_chk CHECK (
                (arah = 'pengeluaran' AND klasifikasi IS NOT NULL AND klasifikasi IN ('langsung', 'operasional', 'investasi'))
                OR (arah = 'pemasukan' AND klasifikasi IS NULL)
            )");
        }
    }
};
