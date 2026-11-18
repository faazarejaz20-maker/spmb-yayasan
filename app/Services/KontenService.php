<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Exceptions\AturanBisnisException;
use App\Exceptions\BerkasTidakValidException;
use App\Models\KontenSekolah;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Kelola Konten. Teks selalu disimpan sebagai teks biasa (tag HTML dibuang)
 * dan di-escape saat tampil. Gambar konten memakai disk publik terpisah dan
 * diproses ulang lewat BerkasService.
 */
class KontenService
{
    public function __construct(private readonly BerkasService $berkas) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function katalog(string $jenis): array
    {
        return config('konten.'.$jenis, []);
    }

    /**
     * @return array<int, array{bagian: string, meta: array<string, mixed>, baris: \Illuminate\Support\Collection<int, KontenSekolah>}>
     */
    public function daftar(?int $sekolahId, string $jenis): array
    {
        $katalog = $this->katalog($jenis);

        $baris = KontenSekolah::query()
            ->tanpaSekolah()
            ->when($sekolahId === null, fn ($q) => $q->whereNull('sekolah_id'), fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->whereIn('bagian', array_keys($katalog))
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->groupBy('bagian');

        $hasil = [];

        foreach ($katalog as $bagian => $meta) {
            $hasil[] = [
                'bagian' => $bagian,
                'meta' => $meta,
                'baris' => $baris->get($bagian, collect()),
            ];
        }

        return $hasil;
    }

    public function simpanTeks(User $pelaku, ?int $sekolahId, string $bagian, ?string $isi): KontenSekolah
    {
        $this->pastikanSekolah($pelaku, $sekolahId);
        $meta = $this->pastikanBagian($pelaku, $bagian);

        if ($meta['banyak'] ?? false) {
            throw new AturanBisnisException('Bagian ini berisi daftar. Gunakan tombol tambah baris.');
        }

        $baris = KontenSekolah::query()
            ->tanpaSekolah()
            ->when($sekolahId === null, fn ($q) => $q->whereNull('sekolah_id'), fn ($q) => $q->where('sekolah_id', $sekolahId))
            ->where('bagian', $bagian)
            ->orderBy('urutan')
            ->orderBy('id')
            ->first();

        if (! $baris) {
            $baris = new KontenSekolah([
                'bagian' => $bagian,
                'urutan' => 0,
            ]);
            $baris->forceFill(['sekolah_id' => $sekolahId]);
        }

        $teks = $this->teks($isi);

        if (($meta['tautan'] ?? false) && $teks !== null) {
            $this->pastikanTautanAman($teks);
        }

        $baris->fill(['isi' => $teks])->save();

        Catat::aksi('simpan_konten', $baris, [
            'bagian' => $bagian,
        ], $sekolahId ?? $pelaku->sekolah_id);

        return $baris->refresh();
    }

    /**
     * @param  array{judul?: string|null, isi?: string|null, urutan?: int|null}  $data
     */
    public function tambah(
        User $pelaku,
        ?int $sekolahId,
        string $bagian,
        array $data,
        ?UploadedFile $gambar = null,
    ): KontenSekolah {
        $this->pastikanSekolah($pelaku, $sekolahId);
        $meta = $this->pastikanBagian($pelaku, $bagian);

        if (! ($meta['banyak'] ?? false)) {
            throw new AturanBisnisException('Bagian ini hanya berisi satu teks. Gunakan formulir simpan teks.');
        }

        $path = null;

        if ($gambar) {
            if (! ($meta['gambar'] ?? false)) {
                throw new BerkasTidakValidException('Bagian ini tidak menerima gambar.');
            }

            $path = $this->berkas->prosesGambarKonten(
                $gambar,
                config('spmb.konten.folder').'/'.($sekolahId ?: 'yayasan')
            )['path'];
        }

        $judul = $this->teks($data['judul'] ?? null);
        $teks = $this->teks($data['isi'] ?? null);

        if (($meta['tautan'] ?? false)) {
            $this->pastikanTautanAman($teks);
        }

        return DB::transaction(function () use ($pelaku, $sekolahId, $bagian, $data, $path, $judul, $teks) {
            $urutan = isset($data['urutan']) && $data['urutan'] !== null
                ? (int) $data['urutan']
                : ((int) KontenSekolah::query()
                    ->tanpaSekolah()
                    ->when($sekolahId === null, fn ($q) => $q->whereNull('sekolah_id'), fn ($q) => $q->where('sekolah_id', $sekolahId))
                    ->where('bagian', $bagian)
                    ->max('urutan')) + 1;

            $baris = new KontenSekolah([
                'bagian' => $bagian,
                'judul' => $judul,
                'isi' => $teks,
                'gambar_path' => $path,
                'urutan' => $urutan,
            ]);
            $baris->forceFill(['sekolah_id' => $sekolahId]);
            $baris->save();

            Catat::aksi('tambah_konten', $baris, [
                'bagian' => $bagian,
                'judul' => $baris->judul,
            ], $sekolahId ?? $pelaku->sekolah_id);

            return $baris->refresh();
        });
    }

    /**
     * @param  array{judul?: string|null, isi?: string|null, urutan?: int|null, hapus_gambar?: bool}  $data
     */
    public function ubah(
        User $pelaku,
        KontenSekolah $konten,
        array $data,
        ?UploadedFile $gambar = null,
    ): KontenSekolah {
        $sekolahId = $konten->sekolah_id !== null ? (int) $konten->sekolah_id : null;
        $this->pastikanSekolah($pelaku, $sekolahId);
        $meta = $this->pastikanBagian($pelaku, $konten->bagian);

        $pathLama = $konten->gambar_path;
        $pathBaru = null;

        if ($gambar) {
            if (! ($meta['gambar'] ?? false)) {
                throw new BerkasTidakValidException('Bagian ini tidak menerima gambar.');
            }

            $pathBaru = $this->berkas->prosesGambarKonten(
                $gambar,
                config('spmb.konten.folder').'/'.($sekolahId ?: 'yayasan')
            )['path'];
        }

        $hapusGambar = (bool) ($data['hapus_gambar'] ?? false) && ($meta['gambar'] ?? false);

        $judul = $this->teks($data['judul'] ?? null);
        $teks = $this->teks($data['isi'] ?? null);

        if (($meta['tautan'] ?? false)) {
            $this->pastikanTautanAman($teks);
        }

        $konten->fill([
            'judul' => $judul,
            'isi' => $teks,
            'urutan' => isset($data['urutan']) && $data['urutan'] !== null ? (int) $data['urutan'] : $konten->urutan,
        ]);

        if ($pathBaru) {
            $konten->gambar_path = $pathBaru;
        } elseif ($hapusGambar) {
            $konten->gambar_path = null;
        }

        $konten->save();

        if ($pathLama && ($pathBaru || $hapusGambar)) {
            $this->berkas->hapusGambarPublik($pathLama);
        }

        Catat::aksi('ubah_konten', $konten, [
            'bagian' => $konten->bagian,
            'judul' => $konten->judul,
        ], $sekolahId ?? $pelaku->sekolah_id);

        return $konten->refresh();
    }

    public function hapus(User $pelaku, KontenSekolah $konten): void
    {
        $sekolahId = $konten->sekolah_id !== null ? (int) $konten->sekolah_id : null;
        $this->pastikanSekolah($pelaku, $sekolahId);
        $this->pastikanBagian($pelaku, $konten->bagian);

        $path = $konten->gambar_path;

        DB::transaction(function () use ($pelaku, $konten, $sekolahId, $path) {
            $konten->delete();

            if ($path) {
                $this->berkas->hapusGambarPublik($path);
            }

            Catat::aksi('hapus_konten', null, [
                'bagian' => $konten->bagian,
                'judul' => $konten->judul,
            ], $sekolahId ?? $pelaku->sekolah_id);
        });
    }

    private function pastikanSekolah(User $pelaku, ?int $sekolahId): void
    {
        if ($pelaku->peran === PeranPengguna::Admin) {
            if (! $pelaku->sekolah_id || $sekolahId === null || (int) $pelaku->sekolah_id !== $sekolahId) {
                throw (new ModelNotFoundException)->setModel(KontenSekolah::class);
            }

            return;
        }

        if ($pelaku->peran === PeranPengguna::SuperAdmin) {
            if ($sekolahId !== null) {
                throw (new ModelNotFoundException)->setModel(KontenSekolah::class);
            }

            return;
        }

        throw (new ModelNotFoundException)->setModel(KontenSekolah::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function pastikanBagian(User $pelaku, string $bagian): array
    {
        $jenis = $pelaku->peran === PeranPengguna::SuperAdmin ? 'yayasan' : 'sekolah';
        $katalog = $this->katalog($jenis);

        if (! isset($katalog[$bagian])) {
            throw (new ModelNotFoundException)->setModel(KontenSekolah::class);
        }

        return $katalog[$bagian];
    }

    private function teks(?string $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $nilai = strip_tags($nilai);
        $nilai = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $nilai);
        $nilai = trim($nilai);

        return $nilai === '' ? null : $nilai;
    }

    private function pastikanTautanAman(?string $nilai): void
    {
        if ($nilai === null) {
            return;
        }

        if (! preg_match('/\Ahttps:\/\/[^\s<>"]+\z/i', $nilai)) {
            throw new AturanBisnisException('Tautan harus diawali https:// dan tidak boleh memakai skema lain.');
        }
    }
}
