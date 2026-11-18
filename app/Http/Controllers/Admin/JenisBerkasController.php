<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AturanBisnisException;
use App\Http\Controllers\Controller;
use App\Http\Requests\JenisBerkasRequest;
use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Policies\PengaturanSekolahPolicy;
use App\Support\Catat;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JenisBerkasController extends Controller
{
    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('admin.berkas.index', [
            'sekolah' => $pengguna->sekolah,
            'daftar' => JenisBerkas::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function simpan(JenisBerkasRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);

        $urutan = $request->validated('urutan');

        if ($urutan === null) {
            $urutan = ((int) JenisBerkas::query()
                ->where('sekolah_id', $pengguna->sekolah_id)
                ->max('urutan')) + 1;
        }

        $jenis = new JenisBerkas([
            'nama' => (string) $request->validated('nama'),
            'wajib' => $request->boolean('wajib'),
            'jumlah_lembar' => (int) $request->validated('jumlah_lembar'),
            'petunjuk' => $this->teksAtauNull($request->validated('petunjuk')),
            'urutan' => (int) $urutan,
            'aktif' => $request->boolean('aktif'),
        ]);
        $jenis->forceFill(['sekolah_id' => $pengguna->sekolah_id])->save();

        Catat::aksi('buat_jenis_berkas', $jenis, [
            'nama' => $jenis->nama,
            'wajib' => $jenis->wajib,
            'aktif' => $jenis->aktif,
        ], $pengguna->sekolah_id);

        return redirect()->route('admin.berkas.index')->with('sukses', 'Jenis berkas ditambahkan.');
    }

    public function ubah(JenisBerkasRequest $request, JenisBerkas $jenisBerkas): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $this->pastikanMilik($pengguna, $jenisBerkas);

        $lama = $this->ringkasan($jenisBerkas);

        $jenisBerkas->fill([
            'nama' => (string) $request->validated('nama'),
            'wajib' => $request->boolean('wajib'),
            'jumlah_lembar' => (int) $request->validated('jumlah_lembar'),
            'petunjuk' => $this->teksAtauNull($request->validated('petunjuk')),
            'urutan' => (int) ($request->validated('urutan') ?? $jenisBerkas->urutan),
            'aktif' => $request->boolean('aktif'),
        ])->save();

        Catat::aksi('ubah_jenis_berkas', $jenisBerkas, [
            'lama' => $lama,
            'baru' => $this->ringkasan($jenisBerkas),
        ], $pengguna->sekolah_id);

        return redirect()->route('admin.berkas.index')->with('sukses', 'Jenis berkas diperbarui.');
    }

    public function hapus(Request $request, JenisBerkas $jenisBerkas): RedirectResponse
    {
        $pengguna = $request->user();
        $this->pastikanPengaturan($pengguna);
        $this->pastikanMilik($pengguna, $jenisBerkas);

        if (Berkas::query()->where('jenis_berkas_id', $jenisBerkas->id)->exists()) {
            return back()->withErrors([
                'berkas' => 'Jenis berkas ini sudah punya unggahan sehingga tidak dapat dihapus. Nonaktifkan saja.',
            ]);
        }

        $ringkasan = $this->ringkasan($jenisBerkas);
        $jenisBerkas->delete();

        Catat::aksi('hapus_jenis_berkas', null, ['berkas' => $ringkasan], $pengguna->sekolah_id);

        return redirect()->route('admin.berkas.index')->with('sukses', 'Jenis berkas dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasan(JenisBerkas $jenis): array
    {
        return [
            'nama' => $jenis->nama,
            'wajib' => $jenis->wajib,
            'jumlah_lembar' => $jenis->jumlah_lembar,
            'urutan' => $jenis->urutan,
            'aktif' => $jenis->aktif,
        ];
    }

    private function pastikanPengaturan(\App\Models\User $pengguna): void
    {
        abort_unless(
            app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id),
            403,
        );
    }

    private function pastikanMilik(\App\Models\User $pengguna, JenisBerkas $jenis): void
    {
        if ((int) $jenis->sekolah_id !== (int) $pengguna->sekolah_id) {
            throw (new ModelNotFoundException)->setModel(JenisBerkas::class, [$jenis->getKey()]);
        }
    }

    private function teksAtauNull(mixed $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
