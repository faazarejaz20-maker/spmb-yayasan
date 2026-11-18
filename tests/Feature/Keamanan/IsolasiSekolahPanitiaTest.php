<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Models\User;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 2: seluruh objek milik sekolah lain menghasilkan 404, termasuk saat
 * mengubah, memutuskan, menyetujui, dan mengunduh.
 */
class IsolasiSekolahPanitiaTest extends PanitiaTestCase
{
    public function test_panitia_sekolah_a_tidak_bisa_membaca_atau_mengubah_objek_sekolah_b(): void
    {
        $waliB = User::factory()->walisantri()->create();
        $lain = $this->pendaftaranSekolahB($waliB, StatusHasil::Diverifikasi->value);
        $berkas = $this->lampirkanBerkas($lain);
        $bayar = $this->pembayaran($lain, StatusPembayaran::Menunggu);

        $this->actingAs($this->panitia);

        $this->get(route('panitia.pendaftar.detail', $lain->uuid))->assertNotFound();

        $this->put(route('panitia.pendaftar.data', $lain->uuid), [])->assertNotFound();
        $this->put(route('panitia.pendaftar.catatan', $lain->uuid), ['catatan_panitia' => 'x'])->assertNotFound();

        $this->post(route('panitia.pendaftar.berkas.periksa', [
            'pendaftaran' => $lain->uuid,
            'berkas' => $berkas->uuid,
        ]), ['status' => 'sesuai'])->assertNotFound();

        $this->post(route('panitia.pendaftar.keputusan', $lain->uuid), [
            'status' => StatusHasil::Diterima->value,
        ])->assertNotFound();

        $this->post(route('panitia.pembayaran.setujui', $bayar->uuid))->assertNotFound();
        $this->post(route('panitia.pembayaran.tolak', $bayar->uuid), ['catatan' => 'salah'])->assertNotFound();

        $this->get(route('panitia.berkas.tampil', $berkas->uuid))->assertNotFound();
        $this->get(route('panitia.pembayaran.tampil', $bayar->uuid))->assertNotFound();

        $this->put(route('panitia.pendaftar.daftar-ulang', $lain->uuid), [
            'hadir' => 1,
        ])->assertNotFound();

        $this->post(route('panitia.pendaftar.mengundurkan-diri', $lain->uuid), [])->assertNotFound();
        $this->post(route('panitia.pendaftar.atur-ulang-password', $lain->uuid))->assertNotFound();
        $this->post(route('panitia.pendaftar.pindah-anak', $lain->uuid), ['no_hp' => $this->wali->no_hp])->assertNotFound();
        $this->post(route('panitia.pendaftar.pembayaran-tunai', $lain->uuid), [
            'nominal' => 10000,
            'dibayar_pada' => now()->toDateString(),
        ])->assertNotFound();
    }

    public function test_admin_sekolah_a_juga_tidak_bisa_menyentuh_sekolah_b(): void
    {
        $lain = $this->pendaftaranSekolahB(null, StatusHasil::Diverifikasi->value);

        $this->actingAs($this->admin)
            ->get(route('panitia.pendaftar.detail', $lain->uuid))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->post(route('panitia.pendaftar.keputusan', $lain->uuid), ['status' => 'Diterima'])
            ->assertNotFound();
    }

    public function test_panitia_sekolah_b_tetap_bisa_membuka_objeknya_sendiri(): void
    {
        $lain = $this->pendaftaranSekolahB();

        $this->actingAs($this->panitiaB)
            ->get(route('panitia.pendaftar.detail', $lain->uuid))
            ->assertOk();
    }
}
