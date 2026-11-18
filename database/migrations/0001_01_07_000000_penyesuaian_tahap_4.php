<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyesuaian Tahap 4.
 *
 * Prompt Tahap 4 meminta beberapa kolom yang belum ada di
 * `docs/struktur-tabel.md`:
 * - `sekolah.singkatan` dan `sekolah.instagram` (halaman super admin),
 * - `jenis_berkas.petunjuk` (petunjuk unggah),
 * - `rincian_biaya.catatan` dan `rincian_biaya.aktif` (pengaturan biaya).
 *
 * `konten_sekolah.sekolah_id` juga dibuat boleh kosong agar super admin dapat
 * menyimpan konten yayasan. Semua perubahan bersifat menambah/melebarkan dan
 * dicatat di laporan tahap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->string('singkatan', 50)->nullable()->after('nama');
            $table->string('instagram', 100)->nullable()->after('kontak');
        });

              Schema::table('jenis_berkas', function (Blueprint $table) {
            $table->text('petunjuk')->nullable()->after('jumlah_lembar');
        });

        Schema::table('rincian_biaya', function (Blueprint $table) {
            $table->text('catatan')->nullable()->after('tipe');
            $table->boolean('aktif')->default(true)->after('urutan');
        });

        // Kolom ini semula NOT NULL. Tanpa relasi asing yang dihapus, MySQL
        // dan SQLite sama-sama dapat melonggarkan nullability lewat `change()`.
        Schema::table('konten_sekolah', function (Blueprint $table) {
            $table->foreignId('sekolah_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('konten_sekolah', function (Blueprint $table) {
            $table->foreignId('sekolah_id')->nullable(false)->change();
        });

        Schema::table('rincian_biaya', function (Blueprint $table) {
            $table->dropColumn(['catatan', 'aktif']);
        });

        Schema::table('jenis_berkas', function (Blueprint $table) {
            $table->dropColumn('petunjuk');
        });

        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropColumn(['singkatan', 'instagram']);
        });
    }
};
