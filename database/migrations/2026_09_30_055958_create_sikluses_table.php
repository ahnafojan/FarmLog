<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikluses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usahas_id')->constrained('usahas')->cascadeOnDelete();
            $table->string('nama', 150);
            $table->date('tanggal_mulai');
            $table->integer('umur_masuk_hari')->default(0);  // 0 untuk DOC/benih, >0 untuk pullet
            $table->integer('populasi_awal');
            $table->string('status', 10)->default('berjalan'); // berjalan | selesai
            $table->date('tanggal_selesai')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['usahas_id', 'deleted_at', 'status', 'tanggal_mulai', 'id'], 'sikluses_status_mulai_idx');
            $table->index(['usahas_id', 'deleted_at', 'status', 'tanggal_selesai'], 'sikluses_selesai_idx');
            // Target FK komposit dari transaksi (menjamin usahas sama)
            $table->unique(['id', 'usahas_id'], 'sikluses_id_usahas_uq');
        });

        DB::statement(<<<'SQL'
ALTER TABLE sikluses ADD CONSTRAINT sikluses_status_chk CHECK (
    (status = 'berjalan' AND tanggal_selesai IS NULL)
    OR (status = 'selesai' AND tanggal_selesai IS NOT NULL AND tanggal_selesai >= tanggal_mulai)
)
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE sikluses ADD CONSTRAINT sikluses_angka_chk
    CHECK (umur_masuk_hari >= 0 AND populasi_awal > 0)
SQL);

    }

    public function down(): void
    {
        Schema::dropIfExists('sikluses');
    }
};
