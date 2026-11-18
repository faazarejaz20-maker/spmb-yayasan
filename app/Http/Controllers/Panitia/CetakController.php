<?php

namespace App\Http\Controllers\Panitia;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Services\CetakService;
use App\Support\Catat;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman cetak untuk panitia dan admin sekolah.
 *
 * Semua halaman diperiksa Policy, memakai `layouts.cetak`, dan dikirim dengan
 * `Cache-Control: no-store`.
 */
class CetakController extends Controller
{
    public function __construct(private readonly CetakService $cetak) {}

    public function formulir(Request $request, Pendaftaran $pendaftaran): Response
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('lihat', $pendaftaran), 404);

        $data = $this->cetak->dataFormulir($pendaftaran);

        Catat::aksi('cetak_formulir', $pendaftaran, [], $pendaftaran->sekolah_id);

        return $this->tampil('panitia.cetak.formulir', $data);
    }

    public function bukti(Request $request, Pendaftaran $pendaftaran): Response
    {
        abort_unless($pendaftaran->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('lihat', $pendaftaran), 404);

        $data = $this->cetak->dataBukti($pendaftaran);

        Catat::aksi('cetak_bukti', $pendaftaran, [], $pendaftaran->sekolah_id);

        return $this->tampil('panitia.cetak.bukti', $data);
    }

    public function kuitansi(Request $request, Pembayaran $pembayaran): Response
    {
        $pembayaran->loadMissing('pendaftaran');

        abort_unless($pembayaran->pendaftaran?->sudahDikirim(), 404);
        abort_unless(Gate::forUser($request->user())->allows('lihat', $pembayaran), 404);

        try {
            $data = $this->cetak->dataKuitansi($pembayaran);
        } catch (AturanBisnisException $galat) {
            abort(403, $galat->getMessage());
        }

        Catat::aksi('cetak_kuitansi', $pembayaran, [
            'nomor_kuitansi' => $data['nomorKuitansi'],
        ], $pembayaran->pendaftaran->sekolah_id);

        return $this->tampil('panitia.cetak.kuitansi', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tampil(string $tampilan, array $data): Response
    {
        return response()
            ->view($tampilan, $data)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
