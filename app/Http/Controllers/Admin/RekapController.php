<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EksporService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RekapController extends Controller
{
    public function __construct(private readonly \App\Services\RekapService $service) {}

    public function index(Request $request): \Illuminate\View\View
    {
        $pengguna = $request->user();

        return view('admin.rekap.index', [
            'rekap' => $this->service->untukSekolah((int) $pengguna->sekolah_id),
        ]);
    }

    public function unduh(Request $request, EksporService $ekspor): BinaryFileResponse
    {
        return $ekspor->rekapSekolah($request->user());
    }
}
