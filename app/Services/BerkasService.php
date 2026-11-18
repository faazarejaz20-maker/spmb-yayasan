<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Enums\StatusBerkas;
use App\Exceptions\AturanBisnisException;
use App\Exceptions\BerkasTidakValidException;
use App\Models\Berkas;
use App\Models\JenisBerkas;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Support\Catat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BerkasService
{
    private const MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];

    /**
     * Validasi tegas berkas lalu simpan di disk privat. Mengembalikan data
     * path yang aman (nama acak, ekstensi terkontrol).
     *
     * @return array{path: string, ext: string, mime: string}
     */
    public function proses(UploadedFile $file, string $folder): array
    {
        if (! $file->isValid()) {
            throw new BerkasTidakValidException('Berkas gagal diunggah. Silakan coba lagi.');
        }

        $ukuran = (int) $file->getSize();
        $maks = ((int) config('spmb.berkas.maks_kb')) * 1024;

        if ($ukuran <= 0) {
            throw new BerkasTidakValidException('Berkas kosong tidak dapat diunggah.');
        }

        if ($ukuran > $maks) {
            $mb = number_format($maks / 1024 / 1024, 0, ',', '.');

            throw new BerkasTidakValidException("Ukuran berkas maksimal {$mb} MB.");
        }

        $namaAsli = (string) $file->getClientOriginalName();
        $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));

        if (in_array($ext, ['heic', 'heif'], true)) {
            throw new BerkasTidakValidException(
                'Foto format HEIC belum didukung. Ubah ke JPG atau PNG, atau ambil foto langsung lewat tombol Ambil foto.'
            );
        }

        // Tolak nama dengan ekstensi ganda seperti `a.php.jpg` atau nama aneh.
        if (! preg_match('/\A[\p{L}\p{N} _\-()]+\.([A-Za-z0-9]+)\z/u', $namaAsli)) {
            throw new BerkasTidakValidException('Nama berkas tidak valid. Gunakan huruf, angka, spasi, atau tanda hubung.');
        }

        if (! isset(self::MIME[$ext])) {
            throw new BerkasTidakValidException('Format berkas harus JPG, PNG, atau PDF.');
        }

        $mimeAsli = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';

        if (in_array($mimeAsli, ['image/heic', 'image/heif'], true)) {
            throw new BerkasTidakValidException(
                'Foto format HEIC belum didukung. Ubah ke JPG atau PNG, atau ambil foto langsung lewat tombol Ambil foto.'
            );
        }

        if ($mimeAsli !== self::MIME[$ext]) {
            throw new BerkasTidakValidException('Isi berkas tidak sesuai dengan ekstensinya.');
        }

        $isi = file_get_contents($file->getRealPath());

        if ($isi === false) {
            throw new BerkasTidakValidException('Berkas tidak dapat dibaca.');
        }

        if ($ext === 'pdf') {
            if (! str_starts_with($isi, '%PDF-')) {
                throw new BerkasTidakValidException('Berkas PDF tidak valid.');
            }

            $isiSimpan = $isi;
        } else {
            $isiSimpan = $this->prosesGambar($isi, $file->getRealPath(), $ext);
        }

        $path = trim($folder, '/').'/'.Str::uuid().'.'.$ext;

        if (! Storage::disk('local')->put($path, $isiSimpan)) {
            throw new BerkasTidakValidException('Berkas gagal disimpan. Silakan coba lagi.');
        }

        return ['path' => $path, 'ext' => $ext, 'mime' => self::MIME[$ext]];
    }

    /**
     * Proses gambar konten sekolah. Berbeda dari berkas pendaftar: hanya JPG,
     * PNG, atau WebP, disimpan di disk publik terpisah, dan selalu ditulis
     * ulang agar EXIF/payload tersembunyi hilang.
     *
     * @param  int|null  $maksKb  Batas ukuran unggahan (KB). Null memakai batas konten.
     * @param  int|null  $sisiMaks  Sisi terpanjang hasil simpan (piksel).
     * @return array{path: string, ext: string, mime: string}
     */
    public function prosesGambarKonten(
        UploadedFile $file,
        string $folder,
        ?int $maksKb = null,
        ?int $sisiMaks = null,
    ): array {
        if (! $file->isValid()) {
            throw new BerkasTidakValidException('Gambar gagal diunggah. Silakan coba lagi.');
        }

        $ukuran = (int) $file->getSize();
        $maks = ($maksKb ?? (int) config('spmb.konten.maks_kb')) * 1024;

        if ($ukuran <= 0) {
            throw new BerkasTidakValidException('Gambar kosong tidak dapat diunggah.');
        }

        if ($ukuran > $maks) {
            $mb = number_format($maks / 1024 / 1024, 0, ',', '.');

            throw new BerkasTidakValidException("Ukuran gambar maksimal {$mb} MB.");
        }

        $namaAsli = (string) $file->getClientOriginalName();
        $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));

        if (! preg_match('/\A[\p{L}\p{N} _\-()]+\.([A-Za-z0-9]+)\z/u', $namaAsli)) {
            throw new BerkasTidakValidException('Nama gambar tidak valid.');
        }

        $mimeSah = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        if (! isset($mimeSah[$ext])) {
            throw new BerkasTidakValidException('Gambar harus berformat JPG, PNG, atau WebP.');
        }

        $mimeAsli = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';

        if ($mimeAsli !== $mimeSah[$ext]) {
            throw new BerkasTidakValidException('Isi gambar tidak sesuai dengan ekstensinya.');
        }

        // Tolak gambar berukuran piksel ekstrem sebelum dimuat ke memori.
        $info = @getimagesize($file->getRealPath());
        $piksel = (int) ($info[0] ?? 0) * (int) ($info[1] ?? 0);

        if ($piksel > 40_000_000) {
            throw new BerkasTidakValidException('Dimensi gambar terlalu besar. Maksimal 40 megapiksel.');
        }

        $isi = file_get_contents($file->getRealPath());

        if ($isi === false) {
            throw new BerkasTidakValidException('Gambar tidak dapat dibaca.');
        }

        $gambar = @imagecreatefromstring($isi);

        if ($gambar === false) {
            throw new BerkasTidakValidException('Gambar tidak valid atau rusak.');
        }

        if ($ext === 'jpg' || $ext === 'jpeg') {
            $gambar = $this->putarSesuaiOrientasi($gambar, $file->getRealPath());
        }

        $gambar = $this->kecilkanGambar($gambar, $sisiMaks ?? (int) config('spmb.konten.sisi_maks'));

        ob_start();

        if ($ext === 'png') {
            imagepng($gambar, null, 6);
        } elseif ($ext === 'webp') {
            if (! function_exists('imagewebp')) {
                imagedestroy($gambar);
                ob_end_clean();

                throw new BerkasTidakValidException('Format WebP belum didukung server ini. Gunakan JPG atau PNG.');
            }

            imagewebp($gambar, null, 82);
        } else {
            imagejpeg($gambar, null, 86);
        }

        $isiSimpan = (string) ob_get_clean();
        imagedestroy($gambar);

        if ($isiSimpan === '') {
            throw new BerkasTidakValidException('Gambar gagal diproses. Silakan coba lagi.');
        }

        $path = trim($folder, '/').'/'.Str::uuid().'.'.$ext;

        if (! Storage::disk('public')->put($path, $isiSimpan)) {
            throw new BerkasTidakValidException('Gambar gagal disimpan. Silakan coba lagi.');
        }

        return ['path' => $path, 'ext' => $ext, 'mime' => $mimeSah[$ext]];
    }

    public function hapusGambarPublik(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  \GdImage  $gambar
     * @return \GdImage
     */
    private function kecilkanGambar($gambar, int $maks)
    {
        $maks = max(1, $maks);
        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);

        if (max($lebar, $tinggi) <= $maks) {
            return $gambar;
        }

        $rasio = $maks / max($lebar, $tinggi);
        $lebarBaru = max(1, (int) round($lebar * $rasio));
        $tinggiBaru = max(1, (int) round($tinggi * $rasio));

        $baru = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagecopyresampled($baru, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
        imagedestroy($gambar);

        return $baru;
    }

    /**
     * Baca ulang gambar dengan GD, putar sesuai EXIF, perkecil bila perlu, dan
     * simpan ulang sehingga metadata (termasuk EXIF) dan muatan tersembunyi
     * hilang.
     */
    private function prosesGambar(string $isi, string $pathAsli, string $ext): string
    {
        $gambar = @imagecreatefromstring($isi);

        if ($gambar === false) {
            throw new BerkasTidakValidException('Gambar tidak valid atau rusak.');
        }

        if ($ext === 'jpg' || $ext === 'jpeg') {
            $gambar = $this->putarSesuaiOrientasi($gambar, $pathAsli);
        }

        $lebar = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $maks = max(1, (int) config('spmb.berkas.sisi_maks'));

        if (max($lebar, $tinggi) > $maks) {
            $rasio = $maks / max($lebar, $tinggi);
            $lebarBaru = max(1, (int) round($lebar * $rasio));
            $tinggiBaru = max(1, (int) round($tinggi * $rasio));

            $baru = imagecreatetruecolor($lebarBaru, $tinggiBaru);

            if ($ext === 'png') {
                imagealphablending($baru, false);
                imagesavealpha($baru, true);
                $transparan = imagecolorallocatealpha($baru, 0, 0, 0, 127);
                imagefilledrectangle($baru, 0, 0, $lebarBaru, $tinggiBaru, $transparan);
            }

            imagecopyresampled($baru, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);
            imagedestroy($gambar);
            $gambar = $baru;
        }

        ob_start();

        if ($ext === 'png') {
            imagepng($gambar, null, 6);
        } else {
            imagejpeg($gambar, null, 88);
        }

        $hasil = (string) ob_get_clean();
        imagedestroy($gambar);

        if ($hasil === '') {
            throw new BerkasTidakValidException('Gambar gagal diproses. Silakan coba lagi.');
        }

        return $hasil;
    }

    /**
     * @param  \GdImage  $gambar
     * @return \GdImage
     */
    private function putarSesuaiOrientasi($gambar, string $pathAsli)
    {
        if (! function_exists('exif_read_data')) {
            return $gambar;
        }

        $exif = @exif_read_data($pathAsli);

        $orientasi = (int) ($exif['Orientation'] ?? 1);

        $sudut = match ($orientasi) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($sudut === 0) {
            return $gambar;
        }

        $diputar = @imagerotate($gambar, $sudut, 0);

        if ($diputar === false) {
            return $gambar;
        }

        imagedestroy($gambar);

        return $diputar;
    }

    public function unggah(User $pelaku, Pendaftaran $pendaftaran, JenisBerkas $jenis, UploadedFile $file): Berkas
    {
        $this->pastikanBolehUbah($pelaku, $pendaftaran);

        if (($jenis->sekolah_id !== $pendaftaran->sekolah_id) || ! $jenis->aktif) {
            throw new BerkasTidakValidException('Jenis berkas tidak tersedia untuk pendaftaran ini.');
        }

        $hasil = $this->proses($file, config('spmb.berkas.folder').'/'.$pendaftaran->uuid);

        return DB::transaction(function () use ($pendaftaran, $jenis, $hasil) {
            $lama = Berkas::query()
                ->where('pendaftaran_id', $pendaftaran->id)
                ->where('jenis_berkas_id', $jenis->id)
                ->first();

            if ($lama) {
                $pathLama = $lama->path;

                $lama->fill([
                    'path' => $hasil['path'],
                    'status' => StatusBerkas::Menunggu,
                    'catatan' => null,
                    'diperiksa_oleh' => null,
                    'diperiksa_pada' => null,
                ])->save();

                if (is_string($pathLama) && $pathLama !== '') {
                    Storage::disk('local')->delete($pathLama);
                }

                Catat::aksi('ganti_berkas', $lama, ['jenis' => $jenis->nama, 'nama_berkas' => $jenis->nama], $pendaftaran->sekolah_id);

                return $lama->refresh();
            }

            $berkas = Berkas::create([
                'pendaftaran_id' => $pendaftaran->id,
                'jenis_berkas_id' => $jenis->id,
                'path' => $hasil['path'],
                'status' => StatusBerkas::Menunggu,
            ]);

            Catat::aksi('unggah_berkas', $berkas, ['jenis' => $jenis->nama], $pendaftaran->sekolah_id);

            return $berkas;
        });
    }

    public function hapus(User $pelaku, Berkas $berkas): void
    {
        $berkas->loadMissing('pendaftaran');
        $pendaftaran = $berkas->pendaftaran;

        $this->pastikanBolehUbah($pelaku, $pendaftaran);

        DB::transaction(function () use ($berkas, $pendaftaran) {
            $path = $berkas->path;

            $berkas->delete();

            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }

            Catat::aksi('hapus_berkas', $pendaftaran, ['berkas_id' => $berkas->id], $pendaftaran->sekolah_id);
        });
    }

    /**
     * Tandai berkas sudah sesuai atau perlu diperbaiki. Catatan wajib bila
     * perlu perbaikan. Hanya staf sekolah terkait.
     */
    public function periksa(User $pelaku, Berkas $berkas, StatusBerkas $status, ?string $catatan = null): Berkas
    {
        if ($status === StatusBerkas::Menunggu) {
            throw new AturanBisnisException('Pilih "Sesuai" atau "Perlu perbaikan".');
        }

        $berkas->loadMissing('pendaftaran');

        /** @var Pendaftaran $pendaftaran */
        $pendaftaran = $berkas->pendaftaran;

        if (! in_array($pelaku->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)
            || ! $pelaku->sekolah_id
            || (int) $pelaku->sekolah_id !== (int) $pendaftaran->sekolah_id) {
            throw (new ModelNotFoundException)->setModel(Berkas::class, [$berkas->getKey()]);
        }

        $catatan = trim((string) $catatan);

        if ($status === StatusBerkas::PerluPerbaikan && $catatan === '') {
            throw new AturanBisnisException('Tuliskan catatan berkas apa yang harus diperbaiki.');
        }

        $berkas->fill([
            'status' => $status,
            'catatan' => $status === StatusBerkas::PerluPerbaikan ? $catatan : null,
            'diperiksa_oleh' => $pelaku->id,
            'diperiksa_pada' => now(),
        ])->save();

        Catat::aksi('periksa_berkas', $berkas, [
            'status' => $status->value,
        ], $pendaftaran->sekolah_id);

        return $berkas->refresh();
    }

    /**
     * Ada berkas pada pendaftaran ini yang ditandai perlu perbaikan.
     */
    public function adaPerluPerbaikan(Pendaftaran $pendaftaran): bool
    {
        return Berkas::query()
            ->tanpaSekolah()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->where('status', StatusBerkas::PerluPerbaikan)
            ->exists();
    }

    /**
     * Jumlah jenis berkas wajib yang belum diunggah atau masih menunggu
     * pemeriksaan.
     */
    public function wajibBelumSiap(Pendaftaran $pendaftaran): int
    {
        $wajib = JenisBerkas::query()
            ->tanpaSekolah()
            ->where('sekolah_id', $pendaftaran->sekolah_id)
            ->where('aktif', true)
            ->where('wajib', true)
            ->pluck('id')
            ->all();

        if ($wajib === []) {
            return 0;
        }

        $siap = Berkas::query()
            ->tanpaSekolah()
            ->where('pendaftaran_id', $pendaftaran->id)
            ->whereIn('jenis_berkas_id', $wajib)
            ->whereNotNull('path')
            ->where('status', '!=', StatusBerkas::Menunggu)
            ->pluck('jenis_berkas_id')
            ->all();

        return count(array_diff($wajib, $siap));
    }

    /**
     * Ringkasan berkas untuk daftar pendaftar: `sesuai` dari `total` jenis
     * aktif. Dipakai setelah relasi `berkas` di-eager-load.
     *
     * @return array{sesuai: int, total: int}
     */
    public function ringkasan(Pendaftaran $pendaftaran, int $totalJenis): array
    {
        $sesuai = $pendaftaran->berkas
            ->where('status', StatusBerkas::Sesuai)
            ->count();

        return ['sesuai' => $sesuai, 'total' => $totalJenis];
    }

    public function sajikan(Berkas $berkas): StreamedResponse
    {
        return $this->alirkan((string) $berkas->path, 'berkas-'.$berkas->uuid);
    }

    public function sajikanBukti(string $path, string $nama): StreamedResponse
    {
        return $this->alirkan($path, $nama);
    }

    private function alirkan(string $path, string $namaDasar): StreamedResponse
    {
        $disk = Storage::disk('local');

        if ($path === '' || ! $disk->exists($path)) {
            abort(404);
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';

        return response()->stream(function () use ($disk, $path) {
            $aliran = $disk->readStream($path);

            if ($aliran === false) {
                return;
            }

            fpassthru($aliran);

            if (is_resource($aliran)) {
                fclose($aliran);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$namaDasar.'.'.$ext.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function pastikanBolehUbah(User $pelaku, Pendaftaran $pendaftaran): void
    {
        // Panitia/admin sekolah terkait boleh mengunggah atas nama walisantri
        // kapan saja, termasuk setelah pendaftaran dikirim.
        if (in_array($pelaku->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)) {
            if (! $pelaku->sekolah_id || (int) $pelaku->sekolah_id !== (int) $pendaftaran->sekolah_id) {
                throw (new ModelNotFoundException)->setModel(Pendaftaran::class, [$pendaftaran->getKey()]);
            }

            return;
        }

        $milik = $pendaftaran->anak()->where('user_id', $pelaku->id)->exists();

        if (! $milik || ! $pendaftaran->bolehDiubah()) {
            throw new AuthorizationException('Data pendaftaran tidak dapat diubah.');
        }
    }
}
