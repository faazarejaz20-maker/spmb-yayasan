<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bersihkan token atur ulang kata sandi dan sesi yang sudah kedaluwarsa.
 *
 * Token reset berlaku 60 menit; sesi mengikuti `session.lifetime`. Baris lama
 * tidak berguna dan hanya membebani tabel.
 */
class BersihkanTokenSesi extends Command
{
    protected $signature = 'spmb:bersihkan-token-sesi';

    protected $description = 'Menghapus token reset kedaluwarsa dan sesi lama.';

    public function handle(): int
    {
        $token = 0;
        $sesi = 0;

        if (Schema::hasTable('password_reset_tokens')) {
            $token = DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subMinutes(90))
                ->delete();
        }

        if (Schema::hasTable('sessions')) {
            $batas = now()->subMinutes((int) config('session.lifetime', 480))->getTimestamp();

            $sesi = DB::table('sessions')
                ->where('last_activity', '<', $batas)
                ->delete();
        }

        $this->info("Token reset dihapus: {$token}. Sesi lama dihapus: {$sesi}.");

        return self::SUCCESS;
    }
}
