<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogFilterRequest;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(LogFilterRequest $request): View
    {
        $pengguna = $request->user();
        $data = $request->validated();

        $dasar = LogAktivitas::query()
            ->tanpaSekolah()
            ->where('sekolah_id', $pengguna->sekolah_id);

        $log = (clone $dasar)
            ->with('user')
            ->when(filled($data['dari'] ?? null), fn ($q) => $q->whereDate('dibuat_pada', '>=', $data['dari']))
            ->when(filled($data['sampai'] ?? null), fn ($q) => $q->whereDate('dibuat_pada', '<=', $data['sampai']))
            ->when(filled($data['pelaku'] ?? null), fn ($q) => $q->where('user_id', (int) $data['pelaku']))
            ->when(filled($data['aksi'] ?? null), fn ($q) => $q->where('aksi', $data['aksi']))
            ->orderByDesc('dibuat_pada')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $daftarAksi = (clone $dasar)->distinct()->orderBy('aksi')->pluck('aksi');

        $pelaku = User::query()
            ->whereIn('id', (clone $dasar)->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('nama')
            // `sekolah_id` ikut diambil karena relasi `sekolah` dimuat bersama
            // model User; tanpa kolom ini pemilihan kolom parsial menghentikan
            // halaman (strict mode).
            ->get(['id', 'nama', 'sekolah_id']);

        return view('admin.log.index', [
            'sekolah' => $pengguna->sekolah,
            'log' => $log,
            'daftarAksi' => $daftarAksi,
            'pelaku' => $pelaku,
            'filter' => $data,
        ]);
    }
}
