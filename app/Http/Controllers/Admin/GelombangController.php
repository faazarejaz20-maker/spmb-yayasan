<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GelombangRequest;
use App\Models\Gelombang;
use App\Models\TahunAjaran;
use App\Policies\PengaturanSekolahPolicy;
use App\Services\GelombangService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GelombangController extends Controller
{
    public function __construct(private readonly GelombangService $service) {}

    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('admin.gelombang.index', [
            'sekolah' => $pengguna->sekolah,
            'tahun' => TahunAjaran::query()->where('aktif', true)->first(),
            'gelombang' => Gelombang::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function simpan(GelombangRequest $request): RedirectResponse
    {
        $this->pastikanPengaturan($request);

        try {
            $this->service->simpan($request->user(), $request->validated());
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['gelombang' => $galat->getMessage()]);
        }

        return redirect()->route('admin.gelombang.index')->with('sukses', 'Gelombang disimpan.');
    }

    public function ubah(GelombangRequest $request, Gelombang $gelombang): RedirectResponse
    {
        $this->pastikanPengaturan($request);

        try {
            $this->service->simpan($request->user(), $request->validated(), $gelombang);
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['gelombang' => $galat->getMessage()]);
        }

        return redirect()->route('admin.gelombang.index')->with('sukses', 'Perubahan gelombang disimpan.');
    }

    public function hapus(Request $request, Gelombang $gelombang): RedirectResponse
    {
        $this->pastikanPengaturan($request);

        try {
            $this->service->hapus($request->user(), $gelombang);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['gelombang' => $galat->getMessage()]);
        }

        return redirect()->route('admin.gelombang.index')->with('sukses', 'Gelombang dihapus.');
    }

    private function pastikanPengaturan(Request $request): void
    {
        $pengguna = $request->user();

        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }
}
