<?php

namespace App\Console\Commands;

use App\Support\GambarPublik;
use GdImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Membuat salinan WebP ringan dari foto publik.
 *
 * Foto asli dari sekolah berukuran besar (hingga 35 MB dan 6000 piksel).
 * Halaman publik tidak boleh mengirim berkas sebesar itu, jadi perintah ini
 * membuat turunan di `public/images/optimasi`. `GambarPublik` otomatis memakai
 * turunan bila ada dan kembali ke berkas asli bila belum dibuat.
 *
 * Jalankan setelah menambah atau mengganti foto:
 *   php artisan spmb:optimalkan-gambar
 */
class OptimalkanGambar extends Command
{
    protected $signature = 'spmb:optimalkan-gambar
        {--paksa : Bangun ulang semua turunan, walaupun sudah ada}
        {--lebar=1600 : Lebar maksimum foto biasa (piksel)}
        {--kualitas=80 : Kualitas WebP, 40 sampai 100}';

    protected $description = 'Membuat versi WebP ringan dari foto publik di public/images.';

    /**
     * Folder sumber dan lebar maksimum bawaannya. Logo cukup kecil karena
     * hanya tampil 32–72 piksel.
     *
     * @var array<string, int>
     */
    private const FOLDER = [
        'logo' => 320,
        'sekolah' => 1600,
        'ekskul' => 1600,
        'prestasi' => 1600,
    ];

    public function handle(): int
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            $this->error('Ekstensi GD dengan dukungan WebP tidak tersedia. Optimasi dibatalkan.');

            return self::FAILURE;
        }

        $paksa = (bool) $this->option('paksa');
        $lebar = max(160, (int) $this->option('lebar'));
        $kualitas = min(100, max(40, (int) $this->option('kualitas')));

        $dibuat = 0;
        $dilewati = 0;
        $gagal = 0;
        $hemat = 0;

        foreach (self::FOLDER as $folder => $maksLogo) {
            $akar = public_path('images/'.$folder);

            if (! is_dir($akar)) {
                continue;
            }

            $maks = $folder === 'logo' ? min($lebar, $maksLogo) : $lebar;

            /** @var SplFileInfo $berkas */
            foreach (File::allFiles($akar) as $berkas) {
                $relatif = trim(str_replace('\\', '/', $folder.'/'.$berkas->getRelativePathname()), '/');
                $sumber = $berkas->getPathname();
                $ext = strtolower($berkas->getExtension());

                if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    continue;
                }

                $tujuan = public_path(
                    GambarPublik::FOLDER_OPTIMASI.'/'.GambarPublik::namaOptimasi($relatif)
                );

                if (! $paksa && is_file($tujuan) && filemtime($tujuan) >= filemtime($sumber)) {
                    $dilewati++;

                    continue;
                }

                $hasil = $this->optimalkan($sumber, $tujuan, $maks, $kualitas);

                if ($hasil === null) {
                    $gagal++;
                    $this->warn('Dilewati: '.$relatif);

                    continue;
                }

                $dibuat++;

                if ($hasil > 0) {
                    $hemat += $hasil;
                }
            }
        }

        $this->info("Selesai: {$dibuat} gambar dibuat, {$dilewati} sudah terbaru, {$gagal} gagal.");
        $this->line('Ukuran yang dihemat: '.$this->ukuran($hemat));

        return $gagal > 0 && $dibuat === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return int|null Selisih ukuran dalam bita, atau null bila gagal.
     */
    private function optimalkan(string $sumber, string $tujuan, int $maks, int $kualitas): ?int
    {
        $isi = @file_get_contents($sumber);

        if ($isi === false) {
            return null;
        }

        $gambar = @imagecreatefromstring($isi);

        if (! $gambar instanceof GdImage) {
            return null;
        }

        $gambar = $this->sesuaikanOrientasi($gambar, $sumber);

        if ($gambar === null) {
            return null;
        }

        $lebarAsli = imagesx($gambar);
        $tinggiAsli = imagesy($gambar);
        $skala = $lebarAsli > $maks ? $maks / $lebarAsli : 1;
        $lebarBaru = max(1, (int) round($lebarAsli * $skala));
        $tinggiBaru = max(1, (int) round($tinggiAsli * $skala));

        $kanvas = imagecreatetruecolor($lebarBaru, $tinggiBaru);

        if ($kanvas === false) {
            imagedestroy($gambar);

            return null;
        }

        // Simpan transparansi untuk logo PNG.
        imagealphablending($kanvas, false);
        imagesavealpha($kanvas, true);
        $transparan = imagecolorallocatealpha($kanvas, 0, 0, 0, 127);

        if ($transparan !== false) {
            imagefilledrectangle($kanvas, 0, 0, $lebarBaru, $tinggiBaru, $transparan);
        }

        imagealphablending($kanvas, true);
        imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebarAsli, $tinggiAsli);

        File::ensureDirectoryExists(dirname($tujuan));

        $berhasil = imagewebp($kanvas, $tujuan, $kualitas);

        imagedestroy($gambar);
        imagedestroy($kanvas);

        if (! $berhasil) {
            return null;
        }

        return max(0, (int) filesize($sumber) - (int) filesize($tujuan));
    }

    /**
     * Putar atau balik gambar sesuai EXIF Orientation agar turunan tidak
     * tampil miring. Orientasi 5 dan 7 sengaja tidak didukung: turunan
     * dilewati sehingga browser memakai berkas asli yang sudah benar.
     */
    private function sesuaikanOrientasi(GdImage $gambar, string $sumber): ?GdImage
    {
        $ext = strtolower((string) pathinfo($sumber, PATHINFO_EXTENSION));

        if (! in_array($ext, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $gambar;
        }

        $data = @exif_read_data($sumber);
        $orientasi = (int) ($data['Orientation'] ?? 1);

        if ($orientasi === 1 || $orientasi < 1 || $orientasi > 8) {
            return $gambar;
        }

        if ($orientasi === 5 || $orientasi === 7) {
            imagedestroy($gambar);

            return null;
        }

        $sudut = match ($orientasi) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($sudut !== 0) {
            $diputar = imagerotate($gambar, $sudut, 0);

            if ($diputar === false) {
                return $gambar;
            }

            imagedestroy($gambar);
            $gambar = $diputar;
        }

        if ($orientasi === 2 || $orientasi === 4) {
            $mode = $orientasi === 2 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL;
            imageflip($gambar, $mode);
        }

        return $gambar;
    }

    private function ukuran(int $bita): string
    {
        if ($bita >= 1048576) {
            return number_format($bita / 1048576, 1, ',', '.').' MB';
        }

        return number_format($bita / 1024, 0, ',', '.').' KB';
    }
}
