<?php

namespace Tests\Feature\Keamanan;

use App\Enums\HubunganOrangTua;
use App\Models\OrangTua;
use App\Models\Pengumuman;
use App\Enums\StatusHasil;
use Tests\Feature\WalisantriTestCase;

/**
 * Butir 15: teks dari pengguna selalu di-escape di semua halaman walisantri.
 */
class XssTest extends WalisantriTestCase
{
    private const JAHAT = '<script>alert("xss")</script>';

    private function isiDataJahat(): void
    {
        $this->anak->forceFill([
            'nama_lengkap' => self::JAHAT,
            'nama_panggilan' => '<img src=x onerror=alert(1)>',
        ])->save();

        OrangTua::create([
            'user_id' => $this->wali->id,
            'hubungan' => HubunganOrangTua::Ayah,
            'nama' => '<b>Bapak</b>',
            'no_hp' => '628111111111',
            'status_hidup' => 'Masih hidup',
        ]);
    }

    public function test_nama_anak_di_escape_di_beranda_dan_detail(): void
    {
        $this->isiDataJahat();

        $this->actingAs($this->wali);

        foreach ([
            '/akun',
            '/akun/pendaftaran/'.$this->pendaftaran->uuid,
            '/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil',
        ] as $jalur) {
            $respons = $this->get($jalur);

            $respons->assertOk();
            $respons->assertDontSee(self::JAHAT, false);
            $respons->assertDontSee('<img src=x onerror=alert(1)>', false);
            $respons->assertSee(e(self::JAHAT), false);
        }
    }

    public function test_ringkasan_dan_biaya_di_escape(): void
    {
        $this->isiDataJahat();

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/langkah/8')
            ->assertOk()
            ->assertDontSee(self::JAHAT, false)
            ->assertDontSee('<b>Bapak</b>', false)
            ->assertSee(e(self::JAHAT), false);
    }

    public function test_tautan_grup_wa_jahat_tidak_dirender(): void
    {
        $this->pendaftaran->forceFill([
            'status_hasil' => StatusHasil::Diterima,
            'dikirim_pada' => now()->subDay(),
        ])->save();

        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
        ]);

        $this->gelombang->forceFill([
            'tautan_grup_wa' => 'javascript:alert(1)',
        ])->save();

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid.'/hasil')
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_catatan_berkas_perbaikan_di_escape(): void
    {
        $this->pendaftaran->forceFill([
            'status_hasil' => StatusHasil::PerluPerbaikan,
            'dikirim_pada' => now()->subDay(),
        ])->save();

        $jenis = $this->buatJenisBerkas(true);

        \App\Models\Berkas::create([
            'pendaftaran_id' => $this->pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => 'berkas/x/y.pdf',
            'status' => \App\Enums\StatusBerkas::PerluPerbaikan,
            'catatan' => self::JAHAT,
        ]);

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$this->pendaftaran->uuid)
            ->assertOk()
            ->assertDontSee(self::JAHAT, false)
            ->assertSee(e(self::JAHAT), false);
    }
}
