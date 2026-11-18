<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaftarAkunTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_berhasil_membuat_walisantri(): void
    {
        $response = $this->post('/daftar-akun', [
            'nama' => 'Wali Baru',
            'no_hp' => '081234567890',
            'kata_sandi' => 'password123',
            'kata_sandi_confirmation' => 'password123',
            'setuju_data' => '1',
        ]);

        $response->assertRedirect('/akun');

        $this->assertDatabaseHas('users', [
            'nama' => 'Wali Baru',
            'no_hp' => '6281234567890',
            'peran' => PeranPengguna::Walisantri->value,
            'aktif' => true,
        ]);
    }

    public function test_input_peran_sekolah_aktif_diabaikan(): void
    {
        $this->post('/daftar-akun', [
            'nama' => 'Wali Dicoba',
            'no_hp' => '081234567890',
            'kata_sandi' => 'password123',
            'kata_sandi_confirmation' => 'password123',
            'setuju_data' => '1',
            // Input berbahaya yang harus diabaikan.
            'peran' => PeranPengguna::Admin->value,
            'sekolah_id' => 1,
            'aktif' => 0,
            'wajib_ganti_password' => 1,
        ]);

        $user = User::where('no_hp', '6281234567890')->first();

        $this->assertSame(PeranPengguna::Walisantri, $user->peran);
        $this->assertNull($user->sekolah_id);
        $this->assertTrue($user->aktif);
        $this->assertFalse($user->wajib_ganti_password);
    }

    public function test_nomor_hp_ganda_ditolak(): void
    {
        User::forceCreate([
            'nama' => 'Wali Lama',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
        ]);

        $response = $this->post('/daftar-akun', [
            'nama' => 'Wali Baru',
            'no_hp' => '081234567890',
            'kata_sandi' => 'password123',
            'kata_sandi_confirmation' => 'password123',
            'setuju_data' => '1',
        ]);

        $response->assertSessionHasErrors('no_hp');
    }

    public function test_persetujuan_wajib(): void
    {
        $response = $this->post('/daftar-akun', [
            'nama' => 'Wali Baru',
            'no_hp' => '081234567890',
            'kata_sandi' => 'password123',
            'kata_sandi_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('setuju_data');
    }
}
