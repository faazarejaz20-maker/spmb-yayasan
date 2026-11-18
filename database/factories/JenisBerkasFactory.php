<?php

namespace Database\Factories;

use App\Models\JenisBerkas;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisBerkas>
 */
class JenisBerkasFactory extends Factory
{
    protected $model = JenisBerkas::class;

    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'nama' => 'Berkas '.fake()->word(),
            'wajib' => true,
            'jumlah_lembar' => 1,
            'urutan' => 0,
            'aktif' => true,
        ];
    }
}
