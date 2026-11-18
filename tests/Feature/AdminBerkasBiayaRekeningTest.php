<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Models\JenisBerkas;
use App\Models\LogAktivitas;
use App\Models\Pengumuman;
use App\Models\RincianBiaya;
use App\Models\RekeningSekolah;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 8: jenis berkas dan biaya. Tidak bisa dihapus bila dipakai; perubahan
 * biaya tidak mengubah persetujuan pendaftaran yang sudah terkirim; nominal
 * negatif atau bukan angka ditolak; rekening tidak muncul di halaman publik;
 * log rekening hanya 4 digit terakhir.
 */
class AdminBerkasBiayaRekeningTest extends PanitiaTestCase
{
    public function test_jenis_berkas_yang_punya_unggahan_tidak_bisa_dihapus(): void
    {
        $jenis = $this->buatJenisBerkas();
        $this->lampirkanBerkas($this->pendaftaran, $jenis);

        $this->actingAs($this->admin)
            ->delete('/admin/berkas/'.$jenis->id)
            ->assertSessionHasErrors(['berkas']);

        $this->assertDatabaseHas('jenis_berkas', ['id' => $jenis->id]);
    }

    public function test_jenis_berkas_belum_dipakai_bisa_dihapus(): void
    {
        $jenis = $this->buatJenisBerkas();

        $this->actingAs($this->admin)
            ->delete('/admin/berkas/'.$jenis->id)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('jenis_berkas', ['id' => $jenis->id]);
    }

    public function test_menonaktifkan_jenis_berkas_tidak_menghapus_unggahan(): void
    {
        $jenis = $this->buatJenisBerkas();
        $berkas = $this->lampirkanBerkas($this->pendaftaran, $jenis);

        $this->actingAs($this->admin)
            ->put('/admin/berkas/'.$jenis->id, [
                'nama' => $jenis->nama,
                'wajib' => '1',
                'jumlah_lembar' => 1,
                'aktif' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $jenis->fresh()->aktif);
        $this->assertDatabaseHas('berkas', ['id' => $berkas->id]);
    }

    public function test_perubahan_biaya_tidak_mengubah_persetujuan_pendaftaran_terkirim(): void
    {
        $biaya = RincianBiaya::create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kelompok' => 'IAP',
            'nama' => 'IAP Gelombang 1',
            'nominal' => 500000,
            'tipe' => 'pilihan',
            'urutan' => 1,
        ]);

        $p = $this->terkirim();
        $p->forceFill([
            'pilihan_iap' => 500000,
            'setuju_biaya_pada' => '2027-01-01 08:00:00',
        ])->save();

        $this->actingAs($this->admin)
            ->put('/admin/biaya/'.$biaya->id, [
                'kelompok' => 'IAP',
                'nama' => 'IAP Gelombang 1',
                'nominal' => 750000,
                'tipe' => 'pilihan',
                'aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(750000, (int) $biaya->fresh()->nominal);

        $p->refresh();
        $this->assertSame(500000, (int) $p->pilihan_iap);
        $this->assertSame('2027-01-01 08:00:00', $p->setuju_biaya_pada->format('Y-m-d H:i:s'));
    }

    public function test_nominal_biaya_negatif_dan_bukan_angka_ditolak(): void
    {
        $biaya = $this->buatRincianBiaya(50000, 'tetap');

        $this->actingAs($this->admin)
            ->put('/admin/biaya/'.$biaya->id, [
                'kelompok' => 'IAP',
                'nama' => $biaya->nama,
                'nominal' => -5000,
                'tipe' => 'tetap',
            ])
            ->assertSessionHasErrors(['nominal']);

        $this->actingAs($this->admin)
            ->post('/admin/biaya', [
                'kelompok' => 'IAP',
                'nama' => 'Biaya Salah',
                'nominal' => 'lima ribu',
                'tipe' => 'tetap',
            ])
            ->assertSessionHasErrors(['nominal']);

        $this->assertDatabaseMissing('rincian_biaya', ['nama' => 'Biaya Salah']);
        $this->assertSame(50000, (int) $biaya->fresh()->nominal);
    }

    public function test_biaya_dipakai_tidak_bisa_dihapus(): void
    {
        $biaya = $this->buatRincianBiaya(50000, 'tetap');

        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);

        \App\Models\Pembayaran::create([
            'pendaftaran_id' => $p->id,
            'rincian_biaya_id' => $biaya->id,
            'metode' => MetodePembayaran::Tunai,
            'nominal' => 50000,
            'status' => StatusPembayaran::Disetujui,
        ]);

        $this->actingAs($this->admin)
            ->delete('/admin/biaya/'.$biaya->id)
            ->assertSessionHasErrors(['biaya']);

        $this->assertDatabaseHas('rincian_biaya', ['id' => $biaya->id]);
    }

    public function test_rekening_tidak_tampil_di_halaman_publik(): void
    {
        RekeningSekolah::create([
            'sekolah_id' => $this->sekolah->id,
            'bank' => 'BRI',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'SDIT Insan Mulia',
            'aktif' => true,
        ]);

        foreach (['/', '/sekolah/SD', '/kebijakan-privasi', '/masuk'] as $rute) {
            $respons = $this->get($rute);

            if ($respons->status() !== 200) {
                continue;
            }

            $this->assertStringNotContainsString('1234567890', (string) $respons->getContent());
            $this->assertStringNotContainsString('nomor_rekening', (string) $respons->getContent());
        }
    }

    public function test_rekening_sekolah_lain_tidak_tampil_ke_walisantri(): void
    {
        RekeningSekolah::create([
            'sekolah_id' => $this->sekolah->id,
            'bank' => 'BRI',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'SDIT Insan Mulia',
            'aktif' => true,
        ]);

        RekeningSekolah::create([
            'sekolah_id' => $this->sekolahB->id,
            'bank' => 'BSI',
            'nomor_rekening' => '9876543210',
            'atas_nama' => 'SMPIT Insan Mulia',
            'aktif' => true,
        ]);

        $p = $this->pendaftaranSekolahB(status: 'Diterima');
        Pengumuman::create([
            'sekolah_id' => $this->sekolahB->id,
            'gelombang_id' => $this->gelombangB->id,
            'diumumkan_pada' => now(),
            'teks_pesan' => 'teks',
        ]);

        $this->actingAs($p->anak->user)
            ->get('/akun/pendaftaran/'.$p->uuid.'/pembayaran')
            ->assertOk()
            ->assertSee('9876543210', false)
            ->assertDontSee('1234567890', false);
    }

    public function test_log_rekening_hanya_4_digit_terakhir(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/rekening', [
                'bank' => 'BRI',
                'nomor_rekening' => '1234567890123',
                'atas_nama' => 'SDIT Insan Mulia',
                'aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rekening_sekolah', ['nomor_rekening' => '1234567890123']);

        $log = LogAktivitas::query()
            ->where('aksi', 'buat_rekening')
            ->firstOrFail();

        $data = (string) $log->data;

        $this->assertStringContainsString('0123', $data);
        $this->assertStringNotContainsString('1234567890123', $data);
        $this->assertStringNotContainsString('nomor_rekening', $data);
    }

    public function test_rekening_dengan_pembayaran_tidak_bisa_dihapus(): void
    {
        $rekening = RekeningSekolah::create([
            'sekolah_id' => $this->sekolah->id,
            'bank' => 'BRI',
            'nomor_rekening' => '1234567890',
            'atas_nama' => 'SDIT Insan Mulia',
            'aktif' => true,
        ]);

        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);

        $this->pembayaran($p, StatusPembayaran::Menunggu, 150000, MetodePembayaran::Transfer);

        $this->actingAs($this->admin)
            ->delete('/admin/rekening/'.$rekening->id)
            ->assertSessionHasErrors(['rekening']);

        $this->assertDatabaseHas('rekening_sekolah', ['id' => $rekening->id]);
    }

    public function test_jenis_berkas_tidak_wajib_menjadi_wajib_tidak_menggagalkan_pendaftaran(): void
    {
        $jenis = $this->buatJenisBerkas(wajib: false);

        $p = $this->terkirim();
        $p->forceFill(['status_hasil' => StatusHasil::Diverifikasi])->save();

        $this->actingAs($this->admin)
            ->put('/admin/berkas/'.$jenis->id, [
                'nama' => $jenis->nama,
                'wajib' => '1',
                'jumlah_lembar' => 1,
                'aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $jenis->fresh()->wajib);
        $this->assertNotNull($p->fresh()->dikirim_pada);
        $this->assertSame(StatusHasil::Diverifikasi, $p->fresh()->status_hasil);
    }
}
