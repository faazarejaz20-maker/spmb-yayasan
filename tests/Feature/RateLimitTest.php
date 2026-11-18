<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;

/**
 * Butir 17: pembatasan laju untuk simpan formulir, unggah berkas, dan kirim.
 */
class RateLimitTest extends WalisantriTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->actingAs($this->wali);
    }

    public function test_simpan_langkah_dibatasi_20_per_menit(): void
    {
        $data = $this->dataLangkah2();

        for ($i = 0; $i < 20; $i++) {
            $this->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2', $data)
                ->assertStatus(302);
        }

        $this->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2', $data)
            ->assertStatus(429);
    }

    public function test_unggah_berkas_dibatasi_20_per_menit(): void
    {
        $jenis = $this->buatJenisBerkas(true);

        for ($i = 0; $i < 20; $i++) {
            $this->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/berkas', [
                'jenis_berkas_id' => $jenis->id,
                'berkas' => \Illuminate\Http\UploadedFile::fake()->createWithContent('akta.pdf', '%PDF-1.4 x'),
            ])->assertStatus(302);
        }

        $this->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/berkas', [
            'jenis_berkas_id' => $jenis->id,
            'berkas' => \Illuminate\Http\UploadedFile::fake()->createWithContent('akta.pdf', '%PDF-1.4 x'),
        ])->assertStatus(429);
    }

    public function test_kirim_dibatasi_10_per_menit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/kirim', [
                'pernyataan_data' => 1,
                'pernyataan_privasi' => 1,
            ])->assertStatus(302);
        }

        $this->post('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/kirim', [
            'pernyataan_data' => 1,
            'pernyataan_privasi' => 1,
        ])->assertStatus(429);
    }

    public function test_unduh_berkas_dibatasi_60_per_menit(): void
    {
        $berkas = $this->lampirkanBerkas($this->pendaftaran);

        for ($i = 0; $i < 60; $i++) {
            $this->get('/akun/berkas/'.$berkas->uuid)->assertNotFound();
        }

        $this->get('/akun/berkas/'.$berkas->uuid)->assertStatus(429);
    }
}
