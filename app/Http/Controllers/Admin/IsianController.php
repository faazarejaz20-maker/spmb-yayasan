<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PengaturanIsianRequest;
use App\Models\PengaturanIsian;
use App\Policies\PengaturanSekolahPolicy;
use App\Support\Catat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IsianController extends Controller
{
    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('admin.isian.index', [
            'sekolah' => $pengguna->sekolah,
            'katalog' => (array) config('isian'),
            'pengaturan' => PengaturanIsian::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->get()
                ->keyBy('kunci_isian'),
        ]);
    }

    public function simpan(PengaturanIsianRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        $isian = $request->isianTersaring();
        $inti = [];

        foreach ((array) config('isian') as $daftar) {
            foreach ($daftar as $kunci => $definisi) {
                if ((bool) ($definisi['inti'] ?? false)) {
                    $inti[$kunci] = true;
                }
            }
        }

        $perubahan = [];

        DB::transaction(function () use ($pengguna, $isian, $inti, &$perubahan) {
            foreach ($isian as $kunci => $nilai) {
                $tampil = isset($inti[$kunci]) ? true : $nilai['tampil'];
                $wajib = isset($inti[$kunci]) ? true : $nilai['wajib'];

                $baris = PengaturanIsian::query()->firstOrNew([
                    'sekolah_id' => $pengguna->sekolah_id,
                    'kunci_isian' => $kunci,
                ]);

                $lama = $baris->exists
                    ? ['tampil' => (bool) $baris->tampil, 'wajib' => (bool) $baris->wajib]
                    : null;

                if ($lama !== null && $lama['tampil'] === $tampil && $lama['wajib'] === $wajib) {
                    continue;
                }

                $baris->fill(['tampil' => $tampil, 'wajib' => $wajib])->save();

                $perubahan[$kunci] = [
                    'lama' => $lama,
                    'baru' => ['tampil' => $tampil, 'wajib' => $wajib],
                ];
            }
        });

        if ($perubahan !== []) {
            Catat::aksi('ubah_pengaturan_isian', null, [
                'jumlah' => count($perubahan),
                'isian' => array_keys($perubahan),
            ], $pengguna->sekolah_id);
        }

        return redirect()->route('admin.isian.index')->with('sukses', 'Pengaturan isian disimpan.');
    }

    private function pastikanPengaturan(\App\Models\User $pengguna): void
    {
        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }
}
