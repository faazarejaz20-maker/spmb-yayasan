<?php

namespace Tests\Feature;

use App\Enums\StatusHasil;
use App\Models\Pengumuman;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 7: Umumkan Hasil. Satu baris pengumuman per sekolah+gelombang,
 * percobaan kedua ditolak, tanpa centang ditolak, peringatan saat ada yang
 * belum final, gelombang sekolah lain 404, teks pesan bersih, hasil bocor
 * hanya sesudah pengumuman, dan koreksi setelahnya hanya admin dengan catatan.
 */
class UmumkanHasilTest extends PanitiaTestCase
{
    public function test_membuat_tepat_satu_baris_pengumuman(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertRedirect(route('admin.pengumuman.index'))
            ->assertSessionHas('sukses');

        $this->assertSame(1, Pengumuman::query()->count());

        $pengumuman = Pengumuman::query()->firstOrFail();

        $this->assertSame($this->sekolah->id, (int) $pengumuman->sekolah_id);
        $this->assertSame($this->gelombang->id, (int) $pengumuman->gelombang_id);
        $this->assertSame($this->admin->id, (int) $pengumuman->diumumkan_oleh);
        $this->assertNotNull($pengumuman->diumumkan_pada);
    }

    public function test_percobaan_kedua_ditolak_tanpa_baris_kedua(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertRedirect(route('admin.pengumuman.index'))
            ->assertSessionHas('pesan');

        $this->assertSame(1, Pengumuman::query()->count());
    }

    public function test_tanpa_centang_konfirmasi_ditolak(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, [])
            ->assertSessionHasErrors(['konfirmasi']);

        $this->assertSame(0, Pengumuman::query()->count());
    }

    public function test_layar_konfirmasi_memperingatkan_bila_ada_yang_belum_final(): void
    {
        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Baru);

        $this->actingAs($this->admin)
            ->get('/admin/pengumuman/'.$this->gelombang->id)
            ->assertOk()
            ->assertSee('belum final', false);
    }

    public function test_gelombang_sekolah_lain_ditolak_404(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/pengumuman/'.$this->gelombangB->id)->assertNotFound();
        $this->post('/admin/pengumuman/'.$this->gelombangB->id, ['konfirmasi' => 1])->assertNotFound();

        $this->assertSame(0, Pengumuman::query()->count());
    }

    public function test_teks_pesan_tidak_memuat_data_per_orang(): void
    {
        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        $teks = Pengumuman::query()->firstOrFail()->teks_pesan;

        $this->assertStringContainsString($this->sekolah->nama, $teks);
        $this->assertStringContainsString($this->gelombang->nama, $teks);

        foreach ([
            'Aisyah Nur Fadhilah',
            (string) $p->nomor_peserta,
            'Diterima',
            'Ditolak',
            'Cadangan',
            '3307015204190001',
        ] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $teks);
        }
    }

    public function test_hasil_tidak_terlihat_sebelum_dan_terlihat_sesudah_pengumuman(): void
    {
        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);
        $jalurHasil = '/akun/pendaftaran/'.$p->uuid.'/hasil';

        $this->actingAs($this->wali)
            ->get($jalurHasil)
            ->assertOk()
            ->assertDontSee('Diterima', false)
            ->assertSee('menunggu pengumuman', false);

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        $this->actingAs($this->wali)
            ->get($jalurHasil)
            ->assertOk()
            ->assertSee('Diterima');
    }

    public function test_hasil_yang_diputuskan_setelah_pengumuman_langsung_terlihat(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        $p2 = $this->buatPendaftaranTerkirim($this->wali, status: 'Baru');
        $this->aturStatus($p2, StatusHasil::Diverifikasi);

        $this->actingAs($this->admin)
            ->post('/panitia/pendaftar/'.$p2->uuid.'/keputusan', [
                'status' => StatusHasil::Diterima->value,
                'konfirmasi_berkas' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$p2->uuid.'/hasil')
            ->assertOk()
            ->assertSee('Diterima');
    }

    public function test_panitia_tidak_bisa_mengubah_hasil_setelah_pengumuman(): void
    {
        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        $this->actingAs($this->panitia)
            ->post('/panitia/pendaftar/'.$p->uuid.'/keputusan', [
                'status' => StatusHasil::Ditolak->value,
                'catatan' => 'Coba mengubah',
            ])
            ->assertSessionHasErrors(['keputusan']);

        $this->assertSame(StatusHasil::Diterima, $p->fresh()->status_hasil);
    }

    public function test_admin_bisa_mengubah_hasil_setelah_pengumuman_dengan_catatan(): void
    {
        $p = $this->terkirim();
        $this->aturStatus($p, StatusHasil::Diterima);

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        // Tanpa catatan ditolak.
        $this->actingAs($this->admin)
            ->post('/panitia/pendaftar/'.$p->uuid.'/keputusan', [
                'status' => StatusHasil::Ditolak->value,
            ])
            ->assertSessionHasErrors(['keputusan']);

        $this->assertSame(StatusHasil::Diterima, $p->fresh()->status_hasil);

        // Dengan catatan diterima.
        $this->actingAs($this->admin)
            ->post('/panitia/pendaftar/'.$p->uuid.'/keputusan', [
                'status' => StatusHasil::Ditolak->value,
                'catatan' => 'Koreksi hasil setelah verifikasi ulang.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusHasil::Ditolak, $p->fresh()->status_hasil);

        $this->actingAs($this->wali)
            ->get('/akun/pendaftaran/'.$p->uuid.'/hasil')
            ->assertOk()
            ->assertSee('Belum diterima');
    }

    public function test_panitia_tidak_bisa_mengakses_layar_konfirmasi(): void
    {
        $this->actingAs($this->panitia)
            ->get('/admin/pengumuman/'.$this->gelombang->id)
            ->assertForbidden();
    }

    public function test_log_umumkan_hasil_ditulis(): void
    {
        $this->terkirim();

        $this->actingAs($this->admin)
            ->post('/admin/pengumuman/'.$this->gelombang->id, ['konfirmasi' => 1])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'umumkan_hasil',
            'sekolah_id' => $this->sekolah->id,
            'objek_id' => Pengumuman::query()->firstOrFail()->id,
        ]);
    }
}
