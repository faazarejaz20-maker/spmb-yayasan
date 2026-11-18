<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Exceptions\AturanBisnisException;
use App\Models\LogAktivitas;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Services\AkunWalisantriService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Butir 12 dan 14: atur ulang kata sandi dengan kata sandi sementara, dan
 * pemindahan anak antar akun walisantri dalam lingkup sekolah yang sama.
 */
class PanitiaAkunTest extends PanitiaTestCase
{
    private function akunBaru(string $noHp, ?User $staf = null): User
    {
        return app(AkunWalisantriService::class)
            ->buatAkun($staf ?? $this->panitia, 'Wali '.$noHp, $noHp)['user'];
    }

    private function buatTabelSesi(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function test_atur_ulang_password_menghasilkan_kata_sandi_sementara_dan_menghapus_sesi(): void
    {
        $this->buatTabelSesi();

        $pendaftaran = $this->terkirim();
        $wali = $pendaftaran->anak->user;
        $lama = (string) $wali->password;

        DB::table('sessions')->insert([
            'id' => 'sesi-lama',
            'user_id' => $wali->id,
            'payload' => 'isi',
            'last_activity' => now()->timestamp,
        ]);

        $respons = $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.atur-ulang-password', $pendaftaran->uuid));

        $respons->assertRedirect(route('panitia.akun.kartu'))->assertSessionHasNoErrors();

        /** @var array<string, mixed> $kartu */
        $kartu = $respons->getSession()->get('kartu_akun');
        $kataSandi = (string) $kartu['kata_sandi'];

        $segar = $wali->refresh();

        $this->assertNotSame($lama, $segar->password);
        $this->assertFalse(Hash::check('password', $segar->password));
        $this->assertTrue(Hash::check($kataSandi, $segar->password));
        $this->assertTrue($segar->wajib_ganti_password);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $wali->id)->count());

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'atur_ulang_kata_sandi',
            'objek' => 'User',
            'objek_id' => $wali->id,
            'user_id' => $this->panitia->id,
            'sekolah_id' => $this->sekolah->id,
        ]);

        $this->assertSame(
            0,
            LogAktivitas::query()->where('data', 'like', '%'.$kataSandi.'%')->count()
        );
    }

    public function test_atur_ulang_password_menolak_sekolah_lain_dan_peran_lain(): void
    {
        $pendaftaran = $this->pendaftaranSekolahB();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.atur-ulang-password', $pendaftaran->uuid))
            ->assertNotFound();

        $this->actingAs($this->wali)
            ->post(route('panitia.pendaftar.atur-ulang-password', $pendaftaran->uuid))
            ->assertForbidden();
    }

    public function test_staf_tidak_bisa_menjadi_target_atur_ulang(): void
    {
        $service = app(AkunWalisantriService::class);

        $this->expectException(AturanBisnisException::class);

        $service->aturUlang($this->panitia, $this->admin);
    }

    public function test_atur_ulang_password_dibatasi_dua_puluh_per_jam(): void
    {
        $pendaftaran = $this->terkirim();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($this->panitia)
                ->post(route('panitia.pendaftar.atur-ulang-password', $pendaftaran->uuid))
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.atur-ulang-password', $pendaftaran->uuid))
            ->assertStatus(429);
    }

    public function test_pindah_anak_dalam_lingkup_berhasil_dan_mencatat_id_saja(): void
    {
        $pendaftaran = $this->terkirim();
        $asal = $pendaftaran->anak->user;
        $tujuan = $this->akunBaru('081300000001');
        $anak = $pendaftaran->anak;

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pindah-anak', $pendaftaran->uuid), [
                'no_hp' => '081300000001',
            ])
            ->assertRedirect(route('panitia.pendaftar.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($tujuan->id, (int) $anak->refresh()->user_id);

        $log = LogAktivitas::query()
            ->where('aksi', 'pindah_anak')
            ->where('objek_id', $anak->id)
            ->firstOrFail();

        $this->assertSame($this->sekolah->id, (int) $log->sekolah_id);
        $this->assertSame(
            ['akun_asal' => $asal->id, 'akun_tujuan' => $tujuan->id],
            json_decode((string) $log->data, true),
        );
        $this->assertStringNotContainsString((string) $anak->nama_lengkap, (string) $log->data);
    }

    public function test_pindah_anak_ditolak_bila_akun_tujuan_di_luar_lingkup(): void
    {
        $pendaftaran = $this->terkirim();
        $tujuan = $this->akunBaru('081300000002', $this->panitiaB);
        $anak = $pendaftaran->anak;
        $asalId = (int) $anak->user_id;

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pindah-anak', $pendaftaran->uuid), [
                'no_hp' => $tujuan->no_hp,
            ])
            ->assertSessionHasErrors('no_hp');

        $this->assertSame($asalId, (int) $anak->refresh()->user_id);
    }

    public function test_pindah_anak_ditolak_bila_anak_punya_pendaftaran_di_sekolah_lain(): void
    {
        $pendaftaran = $this->terkirim();
        $wali = $pendaftaran->anak->user;
        $anak = $pendaftaran->anak;
        $tujuan = $this->akunBaru('081300000003');

        Pendaftaran::factory()->create([
            'anak_id' => $anak->id,
            'sekolah_id' => $this->sekolahB->id,
            'gelombang_id' => $this->gelombangB->id,
            'dibuat_oleh' => $wali->id,
        ]);

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pindah-anak', $pendaftaran->uuid), [
                'no_hp' => $tujuan->no_hp,
            ])
            ->assertSessionHasErrors('no_hp');

        $this->assertSame($wali->id, (int) $anak->refresh()->user_id);
    }

    public function test_pindah_anak_ke_akun_yang_sama_ditolak(): void
    {
        $pendaftaran = $this->terkirim();
        $wali = $pendaftaran->anak->user;

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pindah-anak', $pendaftaran->uuid), [
                'no_hp' => $wali->no_hp,
            ])
            ->assertSessionHasErrors('no_hp');

        $this->assertSame($wali->id, (int) $pendaftaran->anak->refresh()->user_id);
    }

    public function test_pindah_anak_menolak_akun_staf_sebagai_tujuan(): void
    {
        $pendaftaran = $this->terkirim();

        $this->actingAs($this->panitia)
            ->post(route('panitia.pendaftar.pindah-anak', $pendaftaran->uuid), [
                'no_hp' => $this->admin->no_hp,
            ])
            ->assertSessionHasErrors('no_hp');

        $this->assertSame(
            (int) $pendaftaran->anak->user_id,
            (int) $pendaftaran->anak->refresh()->user_id,
        );
        $this->assertSame(PeranPengguna::Admin, $this->admin->refresh()->peran);
    }
}
