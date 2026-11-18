<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 membuat `status_hasil` nullable. Aturan tahap 2: nilai bawaan
 * `Baru` dan tidak boleh null. Draf dikenali dari `dikirim_pada` kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pendaftaran')
            ->whereNull('status_hasil')
            ->update(['status_hasil' => 'Baru']);

        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->string('status_hasil', 30)->default('Baru')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->string('status_hasil', 30)->nullable()->default(null)->change();
        });
    }
};
