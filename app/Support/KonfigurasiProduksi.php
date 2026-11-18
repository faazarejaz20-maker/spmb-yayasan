<?php

namespace App\Support;

use RuntimeException;

/**
 * Penjaga konfigurasi produksi (K-11).
 *
 * Dipanggil saat aplikasi boot di `AppServiceProvider`. Aplikasi harus
 * menolak berjalan bila `APP_ENV=production` tetapi `APP_DEBUG=true` atau
 * `APP_KEY` kosong. Keduanya membuat data pendaftar bocor atau sesi tidak aman.
 */
class KonfigurasiProduksi
{
    public static function pastikan(bool $produksi, bool $debug, ?string $kunci): void
    {
        if (! $produksi) {
            return;
        }

        if ($debug) {
            throw new RuntimeException(
                'Aplikasi menolak berjalan: APP_DEBUG harus false saat APP_ENV=production. '
                .'Jejak galat tidak boleh tampil ke pengguna.'
            );
        }

        if (blank($kunci)) {
            throw new RuntimeException(
                'Aplikasi menolak berjalan: APP_KEY kosong. Jalankan `php artisan key:generate` '
                .'sebelum membuka aplikasi.'
            );
        }
    }
}
