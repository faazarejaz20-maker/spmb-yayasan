<?php

namespace Tests\Feature\Keamanan;

use App\Enums\PeranPengguna;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\KontenSekolah;
use App\Models\Pengumuman;
use App\Models\RincianBiaya;
use App\Models\RekeningSekolah;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 13: mass assignment pada semua formulir admin dan super admin.
 * `peran`, `sekolah_id`, `dibuat_oleh`, `aktif`, `diumumkan_oleh`, dan
 * `diumumkan_pada` tidak bisa disisipkan dari luar.
 */
class PanelMassAssignmentTest extends PanitiaTestCase
{
    public function test_formulir_gelombang_mengabaikan_sekolah_dan_tahun_ajaran(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/gelombang', [
                'nama' => 'Gelombang Uji MA',
                'status' => 'draf',
                'sekolah_id' => $this->sekolahB->id,
                'tahun_ajaran_id' => 123456,
            ])
            ->assertSessionHasNoErrors();

        $gelombang = Gelombang::query()->where('nama', 'Gelombang Uji MA')->firstOrFail();

        $this->assertSame($this->sekolah->id, (int) $gelombang->sekolah_id);
        $this->assertSame($this->tahun->id, (int) $gelombang->tahun_ajaran_id);
    }

    public function test_formulir_jenis_berkas_dan_biaya_mengabaikan_sekolah_id(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/berkas', [
                'nama' => 'KK Uji MA',
                'wajib' => '1',
                'jumlah_lembar' => 1,
                'aktif' => '1',
                'sekolah_id' => $this->sekolahB->id,
            ])
            ->assertSessionHasNoErrors();

        $jenis = JenisBerkas::query()->where('nama', 'KK Uji MA')->firstOrFail();
        $this->assertSame($this->sekolah->id, (int) $jenis->sekolah_id);

        $this->post('/admin/biaya', [
            'kelompok' => 'IAP',
            'nama' => 'Biaya Uji MA',
            'nominal' => 100000,
            'tipe' => 'tetap',
            'aktif' => '1',
            'sekolah_id' => $this->sekolahB->id,
            'tahun_ajaran_id' => 999,
        ])->assertSessionHasNoErrors();

        $biaya = RincianBiaya::query()->where('nama', 'Biaya Uji MA')->firstOrFail();
        $this->assertSame($this->sekolah->id, (int) $biaya->sekolah_id);
        $this->assertSame($this->tahun->id, (int) $biaya->tahun_ajaran_id);
    }

    public function test_formulir_rekening_mengabaikan_sekolah_id(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/rekening', [
                'bank' => 'BRI',
                'nomor_rekening' => '111122223333',
                'atas_nama' => 'SDIT Insan Mulia',
                'aktif' => '1',
                'sekolah_id' => $this->sekolahB->id,
            ])
            ->assertSessionHasNoErrors();

        $rekening = RekeningSekolah::query()->where('nomor_rekening', '111122223333')->firstOrFail();
        $this->assertSame($this->sekolah->id, (int) $rekening->sekolah_id);
    }

    public function test_formulir_akun_panitia_mengabaikan_peran_sekolah_dan_pembuat(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/panitia', [
                'nama' => 'Panitia Uji MA',
                'no_hp' => '081400000001',
                'peran' => 'admin',
                'sekolah_id' => $this->sekolahB->id,
                'dibuat_oleh' => 999,
                'aktif' => '0',
                'wajib_ganti_password' => '0',
            ])
            ->assertRedirect();

        $akun = User::query()->where('no_hp', '6281400000001')->firstOrFail();

        $this->assertSame(PeranPengguna::Panitia, $akun->peran);
        $this->assertSame($this->sekolah->id, (int) $akun->sekolah_id);
        $this->assertSame($this->admin->id, (int) $akun->dibuat_oleh);
        $this->assertTrue((bool) $akun->aktif);
        $this->assertTrue((bool) $akun->wajib_ganti_password);
    }

    public function test_formulir_akun_super_mengabaikan_peran_dan_pembuat(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->post('/super/admin', [
                'nama' => 'Admin Uji MA',
                'no_hp' => '081400000002',
                'sekolah_id' => $this->sekolah->id,
                'peran' => 'super_admin',
                'dibuat_oleh' => 999,
                'aktif' => '0',
            ])
            ->assertRedirect();

        $akun = User::query()->where('no_hp', '6281400000002')->firstOrFail();

        $this->assertSame(PeranPengguna::Admin, $akun->peran);
        $this->assertSame($this->sekolah->id, (int) $akun->sekolah_id);
        $this->assertSame($super->id, (int) $akun->dibuat_oleh);
        $this->assertTrue((bool) $akun->aktif);

        $this->post('/super/yayasan', [
            'nama' => 'Yayasan Uji MA',
            'no_hp' => '081400000003',
            'peran' => 'admin',
            'sekolah_id' => $this->sekolah->id,
        ])->assertRedirect();

        $yayasan = User::query()->where('no_hp', '6281400000003')->firstOrFail();

        $this->assertSame(PeranPengguna::Yayasan, $yayasan->peran);
        $this->assertNull($yayasan->sekolah_id);
    }

    public function test_formulir_umumkan_hasil_mengabaikan_pencatat(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, [
                'konfirmasi' => 1,
                'diumumkan_oleh' => 999,
                'diumumkan_pada' => '2000-01-01 00:00:00',
                'sekolah_id' => $this->sekolahB->id,
                'gelombang_id' => 999,
            ])
            ->assertSessionHas('sukses');

        $pengumuman = Pengumuman::query()->firstOrFail();

        $this->assertSame($this->admin->id, (int) $pengumuman->diumumkan_oleh);
        $this->assertSame($this->sekolah->id, (int) $pengumuman->sekolah_id);
        $this->assertSame($this->gelombang->id, (int) $pengumuman->gelombang_id);
        $this->assertNotSame('2000-01-01 00:00:00', $pengumuman->diumumkan_pada->format('Y-m-d H:i:s'));
    }

    public function test_formulir_konten_mengabaikan_sekolah_id(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/konten/teks', [
                'bagian' => 'slogan',
                'isi' => 'Slogan sah',
                'sekolah_id' => $this->sekolahB->id,
            ])
            ->assertSessionHasNoErrors();

        $baris = KontenSekolah::query()
            ->where('bagian', 'slogan')
            ->where('isi', 'Slogan sah')
            ->firstOrFail();

        $this->assertSame($this->sekolah->id, (int) $baris->sekolah_id);
    }

    public function test_formulir_pengaturan_isian_mengabaikan_sekolah_id(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/isian', [
                'isian' => [
                    'hobi' => ['tampil' => 1, 'wajib' => 1],
                ],
                'sekolah_id' => $this->sekolahB->id,
            ])
            ->assertSessionHasNoErrors();

        $baris = DB::table('pengaturan_isian')
            ->where('kunci_isian', 'hobi')
            ->get();

        $this->assertSame(1, $baris->count());
        $this->assertSame($this->sekolah->id, (int) $baris[0]->sekolah_id);
    }

    public function test_formulir_sekolah_super_mengabaikan_kode_dan_aktif_lain(): void
    {
        $super = User::factory()->superAdmin()->create();
        $kode = $this->sekolah->kode;

        $this->actingAs($super)
            ->put('/super/sekolah/'.$this->sekolah->id, [
                'nama' => $this->sekolah->nama,
                'mode_penerimaan' => 'seleksi',
                'aktif' => '0',
                'kode' => 'HACK',
            ])
            ->assertSessionHasNoErrors();

        $sekolah = $this->sekolah->fresh();

        $this->assertSame($kode, $sekolah->kode);
        // `aktif` tetap dari input (sah bagi super admin), tetapi kode tak berubah.
    }
}
