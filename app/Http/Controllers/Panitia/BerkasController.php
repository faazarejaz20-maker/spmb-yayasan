<?php

namespace App\Http\Controllers\Panitia;

use App\Enums\StatusBerkas;
use App\Exceptions\AturanBisnisException;
use App\Exceptions\BerkasTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PeriksaBerkasRequest;
use App\Http\Requests\UnggahBerkasRequest;
use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Models\Pendaftaran;
use App\Services\BerkasService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BerkasController extends Controller
{
    public function __construct(private readonly BerkasService $berkasService) {}

    /**
     * Tandai berkas sudah sesuai atau perlu diperbaiki.
     */
    public function periksa(PeriksaBerkasRequest $request, Pendaftaran $pendaftaran, Berkas $berkas): RedirectResponse
    {
        $this->pastikanTerkirim($pendaftaran);
        abort_unless((int) $berkas->pendaftaran_id === (int) $pendaftaran->id, 404);
        abort_unless(Gate::forUser($request->user())->allows('periksa', $berkas), 404);

        try {
            $this->berkasService->periksa(
                $request->user(),
                $berkas,
                StatusBerkas::from((string) $request->input('status')),
                $request->input('catatan'),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['berkas' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'berkas'])
            ->with('sukses', 'Status berkas diperbarui.');
    }

    /**
     * Panitia mengunggah berkas atas nama walisantri.
     */
    public function simpan(UnggahBerkasRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanTerkirim($pendaftaran);

        $jenis = JenisBerkas::query()
            ->whereKey($request->integer('jenis_berkas_id'))
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->first();

        abort_if(! $jenis, 404);

        try {
            $this->berkasService->unggah($request->user(), $pendaftaran, $jenis, $request->file('berkas'));
        } catch (BerkasTidakValidException $galat) {
            return back()->withErrors(['berkas' => $galat->getMessage()]);
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'berkas'])
            ->with('sukses', 'Berkas '.$jenis->nama.' berhasil diunggah.');
    }

    public function tampil(Request $request, Berkas $berkas): StreamedResponse
    {
        $berkas->loadMissing('pendaftaran');
        abort_unless($berkas->pendaftaran?->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('lihat', $berkas), 404);

        return $this->berkasService->sajikan($berkas);
    }

    private function pastikanTerkirim(Pendaftaran $pendaftaran): void
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser(request()->user())->allows('kelola', $pendaftaran), 404);
    }
}
