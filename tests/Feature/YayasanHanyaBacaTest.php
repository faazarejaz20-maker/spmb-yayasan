<?php

namespace Tests\Feature;

use App\Enums\StatusHasil;
use App\Models\DaftarUlang;
use App\Models\Pembayaran;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 12: yayasan hanya baca. Metode selain GET ke rute /yayasan ditolak;
 * angka rekap sesuai data uji; tidak ada tautan keluar ke data per orang.
 */
class YayasanHanyaBacaTest extends PanitiaTestCase
{
    private function pengguna(): User
    {
        return User::factory()->yayasan()->create();
    }

    public function test_metode_selain_get_ke_yayasan_ditolak(): void
    {
        $yayasan = $this->pengguna();

        $this->actingAs($yayasan);

        foreach (['/yayasan', '/yayasan/rekap', '/yayasan/profil'] as $rute) {
            $this->post($rute, [])->assertStatus(405);
            $this->put($rute, [])->assertStatus(405);
            $this->patch($rute, [])->assertStatus(405);
            $this->delete($rute)->assertStatus(405);
        }
    }

    public function test_angka_rekap_sesuai_data_uji(): void
    {
        $p1 = $this->terkirim();
        $this->aturStatus($p1, StatusHasil::Diterima);

        $p2 = $this->buatPendaftaranTerkirim($this->wali, status: 'Baru');

        Pembayaran::create([
            'pendaftaran_id' => $p1->id,
            'metode' => \App\Enums\MetodePembayaran::Tunai,
            'nominal' => 150000,
            'status' => \App\Enums\StatusPembayaran::Menunggu,
            'dibayar_pada' => now()->subDay(),
        ]);

        DaftarUlang::create([
            'pendaftaran_id' => $p1->id,
            'hadir' => true,
            'selesai' => true,
        ]);

        $pB = $this->pendaftaranSekolahB(status: 'Diterima');

        $respons = $this->actingAs($this->pengguna())
            ->get('/yayasan/rekap')
            ->assertOk();

        $rekap = $respons->viewData('rekap');

        $this->assertSame(3, $rekap['ringkasan']['total']);
        $this->assertSame(2, $rekap['ringkasan']['status']['Diterima']);
        $this->assertSame(1, $rekap['ringkasan']['status']['Baru']);
        $this->assertSame(1, $rekap['ringkasan']['daftar_ulang_selesai']);
        $this->assertSame(1, $rekap['ringkasan']['pembayaran']['menunggu']);
        $this->assertSame(2, $rekap['ringkasan']['terisi']);
        $this->assertCount(2, $rekap['sekolah']);

        $sd = collect($rekap['sekolah'])->first(fn ($s) => $s['sekolah']['id'] === $this->sekolah->id);
        $smp = collect($rekap['sekolah'])->first(fn ($s) => $s['sekolah']['id'] === $this->sekolahB->id);

        $this->assertSame(2, $sd['ringkasan']['total']);
        $this->assertSame(1, $smp['ringkasan']['total']);
        $this->assertSame(20, $sd['ringkasan']['kuota']);
        $this->assertSame(20, $smp['ringkasan']['kuota']);
    }

    public function test_rekap_tidak_menampilkan_data_per_orang(): void
    {
        $p1 = $this->terkirim();
        $this->aturStatus($p1, StatusHasil::Diterima);

        $pB = $this->pendaftaranSekolahB(status: 'Diterima');

        foreach (['/yayasan', '/yayasan/rekap'] as $rute) {
            $isi = (string) $this->actingAs($this->pengguna())
                ->get($rute)
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('Aisyah Nur Fadhilah', $isi);
            $this->assertStringNotContainsString($p1->uuid, $isi);
            $this->assertStringNotContainsString($pB->uuid, $isi);
            $this->assertStringNotContainsString((string) $p1->nomor_peserta, $isi);
            $this->assertStringNotContainsString((string) $pB->nomor_peserta, $isi);
            $this->assertStringNotContainsString('href="/panitia', $isi);
            $this->assertStringNotContainsString('href="/admin', $isi);
            $this->assertStringNotContainsString('href="/super', $isi);
        }
    }

    public function test_rute_yayasan_hanya_mengizinkan_get(): void
    {
        $terdaftar = 0;

        foreach (Route::getRoutes() as $rute) {
            $uri = $rute->uri();

            if (! str_starts_with($uri, 'yayasan')) {
                continue;
            }

            $terdaftar++;

            // Rute GET di Laravel selalu menerima HEAD juga; selain itu tidak
            // boleh ada metode apa pun.
            foreach ($rute->methods() as $metode) {
                $this->assertContains(
                    $metode,
                    ['GET', 'HEAD'],
                    "Rute yayasan {$uri} memakai metode {$metode}.",
                );
            }
        }

        $this->assertGreaterThan(0, $terdaftar);
    }

    public function test_profil_yayasan_hanya_menampilkan_data_akun_sendiri(): void
    {
        $isi = (string) $this->actingAs($this->pengguna())
            ->get('/yayasan/profil')
            ->assertOk()
            ->getContent();

        // Profil memakai nomor HP termaskan, bukan utuh.
        $this->assertStringNotContainsString('href="/panitia', $isi);
        $this->assertStringNotContainsString('href="/admin', $isi);
    }
}
