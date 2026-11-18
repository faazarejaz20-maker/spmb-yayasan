<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('peran', 20);
            $table->foreignId('sekolah_id')->nullable()->constrained('sekolah')->restrictOnDelete();
            $table->string('no_hp', 20)->nullable()->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('password');
            $table->boolean('wajib_ganti_password')->default(false);
            $table->boolean('aktif')->default(true);
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('terakhir_masuk')->nullable();
            $table->timestamps();

            $table->index(['sekolah_id', 'peran']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
