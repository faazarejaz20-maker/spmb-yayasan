<?php

namespace Tests\Feature\Keamanan;

use App\Models\JenisBerkas;
use App\Models\KontenSekolah;
use App\Models\LogAktivitas;
use App\Models\RekeningSekolah;
use App\Models\RincianBiaya;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 2: admin sekolah A tidak bisa melihat, mengubah, atau mengumumkan
 * data sekolah B (uuid atau id valid) — 404. `sekolah_id` yang dikirim
 * lewat formulir diabaikan.
 */
class IsolasiSekolahAdminTest extends PanitiaTestCase
{
    public function test_admin_a_tidak_bisa_ubah_dan_hapus_gelombang_sekolah_b(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/pengumuman/'.$this->gelombangB->id)->assertNotFound();

        $this->put('/admin/gelombang/'.$this->gelombangB->id, [
            'nama' => 'Direbut',
            'status' => 'draf',
        ])->assertNotFound();

        $this->delete('/admin/gelombang/'.$this->gelombangB->id)->assertNotFound();

        $this->assertSame('Gelombang 1', $this->gelombangB->fresh()->nama);
    }

    public function test_admin_a_tidak_bisa_mengumumkan_gelombang_sekolah_b(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombangB->id, ['konfirmasi' => 1])
            ->assertNotFound();

        $this->assertDatabaseMissing('pengumuman', ['gelombang_id' => $this->gelombangB->id]);
    }

    public function test_admin_a_tidak_bisa_ubah_dan_hapus_jenis_berkas_sekolah_b(): void
    {
        $jenis = JenisBerkas::factory()->create(['sekolah_id' => $this->sekolahB->id]);
        $namaAsli = $jenis->nama;

        $this->actingAs($this->admin)
            ->put('/admin/berkas/'.$jenis->id, [
                'nama' => 'Direbut',
                'jumlah_lembar' => 1,
            ])
            ->assertNotFound();

        $this->delete('/admin/berkas/'.$jenis->id)->assertNotFound();

        $this->assertSame($namaAsli, $jenis->fresh()->nama);
    }

    public function test_admin_a_tidak_bisa_ubah_dan_hapus_biaya_sekolah_b(): void
    {
        $biaya = RincianBiaya::create([
            'sekolah_id' => $this->sekolahB->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kelompok' => 'IAP',
            'nama' => 'IAP SMP',
            'nominal' => 100000,
            'tipe' => 'tetap',
            'urutan' => 1,
        ]);

        $this->actingAs($this->admin)
            ->put('/admin/biaya/'.$biaya->id, [
                'nama' => 'Direbut',
                'nominal' => 1,
                'tipe' => 'tetap',
            ])
            ->assertNotFound();

        $this->delete('/admin/biaya/'.$biaya->id)->assertNotFound();

        $this->assertSame('IAP SMP', $biaya->fresh()->nama);
        $this->assertSame(100000, (int) $biaya->fresh()->nominal);
    }

    public function test_admin_a_tidak_bisa_ubah_dan_hapus_rekening_sekolah_b(): void
    {
        $rekening = RekeningSekolah::create([
            'sekolah_id' => $this->sekolahB->id,
            'bank' => 'BRI',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'SMPIT Insan Mulia',
            'aktif' => true,
        ]);

        $this->actingAs($this->admin)
            ->put('/admin/rekening/'.$rekening->id, [
                'bank' => 'Direbut',
                'nomor_rekening' => '9999999999',
                'atas_nama' => 'Direbut',
            ])
            ->assertNotFound();

        $this->delete('/admin/rekening/'.$rekening->id)->assertNotFound();

        $this->assertSame('BRI', $rekening->fresh()->bank);
    }

    public function test_admin_a_tidak_bisa_kelola_konten_sekolah_b_dan_konten_yayasan(): void
    {
        $konten = KontenSekolah::create([
            'sekolah_id' => $this->sekolahB->id,
            'bagian' => 'slogan',
            'isi' => 'Slogan SMP',
            'urutan' => 0,
        ]);

        $kontenYayasan = KontenSekolah::create([
            'sekolah_id' => null,
            'bagian' => 'profil',
            'isi' => 'Profil yayasan',
            'urutan' => 0,
        ]);

        $this->actingAs($this->admin);

        $this->put('/admin/konten/'.$konten->id, ['bagian' => 'slogan', 'isi' => 'Direbut'])
            ->assertNotFound();

        $this->delete('/admin/konten/'.$konten->id)->assertNotFound();

        $this->put('/admin/konten/'.$kontenYayasan->id, ['bagian' => 'profil', 'isi' => 'Direbut'])
            ->assertNotFound();

        $this->assertSame('Slogan SMP', $konten->fresh()->isi);
        $this->assertSame('Profil yayasan', $kontenYayasan->fresh()->isi);
    }

    public function test_admin_a_tidak_bisa_nonaktifkan_staf_sekolah_lain(): void
    {
        $adminB = \App\Models\User::factory()->admin($this->sekolahB)->create();

        $this->actingAs($this->admin);

        $this->post('/admin/panitia/'.$this->panitiaB->id.'/nonaktifkan')->assertNotFound();
        $this->post('/admin/panitia/'.$this->panitiaB->id.'/aktifkan')->assertNotFound();
        $this->post('/admin/panitia/'.$this->panitiaB->id.'/atur-ulang')->assertNotFound();

        $this->post('/admin/panitia/'.$adminB->id.'/nonaktifkan')->assertNotFound();

        $this->assertTrue((bool) $this->panitiaB->fresh()->aktif);
        $this->assertTrue((bool) $adminB->fresh()->aktif);
    }

    public function test_log_sekolah_b_tidak_muncul_di_admin_a(): void
    {
        LogAktivitas::create([
            'sekolah_id' => $this->sekolahB->id,
            'aksi' => 'tanda_sekolah_b',
        ]);

        LogAktivitas::create([
            'sekolah_id' => $this->sekolah->id,
            'aksi' => 'tanda_sekolah_a',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/log')
            ->assertOk()
            ->assertSee('tanda_sekolah_a', false)
            ->assertDontSee('tanda_sekolah_b', false);
    }

    public function test_sekolah_id_dari_formulir_gelombang_diabaikan(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/gelombang', [
                'nama' => 'Gelombang Sulap',
                'status' => 'draf',
                'sekolah_id' => $this->sekolahB->id,
                'tahun_ajaran_id' => 999999,
            ])
            ->assertSessionHasNoErrors();

        $gelombang = DB::table('gelombang')
            ->where('nama', 'Gelombang Sulap')
            ->first();

        $this->assertNotNull($gelombang);
        $this->assertSame($this->sekolah->id, (int) $gelombang->sekolah_id);
        $this->assertSame($this->tahun->id, (int) $gelombang->tahun_ajaran_id);
    }

    public function test_sekolah_id_dari_formulir_akun_panitia_diabaikan(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/panitia', [
                'nama' => 'Panitia Sulap',
                'no_hp' => '081200000001',
                'sekolah_id' => $this->sekolahB->id,
            ])
            ->assertRedirect();

        $akun = DB::table('users')
            ->where('no_hp', '6281200000001')
            ->first();

        $this->assertNotNull($akun);
        $this->assertSame('panitia', $akun->peran);
        $this->assertSame($this->sekolah->id, (int) $akun->sekolah_id);
    }

    public function test_mode_penerimaan_hanya_mengubah_sekolah_sendiri(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/pengaturan', [
                'mode_penerimaan' => 'seleksi',
                'sekolah_id' => $this->sekolahB->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('seleksi', $this->sekolah->fresh()->mode_penerimaan->value);
        $this->assertSame('tanpa_seleksi', $this->sekolahB->fresh()->mode_penerimaan->value);
    }
}
