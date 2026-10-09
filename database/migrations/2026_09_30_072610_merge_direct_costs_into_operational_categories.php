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
        DB::table('kategoris')->where('klasifikasi', 'langsung')->update(['klasifikasi' => 'operasional']);

        DB::statement('ALTER TABLE kategoris DROP CHECK kategoris_arah_klasifikasi_chk');
        DB::statement("ALTER TABLE kategoris ADD CONSTRAINT kategoris_arah_klasifikasi_chk CHECK (
            (arah = 'pengeluaran' AND klasifikasi IS NOT NULL AND klasifikasi IN ('operasional', 'investasi'))
            OR (arah = 'pemasukan' AND klasifikasi IS NULL)
        )");
    }

    /**
     * Restore the previous constraint. Merged classifications remain operational
     * because their original classification cannot be recovered reliably.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE kategoris DROP CHECK kategoris_arah_klasifikasi_chk');
        DB::statement("ALTER TABLE kategoris ADD CONSTRAINT kategoris_arah_klasifikasi_chk CHECK (
            (arah = 'pengeluaran' AND klasifikasi IS NOT NULL AND klasifikasi IN ('langsung', 'operasional', 'investasi'))
            OR (arah = 'pemasukan' AND klasifikasi IS NULL)
        )");
    }
};
