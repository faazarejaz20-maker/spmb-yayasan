<?php

namespace Tests\Unit;

use App\Enums\PeranPengguna;
use App\Models\Sekolah;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_wajib_punya_sekolah(): void
    {
        $this->expectException(DomainException::class);

        User::forceCreate([
            'nama' => 'Admin tanpa sekolah',
            'peran' => PeranPengguna::Admin,
            'no_hp' => '6281234567890',
            'password' => 'password123',
        ]);
    }

    public function test_panitia_wajib_punya_sekolah(): void
    {
        $this->expectException(DomainException::class);

        User::forceCreate([
            'nama' => 'Panitia tanpa sekolah',
            'peran' => PeranPengguna::Panitia,
            'no_hp' => '6281234567890',
            'password' => 'password123',
        ]);
    }

    public function test_walisantri_tidak_boleh_punya_sekolah(): void
    {
        $sekolah = Sekolah::factory()->create();

        $this->expectException(DomainException::class);

        User::forceCreate([
            'nama' => 'Wali dengan sekolah',
            'peran' => PeranPengguna::Walisantri,
            'sekolah_id' => $sekolah->id,
            'no_hp' => '6281234567890',
            'password' => 'password123',
        ]);
    }

    public function test_no_hp_atau_email_wajib_terisi(): void
    {
        $this->expectException(DomainException::class);

        User::forceCreate([
            'nama' => 'Tanpa kontak',
            'peran' => PeranPengguna::SuperAdmin,
            'password' => 'password123',
        ]);
    }

    public function test_admin_dengan_sekolah_dan_no_hp_berhasil(): void
    {
        $sekolah = Sekolah::factory()->create();

        $user = User::forceCreate([
            'nama' => 'Admin sah',
            'peran' => PeranPengguna::Admin,
            'sekolah_id' => $sekolah->id,
            'no_hp' => '6281234567890',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', ['nama' => 'Admin sah', 'sekolah_id' => $sekolah->id]);
        $this->assertSame(PeranPengguna::Admin, $user->peran);
    }

    public function test_walisantri_hanya_dengan_email_berhasil(): void
    {
        $user = User::forceCreate([
            'nama' => 'Wali email',
            'peran' => PeranPengguna::Walisantri,
            'email' => 'wali@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', ['nama' => 'Wali email']);
    }
}
