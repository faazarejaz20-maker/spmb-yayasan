<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SesiKedaluwarsa
{
    /**
     * Batas tidak aktif (menit): staf 120, walisantri 480.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $terakhir = $request->session()->get('waktu_terakhir_aktif');

            $batas = $user->peran?->staf() ? 120 : 480;

            if ($terakhir && (time() - (int) $terakhir) > ($batas * 60)) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('masuk')
                    ->with('pesan', 'Sesi berakhir, silakan masuk lagi.');
            }

            $request->session()->put('waktu_terakhir_aktif', time());
        }

        return $next($request);
    }
}
