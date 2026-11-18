<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks tambahan untuk kolom yang sering disaring di panel (tahap 6).
 *
 * Kunci asing sudah membuat indeks satu kolom di MySQL, tetapi kombinasi
 * penyaringan yang dipakai daftar pendaftar, rekap, dan log belum terindeks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->index(['sekolah_id', 'dikirim_pada'], 'pendaftaran_sekolah_dikirim');
            $table->index(['gelombang_id', 'status_hasil'], 'pendaftaran_gelombang_hasil');
            $table->index('status_hasil', 'pendaftaran_status_hasil');
        });

        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->index(['sekolah_id', 'dibuat_pada'], 'log_sekolah_dibuat');
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->index(['pendaftaran_id', 'status'], 'pembayaran_pendaftaran_status');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropIndex('pembayaran_pendaftaran_status');
        });

        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->dropIndex('log_sekolah_dibuat');
        });

        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropIndex('pendaftaran_status_hasil');
            $table->dropIndex('pendaftaran_gelombang_hasil');
            $table->dropIndex('pendaftaran_sekolah_dikirim');
        });
    }
};
