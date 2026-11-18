# Aturan sistem

Aturan bisnis yang harus dipatuhi kode. Bila ada yang tidak jelas, jangan menebak: tulis di laporan tahap dan pilih perilaku paling aman (menolak, bukan mengizinkan).

## 1. Peran dan hak akses

| Peran (`users.peran`) | Terikat sekolah | Dibuat oleh | Boleh | Tidak boleh |
| --- | --- | --- | --- | --- |
| `walisantri` | Tidak | Mendaftar sendiri, atau dibuatkan panitia | Mengelola data orang tua dan anak miliknya, mendaftarkan anak, unggah berkas, memilih IAP, menyetujui biaya, membayar (unggah bukti), melihat status dan hasil (setelah diumumkan) | Melihat data akun lain; mengubah pendaftaran setelah dikirim kecuali berstatus Perlu perbaikan |
| `panitia` | Satu sekolah (`sekolah_id`) | Admin sekolah | Melihat dan mengubah data pendaftar sekolahnya, memeriksa berkas, memutuskan hasil, menyetujui atau menolak bukti bayar, mencatat tunai, mencatat daftar ulang dan wawancara, membuat akun walisantri untuk pendaftar offline, mengatur ulang kata sandi walisantri, memindahkan anak antar akun walisantri, mengunduh dan mencetak data sekolahnya | Mengelola gelombang, kuota, akun panitia, konten, pengaturan; menekan Umumkan Hasil; melihat data sekolah lain |
| `admin` | Satu sekolah | Super admin | Semua kemampuan panitia, ditambah: gelombang, kuota, mode penerimaan, akun panitia, daftar berkas, rincian biaya, rekening, isian formulir, Kelola Konten, Umumkan Hasil, rekap, log aktivitas, mengubah keputusan setelah pengumuman | Melihat data sekolah lain; membuat akun admin |
| `super_admin` | Tidak | Perintah artisan awal (lihat tahap 1) | Mengelola data sekolah, akun admin sekolah, akun yayasan | **Melihat data pendaftar dalam bentuk apa pun** |
| `yayasan` | Tidak | Super admin | Melihat dan mengunduh rekap (angka dan tabel agregat) | Mengubah apa pun; melihat data pendaftar per orang |

Aturan penegakan:
- Peran `panitia` dan `admin` wajib punya `sekolah_id`; peran lain wajib kosong. Validasi di aplikasi.
- Semua pembatasan dilakukan **di sisi server** (middleware `peran`, Policy, dan global scope `sekolah_id`). Menyembunyikan tombol di tampilan tidak dihitung sebagai pembatasan.
- Akses ke data sekolah lain, atau ke data walisantri lain, menghasilkan respons 403 (atau 404 bila keberadaannya tidak boleh diketahui).

## 2. Akun walisantri

- Masuk dengan nomor HP **atau** email, plus kata sandi. Nomor HP dinormalisasi (hanya angka, awalan `62`; `08...` diubah ke `628...`) sebelum disimpan dan dicari.
- Daftar akun sendiri: nama lengkap, nomor HP, email (opsional), kata sandi, centang persetujuan penggunaan data. Tidak ada verifikasi OTP.
- Kata sandi minimal 8 karakter, tidak boleh sama dengan nomor HP atau email, dan tidak boleh termasuk daftar kata sandi umum yang mudah (cek dengan aturan `Password::min(8)->uncompromised()` bila ada koneksi, jika tidak cukup `min(8)` plus larangan sama dengan nomor HP).
- Satu akun boleh punya banyak anak (kembar, kakak-adik). Keluarga yang memakai dua akun (ayah dan ibu) dibolehkan; datanya berdiri sendiri.
- Data ayah, ibu, dan wali tersimpan di `orang_tua` milik akun. Pendaftaran tidak boleh dikirim sebelum akun punya minimal satu baris `orang_tua` (ayah atau ibu) yang terisi nama dan nomor HP.
- Panitia dapat memindahkan seorang anak ke akun walisantri lain di sekolah yang sama (misalnya salah akun). Tindakan ini dicatat di log dan hanya bila kedua akun sudah ada.

### Lupa kata sandi
1. **Lewat panitia (utama):** panitia membuka akun walisantri, menekan "Atur ulang kata sandi". Sistem membuat kata sandi sementara acak (10 karakter dari huruf dan angka yang tidak membingungkan, tanpa `0 O 1 l I`), menampilkannya **satu kali** di layar beserta tombol salin dan cetak kartu, dan menandai `wajib_ganti_password = true`. Kata sandi sementara tidak pernah disimpan atau dicatat dalam bentuk asli.
2. **Mandiri lewat email (tambahan):** hanya bila akun punya email dan SMTP aktif. Memakai mekanisme reset bawaan Laravel (`password_reset_tokens`), token berlaku 60 menit, pesan konfirmasi sama persis apakah email terdaftar atau tidak.
3. Pada masuk berikutnya, akun dengan `wajib_ganti_password` diarahkan ke halaman ganti kata sandi dan tidak bisa ke halaman lain sebelum selesai.

### Pendaftar offline
Panitia membuat akun walisantri (nama, nomor HP, kata sandi sementara otomatis, `wajib_ganti_password = true`, `dibuat_oleh` = panitia), menyerahkan kartu akun (cetak) kepada wali, lalu mengisi data orang tua, data anak, dan berkas atas nama walisantri. Pendaftaran yang dibuat panitia punya `dibuat_oleh` = panitia.

## 3. Alur pendaftaran (ringkas)

1. Walisantri membuka halaman sekolah, menekan Daftar, masuk atau membuat akun.
2. Melengkapi data orang tua (sekali).
3. Menambah anak dan mengisi formulir Tahap 1 (langkah di `docs/desain.md`). Setiap langkah tersimpan sebagai draf.
4. Sistem memeriksa data ganda (nama lengkap + tanggal lahir + sekolah tujuan). Bila ada, kirim ditolak dengan pesan jelas; panitia bisa menyelesaikan kasus yang sah.
5. Mengunggah berkas wajib.
6. Melihat rincian biaya (hanya setelah masuk), memilih nominal IAP bila ada pilihan, dan menyetujui biaya.
7. Menyatakan data benar dan menyetujui penggunaan data, lalu Kirim. Sistem membuat nomor peserta, mengisi `dikirim_pada`, `status_hasil = Baru`, dan mencatat `riwayat_status`.
8. Panitia memeriksa. Belum sesuai: `Perlu perbaikan` dengan catatan; walisantri memperbaiki lalu status kembali `Baru`. Sesuai: `Diverifikasi`.
9. Panitia memutuskan `Diterima`, `Ditolak`, atau `Cadangan`.
10. Admin menekan Umumkan Hasil. Baru sesudah itu walisantri melihat hasilnya.
11. Yang diterima: daftar ulang di sekolah (sekaligus wawancara), pembayaran, lalu panitia mencatat daftar ulang sehingga `status_murid = Resmi`.

Walisantri hanya boleh mengubah data pendaftaran selagi masih draf (belum dikirim) atau berstatus `Perlu perbaikan`. Panitia dan admin boleh mengubah data kapan saja, dan setiap perubahan dicatat di log.

### Pendaftaran ulang ke gelombang lain
Anak yang tidak diterima di satu gelombang boleh mendaftar lagi di gelombang berikutnya (pendaftaran baru, baris baru). Seorang anak boleh mendaftar ke lebih dari satu sekolah. Satu anak hanya boleh punya satu pendaftaran per sekolah per gelombang.

## 4. Status

### `status_hasil`
Nilai: `Baru`, `Diverifikasi`, `Perlu perbaikan`, `Diterima`, `Ditolak`, `Cadangan`. Pendaftaran yang masih draf belum punya status (`dikirim_pada` kosong); tampilkan sebagai "Draf".

Perpindahan yang diizinkan:
| Dari | Ke | Oleh |
| --- | --- | --- |
| Baru | Diverifikasi, Perlu perbaikan | panitia, admin |
| Perlu perbaikan | Baru | otomatis saat walisantri mengirim perbaikan |
| Diverifikasi | Diterima, Ditolak, Cadangan, Perlu perbaikan | panitia, admin |
| Cadangan | Diterima, Ditolak | panitia, admin |
| Diterima, Ditolak | Cadangan, Diterima, Ditolak | **hanya admin**, dan hanya dengan catatan wajib, bila pengumuman sudah dilakukan; sebelum pengumuman panitia boleh |

Keputusan Diterima, Ditolak, atau Cadangan hanya boleh dari status `Diverifikasi` atau `Cadangan` (atau perubahan oleh admin di atas). Setiap perpindahan menulis baris `riwayat_status` (jenis `hasil`) dan `log_aktivitas`.

### `status_murid`
Kosong sampai pendaftar Diterima dan menyelesaikan daftar ulang. Nilai: `Resmi` (dicatat panitia saat daftar ulang selesai) dan `Mengundurkan diri` (dapat dicatat panitia kapan pun setelah Diterima; membebaskan kuota). Perubahan juga ditulis ke `riwayat_status` (jenis `murid`).

### Tampilan status ke walisantri
| Kondisi | Teks untuk walisantri |
| --- | --- |
| Draf | Draf, belum dikirim |
| Baru | Menunggu verifikasi |
| Perlu perbaikan | Perlu perbaikan (tampilkan catatan panitia dan berkas yang harus diunggah ulang) |
| Diverifikasi, belum diumumkan | Terverifikasi, menunggu pengumuman |
| Diterima, Ditolak, Cadangan, **belum diumumkan** | Terverifikasi, menunggu pengumuman (**jangan** bocorkan hasilnya lewat teks, warna, ikon, judul halaman, atau respons API) |
| Diterima, sudah diumumkan | Diterima (langkah berikutnya: daftar ulang) |
| Ditolak, sudah diumumkan | Belum diterima (kalimat sopan dan empatik) |
| Cadangan, sudah diumumkan | Daftar cadangan (dengan penjelasan) |
| status_murid Resmi | Murid resmi |
| status_murid Mengundurkan diri | Mengundurkan diri |

## 5. Mode penerimaan dan kuota

- `sekolah.mode_penerimaan`: `tanpa_seleksi` (diterima menurut urutan daftar sampai kuota penuh) atau `seleksi`. Bawaan: `tanpa_seleksi`. Pada kedua mode, **panitia tetap yang menekan keputusan**; mode hanya memengaruhi petunjuk di tampilan (pada `tanpa_seleksi` daftar pendaftar diurutkan menurut `dikirim_pada` dan sistem menyarankan Cadangan bila kuota penuh).
- `gelombang.kuota` diatur admin per gelombang. Kosong berarti tanpa batas.
- Kuota terisi = jumlah pendaftaran pada gelombang itu dengan `status_hasil = Diterima` dan `status_murid` bukan `Mengundurkan diri`.
- Bila kuota terisi sudah sama dengan kuota, tombol Diterima dinonaktifkan dengan pesan "Kuota penuh, pilih Cadangan atau naikkan kuota". Admin yang menaikkan kuota. Pencegahan ini harus berlaku di server (cek di dalam transaksi dengan kunci baris), bukan hanya di tampilan.
- Kuota tidak pernah tampil di halaman publik atau di sisi walisantri.

## 6. Pengumuman

- Tombol Umumkan Hasil ada di menu admin, per sekolah per gelombang. Sebelum menekan, tampilkan ringkasan (jumlah Diterima, Ditolak, Cadangan) dan **peringatan** bila masih ada pendaftar berstatus Baru, Diverifikasi, atau Perlu perbaikan di gelombang itu. Admin harus mencentang konfirmasi, tetapi tidak diblokir.
- Menekan tombol membuat satu baris `pengumuman` (unique per sekolah dan gelombang). Pengumuman kedua untuk pasangan yang sama ditolak.
- Setelah ada baris `pengumuman`, walisantri dari pendaftaran di gelombang itu melihat hasil sesuai tabel di atas. Hasil pendaftaran yang diputuskan **setelah** pengumuman langsung terlihat saat diputuskan.
- Sistem membuat `teks_pesan` siap salin (contoh: "Assalamu'alaikum, hasil SPMB {sekolah} {gelombang} sudah diumumkan. Silakan masuk ke {alamat situs} untuk melihat hasil anak Bapak/Ibu.") dan tombol Bagikan ke WhatsApp (`https://wa.me/?text=...`). Teks tidak memuat nama anak atau hasil.

## 7. Nomor peserta

Format `AWALAN-TAHUN-URUTAN4`, contoh `SD-2027-0001`. `TAHUN` adalah tahun awal tahun ajaran yang aktif (2027 untuk 2027/2028). Awalan: `TKK`, `SD`, `SMP`; untuk sekolah TKI memakai nilai `pendaftaran.program` (`KB`, `TK`, `TPA`). Urutan dihitung per awalan per tahun, dimulai dari 0001, dibuat saat pendaftaran dikirim di dalam transaksi dengan kunci (hindari duplikat pada pengiriman bersamaan). Kolom `nomor_peserta` unique. Program wajib dipilih untuk TKI dan kosong untuk sekolah lain.

## 8. Berkas

- Jenis berkas per sekolah ada di `jenis_berkas` (wajib atau opsional, jumlah lembar). Contoh bawaan di `docs/data-awal.md`.
- Format yang diterima: JPG, PNG, PDF. **HEIC ditolak** dengan pesan "Foto format HEIC belum didukung. Ubah ke JPG atau PNG, atau ambil foto langsung lewat tombol Ambil foto." Maksimal 2 MB per berkas.
- Status berkas: `menunggu`, `sesuai`, `perlu_perbaikan`. Panitia menandai dan menulis catatan bila perlu perbaikan. Bila ada berkas `perlu_perbaikan`, pendaftaran tidak bisa menjadi Diverifikasi.
- Walisantri yang mengunggah ulang menggantikan berkas sebelumnya (berkas lama dihapus dari penyimpanan, riwayat tetap tercatat di log) dan status berkas kembali `menunggu`.
- Berkas hanya dapat dibuka oleh pemilik (walisantri), panitia dan admin sekolah terkait. Tidak ada tautan publik. Detail di `docs/keamanan.md`.

## 9. Biaya dan pembayaran

- Biaya pendidikan (`rincian_biaya`) hanya tampil kepada walisantri yang sudah masuk, yang punya pendaftaran ke sekolah itu. Tidak pernah di halaman publik.
- Setiap sekolah punya rekening sendiri (`rekening_sekolah`, bisa lebih dari satu, yang aktif tampil). Nomor rekening akan diisi admin kemudian.
- Walisantri memilih nominal IAP (bila `tipe = pilihan`) dan menyetujui rincian biaya sebelum Kirim. Sistem mengisi `pilihan_iap` dan `setuju_biaya_pada`. Bila IAP hanya satu nominal tetap (contoh TKIT Al Ishlah), tidak ada pilihan.
- Pembayaran daftar ulang:
  - **Transfer:** walisantri mengunggah bukti (JPG, PNG, PDF, 2 MB). Status `menunggu`. Panitia menyetujui (`disetujui`) atau menolak dengan `catatan_penolakan` (`ditolak`); walisantri mengunggah bukti baru.
  - **Tunai:** panitia menginput nominal dan tanggal; status langsung `disetujui`, `diproses_oleh` = panitia.
- "Biaya formulir" (misalnya Rp50.000 di SDIT) bila diaktifkan admin muncul sebagai baris biaya tetap yang ditagih saat daftar ulang dan dicatat panitia sebagai pembayaran tunai.
- Daftar ulang hanya boleh dicatat selesai bila pendaftar berstatus `Diterima` dan minimal satu pembayaran `disetujui`. Sistem tidak mencatat SPP bulanan.

## 10. Daftar ulang

Dicatat panitia di sekolah, bersamaan dengan wawancara wali. Isi `daftar_ulang`: `hadir` (ya atau belum), `kekurangan` (teks: berkas asli atau lainnya yang belum lengkap), `catatan_wawancara` (teks bebas, **hanya terlihat panitia dan admin sekolah, tidak pernah tampil ke walisantri**), `selesai` (boolean). Saat `selesai` menjadi benar: `status_murid = Resmi`, riwayat dan log tertulis, tautan grup WA (bila ada di gelombang) tampil di akun walisantri.

## 11. Formulir dan isian

- **Tahap 1** (online, saat mendaftar): data anak, alamat, kesehatan dasar, asal sekolah. Kolom tetap di `anak`. Data orang tua di `orang_tua`.
- **Tahap 2** (setelah Diterima dan diumumkan, untuk daftar ulang): isian tambahan seperti hobi, cita-cita, kemampuan khusus, lembar psikologi, surat pernyataan. Disimpan di `jawaban_tambahan` (`bagian = tahap2`). Walisantri boleh mengisi dari akun, panitia boleh mengisi atau mengubah.
- Isian apa yang tampil dan wajib di tiap sekolah diatur di `pengaturan_isian`. Katalog isian (kunci, label, tipe, bagian) ada di `config/isian.php`. Isian inti yang tidak bisa disembunyikan: `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`.
- Asal sekolah: teks bebas pada `anak.asal_sekolah`, ditambah tanda `pendaftaran.lulusan_yayasan` yang dicentang otomatis bila asal sekolah dipilih dari daftar sekolah yayasan, dan bisa diatur panitia. Tidak ada impor data siswa lama.

## 12. Halaman publik

- Beranda yayasan (profil, visi misi, pilihan empat sekolah, alur pendaftaran), halaman tiap sekolah, kebijakan privasi, masuk, daftar akun.
- Halaman sekolah menampilkan: profil dan slogan, visi dan misi, program unggulan, ekstrakurikuler, prestasi, fasilitas, galeri, jam belajar, alur dan berkas, jadwal gelombang yang dibuka (hanya tanggal, **tanpa kuota**), kontak (WhatsApp, alamat, Instagram).
- **Tidak boleh** tampil di halaman publik: biaya, kuota, jumlah pendaftar, atau data pendaftar apa pun. Query halaman publik tidak boleh memuat tabel `rincian_biaya` sama sekali.
- Nomor WhatsApp kontak sekolah boleh tampil (sudah dikonfirmasi).
- Isi halaman bisa diubah admin lewat Kelola Konten. Isi awal dari seeder.

## 13. Ekspor dan cetak

- Panitia dan admin dapat mengunduh daftar pendaftar sekolahnya sebagai Excel dan CSV (pilih kolom), mengunduh berkas satu pendaftar dalam ZIP, dan mencetak formulir lengkap serta bukti pendaftaran satu pendaftar. Kolom ekspor mengikuti data yang ada di sistem (untuk memudahkan impor ke SIMAK atau Dapodik oleh sekolah).
- Yayasan dan admin dapat mengunduh rekap (jumlah per status per sekolah).
- Setiap unduhan dicatat di log (siapa, apa, kapan, berapa baris).

## 14. Log aktivitas

Catat ke `log_aktivitas`: masuk berhasil dan gagal (tanpa kata sandi), keluar, ganti dan atur ulang kata sandi, pembuatan dan perubahan akun, perubahan data pendaftar oleh panitia atau admin, perubahan status, unggah, penandaan, dan unduh berkas, persetujuan atau penolakan pembayaran, pencatatan tunai, daftar ulang, Umumkan Hasil, perubahan pengaturan (gelombang, kuota, biaya, rekening, isian, konten), unduhan ekspor dan ZIP. Kolom `data` berisi ringkasan perubahan (nilai lama dan baru untuk kolom non sensitif; untuk NIK dan data sensitif cukup menyebut kolom yang berubah). Log hanya bisa dibaca admin sekolah (milik sekolahnya) dan tidak bisa diubah atau dihapus lewat aplikasi.
