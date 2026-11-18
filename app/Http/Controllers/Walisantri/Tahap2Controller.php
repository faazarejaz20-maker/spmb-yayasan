<?php

namespace App\Http\Controllers\Walisantri;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tahap2Request;
use App\Models\JawabanTambahan;
use App\Models\Pendaftaran;
use App\Services\AturanIsian;
use App\Support\Catat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class Tahap2Controller extends Controller
{
    public function __construct(private readonly AturanIsian $aturanIsian) {}

    public function form(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->pastikanMilik($request, $pendaftaran);
        abort_unless(Gate::forUser($request->user())->allows('isiTahap2', $pendaftaran), 404);

        $pendaftaran->load(['anak', 'sekolah']);

        $isian = $this->aturanIsian->isianTampil($pendaftaran->sekolah_id, 'tahap2');

        $nilai = JawabanTambahan::query()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->where('bagian', 'tahap2')
            ->pluck('nilai', 'kunci')
            ->all();

        return view('walisantri.pendaftaran.tahap-2', [
            'pendaftaranUuid' => $pendaftaran->uuid,
            'namaAnak' => $pendaftaran->anak->nama_lengkap,
            'isian' => $isian,
            'nilai' => $nilai,
            'aksiSimpan' => route('walisantri.pendaftaran.tahap2.simpan', $pendaftaran->uuid),
        ]);
    }

    public function simpan(Tahap2Request $request, Pendaftaran $pendaftaran): RedirectResponse
    {
        $this->pastikanMilik($request, $pendaftaran);
        abort_unless(Gate::forUser($request->user())->allows('isiTahap2', $pendaftaran), 404);

        $isian = $this->aturanIsian->isianTampil($pendaftaran->sekolah_id, 'tahap2');
        $data = array_intersect_key($request->validated(), $isian);

        foreach ($data as $kunci => $nilai) {
            JawabanTambahan::updateOrCreate(
                [
                    'pendaftaran_id' => $pendaftaran->id,
                    'bagian' => 'tahap2',
                    'kunci' => $kunci,
                ],
                ['nilai' => $nilai === null ? null : (string) $nilai],
            );
        }

        Catat::aksi('simpan_tahap_2', $pendaftaran, array_keys($data), $pendaftaran->sekolah_id);

        return redirect()
            ->route('walisantri.pendaftaran.tahap2', $pendaftaran->uuid)
            ->with('sukses', 'Formulir Tahap 2 tersimpan.');
    }

    private function pastikanMilik(Request $request, Pendaftaran $pendaftaran): void
    {
        abort_unless(
            $pendaftaran->anak()->where('user_id', $request->user()->id)->exists(),
            404
        );
    }
}
