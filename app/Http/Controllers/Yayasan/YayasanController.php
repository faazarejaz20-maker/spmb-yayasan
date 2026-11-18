<?php

namespace App\Http\Controllers\Yayasan;

use App\Http\Controllers\Controller;
use App\Services\EksporService;
use App\Services\RekapService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class YayasanController extends Controller
{
    public function __construct(private readonly RekapService $service) {}

    public function beranda(): View
    {
        return view('yayasan.beranda', [
            'rekap' => $this->service->untukYayasan(),
        ]);
    }

    public function rekap(): View
    {
        return view('yayasan.rekap', [
            'rekap' => $this->service->untukYayasan(),
        ]);
    }

    /**
     * Unduh rekap agregat (K-18). Hanya angka, tanpa data per pendaftar.
     */
    public function unduh(Request $request, EksporService $ekspor): BinaryFileResponse
    {
        return $ekspor->rekapYayasan($request->user());
    }
}
