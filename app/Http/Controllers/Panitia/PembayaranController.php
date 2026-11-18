<?php

namespace App\Http\Controllers\Panitia;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PembayaranTunaiRequest;
use App\Http\Requests\TolakPembayaranRequest;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Services\PembayaranService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PembayaranController extends Controller
{
    public function __construct(private readonly PembayaranService $pembayaranService) {}

    /**
     * Antrean bukti transfer menunggu untuk sekolah panitia.
     */
    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('panitia.pembayaran.index', [
            'antrean' => $this->pembayaranService->antrean((int) $pengguna->sekolah_id),
            'sekolah' => $pengguna->sekolah,
        ]);
    }

    public function tampil(Request $request, Pembayaran $pembayaran): StreamedResponse
    {
        $pembayaran->loadMissing('pendaftaran');
        abort_unless($pembayaran->pendaftaran?->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('lihat', $pembayaran), 404);

        return $this->pembayaranService->sajikanBukti($pembayaran);
    }

    public function setujui(Request $request, Pembayaran $pembayaran): RedirectResponse
    {
        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:1000'],
            'dari' => ['nullable', 'in:antrean,detail'],
        ]);

        abort_unless(Gate::forUser($request->user())->allows('proses', $pembayaran), 404);

        try {
            $this->pembayaranService->setujui($request->user(), $pembayaran, $data['catatan'] ?? null);
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['pembayaran' => $galat->getMessage()]);
        }

        return $this->kembali($pembayaran, $data['dari'] ?? 'detail', 'Pembayaran disetujui.');
    }

    public function tolak(TolakPembayaranRequest $request, Pembayaran $pembayaran): RedirectResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('proses', $pembayaran), 404);

        try {
            $this->pembayaranService->tolak($request->user(), $pembayaran, (string) $request->input('catatan'));
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['pembayaran' => $galat->getMessage()]);
        }

        return $this->kembali($pembayaran, (string) $request->input('dari', 'detail'), 'Pembayaran ditolak.');
    }

    /**
     * Catat pembayaran tunai yang diterima panitia di sekolah.
     */
    public function tunai(PembayaranTunaiRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('kelola', $pendaftaran), 404);

        try {
            $this->pembayaranService->catatTunai(
                $request->user(),
                $pendaftaran,
                (int) $request->input('nominal'),
                (string) $request->input('dibayar_pada'),
                $request->filled('rincian_biaya_id') ? $request->integer('rincian_biaya_id') : null,
                $request->input('catatan'),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withInput()->withErrors(['pembayaran' => $galat->getMessage()]);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', ['pendaftaran' => $pendaftaran->uuid, 'tab' => 'pembayaran'])
            ->with('sukses', 'Pembayaran tunai dicatat.');
    }

    private function kembali(Pembayaran $pembayaran, string $dari, string $pesan): RedirectResponse
    {
        $pembayaran->loadMissing('pendaftaran');

        if ($dari === 'antrean') {
            return redirect()->route('panitia.pembayaran.index')->with('sukses', $pesan);
        }

        return redirect()
            ->route('panitia.pendaftar.detail', [
                'pendaftaran' => $pembayaran->pendaftaran->uuid,
                'tab' => 'pembayaran',
            ])
            ->with('sukses', $pesan);
    }
}
