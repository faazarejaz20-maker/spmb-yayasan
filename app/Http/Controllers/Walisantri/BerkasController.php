<?php

namespace App\Http\Controllers\Walisantri;

use App\Exceptions\BerkasTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UnggahBerkasRequest;
use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Models\Pendaftaran;
use App\Services\BerkasService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BerkasController extends Controller
{
    public function __construct(private readonly BerkasService $berkasService) {}

    public function index(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanMilik($request, $pendaftaran);
        $pendaftaran->load(['anak', 'sekolah']);

        $jenis = JenisBerkas::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $berkas = Berkas::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->get()
            ->keyBy('jenis_berkas_id');

        return view('walisantri.pendaftaran.berkas', [
            'pendaftaranUuid' => $pendaftaran->uuid,
            'langkah' => 6,
            'jenis' => $jenis,
            'berkas' => $berkas,
            'bolehDiubah' => $pendaftaran->bolehDiubah(),
            'namaAnak' => $pendaftaran->anak->nama_lengkap,
        ]);
    }

    public function simpan(UnggahBerkasRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

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
            ->route('walisantri.pendaftaran.berkas.index', $pendaftaran->uuid)
            ->with('sukses', 'Berkas '.$jenis->nama.' berhasil diunggah.');
    }

    public function hapus(Request $request, Pendaftaran $pendaftaran, Berkas $berkas): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        abort_if((int) $berkas->pendaftaran_id !== (int) $pendaftaran->id, 404);
        abort_unless(Gate::forUser($request->user())->allows('hapus', $berkas), 404);

        try {
            $this->berkasService->hapus($request->user(), $berkas);
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()
            ->route('walisantri.pendaftaran.berkas.index', $pendaftaran->uuid)
            ->with('sukses', 'Berkas dihapus.');
    }

    public function tampil(Request $request, Berkas $berkas): StreamedResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('lihat', $berkas), 404);

        return $this->berkasService->sajikan($berkas);
    }

    private function pastikanMilik(Request $request, Pendaftaran $pendaftaran): void
    {
        abort_unless(
            $pendaftaran->anak()->where('user_id', $request->user()->id)->exists(),
            404
        );
    }
}
