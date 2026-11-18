<?php

namespace App\Models;

use App\Models\Concerns\MilikSekolah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KontenSekolah extends Model
{
    use MilikSekolah;

    protected $table = 'konten_sekolah';

    protected $fillable = [
        'sekolah_id',
        'bagian',
        'judul',
        'isi',
        'gambar_path',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
