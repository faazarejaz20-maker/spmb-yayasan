<?php

namespace Tests\Feature;

use App\Enums\JenisRiwayat;
use App\Enums\StatusBerkas;
use App\Enums\StatusHasil;
use App\Models\Berkas;
use App\Models\LogAktivitas;
use App\Models\Pendaftaran;
use App\Models\RiwayatStatus;

/**
 * Butir 7 dan 8: prasyarat kirim, pengisian jejak, dan penguncian setelah
 * terkirim.
 */
class KirimPendaftaranTest extends WalisantriTestCase
{
    private function kirim(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->wali)->post(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/kirim',
            array_merge([
                'pernyataan_data' => 1,
                'pernyataan_privasi' => 1,
            ], $data)
        );
    }

    public function test_gagal_bila_orang_tua_belum_diisi(): void
    {
        $this->lampirkanBerkas($this->pendaftaran);

        $respons = $this->kirim();

        $respons->assertSessionHasErrors('kirim');
        $this->assertNull($this->pendaftaran->fresh()->dikirim_pada);
    }

    public function test_gagal_bila_berkas_wajib_kurang(): void
    {
        $this->lengkapiOrangTuaSaja();
        $this->buatJenisBerkas(true);

        $this->kirim()->assertSessionHasErrors('kirim');

        $this->assertNull($this->pendaftaran->fresh()->dikirim_pada);
    }

    public function test_gagal_bila_biaya_belum_disetujui(): void
    {
        $this->lengkapiOrangTuaSaja();
        $this->lampirkanBerkas($this->pendaftaran);
        $this->buatRincianBiaya();

        $this->kirim()->assertSessionHasErrors('kirim');

        $this->assertNull($this->pendaftaran->fresh()->setuju_biaya_pada);
    }

    public function test_gagal_bila_pernyataan_tidak_dicentang(): void
    {
        $this->siapkanKirim($this->pendaftaran);

        $this->actingAs($this->wali)->post(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/kirim',
            []
        )->assertSessionHasErrors(['pernyataan_data', 'pernyataan_privasi']);

        $this->assertNull($this->pendaftaran->fresh()->dikirim_pada);
    }

    public function test_gagal_bila_gelombang_ditutup(): void
    {
        $this->siapkanKirim($this->pendaftaran);
        $this->gelombang->forceFill(['status' => 'ditutup'])->save();

        $this->kirim()->assertSessionHasErrors('kirim');

        $this->assertNull($this->pendaftaran->fresh()->dikirim_pada);
    }

    public function test_kirim_berhasil_mengisi_nomor_status_riwayat_dan_log(): void
    {
        $this->siapkanKirim($this->pendaftaran);

        $this->kirim()->assertRedirect(route('walisantri.pendaftaran.sukses', $this->pendaftaran->uuid));

        $segar = $this->pendaftaran->fresh();

        $this->assertSame('SD-2027-0001', $segar->nomor_peserta);
        $this->assertNotNull($segar->dikirim_pada);
        $this->assertNotNull($segar->setuju_data_pada);
        $this->assertSame(StatusHasil::Baru, $segar->status_hasil);

        $riwayat = RiwayatStatus::query()->where('pendaftaran_id', $segar->id)->get();

        $this->assertTrue($riwayat->contains(
            fn (RiwayatStatus $r) => $r->jenis === JenisRiwayat::Hasil && $r->ke === StatusHasil::Baru->value
        ));

        $this->assertTrue(LogAktivitas::query()->where('aksi', 'kirim_pendaftaran')->exists());
    }

    public function test_berhasil_dengan_pilihan_iap_sah(): void
    {
        $this->lengkapiOrangTuaSaja();
        $this->lampirkanBerkas($this->pendaftaran);
        $this->buatRincianBiaya(100000, 'pilihan');
        $this->buatRincianBiaya(200000, 'pilihan');

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7',
            ['setuju_biaya' => 1, 'pilihan_iap' => 200000]
        )->assertRedirect();

        $this->assertSame('200000', (string) $this->pendaftaran->fresh()->pilihan_iap);

        $this->kirim()->assertRedirect();

        $this->assertNotNull($this->pendaftaran->fresh()->dikirim_pada);
    }

    public function test_pilihan_iap_di_luar_daftar_ditolak(): void
    {
        $this->buatRincianBiaya(100000, 'pilihan');

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7',
            ['setuju_biaya' => 1, 'pilihan_iap' => 999999]
        )->assertSessionHasErrors('pilihan_iap');

        $this->assertNull($this->pendaftaran->fresh()->pilihan_iap);
    }

    public function test_biaya_belum_dicentang_tidak_mengisi_setuju(): void
    {
        $this->buatRincianBiaya();

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/7',
            ['pilihan_iap' => null]
        )->assertSessionHasErrors('setuju_biaya');

        $this->assertNull($this->pendaftaran->fresh()->setuju_biaya_pada);
    }

    public function test_setelah_terkirim_data_tidak_bisa_diubah(): void
    {
        $this->siapkanKirim($this->pendaftaran);
        $this->kirim()->assertRedirect();

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/3',
            ['alamat' => 'Alamat setelah kirim']
        )->assertForbidden();

        $this->assertNotSame('Alamat setelah kirim', $this->anak->fresh()->alamat);
    }

    public function test_perlu_perbaikan_mengizinkan_ubah_dan_kirim_perbaikan(): void
    {
        $this->siapkanKirim($this->pendaftaran);
        $this->kirim()->assertRedirect();

        $this->pendaftaran->fresh()->forceFill([
            'status_hasil' => StatusHasil::PerluPerbaikan,
        ])->save();

        $this->actingAs($this->wali)->put(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/3',
            ['alamat' => 'Jl. Setelah Perbaikan']
        )->assertRedirect();

        $this->assertSame('Jl. Setelah Perbaikan', $this->anak->fresh()->alamat);

        $this->actingAs($this->wali)->post(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/kirim-perbaikan'
        )->assertRedirect(route('walisantri.pendaftaran.detail', $this->pendaftaran->uuid));

        $this->assertSame(StatusHasil::Baru, $this->pendaftaran->fresh()->status_hasil);
    }

    public function test_kirim_perbaikan_ditolak_bila_berkas_masih_ditandai(): void
    {
        $this->siapkanKirim($this->pendaftaran);

        $this->pendaftaran->forceFill([
            'status_hasil' => StatusHasil::PerluPerbaikan,
            'dikirim_pada' => now()->subDay(),
        ])->save();

        $jenis = $this->buatJenisBerkas(true);

        Berkas::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => 'berkas/x/y.pdf',
            'status' => StatusBerkas::PerluPerbaikan,
            'catatan' => 'Unggah ulang.',
        ]);

        $this->actingAs($this->wali)->post(
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/kirim-perbaikan'
        )->assertSessionHasErrors('kirim');

        $this->assertSame(StatusHasil::PerluPerbaikan, $this->pendaftaran->fresh()->status_hasil);
    }

    private function lengkapiOrangTuaSaja(): void
    {
        $this->lengkapiOrangTua();
    }
}
