<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikluses_penandas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sikluses_id')->constrained('sikluses')->cascadeOnDelete();
            $table->string('nama', 100);
            $table->string('jenis', 20);                     // panen | mulai_produksi | puncak | afkir | pengingat
            $table->date('tanggal');                         // tanggal_mulai + (hari_ke - umur_masuk_hari)
            $table->smallInteger('pengingat_hari_sebelum')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index('sikluses_id');
        });

        DB::statement(<<<'SQL'
ALTER TABLE sikluses_penandas ADD CONSTRAINT sikluses_penandas_jenis_chk
    CHECK (jenis IN ('panen', 'mulai_produksi', 'puncak', 'afkir', 'pengingat'))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE sikluses_penandas ADD CONSTRAINT sikluses_penandas_pengingat_chk
    CHECK (pengingat_hari_sebelum IS NULL OR pengingat_hari_sebelum >= 0)
SQL);

        // penandas yang belum selesai (untuk pengingat dan hitung mundur)
        DB::statement('CREATE INDEX sikluses_penandas_aktif_idx ON sikluses_penandas (tanggal) WHERE selesai_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('sikluses_penandas');
    }
};