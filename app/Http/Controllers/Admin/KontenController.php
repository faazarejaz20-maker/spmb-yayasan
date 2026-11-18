<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AturanBisnisException;
use App\Exceptions\BerkasTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\KontenItemRequest;
use App\Http\Requests\KontenTeksRequest;
use App\Models\KontenSekolah;
use App\Policies\PengaturanSekolahPolicy;
use App\Services\KontenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KontenController extends Controller
{
    public function __construct(private readonly KontenService $service) {}

    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('admin.konten.index', [
            'sekolah' => $pengguna->sekolah,
            'bagian' => $this->service->daftar((int) $pengguna->sekolah_id, 'sekolah'),
            'rute' => [
                'teks' => 'admin.konten.teks',
                'tambah' => 'admin.konten.tambah',
                'ubah' => 'admin.konten.ubah',
                'hapus' => 'admin.konten.hapus',
            ],
        ]);
    }

    public function simpanTeks(KontenTeksRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        try {
            $this->service->simpanTeks(
                $pengguna,
                (int) $pengguna->sekolah_id,
                (string) $request->validated('bagian'),
                $request->validated('isi'),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['konten' => $galat->getMessage()]);
        }

        return back()->with('sukses', 'Teks konten disimpan.');
    }

    public function tambah(KontenItemRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        try {
            $this->service->tambah(
                $pengguna,
                (int) $pengguna->sekolah_id,
                (string) $request->validated('bagian'),
                $request->safe()->only(['judul', 'isi', 'urutan']),
                $request->file('gambar'),
            );
        } catch (AturanBisnisException|BerkasTidakValidException $galat) {
            return back()->withInput()->withErrors(['konten' => $galat->getMessage()]);
        }

        return back()->with('sukses', 'Baris konten ditambahkan.');
    }

    public function ubah(KontenItemRequest $request, KontenSekolah $konten): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        try {
            $this->service->ubah(
                $pengguna,
                $konten,
                $request->safe()->only(['judul', 'isi', 'urutan', 'hapus_gambar']),
                $request->file('gambar'),
            );
        } catch (AturanBisnisException|BerkasTidakValidException $galat) {
            return back()->withInput()->withErrors(['konten' => $galat->getMessage()]);
        }

        return back()->with('sukses', 'Baris konten diperbarui.');
    }

    public function hapus(Request $request, KontenSekolah $konten): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        $this->service->hapus($pengguna, $konten);

        return back()->with('sukses', 'Baris konten dihapus.');
    }

    private function pastikanPengaturan(\App\Models\User $pengguna): void
    {
        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }
}
