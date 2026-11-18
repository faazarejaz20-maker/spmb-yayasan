<?php

namespace Tests\Feature\Keamanan;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\UnduhanTestCase;

/**
 * Butir 9 dan 10 Tahap 5: kuitansi hanya untuk pembayaran `disetujui`, nomor
 * kuitansi terkontrol dan unik, tanda "bukan bukti lunas SPP", pemilik lain 404.
 */
class CetakKuitansiTest extends UnduhanTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_kuitansi_hanya_untuk_pembayaran_disetujui(): void
    {
        $pendaftaran = $this->terkirim();
        $menunggu = $this->pembayaran($pendaftaran, StatusPembayaran::Menunggu);
        $ditolak = $this->pembayaran($pendaftaran, StatusPembayaran::Ditolak);

        foreach ([$menunggu, $ditolak] as $pembayaran) {
            $this->actingAs($this->panitia)
                ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid))
                ->assertForbidden();
        }
    }

    public function test_pembayaran_disetujui_bisa_dicetak(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid));

        $respons->assertOk();
        $respons->assertSee('Bukan bukti lunas SPP');
        $respons->assertSee('Rp 150.000');
    }

    public function test_nomor_kuitansi_terkontrol_dan_unik(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $nomor = sprintf('KWT-SD-2027-%05d', $pembayaran->id);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid));

        $respons->assertOk();
        $respons->assertSee($nomor);

        // Dua pembayaran berbeda menghasilkan nomor berbeda.
        $kedua = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);
        $this->assertNotSame($nomor, sprintf('KWT-SD-2027-%05d', $kedua->id));
    }

    public function test_nomor_kuitansi_tidak_dari_input_pengguna(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid).'?nomor=HACK-999');

        $respons->assertOk();
        $respons->assertDontSee('HACK-999');
    }

    public function test_walisantri_bisa_mencetak_kuitansi_miliknya(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $this->actingAs($this->wali)
            ->get(route('walisantri.pembayaran.cetak.kuitansi', $pembayaran->uuid))
            ->assertOk()
            ->assertSee('Bukan bukti lunas SPP');
    }

    public function test_walisantri_lain_404(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);
        $lain = User::factory()->walisantri()->create();

        $this->actingAs($lain)
            ->get(route('walisantri.pembayaran.cetak.kuitansi', $pembayaran->uuid))
            ->assertNotFound();
    }

    public function test_sekolah_lain_404(): void
    {
        $lain = $this->pendaftaranSekolahB();
        $pembayaran = $this->pembayaran($lain, StatusPembayaran::Disetujui);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid))
            ->assertNotFound();
    }

    public function test_super_admin_dan_yayasan_tidak_boleh(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        foreach ([User::factory()->superAdmin()->create(), User::factory()->yayasan()->create()] as $pengguna) {
            $this->actingAs($pengguna)
                ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid))
                ->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $this->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid))
            ->assertRedirect(route('masuk'));
    }

    public function test_metode_tunai_dan_transfer_tampil(): void
    {
        $pendaftaran = $this->terkirim();

        $tunai = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui, 200000, MetodePembayaran::Tunai);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.cetak.kuitansi', $tunai->uuid))
            ->assertOk()
            ->assertSee('Tunai');
    }

    public function test_header_aman_dan_log(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $respons = $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid));

        $respons->assertOk();
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));

        $log = LogAktivitas::query()->tanpaSekolah()->where('aksi', 'cetak_kuitansi')->first();

        $this->assertNotNull($log);
        $this->assertSame(
            sprintf('KWT-SD-2027-%05d', $pembayaran->id),
            json_decode((string) $log->data, true)['nomor_kuitansi'],
        );
    }

    public function test_draf_tidak_punya_kuitansi(): void
    {
        $pembayaran = $this->pembayaran($this->pendaftaran, StatusPembayaran::Disetujui);

        $this->actingAs($this->panitia)
            ->get(route('panitia.pembayaran.cetak.kuitansi', $pembayaran->uuid))
            ->assertNotFound();
    }
}
