<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GantiPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_wajib_ganti_password_hanya_bisa_ke_ganti_password_dan_keluar(): void
    {
        $user = User::forceCreate([
            'nama' => 'Wali Baru',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
            'wajib_ganti_password' => true,
        ]);

        $this->actingAs($user);

        $this->get('/akun')->assertRedirect('/ganti-password');
        $this->get('/ganti-password')->assertOk();
    }

    public function test_ganti_password_berhasil_mengosongkan_penanda(): void
    {
        $user = User::forceCreate([
            'nama' => 'Wali Baru',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
            'wajib_ganti_password' => true,
        ]);

        $this->actingAs($user);

        $response = $this->post('/ganti-password', [
            'kata_sandi_baru' => 'passwordbaru123',
            'kata_sandi_baru_confirmation' => 'passwordbaru123',
        ]);

        $response->assertRedirect('/akun');
        $this->assertFalse($user->fresh()->wajib_ganti_password);
    }

    public function test_ganti_password_mewajibkan_kata_sandi_lama(): void
    {
        $user = User::forceCreate([
            'nama' => 'Wali Baru',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
            'wajib_ganti_password' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post('/ganti-password', [
            'kata_sandi_baru' => 'passwordbaru123',
            'kata_sandi_baru_confirmation' => 'passwordbaru123',
        ]);

        $response->assertSessionHasErrors('kata_sandi_lama');
    }

    public function test_kata_sandi_tidak_boleh_sama_dengan_no_hp(): void
    {
        $user = User::forceCreate([
            'nama' => 'Wali Baru',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
            'wajib_ganti_password' => true,
        ]);

        $this->actingAs($user);

        $response = $this->post('/ganti-password', [
            'kata_sandi_baru' => '6281234567890',
            'kata_sandi_baru_confirmation' => '6281234567890',
        ]);

        $response->assertSessionHasErrors('kata_sandi_baru');
    }
}
