<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usahas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();                 // pemilik
            $table->string('jenis_usaha', 50);
            $table->string('nama', 150);
            $table->string('rekap_dasar', 20)->default('siklus_selesai');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('nama_aktif', 150)->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN nama ELSE NULL END');
            $table->unique(['user_id', 'nama_aktif'], 'usahas_nama_per_user_uq');
            $table->index(['user_id', 'deleted_at', 'nama'], 'usahas_pemilik_nama_idx');
        });

        DB::statement(<<<'SQL'
ALTER TABLE usahas ADD CONSTRAINT usahas_rekap_dasar_chk
    CHECK (rekap_dasar IN ('siklus_selesai', 'tanggal_transaksi'))
SQL);

    }

    public function down(): void
    {
        Schema::dropIfExists('usahas');
    }
};
