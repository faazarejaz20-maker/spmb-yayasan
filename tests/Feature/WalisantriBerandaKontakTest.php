<?php

namespace Tests\Feature;

use App\Models\Sekolah;

/**
 * Beranda walisantri menampilkan kontak tiap sekolah aktif dan foto prestasi
 * asli per sekolah. Sekolah yang belum punya foto menampilkan "Foto prestasi
 * menyusul", bukan gambar karangan.
 */
class WalisantriBerandaKontakTest extends WalisantriTestCase
{
    public function test_beranda_menampilkan_kontak_setiap_sekolah_aktif(): void
    {
        Sekolah::factory()->create([
            'kode' => 'TKI',
            'nama' => 'KB-TKIT Insan Mulia',
            'jenjang' => 'KB, TK, TPA',
            'alamat' => 'Jl. Tumenggung Jogonegoro, Wonolelo, Wonosobo.',
            'kontak' => '6285695422700',
            'instagram' => 'tkitinsanmulia.wsb',
        ]);

        Sekolah::factory()->create([
            'kode' => 'OFF',
            'nama' => 'Sekolah Nonaktif',
            'kontak' => '628111111111',
            'aktif' => false,
        ]);

        $respons = $this->actingAs($this->wali)->get('/akun');

        $respons->assertOk()
            ->assertSee('Kontak sekolah')
            ->assertSee('SDIT Insan Mulia Wonosobo')
            ->assertSee('KB-TKIT Insan Mulia')
            ->assertSee('Jl. Tumenggung Jogonegoro, Wonolelo, Wonosobo.')
            ->assertSee('0856-9542-2700')
            ->assertSee('https://wa.me/6285695422700', false)
            ->assertSee('https://instagram.com/tkitinsanmulia.wsb', false)
            ->assertDontSee('Sekolah Nonaktif');
    }

    public function test_prestasi_tampil_per_sekolah_dan_menyusul_bila_belum_ada(): void
    {
        Sekolah::factory()->create([
            'kode' => 'SMP',
            'nama' => 'SMPIT Insan Mulia Wonosobo',
            'jenjang' => 'SMP',
        ]);

        Sekolah::factory()->create([
            'kode' => 'TKK',
            'nama' => 'TKIT Al Ishlah Kertek',
            'jenjang' => 'TK',
        ]);

        Sekolah::factory()->create([
            'kode' => 'TKI',
            'nama' => 'KB-TKIT Insan Mulia',
            'jenjang' => 'KB, TK, TPA',
        ]);

        $respons = $this->actingAs($this->wali)->get('/akun');

        $respons->assertOk()
            ->assertSee('Prestasi murid')
            ->assertSee('Juara III bulu tangkis putri Popda SD/MI Kecamatan Wonosobo')
            ->assertSee('Pemenang Gebyar Apresiasi SMPIT Insan Mulia Wonosobo')
            ->assertSee('Foto prestasi TKIT Al Ishlah Kertek menyusul.')
            ->assertSee('Foto prestasi KB-TKIT Insan Mulia menyusul.');
    }

    public function test_semua_foto_prestasi_yang_tampil_benar_benar_ada(): void
    {
        Sekolah::factory()->create([
            'kode' => 'SMP',
            'nama' => 'SMPIT Insan Mulia Wonosobo',
            'jenjang' => 'SMP',
        ]);

        $isi = (string) $this->actingAs($this->wali)->get('/akun')->getContent();

        preg_match_all('/src="(\/images\/[^"]+)"/', $isi, $cocok);

        $this->assertNotEmpty($cocok[1], 'Tidak ada foto prestasi yang tampil di beranda.');

        foreach (array_unique($cocok[1]) as $src) {
            $this->assertFileExists(
                public_path(urldecode($src)),
                "Berkas foto {$src} tidak ditemukan."
            );
        }
    }
}
