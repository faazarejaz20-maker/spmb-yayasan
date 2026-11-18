<?php

namespace App\Models;

use App\Models\Concerns\MilikSekolah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanIsian extends Model
{
    use MilikSekolah;

    protected $table = 'pengaturan_isian';

    protected $fillable = [
        'sekolah_id',
        'kunci_isian',
        'tampil',
        'wajib',
    ];

    protected function casts(): array
    {
        return [
            'tampil' => 'boolean',
            'wajib' => 'boolean',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
