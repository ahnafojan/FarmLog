<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_kategoris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_usahas_id')->constrained('template_usahas')->cascadeOnDelete();
            $table->string('nama', 100);
            $table->string('arah', 12);                      // pengeluaran | pemasukan
            $table->string('klasifikasi', 12)->nullable();   // langsung | operasional | investasi (null untuk pemasukan)
            $table->string('satuan_default', 20)->nullable();
            $table->boolean('pakai_kuantitas')->default(false); // tampilkan jumlah/satuan/harga satuan
            $table->string('kode_sistem', 30)->nullable();   // 'bibit' dipakai form Buat siklus
            $table->smallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['template_usahas_id', 'arah', 'nama']);
        });

        DB::statement(<<<'SQL'
ALTER TABLE template_kategoris ADD CONSTRAINT template_kategoris_arah_klasifikasi_chk CHECK (
    (arah = 'pengeluaran' AND klasifikasi IS NOT NULL AND klasifikasi IN ('langsung', 'operasional', 'investasi'))
    OR (arah = 'pemasukan' AND klasifikasi IS NULL)
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('template_kategoris');
    }
};