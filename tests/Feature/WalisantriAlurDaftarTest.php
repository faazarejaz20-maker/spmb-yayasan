<?php

namespace Tests\Feature;

use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\Sekolah;

/**
 * Halaman "Daftar anak" memandu walisantri dengan alur delapan langkah,
 * daftar berkas nyata milik sekolah, dan catatan cara menyimpan draf.
 */
class WalisantriAlurDaftarTest extends WalisantriTestCase
{
    public function test_halaman_daftar_menampilkan_alur_dan_catatan_pengisian(): void
    {
        $respons = $this->actingAs($this->wali)->get('/akun/daftar/baru');

        $respons->assertOk()
            ->assertSee('Alur pendaftaran')
            ->assertSee('Delapan langkah, semuanya bisa disimpan sebagai draf.')
            ->assertSee('Pilih sekolah')
            ->assertSee('Data anak')
            ->assertSee('Alamat')
            ->assertSee('Kesehatan')
            ->assertSee('Asal sekolah')
            ->assertSee('Berkas')
            ->assertSee('Biaya')
            ->assertSee('Kirim')
            ->assertSee('Siapkan dari sekarang')
            ->assertSee('Kartu Keluarga (KK)')
            ->assertSee('Akta kelahiran')
            ->assertSee('Pas foto anak')
            ->assertSee('Catatan saat mengisi')
            ->assertSee('Simpan &amp; keluar', false)
            ->assertSee('Simpan &amp; lanjut', false)
            ->assertSee('Lihat kontak sekolah')
            ->assertSee('#judul-kontak', false);
    }

    public function test_berkas_wajib_tiap_sekolah_tampil_dan_berkas_nonaktif_disembunyikan(): void
    {
        JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Akta kelahiran',
            'wajib' => true,
            'urutan' => 1,
        ]);

        JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Kartu Keluarga (KK)',
            'wajib' => true,
            'urutan' => 2,
        ]);

        JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Surat keterangan sehat',
            'wajib' => false,
            'urutan' => 3,
        ]);

        JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Berkas lama nonaktif',
            'wajib' => true,
            'aktif' => false,
        ]);

        $respons = $this->actingAs($this->wali)->get('/akun/daftar/baru');

        $respons->assertOk()
            ->assertSee('Berkas yang perlu disiapkan')
            ->assertSee('Akta kelahiran')
            ->assertSee('Kartu Keluarga (KK)')
            ->assertSee('Surat keterangan sehat')
            ->assertSee('2 wajib')
            ->assertSee('Wajib')
            ->assertSee('Opsional')
            ->assertDontSee('Berkas lama nonaktif');
    }

    public function test_berkas_sekolah_yang_tidak_dibuka_tidak_tampil(): void
    {
        $lain = Sekolah::factory()->create([
            'kode' => 'SMP',
            'nama' => 'SMPIT Insan Mulia Wonosobo',
            'jenjang' => 'SMP',
        ]);

        Gelombang::factory()->create([
            'sekolah_id' => $lain->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'nama' => 'Gelombang SMP',
            'status' => 'ditutup',
        ]);

        JenisBerkas::factory()->create([
            'sekolah_id' => $lain->id,
            'nama' => 'Berkas khusus sekolah tutup',
        ]);

        $respons = $this->actingAs($this->wali)->get('/akun/daftar/baru');

        $respons->assertOk()
            ->assertDontSee('SMPIT Insan Mulia Wonosobo')
            ->assertDontSee('Berkas khusus sekolah tutup');
    }
}
