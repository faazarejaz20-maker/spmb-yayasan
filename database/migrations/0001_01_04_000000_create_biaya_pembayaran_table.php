<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rincian_biaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('tahun_ajaran_id')->nullable()->constrained('tahun_ajaran')->restrictOnDelete();
            $table->string('kelompok', 50)->nullable();
            $table->string('nama', 150);
            $table->decimal('nominal', 12, 0)->nullable();
            $table->string('tipe', 20)->default('tetap');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('rekening_sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->string('bank', 100);
            $table->string('nomor_rekening', 50);
            $table->string('atas_nama', 150);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->restrictOnDelete();
            $table->foreignId('rincian_biaya_id')->nullable()->constrained('rincian_biaya')->restrictOnDelete();
            $table->string('metode', 20);
            $table->decimal('nominal', 12, 0)->nullable();
            $table->string('bukti_path', 255)->nullable();
            $table->string('status', 30)->default('menunggu');
            $table->text('catatan_penolakan')->nullable();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibayar_pada')->nullable();
            $table->timestamps();

            $table->index('pendaftaran_id');
        });

        Schema::create('daftar_ulang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->unique()->constrained('pendaftaran')->restrictOnDelete();
            $table->boolean('hadir')->default(false);
            $table->text('kekurangan')->nullable();
            $table->text('catatan_wawancara')->nullable();
            $table->boolean('selesai')->default(false);
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daftar_ulang');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('rekening_sekolah');
        Schema::dropIfExists('rincian_biaya');
    }
};
