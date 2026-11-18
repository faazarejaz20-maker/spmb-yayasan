<?php

namespace Database\Factories;

use App\Enums\PeranPengguna;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '628'.fake()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'aktif' => true,
            'wajib_ganti_password' => false,
        ];
    }

    public function walisantri(): static
    {
        return $this->state(fn () => [
            'peran' => PeranPengguna::Walisantri,
            'sekolah_id' => null,
            'email' => null,
        ]);
    }

    public function panitia(?Sekolah $sekolah = null): static
    {
        return $this->state(fn () => [
            'peran' => PeranPengguna::Panitia,
            'sekolah_id' => $sekolah?->id ?? Sekolah::factory()->create()->id,
            'email' => null,
        ]);
    }

    public function admin(?Sekolah $sekolah = null): static
    {
        return $this->state(fn () => [
            'peran' => PeranPengguna::Admin,
            'sekolah_id' => $sekolah?->id ?? Sekolah::factory()->create()->id,
            'email' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'peran' => PeranPengguna::SuperAdmin,
            'sekolah_id' => null,
        ]);
    }

    public function yayasan(): static
    {
        return $this->state(fn () => [
            'peran' => PeranPengguna::Yayasan,
            'sekolah_id' => null,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }

    public function wajibGantiPassword(): static
    {
        return $this->state(fn () => ['wajib_ganti_password' => true]);
    }

    public function tanpaEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}
