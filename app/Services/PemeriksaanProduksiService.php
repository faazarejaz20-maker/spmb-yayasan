<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeriksaan kesiapan produksi (`php artisan spmb:periksa-produksi`).
 *
 * Setiap butir menghasilkan baris LULUS atau GAGAL beserta keterangan singkat.
 * Pemeriksaan sengaja tidak mengubah apa pun.
 */
class PemeriksaanProduksiService
{
    /**
     * @return array<int, array{kode: string, label: string, lulus: bool, keterangan: string}>
     */
    public function periksa(): array
    {
        return [
            $this->butir('LINGKUNGAN', 'APP_ENV = production', $this->appEnvProduksi(), 'APP_ENV saat ini: '.config('app.env')),
            $this->butir('DEBUG', 'APP_DEBUG = false', config('app.debug') === false, 'Nilai: '.var_export(config('app.debug'), true)),
            $this->butir('KUNCI', 'APP_KEY terisi', filled(config('app.key')), 'Panjang kunci: '.strlen((string) config('app.key'))),
            $this->butir('URL', 'APP_URL memakai https', str_starts_with((string) config('app.url'), 'https://'), 'URL: '.config('app.url')),
            $this->butir('COOKIE', 'Cookie sesi aman', $this->cookieAman(), $this->keteranganCookie()),
            $this->butir('HTTPS', 'Pemaksaan HTTPS aktif', $this->httpsDipaksa(), 'URL skema: '.parse_url((string) config('app.url'), PHP_URL_SCHEME)),
            $this->butir('PHP', 'PHP >= 8.3', PHP_VERSION_ID >= 80300, 'Versi: '.PHP_VERSION),
            $this->butir('EKSTENSI', 'Ekstensi PHP wajib ada', $this->ekstensiKurang() === [], $this->keteranganEkstensi()),
            $this->butir('IZIN', 'storage/ dan bootstrap/cache dapat ditulis', $this->izinTulis(), $this->keteranganIzin()),
            $this->butir('ENV', 'Berkas .env tidak terbaca publik', $this->envTidakTerbacaPublik(), $this->keteranganEnv()),
            $this->butir('STORAGE', 'Folder storage di luar docroot', $this->storageDiLuarPublik(), 'storage: '.storage_path()),
            $this->butir('TAUTAN', 'storage:link hanya ke disk konten publik', $this->tautanStorageAman(), $this->keteranganTautan()),
            $this->butir('PAKET', 'Tidak ada paket dev terpasang', $this->paketDev() === [], $this->keteranganPaket()),
            $this->butir('JADWAL', 'Scheduler cadangan terdaftar', $this->jadwalCadanganAda(), 'Periksa routes/console.php'),
            $this->butir('ANTREAN', 'Antrean tidak memakai driver sync', config('queue.default') !== 'sync', 'Driver: '.config('queue.default')),
            $this->butir('CACHE', 'Cache tidak memakai array', config('cache.default') !== 'array', 'Driver: '.config('cache.default')),
            $this->butir('SESI', 'Sesi tidak memakai array', config('session.driver') !== 'array', 'Driver: '.config('session.driver')),
        ];
    }

    /**
     * @return array{kode: string, label: string, lulus: bool, keterangan: string}
     */
    private function butir(string $kode, string $label, bool $lulus, string $keterangan): array
    {
        return [
            'kode' => $kode,
            'label' => $label,
            'lulus' => $lulus,
            'keterangan' => $keterangan,
        ];
    }

    private function appEnvProduksi(): bool
    {
        return app()->environment('production');
    }

    private function cookieAman(): bool
    {
        return config('session.secure') === true
            && config('session.http_only') !== false
            && in_array(config('session.same_site'), ['lax', 'strict'], true);
    }

    private function keteranganCookie(): string
    {
        return 'secure='.var_export(config('session.secure'), true)
            .', http_only='.var_export(config('session.http_only'), true)
            .', same_site='.config('session.same_site');
    }

    private function httpsDipaksa(): bool
    {
        return str_starts_with((string) config('app.url'), 'https://');
    }

    /**
     * @return array<int, string>
     */
    private function ekstensiKurang(): array
    {
        $wajib = (array) config('spmb.produksi.ekstensi', []);

        return array_values(array_filter(
            $wajib,
            fn (string $ekstensi) => ! extension_loaded($ekstensi)
        ));
    }

    private function keteranganEkstensi(): string
    {
        $kurang = $this->ekstensiKurang();

        return $kurang === [] ? 'Semua ekstensi wajib aktif' : 'Kurang: '.implode(', ', $kurang);
    }

    private function izinTulis(): bool
    {
        $folder = [
            storage_path(),
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($folder as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                return false;
            }
        }

        return true;
    }

    private function keteranganIzin(): string
    {
        $gagal = [];

        foreach ([storage_path(), storage_path('app'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path)) {
                $gagal[] = $path.' (tidak ada)';
            } elseif (! is_writable($path)) {
                $gagal[] = $path.' (tidak dapat ditulis)';
            }
        }

        return $gagal === [] ? 'Semua folder dapat ditulis' : implode('; ', $gagal);
    }

    private function envTidakTerbacaPublik(): bool
    {
        $env = base_path('.env');

        if (! is_file($env)) {
            return true;
        }

        // Windows tidak punya bit izin POSIX. Jangan mengarang hasil: anggap
        // lulus dengan catatan agar pemasang memeriksa lewat panel hosting.
        if (DIRECTORY_SEPARATOR === '\\') {
            return true;
        }

        $izin = fileperms($env);

        return $izin !== false && ($izin & 0o004) === 0;
    }

    private function keteranganEnv(): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return 'Windows: izin POSIX tidak tersedia; pastikan .env di luar docroot dan tidak dapat diunduh';
        }

        $env = base_path('.env');

        if (! is_file($env)) {
            return 'Berkas .env tidak ada (variabel dari lingkungan server)';
        }

        $izin = fileperms($env);

        return 'Izin: '.($izin !== false ? substr(sprintf('%o', $izin), -4) : 'tidak diketahui');
    }

    private function storageDiLuarPublik(): bool
    {
        $storage = realpath(storage_path());
        $publik = realpath(public_path());

        if ($storage === false || $publik === false) {
            return false;
        }

        return ! str_starts_with(
            strtolower(str_replace('\\', '/', $storage)).'/',
            strtolower(str_replace('\\', '/', $publik)).'/'
        );
    }

    private function tautanStorageAman(): bool
    {
        $tautan = public_path('storage');

        if (! file_exists($tautan) && ! is_link($tautan)) {
            return true;
        }

        $tujuan = realpath($tautan);
        $diizinkan = realpath(storage_path('app/public'));

        if ($tujuan === false || $diizinkan === false) {
            return false;
        }

        return $tujuan === $diizinkan;
    }

    private function keteranganTautan(): string
    {
        $tautan = public_path('storage');

        if (! file_exists($tautan) && ! is_link($tautan)) {
            return 'Belum ada tautan public/storage';
        }

        return 'Menunjuk ke: '.(realpath($tautan) ?: 'tidak diketahui');
    }

    /**
     * @return array<int, string>
     */
    private function paketDev(): array
    {
        $berkas = rtrim((string) config('spmb.produksi.vendor_path', base_path('vendor')), '/\\').'/composer/installed.json';

        if (! is_file($berkas)) {
            return ['composer/installed.json tidak ditemukan'];
        }

        $data = json_decode((string) file_get_contents($berkas), true);
        $daftar = is_array($data) && isset($data['packages']) ? $data['packages'] : (is_array($data) ? $data : []);

        $terpasang = array_map(
            fn ($paket) => strtolower((string) ($paket['name'] ?? '')),
            is_array($daftar) ? $daftar : []
        );

        $dev = array_map('strtolower', (array) config('spmb.produksi.paket_dev', []));

        return array_values(array_intersect($dev, $terpasang));
    }

    private function keteranganPaket(): string
    {
        $dev = $this->paketDev();

        return $dev === []
            ? 'Tidak ada paket dev terpasang'
            : 'Terpasang: '.implode(', ', $dev);
    }

    private function jadwalCadanganAda(): bool
    {
        try {
            $events = app(Schedule::class)->events();
        } catch (\Throwable) {
            return false;
        }

        foreach ($events as $event) {
            $perintah = (string) ($event->command ?? '');

            if (str_contains($perintah, 'spmb:cadangan')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Jumlah baris inti, dipakai `spmb:pulihkan` untuk menolak menimpa data.
     */
    public function basisDataKosong(): bool
    {
        $tabel = ['users', 'sekolah', 'pendaftaran', 'anak', 'berkas'];

        foreach ($tabel as $nama) {
            if (Schema::hasTable($nama) && DB::table($nama)->exists()) {
                return false;
            }
        }

        return true;
    }
}
