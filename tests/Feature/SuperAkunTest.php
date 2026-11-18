<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Exceptions\AturanBisnisException;
use App\Models\User;
use App\Services\AkunStafService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 6: akun admin dan yayasan oleh super admin. Peran benar; tidak bisa
 * membuat super admin dari antarmuka; tidak bisa menonaktifkan diri sendiri
 * atau super admin terakhir yang aktif; atur ulang menghapus sesi.
 */
class SuperAkunTest extends PanitiaTestCase
{
    private function dataAkun(array $tambahan = []): array
    {
        return array_merge([
            'nama' => 'Akun Uji',
            'no_hp' => '081300000001',
        ], $tambahan);
    }

    public function test_super_admin_membuat_akun_admin_dengan_sekolah_benar(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->post('/super/admin', $this->dataAkun([
                'sekolah_id' => $this->sekolah->id,
            ]))
            ->assertRedirect(route('kartu-akun.staf'));

        $akun = User::query()->where('no_hp', '6281300000001')->firstOrFail();

        $this->assertSame(PeranPengguna::Admin, $akun->peran);
        $this->assertSame($this->sekolah->id, (int) $akun->sekolah_id);
        $this->assertTrue((bool) $akun->wajib_ganti_password);
    }

    public function test_input_peran_super_admin_diabaikan_saat_membuat_admin(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->post('/super/admin', $this->dataAkun([
                'sekolah_id' => $this->sekolah->id,
                'peran' => 'super_admin',
            ]))
            ->assertRedirect();

        $akun = User::query()->where('no_hp', '6281300000001')->firstOrFail();

        $this->assertSame(PeranPengguna::Admin, $akun->peran);
    }

    public function test_super_admin_membuat_akun_yayasan_tanpa_sekolah(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)
            ->post('/super/yayasan', $this->dataAkun([
                'sekolah_id' => $this->sekolah->id,
                'peran' => 'admin',
            ]))
            ->assertRedirect(route('kartu-akun.staf'));

        $akun = User::query()->where('no_hp', '6281300000001')->firstOrFail();

        $this->assertSame(PeranPengguna::Yayasan, $akun->peran);
        $this->assertNull($akun->sekolah_id);
    }

    public function test_super_admin_tidak_bisa_dibuat_dari_antarmuka(): void
    {
        // Tidak ada rute pembuatan super admin selain lewat konsol.
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $rute) {
            $uri = $rute->uri();

            if (str_starts_with($uri, 'super')) {
                $this->assertStringNotContainsString('super-admin', $uri);
            }
        }

        $super = User::factory()->superAdmin()->create();

        $this->expectException(AturanBisnisException::class);
        $this->expectExceptionMessage('Peran akun ini tidak dapat dibuat dari antarmuka.');

        app(AkunStafService::class)->buat(
            $super,
            PeranPengguna::SuperAdmin,
            null,
            'Super Baru',
            '081300000002',
        );
    }

    public function test_super_admin_tidak_bisa_mengelola_akun_sendiri(): void
    {
        $super = User::factory()->superAdmin()->create();
        $layanan = app(AkunStafService::class);

        try {
            $layanan->nonaktifkan($super, $super);
            $this->fail('Seharusnya ditolak.');
        } catch (AturanBisnisException $galat) {
            $this->assertSame('Anda tidak dapat mengelola akun sendiri.', $galat->getMessage());
        }

        $this->assertTrue((bool) $super->fresh()->aktif);
    }

    public function test_super_admin_terakhir_yang_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $superA = User::factory()->superAdmin()->create();
        $superB = User::factory()->superAdmin()->create();
        $superC = User::factory()->superAdmin()->create();
        $layanan = app(AkunStafService::class);

        // Tiga super admin aktif: menonaktifkan dua lainnya boleh.
        $layanan->nonaktifkan($superA, $superB);
        $this->assertFalse((bool) $superB->fresh()->aktif);

        $layanan->nonaktifkan($superA, $superC);
        $this->assertFalse((bool) $superC->fresh()->aktif);

        // Kini superA satu-satunya yang aktif: pelaku lain (superC) ditolak.
        try {
            $layanan->nonaktifkan($superC, $superA);
            $this->fail('Seharusnya ditolak.');
        } catch (AturanBisnisException $galat) {
            $this->assertSame('Super admin terakhir yang aktif tidak dapat dinonaktifkan.', $galat->getMessage());
        }

        $this->assertTrue((bool) $superA->fresh()->aktif);
    }

    public function test_atur_ulang_admin_menghapus_sesinya(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin($this->sekolah)->create();

        DB::table('sessions')->insert([
            'id' => 'sesi-admin-yang-diatur-ulang',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => base64_encode('uji'),
            'last_activity' => time(),
        ]);

        $respons = $this->actingAs($super)
            ->post('/super/admin/'.$admin->id.'/atur-ulang')
            ->assertRedirect(route('kartu-akun.staf'));

        $kataSandi = $respons->getSession()->get('kartu_akun.kata_sandi');

        $this->assertIsString($kataSandi);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($kataSandi, $admin->fresh()->password));
        $this->assertTrue((bool) $admin->fresh()->wajib_ganti_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-admin-yang-diatur-ulang']);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'atur_ulang_kata_sandi_staf',
            'objek_id' => $admin->id,
        ]);
    }

    public function test_nonaktifkan_admin_menghapus_sesinya(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin($this->sekolah)->create();

        DB::table('sessions')->insert([
            'id' => 'sesi-admin-nonaktif',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => base64_encode('uji'),
            'last_activity' => time(),
        ]);

        $this->actingAs($super)
            ->post('/super/admin/'.$admin->id.'/nonaktifkan')
            ->assertSessionHas('sukses');

        $this->assertFalse((bool) $admin->fresh()->aktif);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-admin-nonaktif']);
    }

    public function test_data_sekolah_tidak_bisa_mengubah_kode_dan_tahun(): void
    {
        $super = User::factory()->superAdmin()->create();
        $kodeAsli = $this->sekolah->kode;

        $this->actingAs($super)
            ->put('/super/sekolah/'.$this->sekolah->id, [
                'nama' => 'Nama Baru SDIT',
                'kode' => 'ZZZ',
                'mode_penerimaan' => 'seleksi',
                'aktif' => '1',
            ])
            ->assertSessionHasNoErrors();

        $sekolah = $this->sekolah->fresh();

        $this->assertSame($kodeAsli, $sekolah->kode);
        $this->assertSame('Nama Baru SDIT', $sekolah->nama);
    }
}
