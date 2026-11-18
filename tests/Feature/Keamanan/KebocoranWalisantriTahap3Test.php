<?php

namespace Tests\Feature\Keamanan;

use App\Enums\HubunganOrangTua;
use App\Enums\StatusBerkas;
use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Enums\StatusPembayaran;
use App\Models\Berkas;
use App\Models\DaftarUlang;
use App\Models\JenisBerkas;
use App\Models\OrangTua;
use App\Models\Pengumuman;
use App\Models\RiwayatStatus;
use App\Services\StatusTampilService;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 18: tindakan panitia tidak boleh membocorkan data internal ke halaman
 * walisantri. Hasil seleksi tetap tersembunyi sampai pengumuman dibuat.
 */
class KebocoranWalisantriTahap3Test extends PanitiaTestCase
{
    private function penandaInternal(): array
    {
        $pendaftaran = $this->terkirim();
        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'wajib' => true,
        ]);

        $pendaftaran->forceFill([
            'status_hasil' => StatusHasil::Diterima,
            'catatan_panitia' => 'CATATAN-PANITIA-RAHASIA',
            'diputuskan_oleh' => $this->panitia->id,
            'diputuskan_pada' => now(),
        ])->save();

        RiwayatStatus::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis' => 'hasil',
            'dari' => StatusHasil::Diverifikasi->value,
            'ke' => StatusHasil::Diterima->value,
            'oleh' => $this->panitia->id,
            'catatan' => 'CATATAN-RIWAYAT-RAHASIA',
        ]);

        Berkas::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis_berkas_id' => $jenis->id,
            'path' => 'berkas/contoh/berkas.pdf',
            'status' => StatusBerkas::Sesuai,
            'catatan' => 'CATATAN-BERKAS-RAHASIA',
            'diperiksa_oleh' => $this->panitia->id,
            'diperiksa_pada' => now(),
        ]);

        DaftarUlang::create([
            'pendaftaran_id' => $pendaftaran->id,
            'hadir' => true,
            'kekurangan' => 'KEKURANGAN-RAHASIA',
            'catatan_wawancara' => 'CATATAN-WAWANCARA-RAHASIA',
            'selesai' => true,
            'dicatat_oleh' => $this->panitia->id,
        ]);

        $pendaftaran->forceFill(['status_murid' => StatusMurid::Resmi])->save();

        $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
        ]);

        return [$pendaftaran, $jenis];
    }

    public function test_halaman_walisantri_tidak_memuat_data_internal_panitia(): void
    {
        [$pendaftaran] = $this->penandaInternal();

        $this->actingAs($this->wali);

        $halaman = [
            '/akun',
            route('walisantri.pendaftaran.detail', $pendaftaran->uuid),
            route('walisantri.pendaftaran.hasil', $pendaftaran->uuid),
            route('walisantri.pendaftaran.pembayaran.index', $pendaftaran->uuid),
        ];

        foreach ($halaman as $jalur) {
            $respons = $this->get($jalur);
            $respons->assertOk();

            foreach ([
                'CATATAN-PANITIA-RAHASIA',
                'CATATAN-RIWAYAT-RAHASIA',
                'CATATAN-BERKAS-RAHASIA',
                'KEKURANGAN-RAHASIA',
                'CATATAN-WAWANCARA-RAHASIA',
            ] as $penanda) {
                $respons->assertDontSee($penanda, false);
            }
        }

        // Setelah pengumuman, status yang memang untuk walisantri tetap tampil.
        $this->get(route('walisantri.pendaftaran.hasil', $pendaftaran->uuid))
            ->assertSee('Murid resmi');
    }

    public function test_hasil_keputusan_panitia_tidak_bocor_sebelum_pengumuman(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.keputusan', $pendaftaran->uuid), [
                'status' => StatusHasil::Diverifikasi->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.keputusan', $pendaftaran->uuid), [
                'status' => StatusHasil::Diterima->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(app(StatusTampilService::class)->sudahDiumumkan($pendaftaran->refresh()));

        $this->actingAs($this->wali);

        foreach ([
            '/akun',
            route('walisantri.pendaftaran.detail', $pendaftaran->uuid),
            route('walisantri.pendaftaran.hasil', $pendaftaran->uuid),
        ] as $jalur) {
            $this->get($jalur)
                ->assertOk()
                ->assertDontSee('Diterima', false);
        }
    }

    public function test_catatan_perlu_perbaikan_tetap_tampil_tanpa_data_internal_lain(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.keputusan', $pendaftaran->uuid), [
                'status' => StatusHasil::PerluPerbaikan->value,
                'catatan' => 'Perbaiki alamat rumah.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->put(route('panitia.pendaftar.catatan', $pendaftaran->uuid), [
                'catatan_panitia' => 'CATATAN-PANITIA-RAHASIA',
            ]);

        $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.detail', $pendaftaran->uuid))
            ->assertOk()
            ->assertSee('Perbaiki alamat rumah.')
            ->assertDontSee('CATATAN-PANITIA-RAHASIA', false);
    }

    public function test_orang_tua_dari_akun_lain_tidak_terbaca(): void
    {
        $lain = \App\Models\User::factory()->walisantri()->create();

        OrangTua::create([
            'user_id' => $lain->id,
            'hubungan' => HubunganOrangTua::Ayah,
            'nama' => 'AYAH-ORANG-LAIN',
            'no_hp' => '628111111999',
            'status_hidup' => 'Masih hidup',
        ]);

        $this->actingAs($this->wali)
            ->get(route('walisantri.orang-tua'))
            ->assertOk()
            ->assertDontSee('AYAH-ORANG-LAIN');
    }
}
