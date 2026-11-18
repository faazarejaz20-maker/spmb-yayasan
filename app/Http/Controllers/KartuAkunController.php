<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kartu akun staf (panitia, admin sekolah, yayasan) setelah kata sandi
 * sementara dibuat. Sama seperti kartu walisantri: hanya tampil sekali.
 */
class KartuAkunController extends Controller
{
    public function tampil(Request $request): View|RedirectResponse
    {
        $kartu = $request->session()->get('kartu_akun');

        if (! is_array($kartu) || empty($kartu['kata_sandi'])) {
            return redirect()
                ->route($this->beranda($request))
                ->with('pesan', 'Kartu akun tidak tersedia. Kata sandi sementara hanya ditampilkan sekali.');
        }

        $request->session()->forget('kartu_akun');

        return view('panel.kartu-akun', [
            'kartu' => $kartu,
            'alamatSitus' => url('/'),
        ]);
    }

    private function beranda(Request $request): string
    {
        return match ($request->user()?->peran?->value) {
            'admin' => 'admin.beranda',
            'super_admin' => 'super.beranda',
            default => 'panitia.beranda',
        };
    }
}
