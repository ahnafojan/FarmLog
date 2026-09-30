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
            $table->foreignId('template_usahas_id')->constrained('template_usahas')->restrictOnDelete();
            $table->string('nama', 150);
            $table->string('rekap_dasar', 20)->default('siklus_selesai'); // disalin dari template_usahas.rekap_default
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(<<<'SQL'
ALTER TABLE usahas ADD CONSTRAINT usahas_rekap_dasar_chk
    CHECK (rekap_dasar IN ('siklus_selesai', 'tanggal_transaksi'))
SQL);

        // Nama usahas unik per pemilik (usahas yang sudah dihapus tidak dihitung)
        DB::statement('CREATE UNIQUE INDEX usahas_nama_per_user_uq ON usahas (user_id, nama) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('usahas');
    }
};