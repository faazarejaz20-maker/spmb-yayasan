<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    use RefreshDatabase;

    private function buatWali(string $kataSandi = 'password123'): User
    {
        return User::forceCreate([
            'nama' => 'Wali Contoh',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => $kataSandi,
            'aktif' => true,
        ]);
    }

    public function test_masuk_berhasil_dengan_no_hp(): void
    {
        $this->buatWali();

        $response = $this->post('/masuk', [
            'identitas' => '0812-3456-7890',
            'kata_sandi' => 'password123',
        ]);

        $response->assertRedirect('/akun');
        $this->assertAuthenticated();
    }

    public function test_masuk_berhasil_dengan_email(): void
    {
        User::forceCreate([
            'nama' => 'Wali Email',
            'peran' => PeranPengguna::Walisantri,
            'email' => 'wali@example.com',
            'password' => 'password123',
            'aktif' => true,
        ]);

        $response = $this->post('/masuk', [
            'identitas' => 'wali@example.com',
            'kata_sandi' => 'password123',
        ]);

        $response->assertRedirect('/akun');
        $this->assertAuthenticated();
    }

    public function test_salah_kata_sandi_dan_akun_tidak_ada_pesan_sama(): void
    {
        $this->buatWali();

        $salahKataSandi = $this->post('/masuk', [
            'identitas' => '6281234567890',
            'kata_sandi' => 'salah12345',
        ]);

        $akunTidakAda = $this->post('/masuk', [
            'identitas' => '6289999999999',
            'kata_sandi' => 'password123',
        ]);

        $salahKataSandi->assertSessionHasErrors('identitas');
        $akunTidakAda->assertSessionHasErrors('identitas');

        $this->assertSame(
            $salahKataSandi->getSession()->get('errors')->first('identitas'),
            $akunTidakAda->getSession()->get('errors')->first('identitas')
        );
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        User::forceCreate([
            'nama' => 'Wali Nonaktif',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => false,
        ]);

        $response = $this->post('/masuk', [
            'identitas' => '6281234567890',
            'kata_sandi' => 'password123',
        ]);

        $response->assertSessionHasErrors('identitas');
        $this->assertGuest();
    }

    public function test_sesi_diregenerasi_setelah_masuk(): void
    {
        $this->buatWali();

        $sebelum = session()->getId();

        $this->post('/masuk', [
            'identitas' => '6281234567890',
            'kata_sandi' => 'password123',
        ]);

        $this->assertNotSame($sebelum, session()->getId());
    }

    public function test_429_setelah_5_percobaan_gagal(): void
    {
        $this->buatWali();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', [
                'identitas' => '6281234567890',
                'kata_sandi' => 'salah12345',
            ]);
        }

        $response = $this->post('/masuk', [
            'identitas' => '6281234567890',
            'kata_sandi' => 'password123',
        ]);

        $response->assertStatus(429);
    }

    public function test_masuk_diarahkan_ke_akun_sesuai_peran(): void
    {
        $sekolah = Sekolah::factory()->create();

        $panitia = User::forceCreate([
            'nama' => 'Panitia A',
            'peran' => PeranPengguna::Panitia,
            'sekolah_id' => $sekolah->id,
            'no_hp' => '6281000000001',
            'password' => 'password123',
            'aktif' => true,
        ]);

        $this->post('/masuk', [
            'identitas' => '6281000000001',
            'kata_sandi' => 'password123',
        ])->assertRedirect('/panitia');
    }
}
