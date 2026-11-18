<?php

namespace App\Console\Commands;

use App\Services\PemeriksaanProduksiService;
use Illuminate\Console\Command;

class PeriksaProduksi extends Command
{
    protected $signature = 'spmb:periksa-produksi';

    protected $description = 'Memeriksa kesiapan konfigurasi dan server untuk produksi.';

    public function handle(PemeriksaanProduksiService $pemeriksaan): int
    {
        $hasil = $pemeriksaan->periksa();

        $this->table(
            ['Butir', 'Pemeriksaan', 'Hasil', 'Keterangan'],
            array_map(fn (array $baris) => [
                $baris['kode'],
                $baris['label'],
                $baris['lulus'] ? 'LULUS' : 'GAGAL',
                $baris['keterangan'],
            ], $hasil)
        );

        $gagal = array_values(array_filter($hasil, fn (array $baris) => ! $baris['lulus']));

        if ($gagal === []) {
            $this->info('Semua pemeriksaan LULUS. Aplikasi siap dibuka.');

            return self::SUCCESS;
        }

        $this->error(count($gagal).' pemeriksaan GAGAL. Perbaiki sebelum membuka aplikasi.');

        return self::FAILURE;
    }
}
