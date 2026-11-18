<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Notifications\ResetKataSandi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Butir 13: atur ulang kata sandi mandiri lewat email. Respons selalu sama,
 * token berumur 60 menit dan sekali pakai, akun staf tidak bisa memakainya,
 * dan surat tidak pernah memuat kata sandi.
 */
class ResetKataSandiEmailTest extends WalisantriTestCase
{
    private function minta(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('lupa-password.simpan'), ['email' => $email]);
    }

    private function akunEmail(string $email = 'wali@contoh.test'): User
    {
        $user = User::factory()->walisantri()->create([
            'nama' => 'Wali Email',
            'email' => $email,
            'password' => 'KataSandiLama123',
        ]);

        return $user;
    }

    public function test_respons_sama_untuk_email_terdaftar_dan_tidak_terdaftar(): void
    {
        Notification::fake();

        $wali = $this->akunEmail();

        $ada = $this->minta($wali->email);
        $tidakAda = $this->minta('tidak-ada@contoh.test');

        $ada->assertRedirect();
        $tidakAda->assertRedirect();

        $this->assertSame(
            $ada->getSession()->get('sukses'),
            $tidakAda->getSession()->get('sukses'),
        );

        Notification::assertSentTo($wali, ResetKataSandi::class);
        Notification::assertCount(1);
    }

    public function test_surat_tidak_memuat_kata_sandi_dan_tautannya_bertanda_tangan(): void
    {
        Notification::fake();

        $wali = $this->akunEmail();

        $this->minta($wali->email)->assertRedirect();

        Notification::assertSentTo(
            $wali,
            ResetKataSandi::class,
            function (ResetKataSandi $notifikasi, array $kanal, User $penerima): bool {
                $this->assertSame(['mail'], $kanal);

                $isi = $notifikasi->toMail($penerima)->render();

                $this->assertStringNotContainsString('KataSandiLama123', $isi);
                $this->assertStringNotContainsString($penerima->password, $isi);
                $this->assertStringContainsString('reset-password', $isi);
                $this->assertStringContainsString('signature=', $isi);

                return true;
            },
        );
    }

    public function test_token_dapat_dipakai_sekali_dan_kata_sandi_berubah(): void
    {
        $wali = $this->akunEmail();
        $token = Password::broker()->createToken($wali);

        $this->post(route('password.reset.simpan'), [
            'token' => $token,
            'email' => $wali->email,
            'password' => 'KataSandiBaru456',
            'password_confirmation' => 'KataSandiBaru456',
        ])->assertRedirect(route('masuk'))->assertSessionHasNoErrors();

        $segar = $wali->refresh();

        $this->assertTrue(Hash::check('KataSandiBaru456', $segar->password));
        $this->assertFalse($segar->wajib_ganti_password);

        $this->assertDatabaseHas('log_aktivitas', [
            'aksi' => 'reset_kata_sandi',
            'objek' => 'User',
            'objek_id' => $wali->id,
        ]);

        // Token yang sama tidak bisa dipakai dua kali.
        $this->post(route('password.reset.simpan'), [
            'token' => $token,
            'email' => $wali->email,
            'password' => 'KataSandiKetiga789',
            'password_confirmation' => 'KataSandiKetiga789',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('KataSandiBaru456', $wali->refresh()->password));
    }

    public function test_token_kedaluwarsa_setelah_enam_puluh_menit(): void
    {
        $wali = $this->akunEmail();
        $token = Password::broker()->createToken($wali);

        DB::table('password_reset_tokens')
            ->where('email', $wali->email)
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->post(route('password.reset.simpan'), [
            'token' => $token,
            'email' => $wali->email,
            'password' => 'KataSandiBaru456',
            'password_confirmation' => 'KataSandiBaru456',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('KataSandiLama123', $wali->refresh()->password));
    }

    public function test_akun_staf_tidak_mendapat_tautan_reset(): void
    {
        Notification::fake();

        $staf = User::factory()->admin($this->sekolah)->create([
            'email' => 'staf@contoh.test',
        ]);

        $this->minta($staf->email)->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_permintaan_dibatasi_tiga_per_email_per_jam(): void
    {
        Notification::fake();

        $wali = $this->akunEmail();

        for ($i = 0; $i < 3; $i++) {
            $this->minta($wali->email)->assertRedirect();
        }

        $this->minta($wali->email)->assertStatus(429);

        Notification::assertSentToTimes($wali, ResetKataSandi::class, 3);
    }

    public function test_kata_sandi_baru_tidak_boleh_sama_dengan_email(): void
    {
        $wali = $this->akunEmail('sama@contoh.test');
        $token = Password::broker()->createToken($wali);

        $this->post(route('password.reset.simpan'), [
            'token' => $token,
            'email' => $wali->email,
            'password' => 'sama@contoh.test',
            'password_confirmation' => 'sama@contoh.test',
        ])->assertSessionHasErrors('password');

        $this->assertSame(PeranPengguna::Walisantri, $wali->refresh()->peran);
    }
}
