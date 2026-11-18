<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Models\DaftarUlang;
use App\Models\Pembayaran;
use App\Models\Pengumuman;
use App\Services\StatusTampilService;
use Tests\Feature\WalisantriTestCase;

/**
 * Butir 11 dan 12: hasil seleksi tidak bocor sebelum pengumuman dan data
 * internal panitia tidak pernah tampil ke walisantri.
 */
class KebocoranHasilTest extends WalisantriTestCase
{
    private function kirimkan(StatusHasil $status): void
    {
        $this->pendaftaran->forceFill([
            'status_hasil' => $status,
            'dikirim_pada' => now()->subDay(),
            'nomor_peserta' => 'SD-2027-0001',
            'diputuskan_oleh' => $this->wali->id,
            'diputuskan_pada' => now(),
            'catatan_panitia' => 'Catatan rahasia panitia.',
        ])->save();

        $this->actingAs($this->wali);
    }

    private function umumkan(): void
    {
        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
            'teks_pesan' => 'Hasil sudah diumumkan.',
        ]);
    }

    public function test_diterima_tidak_bocor_sebelum_pengumuman(): void
    {
        $this->kirimkan(StatusHasil::Diterima);

        foreach ([
            '/akun/pendaftaran/'.$this->pendaftaran->uuid,
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil',
            '/akun',
        ] as $jalur) {
            $respons = $this->get($jalur);

            $respons->assertOk();
            $respons->assertDontSee('Diterima', false);
            $respons->assertDontSee('Lakukan daftar ulang', false);
            $respons->assertSee('menunggu pengumuman');
        }
    }

    public function test_ditolak_dan_cadangan_tidak_bocor_sebelum_pengumuman(): void
    {
        $this->kirimkan(StatusHasil::Ditolak);

        $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertDontSee('Belum diterima', false)
            ->assertDontSee('Ditolak', false);

        $this->pendaftaran->forceFill(['status_hasil' => StatusHasil::Cadangan])->save();

        $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertDontSee('cadangan', false)
            ->assertDontSee('Cadangan', false);
    }

    public function test_status_tampil_mengembalikan_hasil_null_sebelum_pengumuman(): void
    {
        $this->kirimkan(StatusHasil::Diterima);

        $status = app(StatusTampilService::class)->untuk($this->pendaftaran->fresh());

        $this->assertNull($status->hasil);
        $this->assertFalse($status->sudahDiumumkan);
        $this->assertFalse($status->tampilkanGrupWa);
        $this->assertSame('Terverifikasi, menunggu pengumuman', $status->label);
    }

    public function test_hasil_tampil_setelah_pengumuman(): void
    {
        $this->kirimkan(StatusHasil::Diterima);
        $this->umumkan();

        $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertSee('Diterima')
            ->assertSee('Lakukan daftar ulang');
    }

    public function test_data_view_status_tidak_membawa_objek_pendaftaran_mentah(): void
    {
        $this->kirimkan(StatusHasil::Diterima);

        $respons = $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil');

        $this->assertNull($respons->viewData('status')->hasil);
        $this->assertArrayNotHasKey('pendaftaran', $respons->viewData());
    }

    public function test_catatan_panitia_tidak_pernah_tampil(): void
    {
        $this->kirimkan(StatusHasil::Diterima);
        $this->umumkan();

        foreach ([
            '/akun/pendaftaran/'.$this->pendaftaran->uuid,
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil',
        ] as $jalur) {
            $this->get($jalur)
                ->assertOk()
                ->assertDontSee('Catatan rahasia panitia.', false)
                ->assertDontSee('catatan_panitia', false);
        }
    }

    public function test_catatan_wawancara_dan_kekurangan_daftar_ulang_tidak_tampil(): void
    {
        $this->kirimkan(StatusHasil::Diterima);
        $this->umumkan();

        DaftarUlang::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'hadir' => true,
            'kekurangan' => 'Kekurangan berkas internal.',
            'catatan_wawancara' => 'Catatan wawancara internal.',
        ]);

        foreach ([
            '/akun/pendaftaran/'.$this->pendaftaran->uuid,
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil',
        ] as $jalur) {
            $this->get($jalur)
                ->assertOk()
                ->assertDontSee('Catatan wawancara internal.', false)
                ->assertDontSee('Kekurangan berkas internal.', false)
                ->assertDontSee('catatan_wawancara', false);
        }
    }

    public function test_identitas_internal_dan_kuota_tidak_tampil(): void
    {
        $this->kirimkan(StatusHasil::Diterima);
        $this->umumkan();

        $respons = $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil');

        $respons->assertOk()
            ->assertDontSee('diputuskan_oleh', false)
            ->assertDontSee('status_murid', false)
            ->assertDontSee('Kuota', false)
            ->assertDontSee('kuota', false);
    }

    public function test_status_murid_resmi_tampil_setelah_pengumuman(): void
    {
        $this->kirimkan(StatusHasil::Diterima);
        $this->umumkan();

        $this->pendaftaran->forceFill([
            'status_murid' => StatusMurid::Resmi,
        ])->save();

        $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid)
            ->assertOk()
            ->assertSee('Murid resmi');
    }

    public function test_tautan_hasil_tidak_menampilkan_hasil_mentah_di_beranda(): void
    {
        $this->kirimkan(StatusHasil::Ditolak);

        $this->get('/akun')
            ->assertOk()
            ->assertDontSee('Belum diterima', false)
            ->assertSee('Terverifikasi, menunggu pengumuman');
    }

    public function test_pembayaran_tidak_bisa_diunggah_sebelum_pengumuman(): void
    {
        $this->kirimkan(StatusHasil::Diterima);

        $this->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/pembayaran')->assertNotFound();

        $this->assertSame(0, Pembayaran::query()->count());
    }
}
