<?php

namespace App\Services;

use App\Models\Sekolah;
use App\Support\Catat;
use Illuminate\Http\UploadedFile;

/**
 * Foto sampul kartu sekolah di beranda publik.
 *
 * Gambar disimpan di disk publik (`sampul/{sekolah_id}/...`) karena memang
 * tampil untuk pengunjung. Berkas lama dihapus saat diganti atau dihapus agar
 * tidak menumpuk. Bila kolom `sekolah.foto_sampul_path` kosong, tampilan
 * kembali memakai foto bawaan proyek.
 */
class FotoSampulService
{
    public function __construct(private readonly BerkasService $berkas) {}

    public function simpan(Sekolah $sekolah, UploadedFile $gambar): Sekolah
    {
        $pathLama = $sekolah->foto_sampul_path;

        $path = $this->berkas->prosesGambarKonten(
            $gambar,
            ((string) config('spmb.sampul.folder')).'/'.$sekolah->id,
            (int) config('spmb.sampul.maks_kb'),
            (int) config('spmb.sampul.sisi_maks'),
        )['path'];

        $sekolah->forceFill(['foto_sampul_path' => $path])->save();

        if (is_string($pathLama) && $pathLama !== '') {
            $this->berkas->hapusGambarPublik($pathLama);
        }

        Catat::aksi('ubah_foto_sampul', $sekolah, [
            'berkas' => basename($path),
        ], (int) $sekolah->id);

        return $sekolah->refresh();
    }

    public function hapus(Sekolah $sekolah): void
    {
        $pathLama = $sekolah->foto_sampul_path;

        $sekolah->forceFill(['foto_sampul_path' => null])->save();

        if (is_string($pathLama) && $pathLama !== '') {
            $this->berkas->hapusGambarPublik($pathLama);
        }

        Catat::aksi('hapus_foto_sampul', $sekolah, [
            'berkas' => $pathLama ? basename($pathLama) : null,
        ], (int) $sekolah->id);
    }
}
