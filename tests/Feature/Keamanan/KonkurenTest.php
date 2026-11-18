<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusHasil;
use App\Exceptions\AturanBisnisException;
use App\Models\Gelombang;
use App\Models\Pendaftaran;
use App\Services\NomorPesertaService;
use App\Services\PengumumanService;
use App\Services\StatusHasilService;
use Tests\Feature\PanitiaTestCase;

/**
 * Tahap 6 butir 10: dua permintaan berurutan tidak boleh menduplikasi nomor
 * peserta, melampaui kuota, atau membuat pengumuman ganda.
 */
class KonkurenTest extends PanitiaTestCase
{
    public function test_nomor_peserta_berurutan_tidak_duplikat(): void
    {
        $this->terkirim();
        $kedua = $this->pendaftaranSekolahB();
        $kedua->forceFill([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'nomor_peserta' => null,
        ])->save();

        $layanan = $this->app->make(NomorPesertaService::class);

        $satu = $layanan->buat($this->pendaftaran->refresh());

        // Pemanggil nyata (PendaftaranService) langsung menyimpan nomor yang
        // didapat. Simpan di sini juga supaya permintaan berikutnya melihatnya.
        $this->pendaftaran->forceFill(['nomor_peserta' => $satu])->save();

        $dua = $layanan->buat($kedua->refresh());

        $this->assertNotSame($satu, $dua);
        $this->assertMatchesRegularExpression('/^SD-2027-\d{4}$/', $satu);
        $this->assertMatchesRegularExpression('/^SD-2027-\d{4}$/', $dua);
        $this->assertSame(
            ((int) substr($satu, -4)) + 1,
            (int) substr($dua, -4),
            'Nomor peserta kedua harus tepat satu lebih besar dari yang pertama.'
        );
    }

    public function test_kuota_tidak_terlampaui_dua_permintaan_berurutan(): void
    {
        $this->gelombang->forceFill(['kuota' => 1])->save();

        $pertama = $this->aturStatus($this->terkirim(), StatusHasil::Diverifikasi);
        $kedua = $this->tambahPendaftarDiverifikasi();

        $layanan = $this->app->make(StatusHasilService::class);
        $layanan->ubah($this->panitia, $pertama, StatusHasil::Diterima);

        $this->expectException(AturanBisnisException::class);
        $this->expectExceptionMessage('Kuota penuh');

        $layanan->ubah($this->panitia, $kedua, StatusHasil::Diterima);
    }

    public function test_pengumuman_ganda_ditolak(): void
    {
        $gelombang = Gelombang::query()->findOrFail($this->gelombang->id);
        $layanan = $this->app->make(PengumumanService::class);

        $layanan->umumkan($this->admin, $gelombang);

        $this->expectException(AturanBisnisException::class);
        $this->expectExceptionMessage('sudah diumumkan');

        $layanan->umumkan($this->admin, $gelombang->refresh());
    }

    private function tambahPendaftarDiverifikasi(): Pendaftaran
    {
        $lain = $this->pendaftaranSekolahB();
        $lain->forceFill([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'status_hasil' => StatusHasil::Diverifikasi,
            'nomor_peserta' => 'SD-2027-0002',
        ])->save();

        return $lain->refresh();
    }
}
