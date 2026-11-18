<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('anak_id')->constrained('anak')->restrictOnDelete();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('gelombang_id')->constrained('gelombang')->restrictOnDelete();
            $table->string('program', 10)->nullable();
            $table->string('nomor_peserta', 20)->nullable()->unique();
            $table->string('status_hasil', 30)->nullable();
            $table->string('status_murid', 30)->nullable();
            $table->boolean('lulusan_yayasan')->default(false);
            $table->decimal('pilihan_iap', 12, 0)->nullable();
            $table->timestamp('setuju_biaya_pada')->nullable();
            $table->timestamp('setuju_data_pada')->nullable();
            $table->text('catatan_panitia')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->timestamps();

            $table->unique(['anak_id', 'sekolah_id', 'gelombang_id']);
            $table->index('anak_id');
            $table->index(['sekolah_id', 'gelombang_id', 'status_hasil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran');
    }
};
