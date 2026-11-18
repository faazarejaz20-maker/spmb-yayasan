<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Http\Requests\DaftarAkunRequest;
use App\Http\Requests\GantiPasswordRequest;
use App\Http\Requests\LupaKataSandiRequest;
use App\Http\Requests\MasukRequest;
use App\Http\Requests\ResetKataSandiRequest;
use App\Models\User;
use App\Notifications\ResetKataSandi;
use App\Support\Catat;
use App\Support\NomorHp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutentikasiController extends Controller
{
    public function formMasuk(): View
    {
        return view('publik.masuk');
    }

    public function masuk(MasukRequest $request): RedirectResponse
    {
        $identitas = $request->identitasTernormalisasi();
        $kunci = 'masuk:'.$identitas.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $detik = RateLimiter::availableIn($kunci);

            abort(429, "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.");
        }

        $user = $this->cariUser($identitas);

        if (! $user || ! Hash::check($request->input('kata_sandi'), $user->password)) {
            RateLimiter::hit($kunci, 60);

            Catat::aksi('masuk_gagal', null, ['identitas' => $this->samarkanIdentitas($identitas)], $user?->sekolah_id);

            $request->throwGalat();
        }

        if (! $user->aktif) {
            RateLimiter::hit($kunci, 60);

            $request->throwGalat();
        }

        RateLimiter::clear($kunci);

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->put('waktu_terakhir_aktif', time());

        $user->forceFill(['terakhir_masuk' => now()])->save();

        Catat::aksi('masuk', null, ['identitas' => $this->samarkanIdentitas($identitas)], $user->sekolah_id);

        return redirect()->intended(route($this->tujuanPeran($user)));
    }

    public function formDaftar(): View
    {
        return view('publik.daftar-akun');
    }

    public function daftar(DaftarAkunRequest $request): RedirectResponse
    {
        $kunci = 'daftar-akun:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $detik = RateLimiter::availableIn($kunci);

            abort(429, "Terlalu banyak pembuatan akun. Coba lagi dalam {$detik} detik.");
        }

        RateLimiter::hit($kunci, 3600);

        // Peran, aktif, dan wajib_ganti_password diisi lewat kode terkontrol
        // (forceCreate), tidak bisa dipengaruhi input pengguna.
        $user = User::forceCreate([
            'nama' => $request->input('nama'),
            'peran' => PeranPengguna::Walisantri,
            'sekolah_id' => null,
            'no_hp' => $request->input('no_hp'),
            'email' => $request->filled('email') ? strtolower($request->input('email')) : null,
            'password' => $request->input('kata_sandi'),
            'aktif' => true,
            'wajib_ganti_password' => false,
            'dibuat_oleh' => null,
        ]);

        Catat::aksi('daftar_akun', $user, ['identitas' => NomorHp::samarkan($user->no_hp)], null);

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->put('waktu_terakhir_aktif', time());

        return redirect()->route('walisantri.beranda')
            ->with('sukses', 'Akun berhasil dibuat. Selamat datang!');
    }

    public function keluar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            Catat::aksi('keluar', null, [], $user->sekolah_id);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda');
    }

    public function formGantiPassword(): View
    {
        return view('akun.ganti-password');
    }

    public function gantiPassword(GantiPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->input('kata_sandi_baru')),
            'wajib_ganti_password' => false,
        ])->save();

        // Akhiri sesi lain milik akun ini.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        Catat::aksi('ganti_password', null, [], $user->sekolah_id);

        return redirect()->route($this->ruteBerandaPeran($user))
            ->with('sukses', 'Kata sandi berhasil diperbarui.');
    }

    public function lupaPassword(): View
    {
        return view('publik.lupa-password');
    }

    /**
     * Kirim tautan atur ulang kata sandi. Respons selalu sama, terdaftar atau
     * tidak, supaya keberadaan akun tidak bocor.
     */
    public function kirimTautanReset(LupaKataSandiRequest $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $kunciEmail = 'reset-email:'.$email;
        $kunciIp = 'reset-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunciEmail, 3) || RateLimiter::tooManyAttempts($kunciIp, 10)) {
            abort(429, 'Terlalu banyak permintaan atur ulang kata sandi. Coba lagi nanti.');
        }

        RateLimiter::hit($kunciEmail, 3600);
        RateLimiter::hit($kunciIp, 3600);

        $user = User::query()
            ->where('email', $email)
            ->where('peran', PeranPengguna::Walisantri)
            ->where('aktif', true)
            ->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $user->notify(new ResetKataSandi($token));
        } else {
            // Samakan waktu respons dengan permintaan yang sah.
            Hash::make('samar-'.$email);
        }

        Catat::aksi('minta_reset_kata_sandi', null, ['email' => $this->samarkanIdentitas($email)], null);

        return back()->with('sukses', 'Jika email terdaftar, tautan atur ulang kata sandi telah dikirim.');
    }

    public function formResetKataSandi(Request $request, string $token): View
    {
        return view('publik.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetKataSandi(ResetKataSandiRequest $request): RedirectResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $kataSandi) {
                if ($user->peran !== PeranPengguna::Walisantri) {
                    throw ValidationException::withMessages([
                        'email' => 'Tautan atur ulang kata sandi tidak valid atau sudah kedaluwarsa.',
                    ]);
                }

                $user->forceFill([
                    'password' => $kataSandi,
                    'wajib_ganti_password' => false,
                ])->save();

                if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }

                Catat::aksi('reset_kata_sandi', $user, [], null);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Tautan atur ulang kata sandi tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        return redirect()->route('masuk')
            ->with('sukses', 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.');
    }

    private function cariUser(string $identitas): ?User
    {
        if (str_contains($identitas, '@')) {
            return User::where('email', $identitas)->first();
        }

        return User::where('no_hp', $identitas)->first();
    }

    private function tujuanPeran(User $user): string
    {
        return match ($user->peran) {
            PeranPengguna::Walisantri => 'walisantri.beranda',
            PeranPengguna::Panitia, PeranPengguna::Admin => 'panitia.beranda',
            PeranPengguna::SuperAdmin => 'super.beranda',
            PeranPengguna::Yayasan => 'yayasan.beranda',
        };
    }

    private function ruteBerandaPeran(User $user): string
    {
        return $this->tujuanPeran($user);
    }

    private function samarkanIdentitas(string $identitas): string
    {
        if (str_contains($identitas, '@')) {
            return substr($identitas, 0, 2).'***'.strstr($identitas, '@');
        }

        return NomorHp::samarkan($identitas);
    }
}
