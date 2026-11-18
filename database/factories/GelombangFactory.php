<?php

namespace Database\Factories;

use App\Models\Gelombang;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gelombang>
 */
class GelombangFactory extends Factory
{
    protected $model = Gelombang::class;

    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => 'Gelombang '.fake()->numberBetween(1, 3),
            'tanggal_buka' => null,
            'tanggal_tutup' => null,
            'kuota' => null,
            'status' => 'draf',
            'tautan_grup_wa' => null,
        ];
    }
}
