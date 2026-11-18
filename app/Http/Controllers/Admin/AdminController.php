<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusHasil;
use App\Http\Controllers\Controller;
use App\Models\Gelombang;
use App\Models\Pendaftaran;
use App\Models\Pengumuman;
use App\Models\User;
use App\Services\KuotaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private readonly KuotaService $kuota) {}

    public function beranda(Request $request): View
    {
        $pengguna = $request->user();

        $dasar = Pendaftaran::query()->terkirim();

        $status = [];

        foreach (StatusHasil::cases() as $kasus) {
            $status[$kasus->value] = (clone $dasar)->where('status_hasil', $kasus)->count();
        }

        $gelombang = Gelombang::query()
            ->where('sekolah_id', $pengguna->sekolah_id)
            ->orderBy('id')
            ->get()
            ->map(fn (Gelombang $baris) => [
                'gelombang' => $baris,
                'terisi' => $this->kuota->terisi($baris),
                'sisa' => $this->kuota->sisa($baris),
                'diumumkan' => Pengumuman::query()
                    ->tanpaSekolah()
                    ->where('gelombang_id', $baris->id)
                    ->exists(),
            ]);

        return view('admin.beranda', [
            'sekolah' => $pengguna->sekolah,
            'jumlah' => [
                'total' => (clone $dasar)->count(),
                'status' => $status,
                'panitia' => User::query()
                    ->where('peran', 'panitia')
                    ->where('sekolah_id', $pengguna->sekolah_id)
                    ->where('aktif', true)
                    ->count(),
                'tanpa_admin' => false,
            ],
            'gelombang' => $gelombang,
        ]);
    }
}
