<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategoris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usahas_id')->constrained('usahas')->cascadeOnDelete();
            $table->string('nama', 100);
            $table->string('arah', 12);                      // pengeluaran | pemasukan
            $table->string('klasifikasi', 12)->nullable();   // langsung | operasional | investasi (null untuk pemasukan)
            $table->string('satuan_default', 20)->nullable();
            $table->boolean('pakai_kuantitas')->default(false);
            $table->string('kode_sistem', 30)->nullable();   // 'bibit' dipakai form Buat siklus
            $table->boolean('aktif')->default(true);
            $table->smallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['usahas_id', 'arah', 'nama']);
            // Target FK komposit dari transaksi (menjamin usahas dan arah sama)
            $table->unique(['id', 'usahas_id', 'arah'], 'kategoris_id_usahas_arah_uq');
            $table->unique(['usahas_id', 'kode_sistem'], 'kategoris_kode_sistem_uq');
        });

        DB::statement(<<<'SQL'
ALTER TABLE kategoris ADD CONSTRAINT kategoris_arah_klasifikasi_chk CHECK (
    (arah = 'pengeluaran' AND klasifikasi IS NOT NULL AND klasifikasi IN ('langsung', 'operasional', 'investasi'))
    OR (arah = 'pemasukan' AND klasifikasi IS NULL)
)
SQL);

    }

    public function down(): void
    {
        Schema::dropIfExists('kategoris');
    }
};
