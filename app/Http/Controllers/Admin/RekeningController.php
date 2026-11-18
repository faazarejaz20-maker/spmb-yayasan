<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RekeningSekolahRequest;
use App\Models\Pembayaran;
use App\Models\RekeningSekolah;
use App\Policies\PengaturanSekolahPolicy;
use App\Support\Catat;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RekeningController extends Controller
{
    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('admin.rekening.index', [
            'sekolah' => $pengguna->sekolah,
            'daftar' => RekeningSekolah::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function simpan(RekeningSekolahRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        $rekening = new RekeningSekolah([
            'bank' => (string) $request->validated('bank'),
            'nomor_rekening' => (string) $request->validated('nomor_rekening'),
            'atas_nama' => (string) $request->validated('atas_nama'),
            'aktif' => $request->boolean('aktif'),
        ]);
        $rekening->forceFill(['sekolah_id' => $pengguna->sekolah_id])->save();

        Catat::aksi('buat_rekening', $rekening, $this->ringkasan($rekening), $pengguna->sekolah_id);

        return redirect()->route('admin.rekening.index')->with('sukses', 'Rekening ditambahkan.');
    }

    public function ubah(RekeningSekolahRequest $request, RekeningSekolah $rekeningSekolah): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $this->pastikanMilik($pengguna, $rekeningSekolah);

        $lama = $this->ringkasan($rekeningSekolah);

        $rekeningSekolah->fill([
            'bank' => (string) $request->validated('bank'),
            'nomor_rekening' => (string) $request->validated('nomor_rekening'),
            'atas_nama' => (string) $request->validated('atas_nama'),
            'aktif' => $request->boolean('aktif'),
        ])->save();

        Catat::aksi('ubah_rekening', $rekeningSekolah, [
            'lama' => $lama,
            'baru' => $this->ringkasan($rekeningSekolah),
        ], $pengguna->sekolah_id);

        return redirect()->route('admin.rekening.index')->with('sukses', 'Rekening diperbarui.');
    }

    public function hapus(Request $request, RekeningSekolah $rekeningSekolah): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $this->pastikanMilik($pengguna, $rekeningSekolah);

        if (Pembayaran::query()->where('metode', 'transfer')->exists()) {
            return back()->withErrors([
                'rekening' => 'Sudah ada pembayaran transfer di sekolah ini. Rekening tidak dapat dihapus; nonaktifkan saja.',
            ]);
        }

        $ringkasan = $this->ringkasan($rekeningSekolah);
        $rekeningSekolah->delete();

        Catat::aksi('hapus_rekening', null, ['rekening' => $ringkasan], $pengguna->sekolah_id);

        return redirect()->route('admin.rekening.index')->with('sukses', 'Rekening dihapus.');
    }

    /**
     * Nomor rekening tidak pernah dicatat utuh di log.
     *
     * @return array<string, mixed>
     */
    private function ringkasan(RekeningSekolah $rekening): array
    {
        $angka = (string) preg_replace('/\D+/', '', (string) $rekening->nomor_rekening);

        return [
            'bank' => $rekening->bank,
            'atas_nama' => $rekening->atas_nama,
            'nomor_akhir' => substr($angka, -4),
            'aktif' => $rekening->aktif,
        ];
    }

    private function pastikanPengaturan(\App\Models\User $pengguna): void
    {
        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }

    private function pastikanMilik(\App\Models\User $pengguna, RekeningSekolah $rekening): void
    {
        if ((int) $rekening->sekolah_id !== (int) $pengguna->sekolah_id) {
            throw (new ModelNotFoundException)->setModel(RekeningSekolah::class, [$rekening->getKey()]);
        }
    }
}
