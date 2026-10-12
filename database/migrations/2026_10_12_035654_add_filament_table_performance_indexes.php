<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kategoris', function (Blueprint $table): void {
            $table->index(['usahas_id', 'urutan', 'id'], 'kategoris_usahas_urutan_idx');
        });

        Schema::table('sikluses_penandas', function (Blueprint $table): void {
            $table->index(['sikluses_id', 'tanggal', 'id'], 'sikluses_penandas_tanggal_idx');
        });

        Schema::table('transaksis', function (Blueprint $table): void {
            $table->index(['usahas_id', 'deleted_at', 'arah', 'tanggal', 'id'], 'transaksis_usahas_arah_tanggal_idx');
            $table->index(['usahas_id', 'deleted_at', 'total', 'id'], 'transaksis_usahas_total_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table): void {
            $table->dropIndex('transaksis_usahas_arah_tanggal_idx');
            $table->dropIndex('transaksis_usahas_total_idx');
        });

        Schema::table('sikluses_penandas', function (Blueprint $table): void {
            $table->dropIndex('sikluses_penandas_tanggal_idx');
        });

        Schema::table('kategoris', function (Blueprint $table): void {
            $table->dropIndex('kategoris_usahas_urutan_idx');
        });
    }
};
