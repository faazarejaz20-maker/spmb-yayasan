# Struktur tabel database

Sumber kebenaran untuk migrasi. Nama tabel dan kolom **harus persis** seperti di sini. Tipe memakai istilah MySQL; terjemahkan ke Schema Builder Laravel dengan tepat (`bigint` menjadi `id()` atau `foreignId()`, `enum` menjadi `string` dengan cast Enum PHP bila lebih aman).

Dua puluh tabel. Semua tabel punya `id`, `created_at`, `updated_at`, kecuali `riwayat_status` dan `log_aktivitas` (hanya ditambah, tidak pernah diubah, jadi memakai `dibuat_pada` tanpa `updated_at`; model-nya menonaktifkan `UPDATED_AT` dan melarang update dan delete) dan `password_reset_tokens` (bawaan Laravel). Tetapkan `protected $table` di setiap model. Nilai enum disimpan sebagai string dan di-cast ke Enum PHP.

## Urutan migrasi (karena kunci asing)

1. `sekolah`, `tahun_ajaran`
2. `users` (kunci asing ke `sekolah`; `dibuat_oleh` merujuk `users` sendiri, tambahkan setelah tabel dibuat)
3. `orang_tua`, `anak`
4. `gelombang`
5. `pendaftaran`
6. `jenis_berkas`, `berkas`, `jawaban_tambahan`, `riwayat_status`
7. `rincian_biaya`, `rekening_sekolah`, `pembayaran`, `daftar_ulang`
8. `pengumuman`, `konten_sekolah`, `pengaturan_isian`, `log_aktivitas`
9. `password_reset_tokens` (bawaan Laravel)

Perilaku kunci asing: gunakan `restrictOnDelete()` untuk semua kunci asing ke data pendaftar (data tidak dihapus lewat aplikasi). Pengecualian: `jawaban_tambahan`, `berkas`, `riwayat_status`, `pembayaran`, `daftar_ulang` memakai `restrictOnDelete()` juga, karena penghapusan pendaftaran tidak ada di aplikasi.

## 1. Daftar tabel

| No | Tabel | Fungsi | Kolom utama | Relasi |
| --- | --- | --- | --- | --- |
| 1 | sekolah | Empat sekolah | id, kode, nama, jenjang, alamat, kontak, mode_penerimaan, aktif | 1 sekolah punya banyak users, gelombang, dan pengaturan |
| 2 | tahun_ajaran | Tahun ajaran | id, nama, aktif | 1 tahun ajaran punya banyak gelombang |
| 3 | users | Semua akun | id, nama, peran, sekolah_id, no_hp (unik), email (unik), password, wajib_ganti_password, aktif, dibuat_oleh, terakhir_masuk | Banyak users milik 1 sekolah (kosong untuk walisantri, super admin, yayasan) |
| 4 | orang_tua | Ayah, ibu, wali | id, user_id, hubungan, nama, nik, tempat_lahir, tanggal_lahir, status_hidup, pendidikan, pekerjaan, penghasilan, no_hp, alamat | Milik 1 akun walisantri |
| 5 | anak | Calon murid | id, user_id, identitas, alamat, kesehatan dasar (lihat bagian 2) | Milik 1 akun; 1 anak punya banyak pendaftaran |
| 6 | gelombang | Gelombang per sekolah | id, sekolah_id, tahun_ajaran_id, nama, tanggal_buka, tanggal_tutup, kuota, status | Milik 1 sekolah dan 1 tahun ajaran |
| 7 | pendaftaran | Anak mendaftar ke sekolah | id, anak_id, sekolah_id, gelombang_id, nomor_peserta, status_hasil, status_murid (lihat bagian 3) | Menghubungkan anak, sekolah, dan gelombang |
| 8 | jawaban_tambahan | Isian Tahap 2 | id, pendaftaran_id, bagian, kunci, nilai | Banyak per pendaftaran |
| 9 | jenis_berkas | Daftar berkas per sekolah | id, sekolah_id, nama, wajib, jumlah_lembar, urutan, aktif | Milik 1 sekolah |
| 10 | berkas | Berkas yang diunggah | id, pendaftaran_id, jenis_berkas_id, path, status, catatan, diperiksa_oleh, diperiksa_pada | Banyak per pendaftaran |
| 11 | riwayat_status | Jejak perubahan status | id, pendaftaran_id, jenis, dari, ke, oleh, catatan, dibuat_pada | Banyak per pendaftaran |
| 12 | rincian_biaya | Komponen biaya dan pilihan IAP | id, sekolah_id, tahun_ajaran_id, kelompok, nama, nominal, tipe, urutan | Milik 1 sekolah |
| 13 | rekening_sekolah | Rekening tujuan transfer | id, sekolah_id, bank, nomor_rekening, atas_nama, aktif | Milik 1 sekolah |
| 14 | pembayaran | Pembayaran daftar ulang | id, pendaftaran_id, rincian_biaya_id, metode, nominal, bukti_path, status, catatan_penolakan, diproses_oleh, dibayar_pada | Banyak per pendaftaran |
| 15 | daftar_ulang | Catatan daftar ulang | id, pendaftaran_id (unik), hadir, kekurangan, catatan_wawancara, selesai, dicatat_oleh | Satu per pendaftaran |
| 16 | pengumuman | Peristiwa umumkan hasil | id, sekolah_id, gelombang_id, diumumkan_oleh, diumumkan_pada, teks_pesan | Satu per sekolah per gelombang |
| 17 | konten_sekolah | Isi halaman sekolah | id, sekolah_id, bagian, judul, isi, gambar_path, urutan | Milik 1 sekolah |
| 18 | pengaturan_isian | Isian formulir yang tampil dan wajib | id, sekolah_id, kunci_isian, tampil, wajib | Milik 1 sekolah |
| 19 | log_aktivitas | Jejak tindakan pengguna | id, user_id, sekolah_id, aksi, objek, objek_id, data, dibuat_pada | Milik 1 akun |
| 20 | password_reset_tokens | Reset lewat email | Bawaan Laravel | Berdasarkan email akun |

## 2. Kolom tabel `anak`

Data inti Tahap 1, kolom tetap. Satu baris mewakili satu anak, tidak peduli ke sekolah mana ia mendaftar.

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint, PK | Kunci utama |
| user_id | bigint, FK | Akun walisantri pemilik anak (users) |
| nama_lengkap | varchar(150) | Wajib |
| nama_panggilan | varchar(50) | Opsional |
| jenis_kelamin | enum(L, P) | Wajib |
| tempat_lahir | varchar(100) | Wajib |
| tanggal_lahir | date | Wajib; bersama nama dipakai untuk deteksi data ganda |
| nik | char(16), null | NIK anak sesuai KK |
| no_kk | char(16), null | Nomor Kartu Keluarga |
| nisn | varchar(10), null | Untuk jenjang SMP |
| agama | varchar(30) | Default Islam |
| anak_ke | tinyint, null | Urutan anak dalam keluarga |
| jumlah_saudara | tinyint, null | Jumlah saudara kandung |
| bahasa_sehari_hari | varchar(50), null |  |
| tinggi_badan | smallint, null | Dalam cm |
| berat_badan | smallint, null | Dalam kg |
| golongan_darah | enum(A, B, AB, O), null |  |
| riwayat_penyakit | text, null | Isian bebas |
| alergi | text, null | Isian bebas |
| kebutuhan_khusus | text, null | Isian bebas |
| alamat | text | Nama jalan, dusun, atau nomor rumah |
| rt, rw | varchar(5) |  |
| desa | varchar(100) |  |
| kecamatan | varchar(100) |  |
| kabupaten | varchar(100) |  |
| provinsi | varchar(100) |  |
| kode_pos | varchar(10), null |  |
| jarak_ke_sekolah | varchar(50), null | Isian bebas, mis. "3 km" |
| transportasi | varchar(50), null | Antar jemput, motor, jalan kaki, dan seterusnya |
| tinggal_dengan | varchar(50), null | Orang tua, wali, dan seterusnya |
| asal_sekolah | varchar(150), null | Nama TK, SD, atau SMP asal |
| jenis_asal_sekolah | varchar(50), null | Belum sekolah, TK, SD, atau SMP |
| created_at, updated_at | timestamp |  |

Kolom yang tidak dipakai sebuah sekolah tetap ada di tabel, tetapi disembunyikan dari formulir sekolah itu lewat `pengaturan_isian`. Kolom opsional diberi null agar anak tetap bisa disimpan walau belum lengkap.

**Penyesuaian keamanan:** `nik` dan `no_kk` pada tabel ini bertipe `text` (bukan `char(16)`) karena nilainya dienkripsi dengan cast `encrypted` Laravel. Akibatnya kedua kolom tidak bisa dicari atau diurutkan lewat SQL. Deteksi data ganda memakai nama + tanggal lahir + sekolah, jadi tidak membutuhkan pencarian NIK.

## 3. Kolom tabel `pendaftaran`

Pusat sistem. Satu baris berarti satu anak mendaftar ke satu sekolah pada satu gelombang. Semua keputusan panitia ada di sini.

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint, PK | Kunci utama |
| anak_id | bigint, FK | Anak yang mendaftar |
| sekolah_id | bigint, FK | Sekolah tujuan; dipakai untuk membatasi akses panitia dan admin |
| gelombang_id | bigint, FK | Gelombang pendaftaran |
| nomor_peserta | varchar(20), unique, null | Format KODE-TAHUN-URUTAN4, mis. SD-2027-0001; dibuat saat pendaftaran dikirim |
| status_hasil | enum | Baru, Diverifikasi, Perlu perbaikan, Diterima, Ditolak, Cadangan |
| status_murid | enum, null | Resmi (setelah daftar ulang) atau Mengundurkan diri; kosong sebelum diterima |
| lulusan_yayasan | boolean | Tanda lulusan sekolah di bawah yayasan |
| pilihan_iap | decimal(12,0), null | Nominal IAP yang dipilih walisantri |
| setuju_biaya_pada | timestamp, null | Waktu walisantri menyetujui rincian biaya |
| setuju_data_pada | timestamp, null | Waktu walisantri menyatakan data benar |
| catatan_panitia | text, null | Catatan internal, tidak tampil ke walisantri |
| dikirim_pada | timestamp, null | Waktu pendaftaran dikirim; kosong berarti masih draf |
| dibuat_oleh | bigint, FK | Pembuat: walisantri sendiri, atau panitia untuk pendaftar offline |
| diputuskan_oleh | bigint, FK, null | Panitia yang memutuskan hasil |
| diputuskan_pada | timestamp, null |  |
| created_at, updated_at | timestamp |  |

Satu anak tidak boleh punya dua pendaftaran di sekolah dan gelombang yang sama: unique `(anak_id, sekolah_id, gelombang_id)`. Perubahan `status_hasil` dan `status_murid` selalu dicatat ke `riwayat_status`.

## 4. Kolom tabel `users`

Semua jenis akun dalam satu tabel, dibedakan oleh `peran`. Salah satu dari `no_hp` atau `email` boleh kosong, tetapi tidak boleh kosong dua-duanya (validasi di aplikasi). Kolom `no_hp` dan `email` bersifat unique (null boleh berulang).

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint, PK | Kunci utama |
| nama | varchar(150) | Nama pemilik akun |
| peran | enum | super_admin, admin, panitia, yayasan, walisantri |
| sekolah_id | bigint, FK, null | Sekolah tempat admin atau panitia bertugas; kosong untuk super admin, yayasan, dan walisantri |
| no_hp | varchar(20), unique, null | Dipakai untuk login dan dihubungi panitia |
| email | varchar(150), unique, null | Dipakai untuk login dan reset mandiri |
| password | varchar(255) | Disimpan dalam bentuk hash |
| wajib_ganti_password | boolean | Bernilai benar setelah panitia mengatur ulang kata sandi sementara |
| aktif | boolean | Akun nonaktif tidak bisa masuk |
| dibuat_oleh | bigint, FK, null | Akun yang membuat; berguna untuk walisantri yang dibuatkan panitia |
| terakhir_masuk | timestamp, null |  |
| created_at, updated_at | timestamp |  |

## 5. Kolom tabel `orang_tua`

Data ayah, ibu, atau wali, diisi sekali per akun walisantri dan dipakai ulang untuk semua anak dalam akun itu. Unique `(user_id, hubungan)`. `nik` bertipe `text` dan dienkripsi seperti pada `anak`.

| Kolom | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint, PK | Kunci utama |
| user_id | bigint, FK | Akun walisantri pemilik data |
| hubungan | enum | ayah, ibu, wali |
| nama | varchar(150) |  |
| nik | char(16), null |  |
| tempat_lahir | varchar(100), null |  |
| tanggal_lahir | date, null |  |
| status_hidup | enum, null | Masih hidup atau sudah wafat |
| pendidikan | varchar(50), null |  |
| pekerjaan | varchar(100), null |  |
| penghasilan | varchar(50), null | Rentang penghasilan, bukan angka pasti |
| no_hp | varchar(20), null |  |
| alamat | text, null | Diisi bila berbeda dari alamat anak |
| created_at, updated_at | timestamp |  |

## 6. Tabel pendukung

Kolom `id`, `created_at`, `updated_at` ada di semua tabel dan tidak diulang. Kolom bernama `*_id` adalah kunci asing ke tabel yang bersangkutan (`pendaftaran_id` ke `pendaftaran`, dan seterusnya; `oleh`, `diperiksa_oleh`, `diproses_oleh`, `dicatat_oleh`, `diubah_oleh`, `diumumkan_oleh` ke `users`).

| Tabel | Kolom utama | Fungsi |
| --- | --- | --- |
| sekolah | kode (TKK, TKI, SD, SMP), nama, jenjang, alamat, kontak, mode_penerimaan (seleksi atau tanpa seleksi), aktif | Daftar empat sekolah dan pengaturan penerimaannya |
| tahun_ajaran | nama (2027/2028), aktif | Penanda tahun ajaran yang sedang dibuka |
| gelombang | sekolah_id, tahun_ajaran_id, nama, tanggal_buka, tanggal_tutup, kuota, status (draf, dibuka, ditutup) | Periode dan kuota pendaftaran per sekolah |
| jawaban_tambahan | pendaftaran_id, bagian (mis. Tahap 2), kunci, nilai | Isian dinamis di luar kolom tetap, terutama Tahap 2 |
| jenis_berkas | sekolah_id, nama, wajib, jumlah_lembar, urutan, aktif | Daftar berkas yang diminta tiap sekolah |
| berkas | pendaftaran_id, jenis_berkas_id, path, status (menunggu, sesuai, perlu perbaikan), catatan, diperiksa_oleh, diperiksa_pada | File yang diunggah dan hasil pemeriksaannya |
| riwayat_status | pendaftaran_id, jenis (hasil atau murid), dari, ke, oleh, catatan, dibuat_pada | Jejak setiap perubahan status hasil seleksi dan status murid |
| rincian_biaya | sekolah_id, tahun_ajaran_id, kelompok, nama (IAP, iuran, seragam, dan lain-lain), nominal, tipe (tetap atau pilihan), urutan | Biaya pendidikan yang tampil setelah login; nominal IAP yang dipilih walisantri disimpan di pendaftaran.pilihan_iap |
| rekening_sekolah | sekolah_id, bank, nomor_rekening, atas_nama, aktif | Rekening tujuan transfer per sekolah |
| pembayaran | pendaftaran_id, rincian_biaya_id, metode (transfer, tunai), nominal, bukti_path, status (menunggu, disetujui, ditolak), catatan_penolakan, diproses_oleh, dibayar_pada | Pembayaran daftar ulang dan persetujuannya |
| daftar_ulang | pendaftaran_id (unik), hadir, kekurangan, catatan_wawancara, selesai, dicatat_oleh | Penanda anak sudah menyelesaikan daftar ulang, beserta catatan wawancara dan kekurangan berkas |
| pengumuman | sekolah_id, gelombang_id, diumumkan_oleh, diumumkan_pada, teks_pesan | Catatan tombol Umumkan Hasil; sebelum itu walisantri belum melihat hasil |
| konten_sekolah | sekolah_id, bagian (profil, program, ekskul, kontak, syarat), judul, isi, gambar_path, urutan | Konten halaman publik yang diedit admin |
| pengaturan_isian | sekolah_id, kunci_isian, tampil, wajib | Mengatur kolom formulir mana yang dipakai tiap sekolah |
| log_aktivitas | user_id, sekolah_id, aksi, objek, objek_id, data, dibuat_pada | Jejak tindakan penting panitia dan admin |
| password_reset_tokens | email, token, created_at (bawaan Laravel) | Reset mandiri lewat email |

Aturan tambahan untuk tabel pendukung:
- `riwayat_status.jenis` bernilai `hasil` atau `murid`; `dari` dan `ke` berisi nilai status sebelum dan sesudah; `oleh` adalah pengguna yang mengubah.
- `pengumuman`: unique `(sekolah_id, gelombang_id)` agar pengumuman kedua pada gelombang yang sama ditolak.
- `daftar_ulang`: unique `pendaftaran_id` (satu per pendaftaran).
- `pengaturan_isian`: unique `(sekolah_id, kunci_isian)`. Katalog kunci dan label isian ada di `config/isian.php` (lihat tahap 1), bukan di tabel.
- `jawaban_tambahan`: unique `(pendaftaran_id, bagian, kunci)`.
- `rincian_biaya.tipe` bernilai `tetap` atau `pilihan`. Baris bertipe `pilihan` dengan `kelompok` yang sama (misalnya "IAP") adalah pilihan nominal; walisantri memilih satu dan nilainya disimpan di `pendaftaran.pilihan_iap`.
- `berkas.status`: `menunggu`, `sesuai`, `perlu_perbaikan`. `pembayaran.status`: `menunggu`, `disetujui`, `ditolak`.
- `gelombang.status`: `draf`, `dibuka`, `ditutup`. `gelombang.tanggal_buka`, `tanggal_tutup`, dan `kuota` boleh null (null pada kuota berarti tanpa batas).
- `sekolah.mode_penerimaan`: `seleksi` atau `tanpa_seleksi` (urutan daftar sampai kuota penuh). `sekolah.kode`: `TKK`, `TKI`, `SD`, `SMP`.
- Tambahkan indeks pada kolom yang sering dicari: `pendaftaran(sekolah_id, gelombang_id, status_hasil)`, `pendaftaran(anak_id)`, `anak(user_id)`, `anak(nama_lengkap, tanggal_lahir)`, `log_aktivitas(user_id, created_at)`.

## 7. Penambahan terbaru (belum ada di dokumen Word perancangan, tetapi berlaku)

| Perubahan | Alasan |
| --- | --- |
| `pendaftaran.program` `varchar(10)`, null. Nilai `KB`, `TK`, `TPA`, hanya diisi untuk sekolah TKI; kosong untuk sekolah lain. | KB-TKIT Insan Mulia satu sekolah dengan tiga program; menentukan awalan nomor peserta dan logo program. |
| `gelombang.tautan_grup_wa` `varchar(255)`, null. | Admin mengisi tautan grup WA murid baru; tampil di akun walisantri setelah status Diterima atau Resmi. Hanya tautan `https://chat.whatsapp.com/...`, divalidasi. |
| Kolom `uuid` `char(36)` unique pada `anak`, `pendaftaran`, `berkas`, `pembayaran`. | Alamat URL memakai uuid, bukan id berurutan, sebagai lapisan tambahan (policy tetap wajib). |
| `anak.nik`, `anak.no_kk`, `orang_tua.nik` bertipe `text`, cast `encrypted`. | Data sangat sensitif. |
| `rincian_biaya` boleh memuat baris "Biaya formulir" (misalnya Rp50.000 untuk SDIT) yang ditagih saat daftar ulang dan dicatat panitia sebagai pembayaran tunai. | Biaya formulir dari sekolah, bukan biaya web. |
| `users.dibuat_oleh` boleh null dan merujuk `users.id`. | Menandai walisantri yang dibuatkan panitia (pendaftar offline). |
| `sekolah.foto_sampul_path` `varchar(255)`, null. Berisi jalur gambar di disk publik (`sampul/{sekolah_id}/...`). | Admin sekolah (dan super admin) dapat mengganti sendiri foto sampul kartu sekolah di beranda tanpa menimpa berkas proyek. Kosong berarti memakai foto bawaan `GambarPublik::SAMPUL`. |
