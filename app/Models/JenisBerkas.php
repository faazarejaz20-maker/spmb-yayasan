<?php

namespace App\Models;

use App\Models\Concerns\MilikSekolah;
use Database\Factories\JenisBerkasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisBerkas extends Model
{
    /** @use HasFactory<JenisBerkasFactory> */
    use HasFactory, MilikSekolah;

    protected $table = 'jenis_berkas';

    protected $fillable = [
        'sekolah_id',
        'nama',
        'wajib',
        'jumlah_lembar',
        'petunjuk',
        'urutan',
        'aktif',
    ];

    protected $attributes = [
        'wajib' => false,
        'jumlah_lembar' => 1,
        'petunjuk' => null,
        'urutan' => 0,
        'aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'aktif' => 'boolean',
            'jumlah_lembar' => 'integer',
            'urutan' => 'integer',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
