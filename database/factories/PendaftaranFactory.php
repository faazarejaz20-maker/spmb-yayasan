<?php

namespace Database\Factories;

use App\Enums\StatusHasil;
use App\Models\Anak;
use App\Models\Gelombang;
use App\Models\Pendaftaran;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pendaftaran>
 */
class PendaftaranFactory extends Factory
{
    protected $model = Pendaftaran::class;

    public function definition(): array
    {
        $gelombang = Gelombang::factory();

        return [
            'anak_id' => Anak::factory(),
            'sekolah_id' => Sekolah::factory(),
            'gelombang_id' => $gelombang,
            'program' => null,
            'nomor_peserta' => null,
            'status_hasil' => StatusHasil::Baru,
            'status_murid' => null,
            'lulusan_yayasan' => false,
            'dikirim_pada' => null,
            'dibuat_oleh' => null,
        ];
    }

    public function terkirim(): static
    {
        return $this->state(fn () => [
            'status_hasil' => StatusHasil::Baru,
            'dikirim_pada' => now(),
        ]);
    }
}
