<?php

namespace App\Providers;

use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\KontenSekolah;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Services\HalamanPublikService;
use App\Support\KonfigurasiProduksi;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tolak boot bila konfigurasi produksi berbahaya (K-11).
        KonfigurasiProduksi::pastikan(
            $this->app->environment('production'),
            (bool) config('app.debug'),
            config('app.key'),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }

        if (! $this->app->environment('production')) {
            Model::preventLazyLoading();

            if (method_exists(Model::class, 'shouldBeStrict')) {
                Model::shouldBeStrict();
            }
        }

        Carbon::setLocale('id');

        // Proksi tepercaya dari konfigurasi, bukan *.
        $this->konfigurasiProksi();

        $this->batasLajuUnduhan();

        $this->invalidasiHalamanPublik();
    }

    /**
     * Cache halaman publik dibuang saat data yang tampil berubah. Kuncinya
     * versi global, jadi tidak ada data per pengguna di cache bersama.
     */
    protected function invalidasiHalamanPublik(): void
    {
        $dipantau = [
            Sekolah::class,
            KontenSekolah::class,
            Gelombang::class,
            JenisBerkas::class,
            TahunAjaran::class,
        ];

        $lupakan = function (Model $model) use ($dipantau): void {
            if (in_array($model::class, $dipantau, true)) {
                app(HalamanPublikService::class)->lupakan();
            }
        };

        // Listener harus didaftarkan pada tiap model konkret. Memanggil
        // `Model::saved()` pada kelas dasar hanya mendaftarkan event untuk
        // `Illuminate\Database\Eloquent\Model`, yang tidak pernah dipicu oleh
        // model turunan.
        foreach ($dipantau as $kelas) {
            $kelas::saved($lupakan);
            $kelas::deleted($lupakan);
        }
    }

    /**
     * Batas laju unduhan dan cetak per pengguna (K-09, K-20).
     */
    protected function batasLajuUnduhan(): void
    {
        RateLimiter::for('ekspor', fn (Request $request) => Limit::perMinute(5)
            ->by('ekspor:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('zip', fn (Request $request) => Limit::perMinute(5)
            ->by('zip:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('cetak', fn (Request $request) => Limit::perHour(120)
            ->by('cetak:'.($request->user()?->id ?? $request->ip())));
    }

    protected function konfigurasiProksi(): void
    {
        $proksi = env('TRUSTED_PROXIES');

        if ($proksi === null || $proksi === '') {
            return;
        }

        Request::setTrustedProxies(
            array_filter(array_map('trim', explode(',', $proksi))),
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
    }
}
