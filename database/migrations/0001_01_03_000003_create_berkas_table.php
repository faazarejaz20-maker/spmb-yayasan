<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_berkas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('nama', 100);
            $table->boolean('wajib')->default(false);
            $table->unsignedTinyInteger('jumlah_lembar')->default(1);
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('berkas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->restrictOnDelete();
            $table->foreignId('jenis_berkas_id')->constrained('jenis_berkas')->restrictOnDelete();
            $table->string('path', 255)->nullable();
            $table->string('status', 30)->default('menunggu');
            $table->text('catatan')->nullable();
            $table->foreignId('diperiksa_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diperiksa_pada')->nullable();
            $table->timestamps();

            $table->index('pendaftaran_id');
        });

        Schema::create('jawaban_tambahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->restrictOnDelete();
            $table->string('bagian', 30);
            $table->string('kunci', 50);
            $table->text('nilai')->nullable();
            $table->timestamps();

            $table->unique(['pendaftaran_id', 'bagian', 'kunci']);
        });

        Schema::create('riwayat_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->restrictOnDelete();
            $table->string('jenis', 20);
            $table->string('dari', 30)->nullable();
            $table->string('ke', 30)->nullable();
            $table->foreignId('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->useCurrent();

            $table->index('pendaftaran_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_status');
        Schema::dropIfExists('jawaban_tambahan');
        Schema::dropIfExists('berkas');
        Schema::dropIfExists('jenis_berkas');
    }
};
