<?php

namespace App\Models\Concerns;

use App\Enums\PeranPengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope yang membatasi baris pada `sekolah_id` milik pengguna yang sedang
 * masuk, bila pengguna tersebut `panitia` atau `admin`. Bila tidak ada pengguna
 * (konsol, seeder, antrean), scope tidak aktif.
 *
 * Dipasang pada model yang memiliki `sekolah_id` langsung maupun lewat relasi
 * `pendaftaran` (lihat properti `$sekolahMelalui`).
 */
trait MilikSekolah
{
    /**
     * Nama relasi yang menghubungkan model ke `pendaftaran`, bila model tidak
     * punya kolom `sekolah_id` sendiri (mis. Berkas, Pembayaran).
     */
    protected string $sekolahMelalui = 'pendaftaran';

    protected static function bootMilikSekolah(): void
    {
        static::addGlobalScope('milik_sekolah', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();

            $peran = $user->peran instanceof PeranPengguna
                ? $user->peran->value
                : $user->peran;

            if (! in_array($peran, [PeranPengguna::Admin->value, PeranPengguna::Panitia->value], true)) {
                return;
            }

            if (! $user->sekolah_id) {
                return;
            }

            $instance = $builder->getModel();

            if ($instance->hasSekolahLangsung()) {
                $builder->where($instance->getTable().'.sekolah_id', $user->sekolah_id);

                return;
            }

            $relasi = $instance->sekolahMelalui;

            $builder->whereHas($relasi, function (Builder $q) use ($user) {
                $q->where('pendaftaran.sekolah_id', $user->sekolah_id);
            });
        });
    }

    public function hasSekolahLangsung(): bool
    {
        return \Schema::hasColumn($this->getTable(), 'sekolah_id');
    }

    /**
     * Membuang scope `milik_sekolah` untuk keperluan khusus.
     * HANYA dipakai di tempat yang disebut jelas pada komentar pemanggil.
     */
    public function scopeTanpaSekolah(Builder $query): Builder
    {
        return $query->withoutGlobalScope('milik_sekolah');
    }
}
