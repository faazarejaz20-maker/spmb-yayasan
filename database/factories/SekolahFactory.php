<?php

namespace Database\Factories;

use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sekolah>
 */
class SekolahFactory extends Factory
{
    protected $model = Sekolah::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('???')),
            'nama' => fake()->company(),
            'jenjang' => fake()->randomElement(['TK', 'SD', 'SMP']),
            'alamat' => fake()->address(),
            'kontak' => '628'.fake()->numerify('##########'),
            'mode_penerimaan' => 'tanpa_seleksi',
            'aktif' => true,
        ];
    }
}
