<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Models\JenisBerkas;
use App\Models\LogAktivitas;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 16: setiap tindakan panitia meninggalkan jejak di `log_aktivitas`,
 * tanpa NIK utuh, dan log hanya boleh ditambah.
 */
class PanitiaLogTest extends PanitiaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_tindakan_panitia_menulis_log_tanpa_nik_utuh(): void
    {
        $nik = (string) $this->anak->nik;
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.keputusan', $pendaftaran->uuid), [
                'status' => StatusHasil::Diverifikasi->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->put(route('panitia.pendaftar.catatan', $pendaftaran->uuid), [
                'catatan_panitia' => 'Catatan internal, NIK '.$nik.'.',
            ])
            ->assertSessionHasNoErrors();

        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'wajib' => true,
        ]);
        $berkas = $this->lampirkanBerkas($pendaftaran, $jenis);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.berkas.periksa', [
                'pendaftaran' => $pendaftaran->uuid,
                'berkas' => $berkas->uuid,
            ]), ['status' => 'sesuai'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pembayaran-tunai', $pendaftaran->uuid), [
                'nominal' => 200000,
                'dibayar_pada' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $pendaftaran->forceFill(['status_hasil' => StatusHasil::Diterima])->save();

        $this->actingAs($this->panitia)
            ->put(route('panitia.pendaftar.daftar-ulang', $pendaftaran->uuid), [
                'hadir' => 1,
                'selesai' => 1,
            ])
            ->assertSessionHasNoErrors();

        $aksi = [
            'ubah_status_hasil',
            'simpan_catatan_panitia',
            'periksa_berkas',
            'catat_pembayaran_tunai',
            'catat_daftar_ulang',
        ];

        foreach ($aksi as $nama) {
            $log = LogAktivitas::query()->where('aksi', $nama)->first();

            $this->assertNotNull($log, 'Log untuk aksi '.$nama.' tidak ditemukan.');
            $this->assertSame($this->panitia->id, (int) $log->user_id);
            $this->assertSame($this->sekolah->id, (int) $log->sekolah_id);
            $this->assertStringNotContainsString($nik, (string) $log->data);
            $this->assertJson((string) ($log->data ?? '{}'));
        }

        $semua = LogAktivitas::query()->get();

        foreach ($semua as $log) {
            $this->assertStringNotContainsString($nik, (string) $log->data);
        }
    }

    public function test_tindakan_yang_ditolak_tidak_menulis_log_perubahan_status(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.keputusan', $pendaftaran->uuid), [
                'status' => StatusHasil::Diterima->value,
            ])
            ->assertSessionHasErrors('keputusan');

        $this->assertSame(
            0,
            LogAktivitas::query()
                ->where('aksi', 'ubah_status_hasil')
                ->where('objek_id', $pendaftaran->id)
                ->count()
        );
    }

    public function test_log_hanya_boleh_ditambah(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.keputusan', $pendaftaran->uuid), [
                'status' => StatusHasil::Diverifikasi->value,
            ]);

        $log = LogAktivitas::query()->firstOrFail();

        $this->expectException(\DomainException::class);

        $log->aksi = 'diubah';
        $log->save();
    }

    public function test_pembayaran_yang_diproses_ulang_oleh_panitia_tidak_menulis_log(): void
    {
        $pendaftaran = $this->terkirim();
        $pembayaran = $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.tolak', $pembayaran->uuid), ['catatan' => 'Ubah'])
            ->assertSessionHasErrors('pembayaran');

        $this->assertSame(
            0,
            LogAktivitas::query()
                ->whereIn('aksi', ['tolak_pembayaran', 'setujui_pembayaran'])
                ->count()
        );
    }
}
