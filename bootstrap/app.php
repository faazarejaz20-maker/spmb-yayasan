<?php

use App\Http\Middleware\AkunAktif;
use App\Http\Middleware\HeaderKeamanan;
use App\Http\Middleware\PeranMiddleware;
use App\Http\Middleware\SesiKedaluwarsa;
use App\Http\Middleware\WajibGantiPassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'peran' => PeranMiddleware::class,
            'wajib_ganti_password' => WajibGantiPassword::class,
        ]);

        // Rute autentikasi memakai nama Indonesia (`masuk`), bukan `login`.
        $middleware->redirectGuestsTo(fn () => route('masuk'));

        $middleware->redirectUsersTo(fn ($request) => match ($request->user()?->peran?->value) {
            'walisantri' => route('walisantri.beranda'),
            'panitia', 'admin' => route('panitia.beranda'),
            'super_admin' => route('super.beranda'),
            'yayasan' => route('yayasan.beranda'),
            default => route('beranda'),
        });

        $middleware->web(append: [
            HeaderKeamanan::class,
            AkunAktif::class,
            SesiKedaluwarsa::class,
            WajibGantiPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respons galat (404, 500, 419, ...) dirender di luar pipeline
        // middleware, jadi header keamanan dipasang ulang di sini. Tanpa ini,
        // halaman galat tidak memiliki CSP.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            return HeaderKeamanan::pasang($request, $response, true);
        });
    })->create();
