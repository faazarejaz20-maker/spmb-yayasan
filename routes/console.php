<?php

use App\Console\Commands\BersihkanTokenSesi;
use App\Console\Commands\BersihkanTmp;
use App\Console\Commands\Cadangkan;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Jaring pengaman: hapus file sementara ekspor/ZIP yang tertinggal lebih dari
| satu jam. Ekspor dan ZIP normal sudah dihapus setelah dikirim atau gagal.
*/
Schedule::command(BersihkanTmp::class)->hourly();

/*
| Cadangan harian terenkripsi (K-13). Retensi 14 harian + 6 bulanan diatur
| oleh CadanganService::retensi().
*/
Schedule::command(Cadangkan::class)->dailyAt('01:30')->withoutOverlapping();

/*
| Token reset kedaluwarsa dan sesi lama dibersihkan setiap hari.
*/
Schedule::command(BersihkanTokenSesi::class)->dailyAt('02:15')->withoutOverlapping();
