<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PeranMiddleware
{
    /**
     * @param  string  ...$peran  Daftar peran yang diizinkan.
     */
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('masuk');
        }

        if (! in_array($user->peran?->value, $peran, true)) {
            abort(403);
        }

        return $next($request);
    }
}
