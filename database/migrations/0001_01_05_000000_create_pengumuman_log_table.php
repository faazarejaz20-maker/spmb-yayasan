<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('gelombang_id')->constrained('gelombang')->restrictOnDelete();
            $table->foreignId('diumumkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diumumkan_pada')->nullable();
            $table->text('teks_pesan')->nullable();
            $table->timestamps();

            $table->unique(['sekolah_id', 'gelombang_id']);
        });

        Schema::create('konten_sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('bagian', 50);
            $table->string('judul', 150)->nullable();
            $table->text('isi')->nullable();
            $table->string('gambar_path', 255)->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('pengaturan_isian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('kunci_isian', 50);
            $table->boolean('tampil')->default(true);
            $table->boolean('wajib')->default(false);
            $table->timestamps();

            $table->unique(['sekolah_id', 'kunci_isian']);
        });

        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sekolah_id')->nullable()->constrained('sekolah')->restrictOnDelete();
            $table->string('aksi', 50);
            $table->string('objek', 50)->nullable();
            $table->unsignedBigInteger('objek_id')->nullable();
            $table->text('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('dibuat_pada')->useCurrent();

            $table->index(['user_id', 'dibuat_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
        Schema::dropIfExists('pengaturan_isian');
        Schema::dropIfExists('konten_sekolah');
        Schema::dropIfExists('pengumuman');
    }
};
