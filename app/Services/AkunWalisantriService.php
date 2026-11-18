<?php

namespace App\Services;

use App\Enums\PeranPengguna;
use App\Exceptions\AturanBisnisException;
use App\Models\Anak;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Policies\AkunWalisantriPolicy;
use App\Support\Catat;
use App\Support\KataSandiSementara;
use App\Support\NomorHp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembuatan akun walisantri oleh panitia, atur ulang kata sandi, dan
 * perpindahan anak antar akun.
 */
class AkunWalisantriService
{
    public function __construct(private readonly AkunWalisantriPolicy $policy) {}

    /**
     * @return array{user: User, kata_sandi: string}
     */
    public function buatAkun(User $staf, string $nama, string $noHp, ?string $email = null): array
    {
        $this->pastikanStaf($staf);

        $nama = trim($nama);

        if ($nama === '') {
            throw new AturanBisnisException('Nama walisantri wajib diisi.');
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
            throw new AturanBisnisException(
                'Nomor HP sudah terdaftar. Gunakan "Cari akun" bila walisantri sudah punya akun.'
            );
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
            'peran' => PeranPengguna::Walisantri,
            'sekolah_id' => null,
            'aktif' => true,
            'wajib_ganti_password' => true,
            'dibuat_oleh' => $staf->id,
        ])->save();

        Catat::aksi('buat_akun_walisantri', $user, [], $staf->sekolah_id);

        return ['user' => $user->refresh(), 'kata_sandi' => $kataSandi];
    }

    /**
     * Cari akun walisantri berdasarkan nomor HP persis, hanya bila lolos
     * policy. Tidak ditemukan dan di luar lingkup menghasilkan nilai yang
     * sama (`null`) supaya keberadaan akun tidak bocor.
     */
    public function cari(User $staf, string $noHp): ?User
    {
        try {
            $nomor = NomorHp::normalisasi($noHp);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($nomor === null) {
            return null;
        }

        $user = User::query()
            ->where('no_hp', $nomor)
            ->where('peran', PeranPengguna::Walisantri)
            ->first();

        if (! $user || ! $this->policy->kelola($staf, $user)) {
            return null;
        }

        return $user;
    }

    /**
     * Atur ulang kata sandi dan kembalikan kata sandi sementara (hanya untuk
     * ditampilkan sekali, tidak disimpan).
     */
    public function aturUlang(User $staf, User $target): string
    {
        $this->pastikanStaf($staf);

        if (! $this->policy->kelola($staf, $target)) {
            throw new AturanBisnisException('Akun ini tidak berada dalam lingkup kewenangan Anda.');
        }

        $kataSandi = KataSandiSementara::buat(10);

        $target->forceFill([
            'password' => $kataSandi,
            'wajib_ganti_password' => true,
        ])->save();

        $this->hapusSesi($target);

        Catat::aksi('atur_ulang_kata_sandi', $target, [], $staf->sekolah_id);

        return $kataSandi;
    }

    /**
     * Pindahkan satu anak ke akun walisantri lain.
     */
    public function pindahAnak(User $staf, Anak $anak, User $tujuan): void
    {
        $this->pastikanStaf($staf);

        if ((int) $anak->user_id === (int) $tujuan->id) {
            throw new AturanBisnisException('Anak ini sudah berada di akun tujuan.');
        }

        $asal = User::query()->find($anak->user_id);

        if (! $asal || ! $this->policy->kelola($staf, $asal) || ! $this->policy->kelola($staf, $tujuan)) {
            throw new AturanBisnisException('Akun asal atau tujuan tidak berada dalam lingkup kewenangan Anda.');
        }

        $sekolahPendaftaran = Pendaftaran::query()
            ->tanpaSekolah()
            ->where('anak_id', $anak->id)
            ->pluck('sekolah_id')
            ->unique()
            ->values();

        $diLuar = $sekolahPendaftaran->contains(fn ($id) => (int) $id !== (int) $staf->sekolah_id);

        if ($diLuar) {
            throw new AturanBisnisException(
                'Anak ini punya pendaftaran di sekolah lain. Perpindahan hanya boleh dilakukan panitia sekolah tempat semua pendaftarannya berada.'
            );
        }

        DB::transaction(function () use ($anak, $tujuan, $asal, $staf) {
            $anak->forceFill(['user_id' => $tujuan->id])->save();

            Catat::aksi('pindah_anak', $anak, [
                'akun_asal' => $asal->id,
                'akun_tujuan' => $tujuan->id,
            ], $staf->sekolah_id);
        });
    }

    private function pastikanStaf(User $staf): void
    {
        if (! in_array($staf->peran, [PeranPengguna::Panitia, PeranPengguna::Admin], true)) {
            throw new AturanBisnisException('Hanya panitia atau admin yang boleh mengelola akun walisantri.');
        }

        if (! $staf->sekolah_id) {
            throw new AturanBisnisException('Akun staf ini tidak terikat pada sekolah.');
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
