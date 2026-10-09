<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usahas_id')->constrained('usahas')->cascadeOnDelete();
            $table->unsignedBigInteger('sikluses_id')->nullable();   // null = biaya umum usahas
            $table->unsignedBigInteger('kategoris_id');
            $table->string('arah', 12);                            // pengeluaran | pemasukan
            $table->date('tanggal');
            $table->decimal('qty', 12, 3)->nullable();
            $table->string('satuan', 20)->nullable();
            $table->bigInteger('harga_satuan')->nullable();        // Rupiah
            $table->bigInteger('total');                           // Rupiah, boleh diubah manual
            $table->string('pembeli', 150)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['usahas_id', 'deleted_at', 'tanggal', 'id'], 'transaksis_usahas_tanggal_idx');
            $table->index(['sikluses_id', 'deleted_at', 'arah'], 'transaksis_sikluses_idx');
            $table->index(['kategoris_id', 'usahas_id', 'deleted_at', 'tanggal', 'id'], 'transaksis_kategoris_tanggal_idx');

            // FK komposit: kategoris dan sikluses wajib milik usahas yang sama, arah wajib sama dengan kategoris
            $table->foreign(['kategoris_id', 'usahas_id', 'arah'], 'transaksis_kategoris_fk')
                ->references(['id', 'usahas_id', 'arah'])->on('kategoris');
            // Tidak ditegakkan bila sikluses_id NULL (biaya umum)
            $table->foreign(['sikluses_id', 'usahas_id'], 'transaksis_sikluses_fk')
                ->references(['id', 'usahas_id'])->on('sikluses');
        });

        DB::statement(<<<'SQL'
ALTER TABLE transaksis ADD CONSTRAINT transaksis_arah_chk
    CHECK (arah IN ('pengeluaran', 'pemasukan'))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE transaksis ADD CONSTRAINT transaksis_angka_chk CHECK (
    total >= 0
    AND (qty IS NULL OR qty > 0)
    AND (harga_satuan IS NULL OR harga_satuan >= 0)
)
SQL);

    }

    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};
