<?php

/*
|--------------------------------------------------------------------------
| Pengaturan SPMB
|--------------------------------------------------------------------------
|
| Batas dan aturan teknis yang dipakai layanan (service), bukan data yang
| diubah admin lewat web. Data sekolah, gelombang, biaya, dan isian tetap
| berada di database.
|
*/

return [

    'berkas' => [
        // Batas ukuran satu berkas (KB). Dipakai juga untuk bukti pembayaran.
        'maks_kb' => (int) env('SPMB_BERKAS_MAKS_KB', 2048),

        // Ekstensi dan tipe MIME yang diterima. Pemeriksaan MIME dilakukan
        // dari isi berkas (finfo), bukan dari header dari pengguna.
        'mime' => [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
        ],

        // Sisi terpanjang gambar setelah disimpan ulang (piksel).
        'sisi_maks' => (int) env('SPMB_BERKAS_SISI_MAKS', 2400),

        // Folder di disk privat `local` (storage/app/private).
        'folder' => 'berkas',
        'folder_pembayaran' => 'pembayaran',
    ],

    'konten' => [
        // Foto kebutuhan tampilan sekolah (prestasi, galeri, fasilitas, dan
        // gambar baris konten). Batas unggahnya 10 MB karena foto langsung
        // dari kamera HP sering 3–10 MB; hasil yang disimpan tetap dikecilkan
        // ke sisi_maks dan ditulis ulang. Berkas pendaftar tetap 2 MB
        // (lihat blok `berkas`), karena itu data pribadi, bukan tampilan.
        'maks_kb' => (int) env('SPMB_KONTEN_MAKS_KB', 10240),
        'sisi_maks' => (int) env('SPMB_KONTEN_SISI_MAKS', 1600),
        'folder' => 'konten',
    ],

    'sampul' => [
        // Foto sampul kartu sekolah di beranda. Batas unggahnya lebih longgar
        // karena foto langsung dari kamera HP sering 3–10 MB; hasil yang
        // disimpan tetap dikecilkan ke sisi_maks dan ditulis ulang. Pastikan
        // upload_max_filesize dan post_max_size PHP di server tidak lebih
        // kecil dari batas ini.
        'maks_kb' => (int) env('SPMB_SAMPUL_MAKS_KB', 10240),
        'sisi_maks' => (int) env('SPMB_SAMPUL_SISI_MAKS', 1600),
        'folder' => 'sampul',
    ],

    'nomor_peserta' => [
        // Awalan yang sah: kode sekolah, atau program untuk TKI.
        'awalan' => ['TKK', 'TKI', 'SD', 'SMP', 'KB', 'TK', 'TPA'],
        'program_tki' => ['KB', 'TK', 'TPA'],
        'kode_program' => 'TKI',
    ],

    'usia' => [
        // Rentang usia lazim per jenjang (tahun). Hanya untuk peringatan.
        'TK' => [4, 7],
        'SD' => [6, 9],
        'SMP' => [12, 16],
    ],

    'yayasan' => [
        // Nama resmi yayasan. Profil, visi, misi, dan tautan sosial diisi
        // super admin lewat Kelola Konten dan disimpan di `konten_sekolah`
        // dengan `sekolah_id` kosong.
        'nama' => 'Yayasan Al-Ishlah Insan Mulia',
        'alamat' => 'Wonosobo, Jawa Tengah',
    ],

    'cadangan' => [
        // Arsip cadangan disimpan di luar docroot, terenkripsi AES-256.
        'folder' => 'cadangan',
        'kunci' => env('CADANGAN_KUNCI'),
        'retensi_harian' => 14,
        'retensi_bulanan' => 6,
        'perintah_mysqldump' => env('SPMB_MYSQLDUMP', 'mysqldump'),
        'perintah_mysql' => env('SPMB_MYSQL', 'mysql'),
    ],

    'produksi' => [
        // Dipakai `spmb:periksa-produksi`. `vendor_path` dapat diarahkan ke
        // folder lain saat pengujian agar pemeriksaan paket dev tetap jujur.
        'vendor_path' => env('SPMB_VENDOR_PATH', base_path('vendor')),
        'paket_dev' => [
            'phpunit/phpunit',
            'fakerphp/faker',
            'laravel/pail',
            'laravel/pint',
            'mockery/mockery',
            'nunomaduro/collision',
        ],
        'ekstensi' => [
            'pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'zip', 'gd', 'intl', 'tokenizer', 'xml', 'ctype', 'json',
        ],
    ],

];
