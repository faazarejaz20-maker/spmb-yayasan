<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Perintah pemulihan kata sandi staf dari server. Hanya akun staf yang boleh
 * diatur ulang lewat jalur ini; akun walisantri ditolak.
 */
class AturUlangPasswordConsoleTest extends WalisantriTestCase
{
    public function test_menolak_akun_walisantri(): void
    {
        $this->artisan('spmb:atur-ulang-password', ['nomor_hp' => $this->wali->no_hp])
            ->expectsOutputToContain('hanya untuk akun staf')
            ->assertExitCode(1);

        $segar = $this->wali->refresh();

        $this->assertTrue(Hash::check('password', $segar->password));
        $this->assertFalse($segar->wajib_ganti_password);
    }

    public function test_mengatur_ulang_kata_sandi_staf_dan_mewajibkan_ganti(): void
    {
        $staf = User::factory()->panitia($this->sekolah)->create(['no_hp' => '6281200000001']);

        $this->artisan('spmb:atur-ulang-password', ['nomor_hp' => '6281200000001'])
            ->expectsQuestion('Kata sandi baru (minimal 8 karakter)', 'KataSandiBaru123')
            ->expectsQuestion('Ulangi kata sandi baru', 'KataSandiBaru123')
            ->assertExitCode(0);

        $segar = $staf->refresh();

        $this->assertTrue(Hash::check('KataSandiBaru123', $segar->password));
        $this->assertTrue($segar->wajib_ganti_password);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'atur_ulang_kata_sandi_konsol',
            'objek' => 'User',
            'objek_id' => $staf->id,
        ]);
    }

    public function test_kata_sandi_lemah_ditolak(): void
    {
        $staf = User::factory()->panitia($this->sekolah)->create(['no_hp' => '6281200000002']);

        $this->artisan('spmb:atur-ulang-password', ['nomor_hp' => '6281200000002'])
            ->expectsQuestion('Kata sandi baru (minimal 8 karakter)', '12345678')
            ->assertExitCode(1);

        $this->assertTrue(Hash::check('password', $staf->refresh()->password));
    }
}
