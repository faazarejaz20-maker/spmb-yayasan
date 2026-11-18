<?php

namespace App\Console\Commands;

use App\Services\CadanganService;
use Illuminate\Console\Command;
use Throwable;

class Cadangkan extends Command
{
    protected $signature = 'spmb:cadangan';

    protected $description = 'Membuat cadangan basis data dan berkas privat yang terenkripsi.';

    public function handle(CadanganService $cadangan): int
    {
        try {
            $path = $cadangan->buat();
        } catch (Throwable $e) {
            $this->error('Cadangan gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cadangan dibuat: '.$path);

        $dihapus = $cadangan->retensi();

        if ($dihapus !== []) {
            $this->info('Retensi menghapus '.count($dihapus).' cadangan lama.');
        }

        $this->line('Total arsip tersimpan: '.count($cadangan->daftar()));

        return self::SUCCESS;
    }
}
