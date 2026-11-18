<?php

namespace Database\Seeders;

use App\Models\Gelombang;
use App\Models\JenisBerkas;
use App\Models\KontenSekolah;
use App\Models\PengaturanIsian;
use App\Models\RincianBiaya;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class DataAwalSeeder extends Seeder
{
    public function run(): void
    {
        $tahun = TahunAjaran::firstOrCreate(['nama' => '2027/2028'], ['aktif' => true]);

        $sekolahData = [
            [
                'kode' => 'TKK',
                'nama' => 'TKIT Al Ishlah Kertek',
                'jenjang' => 'TK',
                'alamat' => 'Jl. Raya Wonosobo Km.03, Capar, Ngadikusuman, Kertek, Wonosobo (depan patung Carica).',
                'kontak' => '6285234575505',
                'instagram' => 'tkit_alishlah.wsb',
            ],
            [
                'kode' => 'TKI',
                'nama' => 'KB-TKIT Insan Mulia',
                'jenjang' => 'KB, TK, TPA',
                'alamat' => 'Jl. Tumenggung Jogonegoro, Kp. Bumi Godean, Wonolelo, Wonosobo (depan SMA 1 Wonosobo).',
                'kontak' => '6285695422700',
                'instagram' => 'tkitinsanmulia.wsb',
            ],
            [
                'kode' => 'SD',
                'nama' => 'SDIT Insan Mulia Wonosobo',
                'jenjang' => 'SD',
                'alamat' => 'Jl. Kyai Walik, Jlegong, Pagerkukuh, Wonosobo.',
                'kontak' => '628984255748',
                'instagram' => 'sditinsanmuli.wsb',
            ],
            [
                'kode' => 'SMP',
                'nama' => 'SMPIT Insan Mulia Wonosobo',
                'jenjang' => 'SMP',
                'alamat' => 'Jl. Kyai Walik, Jlegong, Pagerkukuh, Wonosobo.',
                'kontak' => '6285747471648',
                'instagram' => 'smpitimwonosobo',
            ],
        ];

        $sekolah = [];

        foreach ($sekolahData as $data) {
            $sekolah[$data['kode']] = Sekolah::firstOrCreate(
                ['kode' => $data['kode']],
                array_merge($data, [
                    'mode_penerimaan' => 'tanpa_seleksi',
                    'aktif' => true,
                ])
            );
        }

        $this->seedKonten($sekolah);
        $this->seedGelombang($sekolah, $tahun);
        $this->seedJenisBerkas($sekolah);
        $this->seedPengaturanIsian($sekolah);
        $this->seedRincianBiaya($sekolah, $tahun);
    }

    private function seedKonten(array $sekolah): void
    {
        $slogan = [
            'TKK' => null,
            'TKI' => 'Cinta Allah dan Rasul Sejak Dini',
            'SD' => 'Menggali Potensi, Membentuk Generasi Rabbani',
            'SMP' => 'Generasi Madani Berkarakter Qur\'ani',
        ];

        foreach ($slogan as $kode => $isi) {
            if ($isi === null) {
                continue;
            }

            KontenSekolah::firstOrCreate(
                ['sekolah_id' => $sekolah[$kode]->id, 'bagian' => 'slogan', 'urutan' => 0],
                ['isi' => $isi]
            );
        }

        $visi = [
            'TKK' => 'Lembaga Pendidikan Terpercaya, Melahirkan Generasi Berakhlak Mulia, Berjiwa Kepemimpinan, Melestarikan Alam',
            'TKI' => 'Mewujudkan PAUD unggulan dalam membina generasi yang shalih, cerdas, sehat, mandiri, dan cinta tanah air',
            'SD' => 'Islami, peduli lingkungan, berwawasan global',
            'SMP' => 'Mewujudkan insan yang berakhlak mulia, berprestasi, berbudaya, dan terampil dalam IPTEK',
        ];

        foreach ($visi as $kode => $isi) {
            KontenSekolah::firstOrCreate(
                ['sekolah_id' => $sekolah[$kode]->id, 'bagian' => 'visi', 'urutan' => 0],
                ['isi' => $isi]
            );
        }

        $misi = [
            'TKK' => [
                'Menjadi lembaga pendidikan yang profesional dan terpercaya dalam mengelola pendidikan yang berkualitas, adaptif, dan berkelanjutan.',
                'Menyelenggarakan pendidikan yang berbasis nilai-nilai spiritual dan akhlak mulia.',
                'Membantu peserta didik mengenal dan mencintai dirinya serta lingkungan sekitar sebagai ciptaan Tuhan.',
                'Menumbuhkan potensi kepemimpinan siswa melalui pembelajaran berbasis proyek, kerja tim, dan pengalaman langsung dalam kehidupan sehari-hari.',
                'Menerapkan pembelajaran kontekstual yang menyatu dengan alam guna menumbuhkan kecintaan, kepedulian, dan tanggung jawab terhadap lingkungan.',
            ],
            'TKI' => [
                'Menanamkan nilai-nilai keimanan dan akhlak mulia sebagai fondasi utama dalam membentuk karakter anak yang shalih dan shalihah, melalui pembiasaan ibadah, tutur kata baik, serta keteladanan sehari-hari.',
                'Mengembangkan potensi anak secara menyeluruh melalui pembelajaran yang aktif, kreatif, menyenangkan, dan sesuai dengan fitrah tumbuh kembang anak.',
                'Membudayakan hidup sehat dalam arti luas, mencakup kesehatan fisik, ketenangan jiwa, kebersihan lingkungan, dan kecukupan gizi, sebagai wujud rasa syukur atas anugerah Allah SWT.',
                'Menumbuhkan kemandirian dan tanggung jawab sejak dini, agar anak terbiasa menyelesaikan tugas-tugas sederhana dengan percaya diri dan tetap beradab.',
                'Menanamkan rasa cinta tanah air sebagai bagian dari iman, dengan mengenalkan budaya, bahasa, dan simbol kebangsaan yang selaras dengan nilai-nilai Islam.',
            ],
            'SD' => [
                'Mengembangkan pendidikan yang sesuai dengan standar nasional pendidikan',
                'Mewujudkan sekolah yang memiliki keunggulan kompetitif dan komparatif dengan sekolah lain',
                'Mewujudkan siswa yang mampu mendasari nilai-nilai Islam secara utuh sebagai kerangka dasar kehidupan',
                'Mengimplementasikan nilai-nilai Islami untuk mencapai optimalisasi proses pembelajaran yang berorientasi pada pembentukan karakter Islami siswa',
                'Menciptakan ruang belajar yang asri',
                'Membentuk siswa yang memiliki kesadaran tinggi terhadap lingkungan',
                'Mencetak siswa yang berprestasi tinggi, kreatif, inovatif, dan mandiri',
                'Menyelenggarakan pendidikan untuk anak inklusi',
                'Mengembangkan pendidik dan tenaga kependidikan yang bekerja secara profesional',
                'Menyelenggarakan pendidikan dengan pembelajaran berdiferensiasi',
            ],
            'SMP' => [
                'Melaksanakan pembiasaan adab-adab Islami',
                'Melaksanakan kegiatan pembelajaran yang terintegrasi dengan nilai-nilai Islam',
                'Menerapkan pembelajaran aktif, inovatif, kreatif, efektif, dan menyenangkan dengan multimetode dan multimedia',
                'Membekali murid dengan kompetensi yang dimiliki baik dalam bidang akademik maupun non akademik',
                'Mengembangkan bakat dan minat murid sesuai dengan potensi',
                'Mengimplementasikan budaya luhur bangsa dalam hubungan dengan masyarakat',
            ],
        ];

        foreach ($misi as $kode => $daftar) {
            foreach ($daftar as $urutan => $isi) {
                KontenSekolah::firstOrCreate(
                    ['sekolah_id' => $sekolah[$kode]->id, 'bagian' => 'misi', 'urutan' => $urutan],
                    ['isi' => $isi]
                );
            }
        }

        $indikatorVisi = [
            'SD' => [
                'Beriman dan bertaqwa kepada Allah SWT',
                'Berakhlak mulia',
                'Optimalisasi pelestarian lingkungan',
                'Minimalisasi pencemaran lingkungan',
                'Terampil dan cerdas dalam berkompetisi',
                'Optimal dalam mengaplikasikan IPTEK dan penggunaan bahasa',
                'Berwawasan nasional dan internasional',
            ],
            'SMP' => [
                'Nilai-nilai Islam dalam kehidupan sehari-hari',
                'Prestasi dalam bidang akademik',
                'Prestasi dalam bidang non akademik',
                'Melestarikan nilai-nilai luhur budaya bangsa',
                'Terampil dalam IPTEK',
            ],
        ];

        foreach ($indikatorVisi as $kode => $daftar) {
            foreach ($daftar as $urutan => $isi) {
                KontenSekolah::firstOrCreate(
                    ['sekolah_id' => $sekolah[$kode]->id, 'bagian' => 'indikator_visi', 'urutan' => $urutan],
                    ['isi' => $isi]
                );
            }
        }

        $programUnggulan = [
            'TKK' => ['dhuha', 'hafalan', 'BTAQ', 'little camp', 'berkebun', 'bermain di alam'],
            'TKI' => ['KB (KBIT)', 'TK (TKIT)', 'TPA (TPA IT) — semua full day school'],
            'SD' => ['Tahfidz Al Quran (target hafal minimal 2 juz)', 'BTAQ Qiroaty', 'BPI', 'POMG dan Parenting', 'Outing Class', 'Kelas Inspirasi'],
            'SMP' => ['Full day school', 'Akselerasi tahfidz', 'BTAQ Qiroaty', 'BPI', 'Outing Class', 'POMG', 'Parenting'],
        ];

        foreach ($programUnggulan as $kode => $daftar) {
            foreach ($daftar as $urutan => $isi) {
                KontenSekolah::firstOrCreate(
                    ['sekolah_id' => $sekolah[$kode]->id, 'bagian' => 'program_unggulan', 'urutan' => $urutan],
                    ['isi' => $isi]
                );
            }
        }

        $ekskul = [
            'TKK' => ['tahfidz', 'melukis', 'menari'],
            'SD' => ['Pramuka', 'TIK', 'Tilawah', 'Taekwondo', 'Rebana', 'Panahan', 'Futsal'],
        ];

        foreach ($ekskul as $kode => $daftar) {
            foreach ($daftar as $urutan => $isi) {
                KontenSekolah::firstOrCreate(
                    ['sekolah_id' => $sekolah[$kode]->id, 'bagian' => 'ekskul', 'urutan' => $urutan],
                    ['isi' => $isi]
                );
            }
        }
    }

    private function seedGelombang(array $sekolah, TahunAjaran $tahun): void
    {
        foreach ($sekolah as $s) {
            foreach (['Gelombang 1', 'Gelombang 2'] as $nama) {
                Gelombang::firstOrCreate(
                    [
                        'sekolah_id' => $s->id,
                        'tahun_ajaran_id' => $tahun->id,
                        'nama' => $nama,
                    ],
                    [
                        'status' => 'draf',
                        'tanggal_buka' => null,
                        'tanggal_tutup' => null,
                        'kuota' => null,
                        'tautan_grup_wa' => null,
                    ]
                );
            }
        }
    }

    private function seedJenisBerkas(array $sekolah): void
    {
        $umum = [
            ['nama' => 'Akta kelahiran', 'wajib' => true, 'jumlah_lembar' => 1],
            ['nama' => 'Kartu Keluarga (KK)', 'wajib' => true, 'jumlah_lembar' => 1],
        ];

        foreach ($sekolah as $kode => $s) {
            $berkas = $umum;

            if ($kode === 'SD') {
                $berkas[] = ['nama' => 'Pas foto 3x4', 'wajib' => true, 'jumlah_lembar' => 1];
            } elseif (in_array($kode, ['TKK', 'TKI'], true)) {
                $berkas[] = ['nama' => 'Pas foto 3x4', 'wajib' => true, 'jumlah_lembar' => 2];
            } elseif ($kode === 'SMP') {
                $berkas[] = ['nama' => 'Surat keterangan sehat', 'wajib' => true, 'jumlah_lembar' => 1];
                $berkas[] = ['nama' => 'Ijazah atau SKL', 'wajib' => false, 'jumlah_lembar' => 1];
                $berkas[] = ['nama' => 'Sertifikat kejuaraan', 'wajib' => false, 'jumlah_lembar' => 1];
            }

            foreach ($berkas as $urutan => $data) {
                JenisBerkas::firstOrCreate(
                    ['sekolah_id' => $s->id, 'nama' => $data['nama']],
                    [
                        'wajib' => $data['wajib'],
                        'jumlah_lembar' => $data['jumlah_lembar'],
                        'urutan' => $urutan,
                        'aktif' => true,
                    ]
                );
            }
        }
    }

    private function seedPengaturanIsian(array $sekolah): void
    {
        $katalog = config('isian');

        // Kunci Tahap 1 (kolom) di semua bagian selain tahap2.
        $tahap1 = [];
        foreach (['identitas', 'alamat', 'kesehatan', 'asal_sekolah'] as $bagian) {
            foreach (array_keys($katalog[$bagian]) as $kunci) {
                $tahap1[$kunci] = $katalog[$bagian][$kunci];
            }
        }

        $wajib = [
            'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
        ];

        foreach ($sekolah as $kode => $s) {
            foreach ($tahap1 as $kunci => $def) {
                $tampil = true;

                // nisn hanya tampil untuk SMP.
                if ($kunci === 'nisn' && $kode !== 'SMP') {
                    $tampil = false;
                }

                PengaturanIsian::firstOrCreate(
                    ['sekolah_id' => $s->id, 'kunci_isian' => $kunci],
                    [
                        'tampil' => $tampil,
                        'wajib' => in_array($kunci, $wajib, true),
                    ]
                );
            }

            // Tahap 2.
            foreach ($katalog['tahap2'] as $kunci => $def) {
                $tampilTahap2 = match ($kode) {
                    'TKK', 'TKI' => true,
                    'SD', 'SMP' => in_array($kunci, ['hobi', 'cita_cita'], true),
                    default => false,
                };

                PengaturanIsian::firstOrCreate(
                    ['sekolah_id' => $s->id, 'kunci_isian' => $kunci],
                    [
                        'tampil' => $tampilTahap2,
                        'wajib' => false,
                    ]
                );
            }
        }
    }

    private function seedRincianBiaya(array $sekolah, TahunAjaran $tahun): void
    {
        $data = [
            'TKK' => [
                ['kelompok' => 'IAP', 'nama' => 'IAP', 'nominal' => 500000, 'tipe' => 'tetap', 'catatan' => null],
            ],
            // SD, TKI, SMP: belum ada komponen biaya ("Biaya formulir" tidak
            // lagi dimuat seeder). Admin mengisinya lewat menu Biaya.
        ];

        foreach ($data as $kode => $daftar) {
            foreach ($daftar as $urutan => $d) {
                RincianBiaya::firstOrCreate(
                    ['sekolah_id' => $sekolah[$kode]->id, 'nama' => $d['nama']],
                    [
                        'tahun_ajaran_id' => $tahun->id,
                        'kelompok' => $d['kelompok'],
                        'nominal' => $d['nominal'],
                        'tipe' => $d['tipe'],
                        'catatan' => $d['catatan'],
                        'urutan' => $urutan,
                        'aktif' => true,
                    ]
                );
            }
        }
    }
}
