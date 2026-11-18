<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Notifications\ResetKataSandi;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'nama',
        'no_hp',
        'email',
        'password',
        'terakhir_masuk',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Sekolah dimuat bersama akun supaya tampilan panel tidak memicu lazy
     * loading pada relasi ini di setiap halaman.
     *
     * @var array<int, string>
     */
    protected $with = ['sekolah'];

    /**
     * Nilai bawaan di memori, supaya model yang baru dibuat tanpa menyebut
     * kolom ini tetap punya atribut lengkap (strict mode Laravel melempar
     * galat bila atribut tidak ada).
     */
    protected $attributes = [
        'aktif' => true,
        'wajib_ganti_password' => false,
        'email' => null,
        'no_hp' => null,
        'sekolah_id' => null,
        'terakhir_masuk' => null,
    ];

    protected function casts(): array
    {
        return [
            'peran' => PeranPengguna::class,
            'wajib_ganti_password' => 'boolean',
            'aktif' => 'boolean',
            'terakhir_masuk' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $terikat = in_array($user->peran?->value, [
                PeranPengguna::Admin->value,
                PeranPengguna::Panitia->value,
            ], true);

            if ($terikat && ! $user->sekolah_id) {
                throw new \DomainException('Akun panitia dan admin wajib memiliki sekolah.');
            }

            if (! $terikat && $user->sekolah_id) {
                throw new \DomainException('Peran ini tidak boleh memiliki sekolah.');
            }

            if (blank($user->no_hp) && blank($user->email)) {
                throw new \DomainException('Nomor HP atau email wajib diisi.');
            }
        });
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(Anak::class);
    }

    public function orangTua(): HasMany
    {
        return $this->hasMany(OrangTua::class);
    }

    /**
     * Tabel `users` tidak punya kolom `remember_token` (sesuai
     * `docs/struktur-tabel.md`), sehingga fitur "ingat saya" tidak dipakai.
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    /**
     * Otentikasi memakai kata sandi. Model memakai cast hashed sehingga
     * otomatis ter-hash; metode ini hanya untuk memeriksa kesesuaian.
     */
    public function cocokKataSandi(string $kataSandi): bool
    {
        return Hash::check($kataSandi, $this->password);
    }

    /**
     * Surat atur ulang kata sandi memakai tautan bertanda tangan sendiri,
     * bukan surat bawaan Laravel (yang memuat alamat situs tanpa tanda tangan).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetKataSandi($token));
    }
}
