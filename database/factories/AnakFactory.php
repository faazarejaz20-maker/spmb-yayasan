<?php

namespace Database\Factories;

use App\Models\Anak;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Anak>
 */
class AnakFactory extends Factory
{
    protected $model = Anak::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->walisantri(),
            'nama_lengkap' => fake()->name(),
            'nama_panggilan' => fake()->firstName(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-12 years', '-4 years')->format('Y-m-d'),
            'agama' => 'Islam',
            'alamat' => fake()->address(),
            'desa' => fake()->city(),
            'kecamatan' => fake()->city(),
            'kabupaten' => fake()->city(),
            'provinsi' => 'Jawa Tengah',
        ];
    }
}
