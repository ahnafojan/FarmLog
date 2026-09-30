<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_penandas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_usahas_id')->constrained('template_usahas')->cascadeOnDelete();
            $table->string('nama', 100);
            $table->string('jenis', 20);                     // panen | mulai_produksi | puncak | afkir | pengingat
            $table->integer('hari_ke');                      // umur hewan (hari) saat penandas terjadi
            $table->smallInteger('pengingat_hari_sebelum')->nullable();
            $table->smallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index('template_usahas_id');
        });

        DB::statement(<<<'SQL'
ALTER TABLE template_penandas ADD CONSTRAINT template_penandas_jenis_chk
    CHECK (jenis IN ('panen', 'mulai_produksi', 'puncak', 'afkir', 'pengingat'))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE template_penandas ADD CONSTRAINT template_penandas_angka_chk
    CHECK (hari_ke >= 0 AND (pengingat_hari_sebelum IS NULL OR pengingat_hari_sebelum >= 0))
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('template_penandas');
    }
};