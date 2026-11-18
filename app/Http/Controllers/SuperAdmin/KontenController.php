<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exceptions\AturanBisnisException;
use App\Exceptions\BerkasTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\KontenItemRequest;
use App\Http\Requests\KontenTeksRequest;
use App\Models\KontenSekolah;
use App\Policies\SuperAdminPolicy;
use App\Services\KontenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KontenController extends Controller
{
    public function __construct(private readonly KontenService $service) {}

    public function index(Request $request): View
    {
        $this->pastikanSuperAdmin($request);

        return view('super-admin.konten.index', [
            'bagian' => $this->service->daftar(null, 'yayasan'),
            'rute' => [
                'teks' => 'super.konten.teks',
                'tambah' => 'super.konten.tambah',
                'ubah' => 'super.konten.ubah',
                'hapus' => 'super.konten.hapus',
            ],
        ]);
    }

    public function simpanTeks(KontenTeksRequest $request): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        try {
            $this->service->simpanTeks(
                $request->user(),
                null,
                (string) $request->validated('bagian'),
                $request->validated('isi'),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['konten' => $galat->getMessage()]);
        }

        return back()->with('sukses', 'Teks konten yayasan disimpan.');
    }

    public function tambah(KontenItemRequest $request): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        try {
            $this->service->tambah(
                $request->user(),
                null,
                (string) $request->validated('bagian'),
                $request->safe()->only(['judul', 'isi', 'urutan']),
                $request->file('gambar'),
            );
        } catch (AturanBisnisException|BerkasTidakValidException $galat) {
            return back()->withInput()->withErrors(['konten' => $galat->getMessage()]);
        }

        return back()->with('sukses', 'Baris konten yayasan ditambahkan.');
    }

    public function ubah(KontenItemRequest $request, KontenSekolah $konten): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        try {
            $this->service->ubah(
                $request->user(),
                $konten,
                $request->safe()->only(['judul', 'isi', 'urutan', 'hapus_gambar']),
                $request->file('gambar'),
            );
        } catch (AturanBisnisException|BerkasTidakValidException $galat) {
            return back()->withInput()->withErrors(['konten' => $galat->getMessage()]);
        }

        return back()->with('sukses', 'Baris konten yayasan diperbarui.');
    }

    public function hapus(Request $request, KontenSekolah $konten): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        $this->service->hapus($request->user(), $konten);

        return back()->with('sukses', 'Baris konten yayasan dihapus.');
    }

    private function pastikanSuperAdmin(Request $request): void
    {
        abort_unless(app(SuperAdminPolicy::class)->kelola($request->user()), 403);
    }
}
