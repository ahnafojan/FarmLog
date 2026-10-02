<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usahas', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique();
        });

        foreach (DB::table('usahas')->orderBy('id')->lazyById() as $usaha) {
            $baseSlug = Str::slug($usaha->nama) ?: 'usaha';
            $slug = $baseSlug;

            for ($suffix = 2; in_array($slug, ['login', 'logout', 'new', 'password-reset', 'profile', 'register'], true) || DB::table('usahas')->where('slug', $slug)->exists(); $suffix++) {
                $slug = $baseSlug.'-'.$suffix;
            }

            DB::table('usahas')->where('id', $usaha->id)->update(['slug' => $slug]);
        }

        Schema::table('usahas', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usahas', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
