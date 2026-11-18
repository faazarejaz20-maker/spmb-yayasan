<?php

namespace App\Http\Controllers\Walisantri;

use App\Exceptions\BerkasTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuktiPembayaranRequest;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\RekeningSekolah;
use App\Services\PembayaranService;
use App\Services\PendaftaranService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PembayaranController extends Controller
{
    public function __construct(
        private readonly PembayaranService $pembayaranService,
        private readonly PendaftaranService $pendaftaranService,
    ) {}

    public function index(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanMilik($request, $pendaftaran);
        abort_unless(Gate::forUser($request->user())->allows('bayar', $pendaftaran), 404);

        $pendaftaran->load(['anak', 'sekolah', 'gelombang']);

        $rekening = RekeningSekolah::query()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->orderBy('id')
            ->get();

        $pembayaran = Pembayaran::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Pembayaran $baris) => [
                'uuid' => $baris->uuid,
                'metode' => $baris->metode->label(),
                'nominal' => $baris->nominal,
                'status' => $baris->status->label(),
                'catatan_penolakan' => $baris->catatan_penolakan,
                'dibayar_pada' => $baris->dibayar_pada,
                'punya_bukti' => filled($baris->bukti_path),
            ])
            ->values();

        return view('walisantri.pendaftaran.pembayaran', [
            'pendaftaranUuid' => $pendaftaran->uuid,
            'namaAnak' => $pendaftaran->anak->nama_lengkap,
            'sekolah' => $pendaftaran->sekolah->nama,
            'rekening' => $rekening,
            'rincian' => $this->pendaftaranService->rincianBiaya($pendaftaran),
            'pembayaran' => $pembayaran,
        ]);
    }

    public function simpan(BuktiPembayaranRequest $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);

        try {
            $this->pembayaranService->unggahBukti(
                $request->user(),
                $pendaftaran,
                (int) $request->input('nominal'),
                (string) $request->input('dibayar_pada'),
                $request->file('bukti'),
            );
        } catch (AuthorizationException) {
            abort(404);
        } catch (BerkasTidakValidException $galat) {
            return back()->withInput()->withErrors(['bukti' => $galat->getMessage()]);
        }

        return redirect()
            ->route('walisantri.pendaftaran.pembayaran.index', $pendaftaran->uuid)
            ->with('sukses', 'Bukti transfer diunggah. Tunggu pemeriksaan panitia.');
    }

    public function bukti(Request $request, Pembayaran $pembayaran): StreamedResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('lihat', $pembayaran), 404);

        return $this->pembayaranService->sajikanBukti($pembayaran);
    }

    private function pastikanMilik(Request $request, Pendaftaran $pendaftaran): void
    {
        abort_unless(
            $pendaftaran->anak()->where('user_id', $request->user()->id)->exists(),
            404
        );
    }
}
