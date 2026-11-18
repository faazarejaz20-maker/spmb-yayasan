<?php

namespace App\Models;

use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Models\Concerns\MilikSekolah;
use App\Models\Concerns\PunyaUuid;
use Database\Factories\PendaftaranFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pendaftaran extends Model
{
    /** @use HasFactory<PendaftaranFactory> */
    use HasFactory, PunyaUuid, MilikSekolah;

    protected $table = 'pendaftaran';

    /**
     * Kolom sensitif (`status_hasil`, `status_murid`, `nomor_peserta`,
     * `dikirim_pada`, `diputuskan_oleh`, `diputuskan_pada`, `catatan_panitia`)
     * sengaja tidak fillable. Semua diisi lewat `forceFill()` di service.
     */
    protected $fillable = [
        'anak_id',
        'sekolah_id',
        'gelombang_id',
        'program',
        'lulusan_yayasan',
        'pilihan_iap',
        'setuju_biaya_pada',
        'setuju_data_pada',
        'dibuat_oleh',
    ];

    /**
     * Nilai bawaan di memori agar strict mode Laravel tidak melempar galat
     * saat atribut belum diisi. Nilai yang sama juga menjadi bawaan kolom.
     */
    protected $attributes = [
        'status_hasil' => 'Baru',
        'status_murid' => null,
        'nomor_peserta' => null,
        'program' => null,
        'lulusan_yayasan' => false,
        'pilihan_iap' => null,
        'setuju_biaya_pada' => null,
        'setuju_data_pada' => null,
        'catatan_panitia' => null,
        'dikirim_pada' => null,
    ];

    protected function casts(): array
    {
        return [
            'status_hasil' => StatusHasil::class,
            'status_murid' => StatusMurid::class,
            'lulusan_yayasan' => 'boolean',
            'pilihan_iap' => 'decimal:0',
            'setuju_biaya_pada' => 'datetime',
            'setuju_data_pada' => 'datetime',
            'dikirim_pada' => 'datetime',
            'diputuskan_pada' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Pendaftaran yang sudah dikirim. Draf tidak ikut.
     */
    public function scopeTerkirim(Builder $query): Builder
    {
        return $query->whereNotNull('dikirim_pada');
    }

    public function sudahDikirim(): bool
    {
        return $this->dikirim_pada !== null;
    }

    public function masihDraf(): bool
    {
        return $this->dikirim_pada === null;
    }

    /**
     * Walisantri hanya boleh mengubah data draf atau saat Perlu perbaikan.
     */
    public function bolehDiubah(): bool
    {
        return $this->masihDraf()
            || $this->status_hasil === StatusHasil::PerluPerbaikan;
    }

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class);
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(Gelombang::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function diputuskanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    public function berkas(): HasMany
    {
        return $this->hasMany(Berkas::class);
    }

    public function jawabanTambahan(): HasMany
    {
        return $this->hasMany(JawabanTambahan::class);
    }

    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function daftarUlang(): HasOne
    {
        return $this->hasOne(DaftarUlang::class);
    }
}
