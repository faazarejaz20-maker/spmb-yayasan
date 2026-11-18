<?php

namespace Tests\Feature;

use App\Enums\StatusHasil;
use App\Models\Anak;
use App\Models\User;

/**
 * Butir 5: deteksi data ganda dari nama + tanggal lahir + sekolah.
 */
class DeteksiGandaTest extends WalisantriTestCase
{
    private function buatKembar(string $nama, StatusHasil $status = StatusHasil::Baru): User
    {
        $lain = User::factory()->walisantri()->create([
            'nama' => 'Wali Lain',
            'no_hp' => '628999999999',
        ]);

        $anakLain = Anak::factory()->create([
            'user_id' => $lain->id,
            'nama_lengkap' => $nama,
            'tanggal_lahir' => '2019-04-12',
        ]);

        $this->buatPendaftaranTerkirim($lain, $anakLain, status: $status->value);

        return $lain;
    }

    public function test_nama_dan_tanggal_sama_ditolak_dengan_pesan_umum(): void
    {
        $this->buatKembar('AISYAH   nur fadhilah');

        $this->actingAs($this->wali);

        $respons = $this->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2',
            $this->dataLangkah2()
        );

        $respons->assertSessionHasErrors('data_ganda');

        $pesan = (string) session('errors')->first('data_ganda');

        $this->assertStringContainsString('sudah terdaftar', $pesan);
        $this->assertStringNotContainsString('Wali Lain', $pesan);
        $this->assertStringNotContainsString('628999999999', $pesan);
    }

    public function test_pendaftar_ditolak_tidak_memblokir(): void
    {
        $this->buatKembar('Aisyah Nur Fadhilah', StatusHasil::Ditolak);

        $this->actingAs($this->wali);

        $this->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2',
            $this->dataLangkah2()
        )->assertSessionHasNoErrors();

        $this->assertSame('Aisyah Nur Fadhilah', $this->anak->fresh()->nama_lengkap);
    }

    public function test_anak_kembar_dengan_nama_berbeda_lolos(): void
    {
        $this->buatKembar('Aisyah Nur Fadhilah Kembar');

        $this->actingAs($this->wali);

        $this->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2',
            $this->dataLangkah2()
        )->assertSessionHasNoErrors();

        $this->assertSame('Aisyah Nur Fadhilah', $this->anak->fresh()->nama_lengkap);
    }

    public function test_sekolah_berbeda_tidak_memblokir(): void
    {
        $lain = User::factory()->walisantri()->create();
        $sekolahLain = \App\Models\Sekolah::factory()->create(['kode' => 'SMP']);
        $gelombangLain = \App\Models\Gelombang::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'status' => 'dibuka',
        ]);

        $anakLain = Anak::factory()->create([
            'user_id' => $lain->id,
            'nama_lengkap' => 'Aisyah Nur Fadhilah',
            'tanggal_lahir' => '2019-04-12',
        ]);

        $this->buatPendaftaranTerkirim($lain, $anakLain, $sekolahLain, $gelombangLain);

        $this->actingAs($this->wali);

        $this->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2',
            $this->dataLangkah2()
        )->assertSessionHasNoErrors();
    }
}
