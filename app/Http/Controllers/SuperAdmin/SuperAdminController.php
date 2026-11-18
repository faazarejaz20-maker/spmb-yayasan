<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\Pendaftaran;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function beranda(): View
    {
        $sekolah = Sekolah::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Sekolah $s) => [
                'sekolah' => $s,
                'admin' => User::query()
                    ->where('peran', PeranPengguna::Admin)
                    ->where('sekolah_id', $s->id)
                    ->where('aktif', true)
                    ->count(),
                'pendaftar' => Pendaftaran::query()
                    ->tanpaSekolah()
                    ->where('sekolah_id', $s->id)
                    ->whereNotNull('dikirim_pada')
                    ->count(),
            ]);

        $akun = [];

        foreach ([PeranPengguna::Admin, PeranPengguna::Panitia, PeranPengguna::Yayasan, PeranPengguna::SuperAdmin] as $peran) {
            $akun[$peran->value] = User::query()
                ->where('peran', $peran)
                ->where('aktif', true)
                ->count();
        }

        return view('super-admin.beranda', [
            'sekolah' => $sekolah,
            'jumlahSekolah' => $sekolah->count(),
            'akun' => $akun,
        ]);
    }
}
