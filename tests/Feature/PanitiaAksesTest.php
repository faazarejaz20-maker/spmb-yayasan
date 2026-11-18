<?php

namespace Tests\Feature;

use App\Enums\StatusPembayaran;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Butir 1 dan 3: matriks akses /panitia dan draf walisantri yang tidak boleh
 * terlihat panitia.
 */
class PanitiaAksesTest extends PanitiaTestCase
{
    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get(route('panitia.beranda'))->assertRedirect(route('masuk'));
        $this->get(route('panitia.pendaftar.index'))->assertRedirect(route('masuk'));
    }

    public function test_peran_selain_panitia_dan_admin_mendapat_403(): void
    {
        $lain = [
            User::factory()->walisantri()->create(),
            User::factory()->superAdmin()->create(),
            User::factory()->yayasan()->create(),
        ];

        foreach ($lain as $user) {
            $this->actingAs($user)->get(route('panitia.beranda'))->assertStatus(403);
            $this->actingAs($user)->get(route('panitia.pendaftar.index'))->assertStatus(403);
        }
    }

    public function test_panitia_dan_admin_sekolah_sendiri_membuka_halaman_utama(): void
    {
        Storage::fake('local');

        $pendaftaran = $this->terkirim();
        $berkas = $this->lampirkanBerkas($pendaftaran);
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Menunggu);

        Storage::disk('local')->put($berkas->path, '%PDF-1.4');
        Storage::disk('local')->put((string) $pembayaran->bukti_path, '%PDF-1.4');

        $urls = [
            route('panitia.beranda'),
            route('panitia.pendaftar.index'),
            route('panitia.pendaftar.detail', $pendaftaran->uuid),
            route('panitia.pembayaran.index'),
            route('panitia.pembayaran.tampil', $pembayaran->uuid),
            route('panitia.berkas.tampil', $berkas->uuid),
            route('panitia.offline.index'),
        ];

        foreach ([$this->panitia, $this->admin] as $staf) {
            foreach ($urls as $url) {
                $this->actingAs($staf)->get($url)->assertStatus(200);
            }
        }
    }

    public function test_draf_walisantri_tidak_tampil_di_daftar(): void
    {
        $this->pendaftaran->forceFill([
            'nomor_peserta' => null,
            'dikirim_pada' => null,
        ])->save();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index'))
            ->assertOk()
            ->assertDontSee($this->anak->nama_lengkap);
    }

    public function test_draf_walisantri_tidak_bisa_dibuka_lewat_rute_panitia(): void
    {
        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.detail', $this->pendaftaran->uuid))
            ->assertNotFound();
    }

    public function test_pendaftar_sekolah_lain_tidak_terlihat(): void
    {
        $lain = $this->pendaftaranSekolahB();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.detail', $lain->uuid))
            ->assertNotFound();

        $this->actingAs($this->panitia)
            ->get(route('panitia.pendaftar.index'))
            ->assertOk()
            ->assertDontSee($lain->anak->nama_lengkap);
    }
}
