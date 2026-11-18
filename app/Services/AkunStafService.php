<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Exceptions\AturanBisnisException;
use App\Models\User;
use App\Support\Catat;
use App\Support\KataSandiSementara;
use App\Support\NomorHp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembuatan dan pengelolaan akun staf (panitia, admin sekolah, yayasan).
 *
 * Peran dan sekolah selalu ditentukan kode, tidak pernah diterima mentah dari
 * formulir. Kata sandi sementara hanya dikembalikan sekali dan tidak disimpan
 * dalam bentuk terbaca maupun ditulis ke log.
 */
class AkunStafService
{
    /**
     * @return array{user: User, kata_sandi: string}
     */
    public function buat(
        User $pelaku,
        PeranPengguna $peran,
        ?int $sekolahId,
        string $nama,
        string $noHp,
        ?string $email = null,
    ): array {
        $sekolahId = $this->sekolahUntukPeranBaru($pelaku, $peran, $sekolahId);

        $nama = trim($nama);

        if ($nama === '') {
            throw new AturanBisnisException('Nama akun wajib diisi.');
        }

        try {
            $nomor = NomorHp::normalisasi($noHp);
        } catch (\InvalidArgumentException $galat) {
            throw new AturanBisnisException($galat->getMessage());
        }

        if ($nomor === null) {
            throw new AturanBisnisException('Nomor HP wajib diisi.');
        }

        if (User::query()->where('no_hp', $nomor)->exists()) {
            throw new AturanBisnisException('Nomor HP sudah dipakai akun lain.');
        }

        $email = filled($email) ? strtolower(trim((string) $email)) : null;

        if ($email !== null && User::query()->where('email', $email)->exists()) {
            throw new AturanBisnisException('Email sudah dipakai akun lain.');
        }

        $kataSandi = KataSandiSementara::buat(10);

        $user = new User([
            'nama' => $nama,
            'no_hp' => $nomor,
            'email' => $email,
            'password' => $kataSandi,
        ]);

        $user->forceFill([
            'peran' => $peran,
            'sekolah_id' => $sekolahId,
            'aktif' => true,
            'wajib_ganti_password' => true,
            'dibuat_oleh' => $pelaku->id,
        ])->save();

        Catat::aksi('buat_akun_staf', $user, [
            'peran' => $peran->value,
            'sekolah_id' => $sekolahId,
        ], $sekolahId ?? $pelaku->sekolah_id);

        return ['user' => $user->refresh(), 'kata_sandi' => $kataSandi];
    }

    public function bolehKelola(User $pelaku, User $target): bool
    {
        if ($pelaku->peran === PeranPengguna::Admin) {
            return $target->peran === PeranPengguna::Panitia
                && $pelaku->sekolah_id !== null
                && (int) $pelaku->sekolah_id === (int) $target->sekolah_id;
        }

        if ($pelaku->peran === PeranPengguna::SuperAdmin) {
            if ($target->peran === PeranPengguna::Admin) {
                return $target->sekolah_id !== null;
            }

            if ($target->peran === PeranPengguna::Yayasan) {
                return $target->sekolah_id === null;
            }

            if ($target->peran === PeranPengguna::SuperAdmin) {
                return true;
            }
        }

        return false;
    }

    public function nonaktifkan(User $pelaku, User $target): void
    {
        $this->pastikanBolehKelola($pelaku, $target);

        if ($target->peran === PeranPengguna::SuperAdmin) {
            if ((int) $pelaku->id === (int) $target->id) {
                throw new AturanBisnisException('Anda tidak dapat menonaktifkan akun sendiri.');
            }

            $aktif = User::query()
                ->where('peran', PeranPengguna::SuperAdmin)
                ->where('aktif', true)
                ->count();

            if ($aktif <= 1) {
                throw new AturanBisnisException('Super admin terakhir yang aktif tidak dapat dinonaktifkan.');
            }
        }

        $target->forceFill(['aktif' => false])->save();
        $this->hapusSesi($target);

        Catat::aksi('nonaktifkan_akun_staf', $target, [
            'peran' => $target->peran?->value,
        ], $target->sekolah_id ?? $pelaku->sekolah_id);
    }

    public function aktifkan(User $pelaku, User $target): void
    {
        $this->pastikanBolehKelola($pelaku, $target);

        $target->forceFill(['aktif' => true])->save();

        Catat::aksi('aktifkan_akun_staf', $target, [
            'peran' => $target->peran?->value,
        ], $target->sekolah_id ?? $pelaku->sekolah_id);
    }

    /**
     * Atur ulang kata sandi dan kembalikan kata sandi sementara sekali pakai.
     */
    public function aturUlang(User $pelaku, User $target): string
    {
        $this->pastikanBolehKelola($pelaku, $target);

        $kataSandi = KataSandiSementara::buat(10);

        $target->forceFill([
            'password' => $kataSandi,
            'wajib_ganti_password' => true,
        ])->save();

        $this->hapusSesi($target);

        Catat::aksi('atur_ulang_kata_sandi_staf', $target, [
            'peran' => $target->peran?->value,
        ], $target->sekolah_id ?? $pelaku->sekolah_id);

        return $kataSandi;
    }

    private function sekolahUntukPeranBaru(User $pelaku, PeranPengguna $peran, ?int $sekolahId): ?int
    {
        if ($pelaku->peran === PeranPengguna::Admin) {
            if ($peran !== PeranPengguna::Panitia) {
                throw new AturanBisnisException('Admin sekolah hanya boleh membuat akun panitia.');
            }

            if (! $pelaku->sekolah_id) {
                throw new AturanBisnisException('Akun admin ini tidak terikat pada sekolah.');
            }

            return (int) $pelaku->sekolah_id;
        }

        if ($pelaku->peran === PeranPengguna::SuperAdmin) {
            if ($peran === PeranPengguna::Admin) {
                $sekolahId = (int) $sekolahId;

                if ($sekolahId <= 0 || ! \App\Models\Sekolah::query()->whereKey($sekolahId)->exists()) {
                    throw new AturanBisnisException('Pilih sekolah untuk akun admin.');
                }

                return $sekolahId;
            }

            if ($peran === PeranPengguna::Yayasan) {
                return null;
            }
        }

        throw new AturanBisnisException('Peran akun ini tidak dapat dibuat dari antarmuka.');
    }

    private function pastikanBolehKelola(User $pelaku, User $target): void
    {
        if (! $this->bolehKelola($pelaku, $target)) {
            throw new AturanBisnisException('Akun ini tidak berada dalam lingkup kewenangan Anda.');
        }

        if ((int) $pelaku->id === (int) $target->id) {
            throw new AturanBisnisException('Anda tidak dapat mengelola akun sendiri.');
        }
    }

    private function hapusSesi(User $user): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
