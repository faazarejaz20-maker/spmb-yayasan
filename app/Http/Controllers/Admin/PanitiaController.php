<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PeranPengguna;
use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AkunStafRequest;
use App\Models\User;
use App\Policies\PengaturanSekolahPolicy;
use App\Services\AkunStafService;
use App\Support\NomorHp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PanitiaController extends Controller
{
    public function __construct(private readonly AkunStafService $service) {}

    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('admin.panitia.index', [
            'sekolah' => $pengguna->sekolah,
            'daftar' => User::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->where('peran', PeranPengguna::Panitia)
                ->orderBy('nama')
                ->get(),
        ]);
    }

    public function simpan(AkunStafRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        try {
            $hasil = $this->service->buat(
                $pengguna,
                PeranPengguna::Panitia,
                (int) $pengguna->sekolah_id,
                (string) $request->validated('nama'),
                (string) $request->validated('no_hp'),
                $request->validated('email'),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['akun' => $galat->getMessage()]);
        }

        $this->simpanKartu($request, $hasil['user'], $hasil['kata_sandi']);

        return redirect()
            ->route('kartu-akun.staf')
            ->with('sukses', 'Akun panitia dibuat. Kartu akun hanya tampil sekali.');
    }

    public function nonaktifkan(Request $request, User $user): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $target = $this->cariPanitia($pengguna, $user);

        try {
            $this->service->nonaktifkan($pengguna, $target);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['akun' => $galat->getMessage()]);
        }

        return redirect()->route('admin.panitia.index')->with('sukses', 'Akun panitia dinonaktifkan dan sesinya dihapus.');
    }

    public function aktifkan(Request $request, User $user): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $target = $this->cariPanitia($pengguna, $user);

        try {
            $this->service->aktifkan($pengguna, $target);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['akun' => $galat->getMessage()]);
        }

        return redirect()->route('admin.panitia.index')->with('sukses', 'Akun panitia diaktifkan kembali.');
    }

    public function aturUlang(Request $request, User $user): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $target = $this->cariPanitia($pengguna, $user);

        $kunci = 'atur-ulang-staf:'.$pengguna->id;

        if (RateLimiter::tooManyAttempts($kunci, 20)) {
            abort(429, 'Terlalu banyak permintaan atur ulang kata sandi. Coba lagi nanti.');
        }

        RateLimiter::hit($kunci, 3600);

        try {
            $kataSandi = $this->service->aturUlang($pengguna, $target);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['akun' => $galat->getMessage()]);
        }

        $this->simpanKartu($request, $target, $kataSandi);

        return redirect()
            ->route('kartu-akun.staf')
            ->with('sukses', 'Kata sandi sementara dibuat. Tampilkan dan serahkan sekarang.');
    }

    private function cariPanitia(User $pengguna, User $target): User
    {
        abort_unless(
            (int) $target->sekolah_id === (int) $pengguna->sekolah_id
            && $target->peran === PeranPengguna::Panitia,
            404,
        );

        return $target;
    }

    private function pastikanPengaturan(User $pengguna): void
    {
        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }

    private function simpanKartu(Request $request, User $target, string $kataSandi): void
    {
        $request->session()->put('kartu_akun', [
            'nama' => $target->nama,
            'identitas' => NomorHp::tampil($target->no_hp) ?: (string) $target->email,
            'kata_sandi' => $kataSandi,
            'sekolah' => $request->user()->sekolah?->nama ?? config('app.name'),
            'peran' => $target->peran?->label(),
            'lanjut' => false,
            'kembali' => 'admin.panitia.index',
        ]);
    }
}
