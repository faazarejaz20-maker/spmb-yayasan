<?php

namespace App\Http\Controllers\Panitia;

use App\Enums\StatusHasil;
use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\KeputusanRequest;
use App\Models\Pendaftaran;
use App\Services\StatusHasilService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class KeputusanController extends Controller
{
    public function __construct(private readonly StatusHasilService $statusHasil) {}

    public function simpan(KeputusanRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('kelola', $pendaftaran), 404);

        try {
            $this->statusHasil->ubah(
                $request->user(),
                $pendaftaran,
                StatusHasil::from((string) $request->input('status')),
                $request->input('catatan'),
                $request->boolean('konfirmasi_berkas'),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['keputusan' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid])
            ->with('sukses', 'Keputusan status disimpan.');
    }
}
