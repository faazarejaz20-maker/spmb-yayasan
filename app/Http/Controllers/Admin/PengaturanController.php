<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BerkasTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FotoSampulRequest;
use App\Http\Requests\ModePenerimaanRequest;
use App\Policies\PengaturanSekolahPolicy;
use App\Services\FotoSampulService;
use App\Support\Catat;
use App\Support\GambarPublik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PengaturanController extends Controller
{
    public function __construct(
        private readonly FotoSampulService $fotoSampul,
        private readonly GambarPublik $gambar,
    ) {}

    public function index(Request $request): View
    {
        $sekolah = $request->user()->sekolah;

        return view('admin.pengaturan', [
            'sekolah' => $sekolah,
            'sampul' => $sekolah
                ? $this->gambar->sampulSekolah($sekolah->kode, $sekolah->nama, $sekolah->foto_sampul_path)
                : null,
            'adaSampulUnggahan' => filled($sekolah?->foto_sampul_path),
        ]);
    }

    public function simpan(ModePenerimaanRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $sekolah = $pengguna->sekolah;

        abort_unless(app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id), 403);
        abort_if($sekolah === null, 404);

        $lama = $sekolah->mode_penerimaan?->value;
        $baru = (string) $request->validated('mode_penerimaan');

        if ($lama === $baru) {
            return redirect()->route('admin.pengaturan')->with('pesan', 'Mode penerimaan tidak berubah.');
        }

        $sekolah->forceFill(['mode_penerimaan' => $baru])->save();

        Catat::aksi('ubah_mode_penerimaan', $sekolah, [
            'lama' => $lama,
            'baru' => $baru,
        ], $sekolah->id);

        return redirect()->route('admin.pengaturan')->with('sukses', 'Mode penerimaan diperbarui.');
    }

    public function simpanFoto(FotoSampulRequest $request): RedirectResponse
    {
        $pengguna = $request->user();
        $sekolah = $pengguna->sekolah;

        abort_unless(app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id), 403);
        abort_if($sekolah === null, 404);

        try {
            $this->fotoSampul->simpan($sekolah, $request->file('foto_sampul'));
        } catch (BerkasTidakValidException $e) {
            return redirect()->route('admin.pengaturan')
                ->withErrors(['foto_sampul' => $e->getMessage()]);
        }

        return redirect()->route('admin.pengaturan')
            ->with('sukses', 'Foto sampul beranda diperbarui.');
    }

    public function hapusFoto(Request $request): RedirectResponse
    {
        $pengguna = $request->user();
        $sekolah = $pengguna->sekolah;

        abort_unless(app(PengaturanSekolahPolicy::class)->kelola($pengguna, $pengguna?->sekolah_id), 403);
        abort_if($sekolah === null, 404);

        $this->fotoSampul->hapus($sekolah);

        return redirect()->route('admin.pengaturan')
            ->with('sukses', 'Foto sampul dikembalikan ke foto bawaan.');
    }
}
