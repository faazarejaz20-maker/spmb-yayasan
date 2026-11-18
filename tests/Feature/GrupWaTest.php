<?php

namespace Tests\Feature;

use App\Enums\StatusHasil;
use App\Models\Pengumuman;

/**
 * Butir 18: tautan grup WhatsApp hanya tampil untuk pendaftar Diterima yang
 * sudah diumumkan, dan hanya tautan resmi chat.whatsapp.com.
 */
class GrupWaTest extends WalisantriTestCase
{
    private const TAUTAN = 'https://chat.whatsapp.com/UndanganGrupContoh';

    protected function setUp(): void
    {
        parent::setUp();

        $this->gelombang->forceFill(['tautan_grup_wa' => self::TAUTAN])->save();

        $this->pendaftaran->forceFill([
            'status_hasil' => StatusHasil::Diterima,
            'dikirim_pada' => now()->subDay(),
            'nomor_peserta' => 'SD-2027-0001',
        ])->save();
    }

    private function umumkan(): void
    {
        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
        ]);
    }

    public function test_tautan_tampil_setelah_diterima_dan_diumumkan(): void
    {
        $this->umumkan();

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertSee(self::TAUTAN, false);
    }

    public function test_tautan_tidak_tampil_sebelum_pengumuman(): void
    {
        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertDontSee(self::TAUTAN, false)
            ->assertDontSee('Buka tautan grup', false);
    }

    public function test_tautan_tidak_tampil_untuk_ditolak(): void
    {
        $this->umumkan();

        $this->pendaftaran->forceFill(['status_hasil' => StatusHasil::Ditolak])->save();

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertDontSee(self::TAUTAN, false);
    }

    public function test_tautan_tidak_tampil_saat_draf(): void
    {
        $this->pendaftaran->forceFill([
            'status_hasil' => StatusHasil::Baru,
            'dikirim_pada' => null,
            'nomor_peserta' => null,
        ])->save();

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid)
            ->assertOk()
            ->assertDontSee(self::TAUTAN, false);
    }

    public function test_tautan_selain_whatsapp_diabaikan(): void
    {
        $this->umumkan();

        foreach (['javascript:alert(1)', 'https://contoh.test/grup', 'http://chat.whatsapp.com/X'] as $tautan) {
            $this->gelombang->forceFill(['tautan_grup_wa' => $tautan])->save();

            $this->actingAs($this->wali)
                ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
                ->assertOk()
                ->assertDontSee($tautan, false)
                ->assertDontSee('Buka tautan grup', false);
        }
    }

    public function test_tautan_kosong_tidak_menampilkan_bagian(): void
    {
        $this->umumkan();

        $this->gelombang->forceFill(['tautan_grup_wa' => null])->save();

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertDontSee('Grup WhatsApp');
    }
}
