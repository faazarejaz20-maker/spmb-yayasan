<?php

namespace Tests\Feature;

use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Enums\StatusPembayaran;
use App\Models\DaftarUlang;
use App\Models\Pengumuman;
use App\Models\Pendaftaran;
use App\Models\RiwayatStatus;

/**
 * Butir 10: daftar ulang hanya untuk Diterima yang sudah punya pembayaran
 * disetujui; selesai menjadikan murid resmi; pembatalan hanya oleh admin.
 */
class PanitiaDaftarUlangTest extends PanitiaTestCase
{
    private function diterimaDenganPembayaran(): Pendaftaran
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->forceFill(['status_hasil' => StatusHasil::Diterima])->save();

        $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        return $pendaftaran->refresh();
    }

    private function catat(Pendaftaran $pendaftaran, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->panitia)->put(
            route('panitia.pendaftar.daftar-ulang', $pendaftaran->uuid),
            $data,
        );
    }

    public function test_ditolak_bila_belum_diterima(): void
    {
        $pendaftaran = $this->terkirim();
        $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $this->catat($pendaftaran, ['hadir' => 1, 'selesai' => 1])
            ->assertSessionHasErrors('daftar_ulang');

        $this->assertSame(0, DaftarUlang::query()->count());
        $this->assertNull($pendaftaran->refresh()->status_murid);
    }

    public function test_ditolak_bila_belum_ada_pembayaran_disetujui(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->forceFill(['status_hasil' => StatusHasil::Diterima])->save();

        $this->pembayaran($pendaftaran, StatusPembayaran::Menunggu);

        $this->catat($pendaftaran, ['hadir' => 1, 'selesai' => 1])
            ->assertSessionHasErrors('daftar_ulang');

        $this->assertSame(0, DaftarUlang::query()->count());
    }

    public function test_catat_daftar_ulang_dan_wawancara(): void
    {
        $pendaftaran = $this->diterimaDenganPembayaran();

        $this->catat($pendaftaran, [
            'hadir' => 1,
            'kekurangan' => 'Fotokopi KK kurang satu',
            'catatan_wawancara' => 'CATATAN-WAWANCARA-RAHASIA',
        ])
            ->assertRedirect(route('panitia.pendaftar.detail', [
                'pendaftaran' => $pendaftaran->uuid,
                'tab' => 'daftar-ulang',
            ]))
            ->assertSessionHasNoErrors();

        $baris = DaftarUlang::query()->where('pendaftaran_id', $pendaftaran->id)->firstOrFail();

        $this->assertTrue($baris->hadir);
        $this->assertFalse($baris->selesai);
        $this->assertSame('Fotokopi KK kurang satu', $baris->kekurangan);
        $this->assertSame('CATATAN-WAWANCARA-RAHASIA', $baris->catatan_wawancara);
        $this->assertSame($this->panitia->id, (int) $baris->dicatat_oleh);

        $this->assertNull($pendaftaran->refresh()->status_murid);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'catat_daftar_ulang',
            'objek' => 'DaftarUlang',
            'objek_id' => $baris->id,
        ]);
    }

    public function test_selesai_menjadikan_murid_resmi(): void
    {
        $pendaftaran = $this->diterimaDenganPembayaran();

        $this->catat($pendaftaran, ['hadir' => 1, 'selesai' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusMurid::Resmi, $pendaftaran->refresh()->status_murid);

        $this->assertDatabaseHas('riwayat_status', [
            'pendaftaran_id' => $pendaftaran->id,
            'jenis' => 'murid',
            'ke' => StatusMurid::Resmi->value,
            'oleh' => $this->panitia->id,
        ]);
    }

    public function test_hanya_admin_bisa_membatalkan_daftar_ulang_selesai(): void
    {
        $pendaftaran = $this->diterimaDenganPembayaran();

        $this->catat($pendaftaran, ['selesai' => 1])->assertSessionHasNoErrors();
        $this->assertSame(StatusMurid::Resmi, $pendaftaran->refresh()->status_murid);

        $this->catat($pendaftaran, ['selesai' => 0])
            ->assertSessionHasErrors('daftar_ulang');

        $this->assertSame(StatusMurid::Resmi, $pendaftaran->refresh()->status_murid);

        $this->actingAs($this->admin)
            ->put(route('panitia.pendaftar.daftar-ulang', $pendaftaran->uuid), [
                'selesai' => 0,
                'catatan' => 'Salah tandai selesai',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($pendaftaran->refresh()->status_murid);
        $this->assertFalse(DaftarUlang::query()->where('pendaftaran_id', $pendaftaran->id)->firstOrFail()->selesai);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'batal_daftar_ulang',
            'sekolah_id' => $this->sekolah->id,
        ]);
    }

    public function test_admin_wajib_catatan_saat_membatalkan(): void
    {
        $pendaftaran = $this->diterimaDenganPembayaran();
        $this->catat($pendaftaran, ['selesai' => 1])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put(route('panitia.pendaftar.daftar-ulang', $pendaftaran->uuid), ['selesai' => 0])
            ->assertSessionHasErrors('daftar_ulang');

        $this->assertSame(StatusMurid::Resmi, $pendaftaran->refresh()->status_murid);
    }

    public function test_mengundurkan_diri_dan_riwayat_murid(): void
    {
        $pendaftaran = $this->diterimaDenganPembayaran();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.mengundurkan-diri', $pendaftaran->uuid), [
                'catatan' => 'Pindah kota',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusMurid::MengundurkanDiri, $pendaftaran->refresh()->status_murid);

        $riwayat = RiwayatStatus::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->where('jenis', 'murid')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(StatusMurid::MengundurkanDiri->value, $riwayat->ke);
        $this->assertSame('Pindah kota', $riwayat->catatan);
    }

    public function test_mengundurkan_diri_ditolak_bila_belum_diterima(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.mengundurkan-diri', $pendaftaran->uuid))
            ->assertSessionHasErrors('mengundurkan_diri');

        $this->assertNull($pendaftaran->refresh()->status_murid);
    }

    public function test_catatan_wawancara_tidak_tampil_ke_walisantri(): void
    {
        $pendaftaran = $this->diterimaDenganPembayaran();

        $this->catat($pendaftaran, [
            'hadir' => 1,
            'kekurangan' => 'KEKURANGAN-RAHASIA',
            'catatan_wawancara' => 'CATATAN-WAWANCARA-RAHASIA',
        ])->assertSessionHasNoErrors();

        Pengumuman::create([
            'sekolah_id' => $this->sekolah->id,
            'gelombang_id' => $this->gelombang->id,
            'diumumkan_pada' => now(),
        ]);

        $this->actingAs($this->wali)
            ->get(route('walisantri.pendaftaran.detail', $pendaftaran->uuid))
            ->assertOk()
            ->assertDontSee('CATATAN-WAWANCARA-RAHASIA')
            ->assertDontSee('KEKURANGAN-RAHASIA');
    }
}
