<?php

namespace App\Http\Controllers\Panitia;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PindahAnakRequest;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Services\AkunWalisantriService;
use App\Support\NomorHp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AkunController extends Controller
{
    public function __construct(private readonly AkunWalisantriService $akunService) {}

    /**
     * Tampilkan kartu akun sekali setelah kata sandi sementara dibuat.
     * Kata sandi hanya ada di sesi ini dan langsung dihapus setelah dibaca.
     */
    public function kartu(Request $request): View|RedirectResponse
    {
        $kartu = $request->session()->get('kartu_akun');

        if (! is_array($kartu) || empty($kartu['kata_sandi'])) {
            return redirect()
                ->route('panitia.beranda')
                ->with('pesan', 'Kartu akun tidak tersedia. Kata sandi sementara hanya ditampilkan sekali.');
        }

        $request->session()->forget('kartu_akun');

        return view('panitia.kartu-akun', [
            'kartu' => $kartu,
            'lanjut' => (bool) ($kartu['lanjut'] ?? false),
            'alamatSitus' => url('/'),
        ]);
    }

    /**
     * Atur ulang kata sandi walisantri. Kata sandi sementara tampil sekali.
     */
    public function aturUlangPassword(Request $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('kelola', $pendaftaran), 404);

        $kunci = 'atur-ulang-password:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($kunci, 20)) {
            abort(429, 'Terlalu banyak permintaan atur ulang kata sandi. Coba lagi nanti.');
        }

        RateLimiter::hit($kunci, 3600);

        $pendaftaran->loadMissing('anak.user');

        /** @var User $target */
        $target = $pendaftaran->anak->user;

        try {
            $kataSandi = $this->akunService->aturUlang($request->user(), $target);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['akun' => $galat->getMessage()]);
        }

        $request->session()->put('kartu_akun', [
            'nama' => $target->nama,
            'identitas' => NomorHp::tampil($target->no_hp) ?: (string) $target->email,
            'kata_sandi' => $kataSandi,
            'sekolah' => $request->user()->sekolah?->nama ?? config('app.name'),
            'lanjut' => false,
        ]);

        return redirect()
            ->route('panitia.akun.kartu')
            ->with('sukses', 'Kata sandi sementara dibuat. Tampilkan dan serahkan kepada walisantri sekarang.');
    }

    /**
     * Pindahkan anak ke akun walisantri lain (nomor HP persis).
     */
    public function pindahAnak(PindahAnakRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('kelola', $pendaftaran), 404);

        $tujuan = $this->akunService->cari($request->user(), (string) $request->input('no_hp'));

        if (! $tujuan) {
            return back()->withErrors([
                'no_hp' => 'Akun tidak ditemukan. Buat akun baru, atau minta walisantri masuk dan mendaftar sendiri.',
            ]);
        }

        try {
            $this->akunService->pindahAnak($request->user(), $pendaftaran->anak, $tujuan);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['no_hp' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.pendaftar.index')
            ->with('sukses', 'Anak dipindahkan ke akun '.$tujuan->nama.'.');
    }
}
