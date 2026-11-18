<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RincianBiayaRequest;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\RincianBiaya;
use App\Models\TahunAjaran;
use App\Policies\PengaturanSekolahPolicy;
use App\Support\Catat;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RincianBiayaController extends Controller
{
    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('admin.biaya.index', [
            'sekolah' => $pengguna->sekolah,
            'daftar' => RincianBiaya::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function simpan(RincianBiayaRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        $tahun = TahunAjaran::query()->where('aktif', true)->first();

        if (! $tahun) {
            return back()->withInput()->withErrors(['biaya' => 'Belum ada tahun ajaran aktif.']);
        }

        $rincian = new RincianBiaya([
            'tahun_ajaran_id' => $tahun->id,
            'kelompok' => $this->teksAtauNull($request->validated('kelompok')),
            'nama' => (string) $request->validated('nama'),
            'nominal' => (int) $request->validated('nominal'),
            'tipe' => (string) $request->validated('tipe'),
            'catatan' => $this->teksAtauNull($request->validated('catatan')),
            'urutan' => (int) ($request->validated('urutan') ?? ((int) RincianBiaya::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->max('urutan') + 1)),
            'aktif' => $request->boolean('aktif'),
        ]);
        $rincian->forceFill(['sekolah_id' => $pengguna->sekolah_id])->save();

        Catat::aksi('buat_rincian_biaya', $rincian, $this->ringkasan($rincian), $pengguna->sekolah_id);

        return redirect()->route('admin.biaya.index')->with('sukses', 'Rincian biaya ditambahkan.');
    }

    public function ubah(RincianBiayaRequest $request, RincianBiaya $rincianBiaya): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $this->pastikanMilik($pengguna, $rincianBiaya);

        $lama = $this->ringkasan($rincianBiaya);

        $rincianBiaya->fill([
            'kelompok' => $this->teksAtauNull($request->validated('kelompok')),
            'nama' => (string) $request->validated('nama'),
            'nominal' => (int) $request->validated('nominal'),
            'tipe' => (string) $request->validated('tipe'),
            'catatan' => $this->teksAtauNull($request->validated('catatan')),
            'urutan' => (int) ($request->validated('urutan') ?? $rincianBiaya->urutan),
            'aktif' => $request->boolean('aktif'),
        ])->save();

        Catat::aksi('ubah_rincian_biaya', $rincianBiaya, [
            'lama' => $lama,
            'baru' => $this->ringkasan($rincianBiaya),
        ], $pengguna->sekolah_id);

        return redirect()->route('admin.biaya.index')->with('sukses', 'Rincian biaya diperbarui.');
    }

    public function hapus(Request $request, RincianBiaya $rincianBiaya): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $this->pastikanMilik($pengguna, $rincianBiaya);

        if ($this->sudahDipakai($rincianBiaya)) {
            return back()->withErrors([
                'biaya' => 'Rincian biaya ini sudah dipakai pendaftaran atau pembayaran sehingga tidak dapat dihapus. Nonaktifkan saja.',
            ]);
        }

        $ringkasan = $this->ringkasan($rincianBiaya);
        $rincianBiaya->delete();

        Catat::aksi('hapus_rincian_biaya', null, ['biaya' => $ringkasan], $pengguna->sekolah_id);

        return redirect()->route('admin.biaya.index')->with('sukses', 'Rincian biaya dihapus.');
    }

    private function sudahDipakai(RincianBiaya $rincian): bool
    {
        if (Pembayaran::query()->where('rincian_biaya_id', $rincian->id)->exists()) {
            return true;
        }

        if ($rincian->tipe === \App\Enums\TipeBiaya::Pilihan) {
            return Pendaftaran::query()
                ->tanpaSekolah()
                ->where('sekolah_id', $rincian->sekolah_id)
                ->whereNotNull('dikirim_pada')
                ->where('pilihan_iap', $rincian->nominal)
                ->exists();
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasan(RincianBiaya $rincian): array
    {
        return [
            'kelompok' => $rincian->kelompok,
            'nama' => $rincian->nama,
            'nominal' => (int) $rincian->nominal,
            'tipe' => $rincian->tipe?->value,
            'aktif' => $rincian->aktif,
        ];
    }

    private function pastikanPengaturan(\App\Models\User $pengguna): void
    {
        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }

    private function pastikanMilik(\App\Models\User $pengguna, RincianBiaya $rincian): void
    {
        if ((int) $rincian->sekolah_id !== (int) $pengguna->sekolah_id) {
            throw (new ModelNotFoundException)->setModel(RincianBiaya::class, [$rincian->getKey()]);
        }
    }

    private function teksAtauNull(mixed $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
