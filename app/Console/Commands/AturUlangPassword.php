<?php

namespace App\Console\Commands;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\Catat;
use App\Support\NomorHp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class AturUlangPassword extends Command
{
    protected $signature = 'spmb:atur-ulang-password {nomor_hp}';

    protected $description = 'Mengatur ulang kata sandi akun staf dari server (interaktif).';

    public function handle(): int
    {
        try {
            $nomor = NomorHp::normalisasi((string) $this->argument('nomor_hp'));
        } catch (\InvalidArgumentException $galat) {
            $this->error($galat->getMessage());

            return self::FAILURE;
        }

        if ($nomor === null) {
            $this->error('Nomor HP wajib diisi.');

            return self::FAILURE;
        }

        /** @var User|null $user */
        $user = User::query()->where('no_hp', $nomor)->first();

        if (! $user) {
            $this->error('Tidak ada akun dengan nomor HP tersebut.');

            return self::FAILURE;
        }

        if ($user->peran === PeranPengguna::Walisantri) {
            $this->error('Perintah ini hanya untuk akun staf. Akun walisantri diatur ulang oleh panitia.');

            return self::FAILURE;
        }

        $this->line('Akun ditemukan: '.$user->nama.' ('.$user->peran->label().').');

        $kataSandi = $this->secret('Kata sandi baru (minimal 8 karakter)');

        $validator = validator(
            ['kata_sandi' => $kataSandi],
            ['kata_sandi' => ['required', Password::min(8)->letters()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $galat) {
                $this->error($galat);
            }

            return self::FAILURE;
        }

        $konfirmasi = $this->secret('Ulangi kata sandi baru');

        if (! hash_equals((string) $kataSandi, (string) $konfirmasi)) {
            $this->error('Ulangi kata sandi tidak sesuai.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $kataSandi,
            'wajib_ganti_password' => true,
        ])->save();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        Catat::aksi('atur_ulang_kata_sandi_konsol', $user, [], $user->sekolah_id);

        $this->info('Kata sandi akun '.$user->nama.' berhasil diatur ulang.');
        $this->warn('Akun wajib mengganti kata sandi saat masuk berikutnya.');

        return self::SUCCESS;
    }
}
