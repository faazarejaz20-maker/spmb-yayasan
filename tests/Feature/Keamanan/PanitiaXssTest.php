<?php

namespace Tests\Feature\Keamanan;

use App\Enums\StatusBerkas;
use App\Enums\StatusHasil;
use App\Enums\StatusPembayaran;
use App\Models\JenisBerkas;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 17: seluruh teks dari pengguna di panel panitia di-escape, termasuk
 * nama anak, catatan internal, catatan berkas, dan catatan wawancara.
 */
class PanitiaXssTest extends PanitiaTestCase
{
    private const JAHAT = '<script>alert("panitia")</script>';

    private const GAMBAR = '<img src=x onerror=alert(1)>';

    private function pastikanBersih(\Illuminate\Testing\TestResponse $respons, array $teks): void
    {
        $respons->assertOk();

        foreach ($teks as $nilai) {
            $respons->assertDontSee($nilai, false);
            $respons->assertSee(e($nilai), false);
        }
    }

    public function test_nama_anak_dan_catatan_panitia_di_escape_di_daftar_dan_detail(): void
    {
        $pendaftaran = $this->terkirim();
        $pendaftaran->anak->forceFill(['nama_lengkap' => self::JAHAT])->save();
        $pendaftaran->forceFill(['catatan_panitia' => self::GAMBAR])->save();

        $this->actingAs($this->panitia);

        $this->pastikanBersih(
            $this->get(route('panitia.pendaftar.index')),
            [self::JAHAT],
        );

        $this->pastikanBersih(
            $this->get(route('panitia.pendaftar.detail', $pendaftaran->uuid)),
            [self::JAHAT, self::GAMBAR],
        );
    }

    public function test_catatan_berkas_dan_wawancara_di_escape(): void
    {
        $pendaftaran = $this->terkirim();
        $jenis = JenisBerkas::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'wajib' => true,
        ]);
        $berkas = $this->lampirkanBerkas($pendaftaran, $jenis);

        $berkas->forceFill([
            'status' => StatusBerkas::PerluPerbaikan,
            'catatan' => self::GAMBAR,
        ])->save();

        $pendaftaran->forceFill(['status_hasil' => StatusHasil::Diterima])->save();
        $this->pembayaran($pendaftaran, StatusPembayaran::Disetujui);

        $this->actingAs($this->panitia)
            ->put(route('panitia.pendaftar.daftar-ulang', $pendaftaran->uuid), [
                'hadir' => 1,
                'catatan_wawancara' => self::JAHAT,
                'kekurangan' => self::GAMBAR,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->panitia);

        $this->pastikanBersih(
            $this->get(route('panitia.pendaftar.detail', [
                'pendaftaran' => $pendaftaran->uuid,
                'tab' => 'berkas',
            ])),
            [self::GAMBAR],
        );

        $this->pastikanBersih(
            $this->get(route('panitia.pendaftar.detail', [
                'pendaftaran' => $pendaftaran->uuid,
                'tab' => 'daftar-ulang',
            ])),
            [self::JAHAT, self::GAMBAR],
        );
    }

    public function test_alasan_penolakan_pembayaran_di_escape(): void
    {
        $pendaftaran = $this->terkirim();
        $bayar = $this->pembayaran($pendaftaran, StatusPembayaran::Menunggu);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pembayaran.tolak', $bayar->uuid), ['catatan' => self::JAHAT])
            ->assertSessionHasNoErrors();

        $this->pastikanBersih(
            $this->get(route('panitia.pendaftar.detail', [
                'pendaftaran' => $pendaftaran->uuid,
                'tab' => 'pembayaran',
            ])),
            [self::JAHAT],
        );
    }

    public function test_nama_akun_offline_di_escape_di_kartu_akun(): void
    {
        $this->actingAs($this->panitia)
            ->post(route('panitia.offline.akun'), [
                'nama' => self::JAHAT,
                'no_hp' => '081234500009',
            ])
            ->assertSessionHasNoErrors();

        $this->pastikanBersih(
            $this->get(route('panitia.akun.kartu')),
            [self::JAHAT],
        );
    }
}
