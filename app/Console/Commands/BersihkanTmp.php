<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus file sementara (ekspor/ZIP) yang tertinggal lebih dari batas umur.
 *
 * Ekspor dan ZIP sudah dihapus setelah dikirim atau gagal, tetapi perintah ini
 * menjadi jaring pengaman bila proses terhenti mendadak.
 */
class BersihkanTmp extends Command
{
    protected $signature = 'spmb:bersihkan-tmp';

    protected $description = 'Menghapus file sementara ekspor/ZIP yang lebih tua dari batas umur.';

    public function handle(): int
    {
        $folder = (string) config('ekspor.tmp_folder');
        $menit = (int) config('ekspor.tmp_umur_menit', 60);
        $disk = Storage::disk('local');

        if (! $disk->exists($folder)) {
            $this->info('Folder sementara belum ada. Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $batas = now()->subMinutes($menit)->getTimestamp();
        $dihapus = 0;

        foreach ($disk->files($folder) as $path) {
            $waktu = $disk->lastModified($path);

            if ($waktu !== false && $waktu < $batas) {
                if ($disk->delete($path)) {
                    $dihapus++;
                }
            }
        }

        $this->info("{$dihapus} file sementara dihapus.");

        return self::SUCCESS;
    }
}
