<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WajibGantiPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->wajib_ganti_password) {
            $rute = $request->route()?->getName();

            if (! in_array($rute, ['ganti-password', 'ganti-password.simpan', 'keluar'], true)) {
                return redirect()->route('ganti-password');
            }
        }

        return $next($request);
    }
}
