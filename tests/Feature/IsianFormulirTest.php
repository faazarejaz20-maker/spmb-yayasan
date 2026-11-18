<?php

namespace Tests\Feature;

use App\Models\PengaturanIsian;

/**
 * Butir 4: isian yang disembunyikan tidak diterima walau dikirim paksa, dan
 * isian wajib yang kosong ditolak.
 */
class IsianFormulirTest extends WalisantriTestCase
{
    public function test_isian_tersembunyi_dibuang_walau_dikirim(): void
    {
        PengaturanIsian::create([
            'sekolah_id' => $this->sekolah->id,
            'kunci_isian' => 'nisn',
            'tampil' => false,
            'wajib' => false,
        ]);

        $this->assertNull($this->anak->nisn);

        $this->actingAs($this->wali);

        $this->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2', $this->dataLangkah2([
            'nisn' => '1234567890',
        ]))->assertRedirect();

        $this->assertNull($this->anak->fresh()->nisn);
    }

    public function test_isian_wajib_yang_kosong_ditolak(): void
    {
        PengaturanIsian::create([
            'sekolah_id' => $this->sekolah->id,
            'kunci_isian' => 'nama_panggilan',
            'tampil' => true,
            'wajib' => true,
        ]);

        $data = $this->dataLangkah2();
        unset($data['nama_panggilan']);

        $this->actingAs($this->wali);

        $this->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2', $data)
            ->assertSessionHasErrors('nama_panggilan');
    }

    public function test_isian_inti_selalu_wajib_walau_pengaturan_menyembunyikan(): void
    {
        PengaturanIsian::create([
            'sekolah_id' => $this->sekolah->id,
            'kunci_isian' => 'nama_lengkap',
            'tampil' => false,
            'wajib' => false,
        ]);

        $data = $this->dataLangkah2();
        unset($data['nama_lengkap']);

        $this->actingAs($this->wali);

        $this->put('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/2', $data)
            ->assertSessionHasErrors('nama_lengkap');
    }
}
