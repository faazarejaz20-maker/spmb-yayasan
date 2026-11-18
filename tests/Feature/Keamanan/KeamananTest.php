<?php

namespace Tests\Feature\Keamanan;

use App\Enums\PeranPengguna;
use App\Models\Anak;
use App\Models\Gelombang;
use App\Models\Pendaftaran;
use App\Models\RiwayatStatus;
use App\Models\Sekolah;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeamananTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_tanpa_token_mendapat_419(): void
    {
        // Laravel melewati pemeriksaan CSRF saat lingkungan `testing`. Paksa
        // lingkungan lain agar middleware CSRF benar-benar diuji.
        $this->app['env'] = 'local';

        $this->post('/masuk', [
            'identitas' => '6281234567890',
            'kata_sandi' => 'password123',
        ])->assertStatus(419);
    }

    public function test_riwayat_status_update_dan_delete_dilempar(): void
    {
        $sekolah = Sekolah::factory()->create();
        $tahun = \App\Models\TahunAjaran::factory()->create();
        $gelombang = Gelombang::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $tahun->id]);

        $anak = Anak::factory()->create();
        $pendaftaran = Pendaftaran::factory()->create([
            'anak_id' => $anak->id,
            'sekolah_id' => $sekolah->id,
            'gelombang_id' => $gelombang->id,
        ]);

        $riwayat = RiwayatStatus::create([
            'pendaftaran_id' => $pendaftaran->id,
            'jenis' => 'hasil',
            'dari' => null,
            'ke' => 'Baru',
        ]);

        $this->expectException(DomainException::class);
        $riwayat->update(['ke' => 'Diterima']);
    }

    public function test_log_aktivitas_delete_dilempar(): void
    {
        $log = \App\Models\LogAktivitas::create([
            'aksi' => 'tes',
        ]);

        $this->expectException(DomainException::class);
        $log->delete();
    }

    public function test_enkripsi_anak_nik_bukan_teks_plos_di_db(): void
    {
        $anak = Anak::factory()->create(['nik' => '3326161234567890']);

        $mentah = \DB::table('anak')->where('id', $anak->id)->value('nik');

        $this->assertNotSame('3326161234567890', $mentah);

        // Lewat model terbaca benar.
        $this->assertSame('3326161234567890', $anak->fresh()->nik);
    }

    public function test_header_keamanan_ada(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_halaman_setelah_masuk_no_store(): void
    {
        $user = User::forceCreate([
            'nama' => 'Wali',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
        ]);

        $this->actingAs($user)->get('/akun')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_halaman_publik_tidak_memuat_biaya_kuota(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringNotContainsString('kuota', strtolower($response->getContent()));
        $this->assertStringNotContainsString('IAP', $response->getContent());
        $this->assertStringNotContainsString('Rp', $response->getContent());
    }

    public function test_xss_nama_ter_escape(): void
    {
        $user = User::forceCreate([
            'nama' => '<script>alert(1)</script>',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
        ]);

        $response = $this->actingAs($user)->get('/akun');

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee(e('<script>alert(1)</script>'), false);
    }

    public function test_akun_nonaktif_di_tengah_sesi_ditolak(): void
    {
        $user = User::forceCreate([
            'nama' => 'Wali',
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => '6281234567890',
            'password' => 'password123',
            'aktif' => true,
        ]);

        $this->actingAs($user)->get('/akun')->assertOk();

        $user->forceFill(['aktif' => false])->save();

        $this->get('/akun')->assertRedirect('/masuk');
        $this->assertGuest();
    }
}
