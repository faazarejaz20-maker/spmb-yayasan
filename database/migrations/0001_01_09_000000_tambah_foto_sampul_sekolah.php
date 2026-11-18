<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto sampul kartu sekolah di beranda publik.
 *
 * Sebelumnya foto kartu hanya berasal dari berkas proyek di
 * `public/images/sekolah`. Kolom ini menyimpan gambar unggahan admin pada disk
 * publik (`sampul/{sekolah_id}/...`), sehingga sekolah dapat mengganti sendiri
 * lewat panel tanpa menyentuh berkas aplikasi. Bila kosong, tampilan kembali ke
 * foto bawaan proyek (lihat `GambarPublik::SAMPUL`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->string('foto_sampul_path', 255)->nullable()->after('instagram');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropColumn('foto_sampul_path');
        });
    }
};
