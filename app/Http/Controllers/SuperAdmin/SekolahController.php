<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exceptions\BerkasTidakValidException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FotoSampulRequest;
use App\Http\Requests\SekolahRequest;
use App\Models\Sekolah;
use App\Policies\SuperAdminPolicy;
use App\Services\FotoSampulService;
use App\Support\Catat;
use App\Support\GambarPublik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SekolahController extends Controller
{
    public function __construct(
        private readonly FotoSampulService $fotoSampul,
        private readonly GambarPublik $gambar,
    ) {}

    public function index(): View
    {
        return view('super-admin.sekolah.index', [
            'sekolah' => Sekolah::query()->orderBy('id')->get(),
        ]);
    }

    public function edit(Request $request, Sekolah $sekolah): View
    {
        $this->pastikanSuperAdmin($request);

        return view('super-admin.sekolah.edit', [
            'sekolah' => $sekolah,
            'sampul' => $this->gambar->sampulSekolah($sekolah->kode, $sekolah->nama, $sekolah->foto_sampul_path),
            'adaSampulUnggahan' => filled($sekolah->foto_sampul_path),
        ]);
    }

    public function simpan(SekolahRequest $request, Sekolah $sekolah): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        $lama = $this->ringkasan($sekolah);

        $sekolah->fill([
            'nama' => (string) $request->validated('nama'),
            'singkatan' => $this->teksAtauNull($request->validated('singkatan')),
            'alamat' => $this->teksAtauNull($request->validated('alamat')),
            'kontak' => $this->teksAtauNull($request->validated('kontak')),
            'instagram' => $this->teksAtauNull($request->validated('instagram')),
            'mode_penerimaan' => (string) $request->validated('mode_penerimaan'),
            'aktif' => $request->boolean('aktif'),
        ])->save();

        Catat::aksi('ubah_sekolah', $sekolah, [
            'lama' => $lama,
            'baru' => $this->ringkasan($sekolah),
        ], $sekolah->id);

        return redirect()->route('super.sekolah.index')->with('sukses', 'Data sekolah diperbarui.');
    }

    public function simpanFoto(FotoSampulRequest $request, Sekolah $sekolah): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        try {
            $this->fotoSampul->simpan($sekolah, $request->file('foto_sampul'));
        } catch (BerkasTidakValidException $e) {
            return redirect()->route('super.sekolah.edit', $sekolah)
                ->withErrors(['foto_sampul' => $e->getMessage()]);
        }

        return redirect()->route('super.sekolah.edit', $sekolah)
            ->with('sukses', 'Foto sampul beranda diperbarui.');
    }

    public function hapusFoto(Request $request, Sekolah $sekolah): RedirectResponse
    {
        $this->pastikanSuperAdmin($request);

        $this->fotoSampul->hapus($sekolah);

        return redirect()->route('super.sekolah.edit', $sekolah)
            ->with('sukses', 'Foto sampul dikembalikan ke foto bawaan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasan(Sekolah $sekolah): array
    {
        return [
            'nama' => $sekolah->nama,
            'singkatan' => $sekolah->singkatan,
            'alamat' => $sekolah->alamat,
            'kontak' => $sekolah->kontak,
            'instagram' => $sekolah->instagram,
            'mode_penerimaan' => $sekolah->mode_penerimaan?->value,
            'aktif' => $sekolah->aktif,
        ];
    }

    private function pastikanSuperAdmin(Request $request): void
    {
        abort_unless(app(SuperAdminPolicy::class)->kelola($request->user()), 403);
    }

    private function teksAtauNull(mixed $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
