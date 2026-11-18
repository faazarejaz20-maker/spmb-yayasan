<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HeaderKeamanan
{
    /**
     * CSP enforce (bukan report-only) sejak tahap 6. Tidak ada
     * `unsafe-inline`/`unsafe-eval`: gaya dan skrip hanya dari berkas milik
     * aplikasi (hasil build Vite). Bila suatu saat butuh gaya inline, tambahkan
     * nonce di sini, jangan melonggarkan kebijakan.
     */
    private const CSP = "default-src 'self'; "
        ."script-src 'self'; "
        ."style-src 'self'; "
        ."img-src 'self' data:; "
        ."font-src 'self'; "
        ."connect-src 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'none'; "
        ."base-uri 'self'; "
        ."object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        return self::pasang($request, $next($request));
    }

    /**
     * Pasang seluruh header keamanan pada satu respons.
     *
     * Dipakai dua tempat: middleware `web` untuk respons normal, dan
     * `bootstrap/app.php` lewat `$exceptions->respond(...)` untuk halaman galat.
     * Halaman galat dirender di luar pipeline middleware, jadi tanpa pemasangan
     * kedua, 404/500 tidak memiliki CSP maupun header lain.
     */
    public static function pasang(Request $request, Response $response, ?bool $paksaNoStore = null): Response
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', self::CSP);

        // HSTS hanya di produksi dan hanya bila permintaan memang lewat HTTPS.
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // `$paksaNoStore` menghindari pemanggilan auth() saat merender galat
        // (mis. basis data mati) yang bisa memicu galat kedua di dalam handler.
        $noStore = $paksaNoStore ?? auth()->check();

        if ($noStore) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
