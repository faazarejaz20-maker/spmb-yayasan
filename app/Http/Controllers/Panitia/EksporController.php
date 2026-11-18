<?php

namespace App\Http\Controllers\Panitia;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EksporRequest;
use App\Models\Gelombang;
use App\Services\EksporService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman pilihan kolom dan penyaring ekspor, lalu unduhan xlsx/csv.
 *
 * POST dipakai untuk menghasilkan unduhan agar parameter tidak tersimpan di
 * riwayat peramban atau log server.
 */
class EksporController extends Controller
{
    public function __construct(private readonly EksporService $ekspor) {}

    public function index(Request $request): View
    {
        $pengguna = $request->user();

        $gelombang = Gelombang::query()
            ->where('sekolah_id', $pengguna->sekolah_id)
            ->orderBy('id')
            ->get();

        return view('panitia.ekspor.index', [
            'sekolah' => $pengguna->sekolah,
            'gelombang' => $gelombang,
            'grup' => config('ekspor.grup'),
            'sensitif' => config('ekspor.sensitif'),
            'konfirmasiSensitif' => config('ekspor.konfirmasi_sensitif'),
            'saring' => [
                'cari' => (string) $request->query('cari', ''),
                'gelombang' => (string) $request->query('gelombang', ''),
                'status' => (string) $request->query('status', ''),
                'jenis' => (string) $request->query('jenis', ''),
                'pembayaran' => (string) $request->query('pembayaran', ''),
                'program' => (string) $request->query('program', ''),
                'kelengkapan' => (string) $request->query('kelengkapan', ''),
            ],
        ]);
    }

    public function unduh(EksporRequest $request): BinaryFileResponse|RedirectResponse
    {
        try {
            return $this->ekspor->pendaftar(
                $request->user(),
                $request->saring(),
                $request->kolom(),
                $request->formatEkspor(),
                $request->ikutSensitif(),
                $request->konfirmasiSensitif(),
            );
        } catch (AturanBisnisException $galat) {
            return back()->withErrors(['ekspor' => $galat->getMessage()])->withInput();
        }
    }
}
