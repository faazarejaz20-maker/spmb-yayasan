<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JawabanTambahan extends Model
{
    protected $table = 'jawaban_tambahan';

    protected $fillable = [
        'pendaftaran_id',
        'bagian',
        'kunci',
        'nilai',
    ];

    protected $attributes = [
        'nilai' => null,
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }
}
