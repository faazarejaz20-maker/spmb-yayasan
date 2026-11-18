<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UmumkanHasilRequest;
use App\Models\Gelombang;
use App\Models\Pengumuman;
use App\Policies\PengaturanSekolahPolicy;
use App\Services\PengumumanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PengumumanController extends Controller
{
    public function __construct(private readonly PengumumanService $service) {}

    public function index(Request $request): View
    {
        $pengguna = $request->user();

        $gelombang = Gelombang::query()
            ->where('sekolah_id', $pengguna->sekolah_id)
            ->orderBy('id')
            ->get();

        $pengumuman = Pengumuman::query()
            ->tanpaSekolah()
            ->where('sekolah_id', $pengguna->sekolah_id)
            ->get()
            ->keyBy('gelombang_id');

        $ringkasan = [];

        foreach ($gelombang as $baris) {
            $ringkasan[$baris->id] = $this->service->ringkasan($baris);
        }

        return view('admin.pengumuman.index', [
            'sekolah' => $pengguna->sekolah,
            'gelombang' => $gelombang,
            'pengumuman' => $pengumuman,
            'ringkasan' => $ringkasan,
        ]);
    }

    public function konfirmasi(Request $request, Gelombang $gelombang): View|RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        if ($this->service->sudahDiumumkanGelombang((int) $pengguna->sekolah_id, $gelombang->id)) {
            return redirect()
                ->route('admin.pengumuman.index')
                ->with('pesan', 'Hasil gelombang ini sudah diumumkan.');
        }

        return view('admin.pengumuman.konfirmasi', [
            'sekolah' => $pengguna->sekolah,
            'gelombang' => $gelombang,
            'ringkasan' => $this->service->ringkasan($gelombang),
            'teksPesan' => $this->service->teksPesan($gelombang),
        ]);
    }

    public function umumkan(UmumkanHasilRequest $request, Gelombang $gelombang): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        try {
            $this->service->umumkan($pengguna, $gelombang);
        } catch (AturanBisnisException $galat) {
            return redirect()
                ->route('admin.pengumuman.index')
                ->with('pesan', $galat->getMessage());
        }

        return redirect()
            ->route('admin.pengumuman.index')
            ->with('sukses', 'Hasil gelombang '.$gelombang->nama.' sudah diumumkan dan langsung terlihat oleh walisantri.');
    }

    private function pastikanPengaturan(\App\Models\User $pengguna): void
    {
        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }
}
