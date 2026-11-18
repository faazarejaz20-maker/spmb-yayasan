<?php

/*
|--------------------------------------------------------------------------
| Bagian konten halaman sekolah dan yayasan
|--------------------------------------------------------------------------
|
| Katalog ini menentukan bagian mana yang boleh diisi lewat Kelola Konten.
| `banyak` berarti satu bagian terdiri atas banyak baris (daftar). `gambar`
| berarti baris boleh (atau wajib) memiliki gambar. Teks tetap disimpan
| sebagai teks biasa dan di-escape saat tampil.
|
*/

return [

    'sekolah' => [
        'slogan' => ['label' => 'Slogan', 'banyak' => false, 'gambar' => false, 'panjang' => false],
        'profil' => ['label' => 'Profil sekolah', 'banyak' => false, 'gambar' => false, 'panjang' => true],
        'visi' => ['label' => 'Visi', 'banyak' => false, 'gambar' => false, 'panjang' => true],
        'indikator_visi' => ['label' => 'Indikator visi', 'banyak' => true, 'gambar' => false, 'panjang' => false],
        'misi' => ['label' => 'Misi', 'banyak' => true, 'gambar' => false, 'panjang' => true],
        'program_unggulan' => ['label' => 'Program unggulan', 'banyak' => true, 'gambar' => false, 'panjang' => false],
        'ekskul' => ['label' => 'Ekstrakurikuler', 'banyak' => true, 'gambar' => true, 'panjang' => false],
        'prestasi' => ['label' => 'Prestasi', 'banyak' => true, 'gambar' => true, 'panjang' => false],
        'fasilitas' => ['label' => 'Fasilitas', 'banyak' => true, 'gambar' => true, 'panjang' => false],
        'galeri' => ['label' => 'Galeri', 'banyak' => true, 'gambar' => true, 'panjang' => false],
        'jam_belajar' => ['label' => 'Jam belajar', 'banyak' => false, 'gambar' => false, 'panjang' => true],
        'kontak' => ['label' => 'Kontak', 'banyak' => false, 'gambar' => false, 'panjang' => true],
        'alur' => ['label' => 'Alur pendaftaran', 'banyak' => false, 'gambar' => false, 'panjang' => true],
        'syarat' => ['label' => 'Syarat pendaftaran', 'banyak' => false, 'gambar' => false, 'panjang' => true],
    ],

    'yayasan' => [
        'profil' => ['label' => 'Profil yayasan', 'banyak' => false, 'gambar' => false, 'panjang' => true],
        'visi' => ['label' => 'Visi yayasan', 'banyak' => false, 'gambar' => false, 'panjang' => true],
        'misi' => ['label' => 'Misi yayasan', 'banyak' => true, 'gambar' => false, 'panjang' => true],
        'tautan_sosial' => ['label' => 'Tautan sosial', 'banyak' => false, 'gambar' => false, 'panjang' => false, 'tautan' => true],
    ],

];
