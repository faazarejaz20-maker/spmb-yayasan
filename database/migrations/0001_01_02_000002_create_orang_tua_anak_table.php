<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orang_tua', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('hubungan', 20);
            $table->string('nama', 150);
            $table->text('nik')->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('status_hidup', 30)->nullable();
            $table->string('pendidikan', 50)->nullable();
            $table->string('pekerjaan', 100)->nullable();
            $table->string('penghasilan', 50)->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hubungan']);
        });

        Schema::create('anak', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('nama_lengkap', 150);
            $table->string('nama_panggilan', 50)->nullable();
            $table->string('jenis_kelamin', 1);
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->text('nik')->nullable();
            $table->text('no_kk')->nullable();
            $table->string('nisn', 10)->nullable();
            $table->string('agama', 30)->default('Islam');
            $table->unsignedTinyInteger('anak_ke')->nullable();
            $table->unsignedTinyInteger('jumlah_saudara')->nullable();
            $table->string('bahasa_sehari_hari', 50)->nullable();
            $table->unsignedSmallInteger('tinggi_badan')->nullable();
            $table->unsignedSmallInteger('berat_badan')->nullable();
            $table->string('golongan_darah', 2)->nullable();
            $table->text('riwayat_penyakit')->nullable();
            $table->text('alergi')->nullable();
            $table->text('kebutuhan_khusus')->nullable();
            $table->text('alamat');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('desa', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kabupaten', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->string('kode_pos', 10)->nullable();
            $table->string('jarak_ke_sekolah', 50)->nullable();
            $table->string('transportasi', 50)->nullable();
            $table->string('tinggal_dengan', 50)->nullable();
            $table->string('asal_sekolah', 150)->nullable();
            $table->string('jenis_asal_sekolah', 50)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['nama_lengkap', 'tanggal_lahir']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak');
        Schema::dropIfExists('orang_tua');
    }
};
