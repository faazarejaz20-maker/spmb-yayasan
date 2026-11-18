<?php

namespace App\Console\Commands;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\NomorHp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class BuatSuperAdmin extends Command
{
    protected $signature = 'spmb:buat-super-admin';

    protected $description = 'Membuat akun super admin pertama (interaktif).';

    public function handle(): int
    {
        $this->info('Membuat akun super admin baru.');

        $nama = $this->ask('Nama lengkap');

        $noHp = $this->ask('Nomor HP (format 08xx atau +62)');

        try {
            $noHp = NomorHp::normalisasi($noHp);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (User::where('no_hp', $noHp)->exists()) {
            $this->error('Nomor HP sudah terdaftar.');

            return self::FAILURE;
        }

        $kataSandi = $this->secret('Kata sandi (minimal 8 karakter)');

        $validator = validator(
            ['kata_sandi' => $kataSandi],
            ['kata_sandi' => ['required', Password::min(8)->letters()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $galat) {
                $this->error($galat);
            }

            return self::FAILURE;
        }

        $konfirmasi = $this->secret('Ulangi kata sandi');

        if (! hash_equals($kataSandi, $konfirmasi)) {
            $this->error('Ulangi kata sandi tidak sesuai.');

            return self::FAILURE;
        }

        $user = User::forceCreate([
            'nama' => $nama,
            'peran' => PeranPengguna::SuperAdmin,
            'sekolah_id' => null,
            'no_hp' => $noHp,
            'email' => null,
            'password' => Hash::make($kataSandi),
            'wajib_ganti_password' => true,
            'aktif' => true,
        ]);

        $this->info("Super admin '{$user->nama}' berhasil dibuat.");
        $this->warn('Akun ini wajib mengganti kata sandi saat masuk pertama kali.');

        return self::SUCCESS;
    }
}
