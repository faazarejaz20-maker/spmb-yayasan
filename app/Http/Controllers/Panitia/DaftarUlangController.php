<?php

namespace App\Http\Controllers\Panitia;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DaftarUlangRequest;
use App\Http\Requests\MengundurkanDiriRequest;
use App\Models\Pendaftaran;
use App\Services\DaftarUlangService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DaftarUlangController extends Controller
{
    public function __construct(private readonly DaftarUlangService $daftarUlang) {}

    public function simpan(DaftarUlangRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('kelola', $pendaftaran), 404);

        try {
            $this->daftarUlang->catat($request->user(), $pendaftaran, [
                'hadir' => $request->boolean('hadir'),
                'kekurangan' => $request->input('kekurangan'),
                'catatan_wawancara' => $request->input('catatan_wawancara'),
                'selesai' => $request->boolean('selesai'),
                'catatan' => $request->input('catatan'),
            ]);
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['daftar_ulang' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'daftar-ulang'])
            ->with('sukses', 'Daftar ulang disimpan.');
    }

    public function mengundurkanDiri(MengundurkanDiriRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('kelola', $pendaftaran), 404);

        try {
            $this->daftarUlang->mengundurkanDiri($request->user(), $pendaftaran, $request->input('catatan'));
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['mengundurkan_diri' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'daftar-ulang'])
            ->with('sukses', 'Pendaftar dicatat mengundurkan diri. Kuota dibebaskan.');
    }
}
