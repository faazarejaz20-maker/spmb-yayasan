<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 20);
            $table->boolean('aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 150);
            $table->string('jenjang', 50);
            $table->text('alamat')->nullable();
            $table->string('kontak', 20)->nullable();
            $table->string('mode_penerimaan', 20)->default('tanpa_seleksi');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sekolah');
        Schema::dropIfExists('tahun_ajaran');
    }
};
