<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\PeranPengguna;
use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AkunAdminRequest;
use App\Models\Sekolah;
use App\Models\User;
use App\Policies\SuperAdminPolicy;
use App\Services\AkunStafService;
use App\Support\NomorHp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private readonly AkunStafService $service) {}

    public function index(): View
    {
        return view('super-admin.admin.index', [
            'daftar' => User::query()
                ->where('peran', PeranPengguna::Admin)
                ->with('sekolah')
                ->orderBy('nama')
                ->get(),
            'sekolah' => Sekolah::query()->orderBy('nama')->get(),
        ]);
    }

    public function simpan(AkunAdminRequest $request): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        try {
            $hasil = $this->service->buat(
                $request->user(),
                PeranPengguna::Admin,
                (int) $request->validated('sekolah_id'),
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
            ->with('sukses', 'Akun admin sekolah dibuat. Kartu akun hanya tampil sekali.');
    }

    public function nonaktifkan(Request $request, User $user): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);
        $target = $this->cariAdmin($user);

        try {
            $this->service->nonaktifkan($request->user(), $target);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['akun' => $galat->getMessage()]);
        }

        return redirect()->route('super.admin.index')->with('sukses', 'Akun admin dinonaktifkan dan sesinya dihapus.');
    }

    public function aktifkan(Request $request, User $user): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);
        $target = $this->cariAdmin($user);

        try {
            $this->service->aktifkan($request->user(), $target);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['akun' => $galat->getMessage()]);
        }

        return redirect()->route('super.admin.index')->with('sukses', 'Akun admin diaktifkan kembali.');
    }

    public function aturUlang(Request $request, User $user): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);
        $target = $this->cariAdmin($user);

        $kunci = 'atur-ulang-staf:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($kunci, 20)) {
            abort(429, 'Terlalu banyak permintaan atur ulang kata sandi. Coba lagi nanti.');
        }

        RateLimiter::hit($kunci, 3600);

        try {
            $kataSandi = $this->service->aturUlang($request->user(), $target);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['akun' => $galat->getMessage()]);
        }

        $this->simpanKartu($request, $target, $kataSandi);

        return redirect()
            ->route('kartu-akun.staf')
            ->with('sukses', 'Kata sandi sementara dibuat. Tampilkan dan serahkan sekarang.');
    }

    private function cariAdmin(User $target): User
    {
        abort_unless($target->peran === PeranPengguna::Admin, 404);

        return $target;
    }

    private function pastikanSuperAdmin(Request $request): void
    {
        abort_unless(app(SuperAdminPolicy::class)->kelola($request->user()), 403);
    }

    private function simpanKartu(Request $request, User $target, string $kataSandi): void
    {
        $request->session()->put('kartu_akun', [
            'nama' => $target->nama,
            'identitas' => NomorHp::tampil($target->no_hp) ?: (string) $target->email,
            'kata_sandi' => $kataSandi,
            'sekolah' => $target->sekolah?->nama ?? 'Yayasan',
            'peran' => $target->peran?->label(),
            'lanjut' => false,
            'kembali' => 'super.admin.index',
        ]);
    }
}
