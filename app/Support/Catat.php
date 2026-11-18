<?php

namespace App\Support;

use App\Models\LogAktivitas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Catat
{
    /**
     * Menulis satu baris ke `log_aktivitas`.
     *
     * @param  string  $aksi  Nama aksi singkat, mis. `masuk`, `keluar`.
     * @param  Model|null  $objek  Objek yang dikenai aksi.
     * @param  array<string, mixed>  $data  Ringkasan perubahan (tanpa data sensitif).
     * @param  int|null  $sekolahId  Id sekolah bila diketahui.
     */
    public static function aksi(
        string $aksi,
        ?Model $objek = null,
        array $data = [],
        ?int $sekolahId = null
    ): LogAktivitas {
        $user = Auth::user();

        return LogAktivitas::create([
            'user_id' => $user?->id,
            'sekolah_id' => $sekolahId ?? self::deteksiSekolah($objek),
            'aksi' => $aksi,
            'objek' => $objek ? class_basename($objek) : null,
            'objek_id' => $objek?->getKey(),
            'data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()?->ip(),
        ]);
    }

    private static function deteksiSekolah(?Model $objek): ?int
    {
        if (! $objek) {
            return Auth::user()?->sekolah_id;
        }

        if (isset($objek->sekolah_id)) {
            return $objek->sekolah_id;
        }

        if (method_exists($objek, 'pendaftaran') && $objek->relationLoaded('pendaftaran')) {
            return $objek->pendaftaran?->sekolah_id;
        }

        return Auth::user()?->sekolah_id;
    }
}
