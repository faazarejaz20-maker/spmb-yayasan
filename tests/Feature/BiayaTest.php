<?php

namespace Tests\Feature;

use App\Enums\TipeBiaya;
use App\Models\RincianBiaya;

/**
 * Butir 13: biaya pendidikan hanya tampil setelah masuk, pilihan IAP
 * tersimpan, dan total ringkasan dihitung benar.
 */
class BiayaTest extends WalisantriTestCase
{
    public function test_biaya_tidak_tampil_di_halaman_publik(): void
    {
        RincianBiaya::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kelompok' => 'Biaya masuk',
            'nama' => 'Uang pangkal',
            'nominal' => 1500000,
            'tipe' => TipeBiaya::Tetap,
            'urutan' => 0,
        ]);

        foreach (['/', '/sekolah/SD', '/kebijakan-privasi'] as $jalur) {
            $this->get($jalur)
                ->assertOk()
                ->assertDontSee('Uang pangkal', false)
                ->assertDontSee('1.500.000', false);
        }
    }

    public function test_biaya_tidak_tampil_sebelum_masuk(): void
    {
        $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7')
            ->assertRedirect('/masuk');
    }

    public function test_biaya_tampil_setelah_masuk(): void
    {
        $this->buatRincianBiaya(1500000);

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7')
            ->assertOk()
            ->assertSee('Biaya tetap 1500000')
            ->assertSee('1.500.000');
    }

    public function test_pilihan_iap_disimpan_dan_total_benar(): void
    {
        $this->buatRincianBiaya(1000000, 'tetap');
        $this->buatRincianBiaya(2000000, 'pilihan');

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7',
            ['setuju_biaya' => 1, 'pilihan_iap' => 2000000]
        )->assertRedirect(route('walisantri.pendaftaran.langkah', [
            'pendaftaran' => $this->pendaftaran->uuid,
            'n' => 8,
        ]));

        $segar = $this->pendaftaran->fresh();

        $this->assertSame('2000000', (string) $segar->pilihan_iap);
        $this->assertNotNull($segar->setuju_biaya_pada);

        $respons = $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/8');

        $ringkasan = $respons->viewData('ringkasan');

        $this->assertSame(3000000, (int) $ringkasan['total']);
    }

    public function test_pilihan_iap_wajib_bila_ada_pilihan(): void
    {
        $this->buatRincianBiaya(1000000, 'pilihan');

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7',
            ['setuju_biaya' => 1]
        )->assertSessionHasErrors('pilihan_iap');
    }

    public function test_biaya_kosong_boleh_dilanjutkan_tanpa_centang(): void
    {
        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7')
            ->assertOk()
            ->assertSee('Rincian biaya menyusul');

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7',
            []
        )->assertRedirect();
    }

    public function test_biaya_sekolah_lain_tidak_tercampur(): void
    {
        $lain = \App\Models\Sekolah::factory()->create(['kode' => 'SMP']);

        RincianBiaya::create([
            'sekolah_id' => $lain->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kelompok' => 'Biaya sekolah lain',
            'nama' => 'Biaya SMP',
            'nominal' => 777000,
            'tipe' => TipeBiaya::Tetap,
            'urutan' => 0,
        ]);

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7')
            ->assertOk()
            ->assertDontSee('Biaya SMP', false);
    }
}
