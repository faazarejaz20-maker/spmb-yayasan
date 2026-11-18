<?php

namespace App\Console\Commands;

use App\Services\CadanganService;
use App\Services\PemeriksaanProduksiService;
use Illuminate\Console\Command;
use Throwable;

class PulihkanCadangan extends Command
{
    protected $signature = 'spmb:pulihkan {berkas : Nama atau path arsip cadangan} {--force : Paksa pulihkan walau basis data sudah berisi}';

    protected $description = 'Memulihkan basis data dan berkas privat dari arsip cadangan terenkripsi.';

    public function handle(CadanganService $cadangan, PemeriksaanProduksiService $pemeriksaan): int
    {
        $berkas = $this->jalur($cadangan, (string) $this->argument('berkas'));

        if (! is_file($berkas)) {
            $this->error('Berkas cadangan tidak ditemukan: '.basename($berkas));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $pemeriksaan->basisDataKosong()) {
            $this->error('Basis data tidak kosong. Pemulihan menimpa data yang ada.');
            $this->line('Jalankan ulang dengan --force bila Anda yakin, setelah membuat cadangan terbaru.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Pemulihan akan MENIMPA basis data dan folder berkas privat.');

            if (! $this->confirm('Lanjutkan pemulihan?')) {
                $this->info('Dibatalkan. Tidak ada data yang diubah.');

                return self::SUCCESS;
            }
        }

        try {
            $cadangan->pulihkan($berkas);
        } catch (Throwable $e) {
            $this->error('Pemulihan gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Pemulihan selesai. Periksa aplikasi, lalu masuk sebagai super admin.');

        return self::SUCCESS;
    }

    private function jalur(CadanganService $cadangan, string $berkas): string
    {
        if (is_file($berkas)) {
            return $berkas;
        }

        return $cadangan->folder().DIRECTORY_SEPARATOR.basename($berkas);
    }
}
