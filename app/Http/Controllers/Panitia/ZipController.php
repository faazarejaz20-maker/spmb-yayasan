<?php

namespace App\Http\Controllers\Panitia;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Models\Pendaftaran;
use App\Services\ZipBerkasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ZipController extends Controller
{
    public function __construct(private readonly ZipBerkasService $zip) {}

    public function unduh(Request $request, Pendaftaran $pendaftaran): BinaryFileResponse|RedirectResponse
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        // Policy + global scope: sekolah lain → 404.
        abort_unless(Gate::forUser($request->user())->allows('lihat', $pendaftaran), 404);

        try {
            return $this->zip->unduh($request->user(), $pendaftaran);
        } catch (AturanBisnisException $galat) {
            return redirect()
                ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'berkas'])
                ->withErrors(['zip' => $galat->getMessage()]);
        }
    }
}
