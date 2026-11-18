<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Mengisi kolom `uuid` secara otomatis saat model dibuat.
 * Model yang memakai trait ini sebaiknya menjadikan `uuid` sebagai route key.
 */
trait PunyaUuid
{
    protected static function bootPunyaUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }
}
