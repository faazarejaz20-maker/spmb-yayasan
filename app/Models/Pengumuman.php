<?php

namespace App\Models;

use App\Models\Concerns\MilikSekolah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengumuman extends Model
{
    use MilikSekolah;

    protected $table = 'pengumuman';

    protected $fillable = [
        'sekolah_id',
        'gelombang_id',
        'diumumkan_oleh',
        'diumumkan_pada',
        'teks_pesan',
    ];

    protected $attributes = [
        'diumumkan_oleh' => null,
        'diumumkan_pada' => null,
        'teks_pesan' => null,
    ];

    protected function casts(): array
    {
        return [
            'diumumkan_pada' => 'datetime',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(Gelombang::class);
    }

    public function pengumum(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diumumkan_oleh');
    }
}
