<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;

/**
 * Butir 9: persetujuan dan penolakan bukti transfer, pembayaran tunai, dan
 * antrean per sekolah.
 */
class PanitiaPembayaranTest extends PanitiaTestCase
{
    public function test_setujui_bukti_transfer(): void
    {
        $pendaftaran = $this->terkirim();
        $bayar = $this->pembayaran($pendaftaran);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.setujui', $bayar->uuid), ['dari' => 'antrean'])
            ->assertRedirect(route('panitia.pembayaran.index'))
            ->assertSessionHasNoErrors();

        $segar = $bayar->refresh();
        $this->assertSame(StatusPembayaran::Disetujui, $segar->status);
        $this->assertSame($this->panitia->id, (int) $segar->diproses_oleh);
        $this->assertNull($segar->catatan_penolakan);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'setujui_pembayaran',
            'objek' => 'Pembayaran',
            'objek_id' => $bayar->id,
            'user_id' => $this->panitia->id,
            'sekolah_id' => $this->sekolah->id,
        ]);
    }

    public function test_tolak_wajib_alasan(): void
    {
        $pendaftaran = $this->terkirim();
        $bayar = $this->pembayaran($pendaftaran);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.tolak', $bayar->uuid), [])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(StatusPembayaran::Menunggu, $bayar->refresh()->status);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.tolak', $bayar->uuid), ['catatan' => 'Nominal tidak sesuai'])
            ->assertSessionHasNoErrors();

        $segar = $bayar->refresh();
        $this->assertSame(StatusPembayaran::Ditolak, $segar->status);
        $this->assertSame('Nominal tidak sesuai', $segar->catatan_penolakan);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'tolak_pembayaran',
            'objek' => 'Pembayaran',
            'objek_id' => $bayar->id,
        ]);
    }

    public function test_pembayaran_tunai_langsung_disetujui(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pembayaran-tunai', $pendaftaran->uuid), [
                'nominal' => 250000,
                'dibayar_pada' => now()->subDay()->toDateString(),
                'catatan' => 'Diterima di kantor',
            ])
            ->assertRedirect(route('panitia.pendaftar.detail', [
                'pendaftaran' => $pendaftaran->uuid,
                'tab' => 'pembayaran',
            ]))
            ->assertSessionHasNoErrors();

        $bayar = Pembayaran::query()->where('pendaftaran_id', $pendaftaran->id)->firstOrFail();

        $this->assertSame(MetodePembayaran::Tunai, $bayar->metode);
        $this->assertSame(StatusPembayaran::Disetujui, $bayar->status);
        $this->assertSame($this->panitia->id, (int) $bayar->diproses_oleh);
        $this->assertNull($bayar->bukti_path);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'catat_pembayaran_tunai',
            'objek' => 'Pembayaran',
            'objek_id' => $bayar->id,
        ]);
    }

    public function test_pembayaran_tunai_menolak_nominal_nol_dan_tanggal_masa_depan(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pembayaran-tunai', $pendaftaran->uuid), [
                'nominal' => 0,
                'dibayar_pada' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('nominal');

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pembayaran-tunai', $pendaftaran->uuid), [
                'nominal' => 10000,
                'dibayar_pada' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('dibayar_pada');

        $this->assertSame(0, Pembayaran::query()->count());
    }

    public function test_panitia_tidak_bisa_memproses_ulang_dan_admin_wajib_catatan(): void
    {
        $pendaftaran = $this->terkirim();
        $bayar = $this->pembayaran($pendaftaran);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.setujui', $bayar->uuid))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.tolak', $bayar->uuid), ['catatan' => 'Ubah keputusan'])
            ->assertSessionHasErrors('pembayaran');

        $this->assertSame(StatusPembayaran::Disetujui, $bayar->refresh()->status);

        $this->actingAs($this->admin)
            ->post(route('panitia.pembayaran.setujui', $bayar->uuid))
            ->assertSessionHasErrors('pembayaran');

        $this->actingAs($this->admin)
            ->post(route('panitia.pembayaran.setujui', $bayar->uuid), ['catatan' => 'Koreksi admin'])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusPembayaran::Disetujui, $bayar->refresh()->status);
    }

    public function test_antrean_hanya_sekolah_sendiri(): void
    {
        $sendiri = $this->terkirim();
        $pembayaranSendiri = $this->pembayaran($sendiri);
        $anakSendiri = $sendiri->anak->nama_lengkap;

        $lain = $this->pendaftaranSekolahB();
        $pembayaranLain = $this->pembayaran($lain, StatusPembayaran::Menunggu);
        $anakLain = $lain->anak->nama_lengkap;

        $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.index'))
            ->assertOk()
            ->assertSee($anakSendiri)
            ->assertDontSee($anakLain);

        $this->actingAs($this->panitiaB)
            ->get(route('panitia.pembayaran.index'))
            ->assertOk()
            ->assertSee($anakLain)
            ->assertDontSee($anakSendiri);

        // Bukti milik sekolah lain tidak bisa dibuka, walau uuid valid.
        $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.tampil', $pembayaranLain->uuid))
            ->assertNotFound();
    }

    public function test_antrean_hanya_memuat_transfer_menunggu_dari_pendaftaran_terkirim(): void
    {
        $terkirim = $this->terkirim();
        $this->pembayaran($terkirim, StatusPembayaran::Disetujui);
        $this->pembayaran($terkirim, StatusPembayaran::Menunggu, 100000, MetodePembayaran::Tunai);

        $waliDraf = \App\Models\User::factory()->walisantri()->create();
        $draf = \App\Models\Pendaftaran::factory()->create([
            'anak_id' => \App\Models\Anak::factory()->create([
                'user_id' => $waliDraf->id,
                'nama_lengkap' => 'Anak Draf Tersembunyi',
            ])->id,
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'dibuat_oleh' => $waliDraf->id,
        ]);
        $this->pembayaran($draf, StatusPembayaran::Menunggu);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.index'))
            ->assertOk()
            ->assertDontSee($terkirim->anak->nama_lengkap)
            ->assertDontSee('Anak Draf Tersembunyi');
    }

    public function test_pembayaran_terkirim_untuk_sekolah_lain_tidak_bisa_diproses(): void
    {
        $lain = $this->pendaftaranSekolahB();
        $bayar = $this->pembayaran($lain);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.setujui', $bayar->uuid))
            ->assertNotFound();

        $this->assertSame(StatusPembayaran::Menunggu, $bayar->refresh()->status);
    }
}
