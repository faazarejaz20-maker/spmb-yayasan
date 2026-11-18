<?php

/*
|--------------------------------------------------------------------------
| Katalog isian formulir
|--------------------------------------------------------------------------
|
| Kunci isian formulir Tahap 1 (kolom tetap di tabel `anak`) dan Tahap 2
| (disimpan di `jawaban_tambahan`). Kunci di sini dipakai oleh seeder
| `pengaturan_isian` dan oleh tampilan formulir.
|
*/

return [

    'identitas' => [
        'nama_lengkap' => ['label' => 'Nama lengkap', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => true],
        'nama_panggilan' => ['label' => 'Nama panggilan', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
        'jenis_kelamin' => ['label' => 'Jenis kelamin', 'tipe' => 'pilihan', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => true, 'pilihan' => ['L' => 'Laki-laki', 'P' => 'Perempuan']],
        'tempat_lahir' => ['label' => 'Tempat lahir', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => true],
        'tanggal_lahir' => ['label' => 'Tanggal lahir', 'tipe' => 'tanggal', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => true],
        'nik' => ['label' => 'NIK', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
        'no_kk' => ['label' => 'Nomor Kartu Keluarga (KK)', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
        'nisn' => ['label' => 'NISN', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
        'agama' => ['label' => 'Agama', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
        'anak_ke' => ['label' => 'Anak ke-', 'tipe' => 'angka', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
        'jumlah_saudara' => ['label' => 'Jumlah saudara kandung', 'tipe' => 'angka', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
        'bahasa_sehari_hari' => ['label' => 'Bahasa sehari-hari', 'tipe' => 'teks', 'bagian' => 'identitas', 'sumber' => 'kolom', 'inti' => false],
    ],

    'alamat' => [
        'alamat' => ['label' => 'Alamat', 'tipe' => 'teks_panjang', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'rt' => ['label' => 'RT', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'rw' => ['label' => 'RW', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'desa' => ['label' => 'Desa/Kelurahan', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'kecamatan' => ['label' => 'Kecamatan', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'kabupaten' => ['label' => 'Kabupaten/Kota', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'provinsi' => ['label' => 'Provinsi', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'kode_pos' => ['label' => 'Kode pos', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'jarak_ke_sekolah' => ['label' => 'Jarak ke sekolah', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'transportasi' => ['label' => 'Transportasi', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
        'tinggal_dengan' => ['label' => 'Tinggal bersama', 'tipe' => 'teks', 'bagian' => 'alamat', 'sumber' => 'kolom', 'inti' => false],
    ],

    'kesehatan' => [
        'tinggi_badan' => ['label' => 'Tinggi badan (cm)', 'tipe' => 'angka', 'bagian' => 'kesehatan', 'sumber' => 'kolom', 'inti' => false],
        'berat_badan' => ['label' => 'Berat badan (kg)', 'tipe' => 'angka', 'bagian' => 'kesehatan', 'sumber' => 'kolom', 'inti' => false],
        'golongan_darah' => ['label' => 'Golongan darah', 'tipe' => 'pilihan', 'bagian' => 'kesehatan', 'sumber' => 'kolom', 'inti' => false, 'pilihan' => ['A' => 'A', 'B' => 'B', 'AB' => 'AB', 'O' => 'O']],
        'riwayat_penyakit' => ['label' => 'Riwayat penyakit', 'tipe' => 'teks_panjang', 'bagian' => 'kesehatan', 'sumber' => 'kolom', 'inti' => false],
        'alergi' => ['label' => 'Alergi', 'tipe' => 'teks_panjang', 'bagian' => 'kesehatan', 'sumber' => 'kolom', 'inti' => false],
        'kebutuhan_khusus' => ['label' => 'Kebutuhan khusus', 'tipe' => 'teks_panjang', 'bagian' => 'kesehatan', 'sumber' => 'kolom', 'inti' => false],
    ],

    'asal_sekolah' => [
        'asal_sekolah' => ['label' => 'Asal sekolah', 'tipe' => 'teks', 'bagian' => 'asal_sekolah', 'sumber' => 'kolom', 'inti' => false],
        'jenis_asal_sekolah' => ['label' => 'Jenis asal sekolah', 'tipe' => 'pilihan', 'bagian' => 'asal_sekolah', 'sumber' => 'kolom', 'inti' => false, 'pilihan' => ['Belum sekolah' => 'Belum sekolah', 'TK' => 'TK', 'SD' => 'SD', 'SMP' => 'SMP']],
    ],

    'tahap2' => [
        'hobi' => ['label' => 'Hobi', 'tipe' => 'teks', 'bagian' => 'tahap2', 'sumber' => 'jawaban', 'inti' => false],
        'cita_cita' => ['label' => 'Cita-cita', 'tipe' => 'teks', 'bagian' => 'tahap2', 'sumber' => 'jawaban', 'inti' => false],
        'kemampuan_khusus' => ['label' => 'Kemampuan khusus', 'tipe' => 'teks_panjang', 'bagian' => 'tahap2', 'sumber' => 'jawaban', 'inti' => false],
        'lembar_psikologi' => ['label' => 'Lembar psikologi', 'tipe' => 'teks_panjang', 'bagian' => 'tahap2', 'sumber' => 'jawaban', 'inti' => false],
        'surat_pernyataan' => ['label' => 'Surat pernyataan', 'tipe' => 'centang', 'bagian' => 'tahap2', 'sumber' => 'jawaban', 'inti' => false],
    ],

];
