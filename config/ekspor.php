<?php

/*
|--------------------------------------------------------------------------
| Katalog ekspor data pendaftar
|--------------------------------------------------------------------------
|
| Daftar putih kolom yang boleh diekspor, dibagi per grup. Kolom di luar
| daftar ini tidak dapat dipilih meskipun dikirim manual lewat permintaan.
| Kolom sensitif tidak ikut bawaan dan hanya tersedia bagi admin dengan
| centang konfirmasi. Kolom terlarang (catatan panitia, catatan wawancara,
| kekurangan, kata sandi, token, jalur berkas di disk) tidak ada di sini.
|
*/

return [

    // Batas jumlah baris per unduhan (K-20: ekspor besar).
    'batas_baris' => 5000,

    // Batas ukuran total ZIP (MB).
    'zip_maks_mb' => 40,

    // Folder sementara di disk privat `local` (storage/app/private/tmp).
    'tmp_folder' => 'tmp',

    // Umur maksimum file sementara sebelum dihapus perintah terjadwal (menit).
    'tmp_umur_menit' => 60,

    'grup' => [
        'identitas' => [
            'label' => 'Identitas',
            'kolom' => [
                'nomor_peserta' => ['label' => 'Nomor peserta'],
                'nama' => ['label' => 'Nama lengkap'],
                'jenis_kelamin' => ['label' => 'Jenis kelamin'],
                'tempat_lahir' => ['label' => 'Tempat lahir'],
                'tanggal_lahir' => ['label' => 'Tanggal lahir'],
                'asal_sekolah' => ['label' => 'Asal sekolah'],
                'jenis' => ['label' => 'Jenis pendaftar'],
                'program' => ['label' => 'Program'],
                'gelombang' => ['label' => 'Gelombang'],
            ],
        ],

        'kontak' => [
            'label' => 'Kontak orang tua',
            'kolom' => [
                'wali_nama' => ['label' => 'Nama wali murid'],
                'wali_no_hp' => ['label' => 'Nomor HP wali murid'],
                'alamat' => ['label' => 'Alamat'],
            ],
        ],

        'status' => [
            'label' => 'Status',
            'kolom' => [
                'status_hasil' => ['label' => 'Status hasil'],
                'status_murid' => ['label' => 'Status murid'],
                'tanggal_kirim' => ['label' => 'Tanggal kirim'],
            ],
        ],

        'berkas' => [
            'label' => 'Berkas',
            'kolom' => [
                'berkas_sesuai' => ['label' => 'Berkas sesuai'],
            ],
        ],

        'pembayaran' => [
            'label' => 'Pembayaran',
            'kolom' => [
                'status_pembayaran' => ['label' => 'Status pembayaran'],
                'total_disetujui' => ['label' => 'Total disetujui'],
            ],
        ],

        'daftar_ulang' => [
            'label' => 'Daftar ulang',
            'kolom' => [
                'hadir' => ['label' => 'Hadir daftar ulang'],
                'selesai' => ['label' => 'Daftar ulang selesai'],
            ],
        ],
    ],

    // Kolom sensitif: hanya admin, wajib centang konfirmasi, tidak ikut bawaan.
    'sensitif' => [
        'identitas' => [
            'label' => 'Identitas sensitif',
            'kolom' => [
                'nik' => ['label' => 'NIK', 'teks' => true],
                'no_kk' => ['label' => 'Nomor KK', 'teks' => true],
            ],
        ],
        'kesehatan' => [
            'label' => 'Data kesehatan',
            'kolom' => [
                'riwayat_penyakit' => ['label' => 'Riwayat penyakit'],
                'alergi' => ['label' => 'Alergi'],
                'kebutuhan_khusus' => ['label' => 'Kebutuhan khusus'],
            ],
        ],
    ],

    // Kalimat konfirmasi untuk kolom sensitif.
    'konfirmasi_sensitif' => 'Saya paham file ini memuat data pribadi sensitif dan akan menjaganya.',
];
