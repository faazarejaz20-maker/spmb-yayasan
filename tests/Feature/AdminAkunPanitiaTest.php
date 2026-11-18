<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\PanitiaTestCase;

/**
 * Butir 5: akun panitia oleh admin. `peran` dan `sekolah_id` dipaksa; kata
 * sandi sementara acak, tampil sekali, tidak tersimpan sebagai teks asli,
 * tidak ada di log aktivitas maupun berkas log; nonaktif menghapus sesi dan
 * memblokir masuk; admin tidak bisa mengelola akun di luar lingkupnya.
 */
class AdminAkunPanitiaTest extends PanitiaTestCase
{
    public function test_peran_dan_sekolah_id_dipaksa_saat_membuat_panitia(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/panitia', [
                'nama' => 'Panitia Uji',
                'no_hp' => '081200000002',
                'peran' => 'super_admin',
                'sekolah_id' => $this->sekolahB->id,
                'aktif' => '0',
                'wajib_ganti_password' => '0',
                'dibuat_oleh' => '999',
            ])
            ->assertRedirect(route('kartu-akun.staf'));

        $akun = User::query()->where('no_hp', '6281200000002')->firstOrFail();

        $this->assertSame(PeranPengguna::Panitia, $akun->peran);
        $this->assertSame($this->sekolah->id, (int) $akun->sekolah_id);
        $this->assertTrue((bool) $akun->aktif);
        $this->assertTrue((bool) $akun->wajib_ganti_password);
        $this->assertSame($this->admin->id, (int) $akun->dibuat_oleh);
    }

    public function test_kata_sandi_sementara_acak_hanya_hash_di_database(): void
    {
        $respons = $this->actingAs($this->admin)
            ->post('/admin/panitia', [
                'nama' => 'Panitia Sandi',
                'no_hp' => '081200000003',
            ])
            ->assertRedirect();

        $kataSandi = $respons->getSession()->get('kartu_akun.kata_sandi');

        $this->assertIsString($kataSandi);
        $this->assertSame(10, mb_strlen($kataSandi));
        $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $kataSandi);

        $akun = User::query()->where('no_hp', '6281200000003')->firstOrFail();

        $this->assertNotSame($kataSandi, $akun->password);
        $this->assertTrue(Hash::check($kataSandi, $akun->password));
    }

    public function test_kartu_akun_hanya_tampil_sekali(): void
    {
        $respons = $this->actingAs($this->admin)
            ->post('/admin/panitia', [
                'nama' => 'Panitia Kartu',
                'no_hp' => '081200000004',
            ])
            ->assertRedirect(route('kartu-akun.staf'));

        $kataSandi = $respons->getSession()->get('kartu_akun.kata_sandi');

        $kartu = $this->get('/kartu-akun');
        $kartu->assertOk();
        $this->assertStringContainsString($kataSandi, (string) $kartu->getContent());

        // Kunjungan kedua tidak lagi menampilkan kata sandi.
        $ulang = $this->get('/kartu-akun');
        $ulang->assertRedirect(route('admin.beranda'));
        $this->assertStringNotContainsString($kataSandi, (string) $ulang->getContent());
    }

    public function test_kata_sandi_tidak_ada_di_log_aktivitas_dan_berkas_log(): void
    {
        $respons = $this->actingAs($this->admin)
            ->post('/admin/panitia', [
                'nama' => 'Panitia Log',
                'no_hp' => '081200000005',
            ])
            ->assertRedirect();

        $kataSandi = $respons->getSession()->get('kartu_akun.kata_sandi');

        foreach (LogAktivitas::query()->get() as $log) {
            $this->assertStringNotContainsString((string) $kataSandi, (string) $log->data);
        }

        $isiLog = '';

        foreach (glob(storage_path('logs').'/*.log') ?: [] as $berkas) {
            $isiLog .= (string) file_get_contents($berkas);
        }

        $this->assertStringNotContainsString((string) $kataSandi, $isiLog);
    }

    public function test_nonaktifkan_memblokir_masuk_dan_menghapus_sesi(): void
    {
        $panitia = User::factory()->panitia($this->sekolah)->create([
            'no_hp' => '6281200000099',
            'password' => 'sandi-asli-panitia',
        ]);

        DB::table('sessions')->insert([
            'id' => 'sesi-panitia-uji',
            'user_id' => $panitia->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => base64_encode('uji'),
            'last_activity' => time(),
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/panitia/'.$panitia->id.'/nonaktifkan')
            ->assertRedirect(route('admin.panitia.index'))
            ->assertSessionHas('sukses');

        $this->assertFalse((bool) $panitia->fresh()->aktif);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-panitia-uji']);

        // Masuk dengan akun nonaktif ditolak tanpa mengungkap statusnya.
        $this->post('/keluar');

        $respons = $this->post('/masuk', [
            'identitas' => '081200000099',
            'kata_sandi' => 'sandi-asli-panitia',
        ]);

        $respons->assertRedirect();
        $respons->assertSessionHasErrors('identitas');
        $this->assertGuest();
    }

    public function test_aktifkan_kembali_mengizinkan_masuk(): void
    {
        $panitia = User::factory()->panitia($this->sekolah)->nonaktif()->create([
            'no_hp' => '6281200000098',
            'password' => 'sandi-asli-panitia',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/panitia/'.$panitia->id.'/aktifkan')
            ->assertSessionHas('sukses');

        $this->assertTrue((bool) $panitia->fresh()->aktif);

        $this->post('/keluar');

        $this->post('/masuk', [
            'identitas' => '081200000098',
            'kata_sandi' => 'sandi-asli-panitia',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($panitia->fresh());
    }

    public function test_atur_ulang_membuat_kata_sandi_sementara_dan_menghapus_sesi(): void
    {
        $panitia = User::factory()->panitia($this->sekolah)->create([
            'no_hp' => '6281200000097',
        ]);

        DB::table('sessions')->insert([
            'id' => 'sesi-atur-ulang',
            'user_id' => $panitia->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => base64_encode('uji'),
            'last_activity' => time(),
        ]);

        $respons = $this->actingAs($this->admin)
            ->post('/admin/panitia/'.$panitia->id.'/atur-ulang')
            ->assertRedirect(route('kartu-akun.staf'));

        $kataSandi = $respons->getSession()->get('kartu_akun.kata_sandi');
        $akun = $panitia->fresh();

        $this->assertTrue(Hash::check($kataSandi, $akun->password));
        $this->assertTrue((bool) $akun->wajib_ganti_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-atur-ulang']);
    }

    public function test_atur_ulang_dibatasi_20_per_jam_per_admin(): void
    {
        $panitia = User::factory()->panitia($this->sekolah)->create([
            'no_hp' => '6281200000096',
        ]);

        $this->actingAs($this->admin);

        for ($i = 0; $i < 20; $i++) {
            $this->post('/admin/panitia/'.$panitia->id.'/atur-ulang')->assertRedirect();
        }

        $this->post('/admin/panitia/'.$panitia->id.'/atur-ulang')->assertStatus(429);
    }

    public function test_admin_tidak_bisa_mengelola_akun_di_luar_lingkupnya(): void
    {
        $adminB = User::factory()->admin($this->sekolahB)->create();
        $yayasan = User::factory()->yayasan()->create();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($this->admin);

        foreach ([$this->panitiaB, $adminB, $yayasan, $super] as $target) {
            $this->post('/admin/panitia/'.$target->id.'/nonaktifkan')->assertNotFound();
            $this->post('/admin/panitia/'.$target->id.'/aktifkan')->assertNotFound();
            $this->post('/admin/panitia/'.$target->id.'/atur-ulang')->assertNotFound();
        }

        $this->assertTrue((bool) $this->panitiaB->fresh()->aktif);
    }
}
