<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_usahas', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();           // ayam-petelur | lele-pendederan | lele-pembesaran
            $table->string('nama', 100);
            $table->string('satuan_populasi', 20)->default('ekor');
            $table->string('rekap_default', 20)->default('siklus_selesai'); // ayam-petelur: tanggal_transaksi
            $table->boolean('aktif')->default(true);
            $table->smallInteger('urutan')->default(0);
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
ALTER TABLE template_usahas ADD CONSTRAINT template_usahas_rekap_default_chk
    CHECK (rekap_default IN ('siklus_selesai', 'tanggal_transaksi'))
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('template_usahas');
    }
};