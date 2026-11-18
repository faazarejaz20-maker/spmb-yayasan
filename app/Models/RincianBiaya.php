<?php

namespace App\Models;

use App\Enums\TipeBiaya;
use App\Models\Concerns\MilikSekolah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RincianBiaya extends Model
{
    use MilikSekolah;

    protected $table = 'rincian_biaya';

    protected $fillable = [
        'sekolah_id',
        'tahun_ajaran_id',
        'kelompok',
        'nama',
        'nominal',
        'tipe',
        'catatan',
        'urutan',
        'aktif',
    ];

    protected $attributes = [
        'tahun_ajaran_id' => null,
        'kelompok' => null,
        'nominal' => null,
        'tipe' => 'tetap',
        'catatan' => null,
        'urutan' => 0,
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeBiaya::class,
            'nominal' => 'decimal:0',
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
