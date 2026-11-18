<?php

namespace App\Models;

use App\Models\Concerns\MilikSekolah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekeningSekolah extends Model
{
    use MilikSekolah;

    protected $table = 'rekening_sekolah';

    protected $fillable = [
        'sekolah_id',
        'bank',
        'nomor_rekening',
        'atas_nama',
        'aktif',
    ];

    protected $attributes = [
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
