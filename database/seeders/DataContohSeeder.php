<?php

namespace Database\Seeders;

use App\Enums\HubunganOrangTua;
use App\Enums\PeranPengguna;
use App\Enums\StatusBerkas;
use App\Enums\StatusHasil;
use App\Enums\StatusMurid;
use App\Models\Anak;
use App\Models\Berkas;
use App\Models\DaftarUlang;
use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Pengumuman;
use App\Models\RekeningSekolah;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DataContohSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DataContohSeeder tidak boleh dijalankan di lingkungan produksi.');
        }

        $tahun = TahunAjaran::query()->where('nama', '2027/2028')->firstOrFail();
        $akun = [];

        $kataSandiSuper = Str::random(12);
        $akun[] = ['label' => 'Super admin', 'identitas' => '6280000000000', 'kata_sandi' => $kataSandiSuper];
        User::create([
            'nama' => 'Super Admin Demo',
            'peran' => PeranPengguna::SuperAdmin,
            'no_hp' => '6280000000000',
            'email' => null,
            'password' => Hash::make($kataSandiSuper),
            'aktif' => true,
        ]);

        $sekolah = Sekolah::all()->keyBy('kode');

        $kataSandiYayasan = Str::random(12);
        User::create([
            'nama' => 'Yayasan Demo',
            'peran' => PeranPengguna::Yayasan,
            'sekolah_id' => null,
            'no_hp' => '6280000000001',
            'email' => null,
            'password' => Hash::make($kataSandiYayasan),
            'aktif' => true,
        ]);
        $akun[] = ['label' => 'Yayasan', 'identitas' => '6280000000001', 'kata_sandi' => $kataSandiYayasan];

        foreach ($sekolah as $kode => $s) {
            $kataSandiAdmin = Str::random(12);
            $admin = User::create([
                'nama' => "Admin {$kode} Demo",
                'peran' => PeranPengguna::Admin,
                'sekolah_id' => $s->id,
                'no_hp' => '6281'.fake()->unique()->numerify('#########'),
                'email' => null,
                'password' => Hash::make($kataSandiAdmin),
                'aktif' => true,
            ]);
            $akun[] = ['label' => "Admin {$kode}", 'identitas' => $admin->no_hp, 'kata_sandi' => $kataSandiAdmin];

            for ($i = 1; $i <= 2; $i++) {
                $kataSandiPanitia = Str::random(12);
                $panitia = User::create([
                    'nama' => "Panitia {$kode} {$i} Demo",
                    'peran' => PeranPengguna::Panitia,
                    'sekolah_id' => $s->id,
                    'no_hp' => '6282'.fake()->unique()->numerify('#########'),
                    'email' => null,
                    'password' => Hash::make($kataSandiPanitia),
                    'aktif' => true,
                ]);
                $akun[] = ['label' => "Panitia {$kode} {$i}", 'identitas' => $panitia->no_hp, 'kata_sandi' => $kataSandiPanitia];
            }
        }

        // Buka gelombang pertama setiap sekolah khusus lingkungan demo.
        $gelombang = [];

        foreach ($sekolah as $kode => $s) {
            $g = Gelombang::query()
                ->where('sekolah_id', $s->id)
                ->where('tahun_ajaran_id', $tahun->id)
                ->orderBy('id')
                ->firstOrFail();

            $g->forceFill([
                'status' => 'dibuka',
                'tanggal_buka' => now()->subDays(30)->toDateString(),
                'tanggal_tutup' => now()->addDays(60)->toDateString(),
                'kuota' => 40,
                'tautan_grup_wa' => 'https://chat.whatsapp.com/DemoGrup'.strtoupper($kode),
            ])->save();

            $gelombang[$kode] = $g->refresh();
        }

        // Rekening contoh untuk SD agar halaman pembayaran dapat dicoba.
        RekeningSekolah::firstOrCreate(
            ['sekolah_id' => $sekolah['SD']->id, 'nomor_rekening' => '1234567890'],
            ['bank' => 'Bank Syariah Indonesia (contoh)', 'atas_nama' => 'Yayasan Al-Ishlah Insan Mulia', 'aktif' => true],
        );

        $wali = $this->buatWali('Walisantri Satu', '6283573522241', $akun);
        $wali2 = $this->buatWali('Walisantri Dua', '6283573522242', $akun);
        $wali3 = $this->buatWali('Walisantri Tiga', '6283573522243', $akun);
        $wali4 = $this->buatWali('Walisantri Empat', '6283573522244', $akun);

        // 1) Draf: belum dikirim, dapat dilanjutkan.
        $anak1 = $this->buatAnak($wali, 'Aisyah Nur Fadhilah', 'P', '2019-04-12', $sekolah['SD']->id);
        $this->buatPendaftaran($anak1, $sekolah['SD'], $gelombang['SD'], $wali, null, null);

        // 2) Terkirim, menunggu verifikasi (TKIT, program KB).
        $anak2 = $this->buatAnak($wali2, 'Muhammad Rizky Pratama', 'L', '2022-01-08', $sekolah['TKI']->id);
        $terkirim = $this->buatPendaftaran($anak2, $sekolah['TKI'], $gelombang['TKI'], $wali2, StatusHasil::Baru, 'KB-2027-0001');
        $terkirim->forceFill(['setuju_biaya_pada' => now()])->save();

        // 3) Diterima dan sudah diumumkan, dengan daftar ulang dan bukti pembayaran.
        $anak3 = $this->buatAnak($wali3, 'Fatimah Az Zahra', 'P', '2018-09-03', $sekolah['SD']->id);
        $diterima = $this->buatPendaftaran($anak3, $sekolah['SD'], $gelombang['SD'], $wali3, StatusHasil::Diterima, 'SD-2027-0001');
        $diterima->forceFill([
            'setuju_biaya_pada' => now(),
            'setuju_data_pada' => now(),
            'status_murid' => StatusMurid::Resmi,
        ])->save();

        Pengumuman::firstOrCreate(
            ['sekolah_id' => $sekolah['SD']->id, 'gelombang_id' => $gelombang['SD']->id],
            ['diumumkan_pada' => now()->subDay(), 'teks_pesan' => 'Hasil seleksi gelombang ini sudah diumumkan.'],
        );

        DaftarUlang::firstOrCreate(
            ['pendaftaran_id' => $diterima->id],
            ['hadir' => true, 'selesai' => false],
        );

        Pembayaran::create([
            'pendaftaran_id' => $diterima->id,
            'metode' => 'transfer',
            'nominal' => 50000,
            'status' => 'menunggu',
            'dibayar_pada' => now()->subHours(6)->toDateString(),
        ]);

        // 4) Perlu perbaikan: ada berkas wajib yang harus diunggah ulang.
        $anak4 = $this->buatAnak($wali4, 'Abdullah Hakim', 'L', '2017-11-21', $sekolah['SD']->id);
        $perbaikan = $this->buatPendaftaran($anak4, $sekolah['SD'], $gelombang['SD'], $wali4, StatusHasil::PerluPerbaikan, 'SD-2027-0002');
        $perbaikan->forceFill([
            'setuju_biaya_pada' => now(),
            'setuju_data_pada' => now(),
        ])->save();

        $akta = JenisBerkas::query()
            ->where('sekolah_id', $sekolah['SD']->id)
            ->where('nama', 'Akta kelahiran')
            ->first();

        if ($akta) {
            Berkas::create([
                'pendaftaran_id' => $perbaikan->id,
                'jenis_berkas_id' => $akta->id,
                'status' => StatusBerkas::PerluPerbaikan,
                'catatan' => 'Foto kurang jelas. Mohon unggah ulang dengan pencahayaan yang cukup.',
            ]);
        }

        $this->command->info('Akun demo berhasil dibuat. Kata sandi dicetak di bawah (disimpan secara acak per jalankan):');

        foreach ($akun as $a) {
            $this->command->line("  {$a['label']}: {$a['identitas']} / {$a['kata_sandi']}");
        }
    }

    /**
     * @param  array<int, array{label: string, identitas: string, kata_sandi: string}>  $akun
     */
    private function buatWali(string $nama, string $noHp, array &$akun): User
    {
        $kataSandi = Str::random(12);

        $wali = User::create([
            'nama' => $nama,
            'peran' => PeranPengguna::Walisantri,
            'no_hp' => $noHp,
            'email' => null,
            'password' => Hash::make($kataSandi),
            'aktif' => true,
        ]);

        $akun[] = ['label' => $nama, 'identitas' => $wali->no_hp, 'kata_sandi' => $kataSandi];

        OrangTua::create([
            'user_id' => $wali->id,
            'hubungan' => HubunganOrangTua::Ayah,
            'nama' => 'Bapak '.$nama,
            'no_hp' => $noHp,
            'status_hidup' => 'Masih hidup',
            'pekerjaan' => 'Karyawan swasta',
            'pendidikan' => 'SMA/SMK',
            'alamat' => 'Alamat contoh, Wonosobo',
        ]);

        OrangTua::create([
            'user_id' => $wali->id,
            'hubungan' => HubunganOrangTua::Ibu,
            'nama' => 'Ibu '.$nama,
            'no_hp' => '6283'.fake()->unique()->numerify('#########'),
            'status_hidup' => 'Masih hidup',
            'pekerjaan' => 'Ibu rumah tangga',
            'pendidikan' => 'SMA/SMK',
            'alamat' => 'Alamat contoh, Wonosobo',
        ]);

        return $wali;
    }

    private function buatAnak(User $wali, string $nama, string $jenisKelamin, string $tanggalLahir, int $sekolahId): Anak
    {
        return Anak::create([
            'user_id' => $wali->id,
            'nama_lengkap' => $nama,
            'nama_panggilan' => Str::before($nama, ' '),
            'jenis_kelamin' => $jenisKelamin,
            'tempat_lahir' => 'Wonosobo',
            'tanggal_lahir' => $tanggalLahir,
            'agama' => 'Islam',
            'alamat' => 'Alamat contoh, Wonosobo',
            'desa' => 'Desa Contoh',
            'kecamatan' => 'Kecamatan Contoh',
            'kabupaten' => 'Wonosobo',
            'provinsi' => 'Jawa Tengah',
            'nik' => fake()->numerify('3307############'),
            'no_kk' => fake()->numerify('3307############'),
        ]);
    }

    private function buatPendaftaran(
        Anak $anak,
        Sekolah $sekolah,
        Gelombang $gelombang,
        User $wali,
        ?StatusHasil $status,
        ?string $nomorPeserta,
    ): Pendaftaran {
        $pendaftaran = new Pendaftaran([
            'anak_id' => $anak->id,
            'sekolah_id' => $sekolah->id,
            'gelombang_id' => $gelombang->id,
            'program' => $sekolah->kode === 'TKI' ? 'KB' : null,
            'dibuat_oleh' => $wali->id,
            'lulusan_yayasan' => false,
        ]);

        $pendaftaran->forceFill([
            'status_hasil' => $status ?? StatusHasil::Baru,
            'nomor_peserta' => $nomorPeserta,
            'dikirim_pada' => $status ? now()->subDays(5) : null,
        ])->save();

        return $pendaftaran;
    }
}
