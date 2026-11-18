<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelombang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->restrictOnDelete();
            $table->string('nama', 100);
            $table->date('tanggal_buka')->nullable();
            $table->date('tanggal_tutup')->nullable();
            $table->unsignedInteger('kuota')->nullable();
            $table->string('status', 20)->default('draf');
            $table->string('tautan_grup_wa', 255)->nullable();
            $table->timestamps();

            $table->index(['sekolah_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelombang');
    }
};
