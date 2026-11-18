# Tahap 4 — Admin sekolah, super admin, dan yayasan

Prasyarat: tahap 1 sampai 3 selesai dan `php artisan test` lulus. Kerjakan hanya tahap ini. Jangan membangun ekspor Excel, ZIP, atau cetak (tahap 5), dan jangan membangun halaman publik atau konfigurasi produksi (tahap 6). Namun **bagian Kelola Konten (menyimpan konten) dibangun di sini**; tampilannya di halaman publik dibangun di tahap 6.

Tahap ini membangun tiga panel:
- **Admin sekolah** (`/admin`, peran `admin`, satu sekolah): pengaturan sekolah, Umumkan Hasil, rekap, log.
- **Super admin** (`/super`, peran `super_admin`): mengelola sekolah dan akun staf. **Tidak pernah melihat data pendaftar.**
- **Yayasan** (`/yayasan`, peran `yayasan`): hanya rekap agregat, hanya baca.

## 0. Sebelum menulis kode

1. Baca ulang `AGENTS.md`, `docs/aturan-sistem.md` (terutama bagian 1, 3, 5, 6, 9, 11, 12), `docs/struktur-tabel.md`, `docs/keamanan.md` (K-01 sampai K-10, K-18, K-19), `docs/desain.md` (bagian admin, super admin, yayasan), `docs/data-awal.md`, dan laporan tahap 1 sampai 3.
2. Tulis rencana singkat (daftar file), lalu langsung kerjakan.

## 1. Prinsip yang wajib dipatuhi

- **Rute `/admin`** memakai middleware `auth`, `peran:admin`, dan semua query lewat global scope `MilikSekolah`. Admin hanya mengurus **sekolahnya sendiri**. Data sekolah lain → **404**. `sekolah_id` **tidak pernah** diterima dari input; selalu diambil dari akun yang masuk.
- **Admin mewarisi panitia**: rute `/panitia` sudah terbuka untuk admin (tahap 3). Di menu admin tampilkan menu panitia dan menu khusus admin dalam satu sidebar.
- **Super admin dan yayasan tidak punya satu pun rute, query, tampilan, atau respons yang memuat data pendaftar per orang** (nama anak, NIK, berkas, pembayaran per orang, nomor peserta). Ini bukan hanya soal menyembunyikan tombol: tidak boleh ada kode yang membuatnya mungkin (K-18). Tes wajib.
- **Setiap perubahan** ditulis ke `log_aktivitas` (siapa, kapan, apa; ringkasan nilai lama dan baru untuk pengaturan; **tidak pernah** kata sandi, token, atau nomor rekening utuh di log: untuk rekening cukup 4 digit terakhir).
- **Tidak ada penghapusan permanen** data yang sudah dipakai. Gelombang, jenis berkas, rincian biaya, dan akun yang sudah punya data terkait hanya bisa **dinonaktifkan** (`aktif = false` atau status `ditutup`). Hapus hanya untuk baris yang belum dipakai sama sekali, dan tetap dengan dialog konfirmasi.
- Semua input divalidasi dengan Form Request; semua keluaran di-escape; mass assignment dilindungi (`$fillable`/DTO); tidak ada `{!! !!}` kecuali untuk konten yang sudah dibersihkan (bagian 6).

## 2. Admin sekolah: pengaturan

Rute awalan `/admin`, nama `admin.`. Semua perubahan lewat Policy `PengaturanSekolahPolicy` (hanya `admin` dari sekolah itu).

### 2.1 Gelombang (`/admin/gelombang`)
- Daftar, tambah, ubah gelombang: nama, `tanggal_buka`, `tanggal_tutup`, `kuota` (boleh kosong = tanpa batas), `tautan_grup_wa`, status (`draf`, `dibuka`, `ditutup`), tahun ajaran (otomatis yang aktif).
- Validasi: tanggal tutup tidak sebelum tanggal buka; kuota bilangan bulat ≥ 0 bila diisi; **`tautan_grup_wa` harus URL `https://chat.whatsapp.com/...`** (tolak skema lain seperti `javascript:`); gelombang berstatus `dibuka` wajib punya tanggal.
- Menurunkan kuota **di bawah jumlah Diterima** (bukan mengundurkan diri) ditolak dengan penjelasan.
- Kuota dan tautan grup **tidak pernah tampil publik** (K-17).
- Perubahan kuota memakai `lockForUpdate` pada baris gelombang agar tidak berbenturan dengan keputusan panitia (gunakan `KuotaService` tahap 3).
- Gelombang yang sudah punya pendaftaran tidak bisa dihapus; hanya ditutup.

### 2.2 Mode penerimaan (`/admin/pengaturan`)
Pilihan `seleksi` atau `tanpa_seleksi` untuk sekolahnya, dengan penjelasan singkat dampaknya (urutan bawaan daftar dan petunjuk di panel panitia, tahap 3). Mengubahnya dicatat di log.

### 2.3 Akun panitia (`/admin/panitia`)
- Admin membuat, menonaktifkan (`aktif = false`), mengaktifkan kembali, dan mengatur ulang kata sandi akun **panitia sekolahnya sendiri**. `peran` dipaksa `panitia` dan `sekolah_id` dipaksa sekolah admin; input lain diabaikan.
- Pembuatan: nama, nomor HP (dinormalisasi, unik), email opsional. Kata sandi sementara 10 karakter dari `random_int` (tanpa `0 O 1 l I`), `wajib_ganti_password = true`, `dibuat_oleh` = admin, **ditampilkan sekali** dengan tombol Salin dan Cetak kartu akun (halaman cetak sederhana seperti tahap 3). Tidak disimpan dan tidak dicatat di log.
- Menonaktifkan akun **menghapus semua sesinya** dan membuatnya tidak bisa masuk (middleware `AkunAktif`).
- Atur ulang kata sandi: seperti tahap 3 (sementara, wajib ganti, sesi dihapus, batas 20 per jam per admin).
- Admin **tidak bisa** mengelola admin lain, super admin, yayasan, atau panitia sekolah lain (404).

### 2.4 Jenis berkas (`/admin/berkas`)
Tambah, ubah, urutkan, dan nonaktifkan jenis berkas sekolahnya: nama, wajib, jumlah lembar, petunjuk. Jenis berkas yang sudah punya unggahan tidak boleh dihapus, hanya dinonaktifkan; menonaktifkan tidak menghapus berkas yang sudah ada. Mengubah dari tidak wajib menjadi wajib tidak menggagalkan pendaftaran yang sudah terkirim.

### 2.5 Rincian biaya dan rekening (`/admin/biaya`, `/admin/rekening`)
- Rincian biaya: kelompok (`IAP`, `Formulir`, lainnya), nama, nominal (bilangan bulat rupiah ≥ 0), `tipe` (`tetap` atau `pilihan`), catatan, `aktif`. Untuk `pilihan`, beberapa nominal dalam satu kelompok. "Biaya formulir" yang diaktifkan muncul sebagai biaya tetap saat daftar ulang (tahap 3 sudah menangani pencatatan tunai).
- Perubahan biaya **tidak mengubah** `pilihan_iap` dan `setuju_biaya_pada` pada pendaftaran yang sudah terkirim (simpan nominal yang disetujui pada saat itu bila belum, mengikuti `docs/struktur-tabel.md`).
- Rekening: bank, nomor, atas nama, `aktif` (boleh lebih dari satu, yang aktif tampil ke walisantri). Nomor rekening ditampilkan hanya kepada walisantri yang punya pendaftaran ke sekolah itu dan kepada staf sekolah; **tidak di halaman publik**. Di log hanya 4 digit terakhir.

### 2.5.1 Isian formulir (`/admin/isian`)
Daftar isian dari `config/isian.php` dengan saklar `tampil` dan `wajib` per isian untuk sekolahnya (`pengaturan_isian`). Isian inti (`nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`) tidak bisa disembunyikan atau dijadikan tidak wajib. Menyembunyikan isian tidak menghapus jawaban yang sudah ada.

## 3. Umumkan Hasil (`PengumumanService`)

Rute: `GET /admin/pengumuman` (daftar gelombang sekolah beserta status "Belum diumumkan" atau "Sudah diumumkan pada ..."), `POST /admin/pengumuman/{gelombang}`.

- Layar konfirmasi menampilkan ringkasan jumlah Diterima, Ditolak, Cadangan, dan **peringatan** (bukan pemblokir) bila masih ada pendaftar berstatus Baru, Diverifikasi, atau Perlu perbaikan pada gelombang itu. Admin wajib mencentang konfirmasi ("Saya paham hasil akan langsung terlihat oleh walisantri dan tidak dapat ditarik kembali").
- Proses dalam satu transaksi: membuat satu baris `pengumuman` (`sekolah_id` dari akun, `gelombang_id`, `diumumkan_oleh`, `diumumkan_pada`, `teks_pesan`). **Unique `(sekolah_id, gelombang_id)`**; percobaan kedua (termasuk dua klik bersamaan) ditolak dengan pesan jelas dan tidak membuat baris kedua. Gelombang harus milik sekolah admin (kalau tidak, 404).
- `PengumumanService::sudahDiumumkan($pendaftaran)` (dari tahap 3) kini membaca baris ini. `StatusTampilService` (tahap 2) otomatis mulai menampilkan hasil. Pastikan **tidak ada** jalur lain yang membuka hasil.
- `teks_pesan` dibuat sistem, siap salin: "Assalamu'alaikum, hasil SPMB {sekolah} {gelombang} sudah diumumkan. Silakan masuk ke {alamat situs} untuk melihat hasil anak Bapak/Ibu." Tampilkan tombol Salin dan tombol "Bagikan ke WhatsApp" yang membuka `https://wa.me/?text=` dengan teks itu di-URL-encode. **Teks tidak boleh memuat nama anak, nomor peserta, atau hasil** (K-19). Nama sekolah dan gelombang berasal dari data terkontrol, bukan input bebas dari pengguna.
- Tidak ada fitur "tarik pengumuman" di antarmuka. Koreksi setelah pengumuman dilakukan per pendaftar oleh admin (aturan tahap 3).
- Log: siapa mengumumkan, sekolah, gelombang, jumlah per hasil.
- Walisantri tidak diberi notifikasi otomatis (tidak ada email atau WA otomatis); pengumuman disebarkan oleh sekolah lewat teks siap salin.

## 4. Rekap dan log (admin)

- **Rekap sekolah** (`/admin/rekap`): kartu angka dan tabel agregat per gelombang: total pendaftar terkirim, per status hasil, Baru versus Lulusan Yayasan, per program (bila TKI), pembayaran (menunggu, disetujui, ditolak), daftar ulang (selesai, belum), mengundurkan diri, kuota terpakai. Hanya angka; tidak ada daftar nama di halaman ini. (Tombol unduh **belum dibuat**; tahap 5.)
- **Log aktivitas** (`/admin/log`): daftar `log_aktivitas` sekolahnya, hanya baca, dengan penyaring tanggal, pelaku, dan jenis tindakan, halaman 50 baris, urutan terbaru. Tidak ada tombol ubah atau hapus, dan model `LogAktivitas` menolak `update` dan `delete` di level aplikasi (K-08, log tidak bisa diubah). Isi log sudah bersih dari rahasia (lihat prinsip 1); tampilannya tetap di-escape.

## 5. Super admin (`/super`)

Middleware `auth`, `peran:super_admin`. Policy `SuperAdminPolicy`. **Tidak ada rute yang memuat pendaftaran, anak, orang tua, berkas, atau pembayaran.**

- **Dasbor**: jumlah sekolah, jumlah akun staf aktif per peran, status per sekolah (ada admin atau belum), dan tautan ke menu. Tanpa data pendaftar. (Angka agregat pendaftar boleh ditampilkan **hanya** sebagai total per sekolah, tanpa tautan ke detail.)
- **Sekolah** (`/super/sekolah`): ubah data sekolah (nama, singkatan, alamat, nomor WhatsApp dalam format `62...`, Instagram, mode awal, aktif). Nomor WA divalidasi. Kode sekolah dan awalan nomor peserta **tidak dapat diubah** setelah ada pendaftaran. Tidak ada tambah atau hapus sekolah dari antarmuka (empat sekolah dibuat seeder).
- **Akun admin sekolah** (`/super/admin`): membuat, menonaktifkan, mengaktifkan, dan mengatur ulang kata sandi akun `admin` per sekolah. Satu sekolah boleh punya lebih dari satu admin. Kata sandi sementara seperti bagian 2.3 (tampil sekali, tidak dicatat). Menonaktifkan menghapus sesinya.
- **Akun yayasan** (`/super/yayasan`): membuat, menonaktifkan, mengatur ulang kata sandi akun `yayasan` (`sekolah_id` kosong).
- **Akun super admin lain**: **tidak dibuat dari antarmuka**; hanya lewat `php artisan spmb:buat-super-admin` (tahap 1). Super admin tidak bisa menonaktifkan dirinya sendiri, dan tidak bisa menonaktifkan super admin terakhir yang aktif (tolak, jelaskan).
- **Kelola Konten yayasan** (beranda): ubah profil, visi, dan misi yayasan, dan tautan sosial lewat mekanisme konten bagian 6 (`konten_sekolah` dengan `sekolah_id` kosong untuk yayasan; ikuti `docs/struktur-tabel.md`; bila kolom tidak mengizinkan null, buat penyesuaian migrasi baru yang aman dan catat di laporan). Visi dan misi yayasan **belum ada**: biarkan kosong dengan tampilan "Segera hadir".
- **Log global** (`/super/log`): semua `log_aktivitas` (hanya baca), penyaring sekolah, pelaku, tanggal.
- **Aturan umum**: setiap pembuatan, penonaktifan, dan atur ulang kata sandi akun staf ditulis ke log. Tidak ada impersonasi ("masuk sebagai pengguna lain") dalam bentuk apa pun.

## 6. Kelola Konten (`/admin/konten`)

Mengisi tabel `konten_sekolah` sesuai bagian pada `docs/data-awal.md` bagian 3: `slogan`, `profil`, `visi`, `indikator_visi`, `misi`, `program_unggulan`, `ekskul`, `prestasi`, `fasilitas`, `galeri`, `jam_belajar`, `kontak`, `alur`, `syarat`.

- Satu halaman dengan bagian lipat per jenis konten; daftar yang bisa bertambah (misalnya ekskul, program unggulan) dengan tambah, ubah, urutkan, dan hapus baris. Pratinjau "Lihat di halaman publik" (tautan ke halaman tahap 6).
- **Keamanan teks (K-06, K-17):** konten **teks biasa** (tanpa HTML). Simpan apa adanya dan **escape saat tampil**. Bila perlu pemformatan, hanya izinkan tanda baris baru dan daftar sederhana yang diproses di server menjadi elemen aman; **tidak ada editor HTML bebas dan tidak ada `{!! !!}`** untuk konten ini. Tolak tag HTML (`<` `>`) pada kolom teks dengan pesan jelas atau buang dengan `strip_tags` sebelum simpan, dan tetap escape saat tampil (pertahanan berlapis). Tautan eksternal hanya `https://`; `javascript:`, `data:` ditolak.
- **Gambar** (galeri, ekskul, prestasi, fasilitas): unggah lewat `BerkasService`-style pipeline yang sama (finfo MIME, hanya JPG, PNG, atau WebP, maksimal 2 MB, **di-encode ulang** untuk membuang EXIF dan muatan tersembunyi, nama acak, dimensi dibatasi). Disimpan di disk **publik** terpisah (`storage/app/public/konten/{sekolah}/`) khusus konten sekolah, **bukan** disk privat berkas pendaftar; keduanya tidak boleh bercampur. Berkas pendaftar tidak pernah masuk disk publik. Tambahkan `alt` teks untuk tiap gambar. Hapus baris menghapus berkasnya dari disk.
- Konten kosong tampil "Menyusul" atau "Foto menyusul" (diurus tahap 6). Jangan mengisi data karangan.
- Admin hanya mengubah konten sekolahnya (404 untuk lainnya). Setiap simpan dicatat di log.

## 7. Yayasan (`/yayasan`)

Middleware `auth`, `peran:yayasan`. Hanya baca.

- **Rekap** (`/yayasan/rekap`): tabel dan kartu angka agregat untuk keempat sekolah: pendaftar terkirim per gelombang dan per sekolah, per status hasil, Baru versus Lulusan Yayasan, pembayaran (jumlah, bukan per orang), daftar ulang selesai, mengundurkan diri, kuota terpakai versus kuota (yayasan boleh melihat kuota). Hasil **belum diumumkan** tetap boleh dihitung sebagai angka agregat (ini data internal, bukan walisantri), dan diberi label jelas.
- **Tidak ada** tautan, kolom, atau filter yang mengarah ke data per orang. Tidak ada tombol aksi. Rute selain `GET` ke `/yayasan` ditolak.
- Menu: Rekap, Profil akun, Ganti kata sandi. (Tombol unduh rekap **belum dibuat**; tahap 5.)

## 8. Atur ulang kata sandi staf

Ringkasan jalur (jangan tabrakan dengan tahap 3):
- Walisantri: oleh panitia, atau email mandiri (tahap 3).
- Panitia: oleh admin sekolahnya.
- Admin dan yayasan: oleh super admin.
- Super admin: lewat `php artisan spmb:atur-ulang-password` (tahap 3).
Halaman `/lupa-password` tahap 1 dan 3 diperbarui dengan teks sesuai ("Staf: hubungi atasan Anda"), tanpa mengungkap apakah akun ada.

## 9. Tampilan

Ikuti `docs/desain.md` bagian admin, super admin, dan yayasan: sidebar kiri per panel, kartu angka, tabel konsisten dengan panel panitia, formulir dengan label jelas dan pesan galat per kolom, modal konfirmasi untuk tindakan tidak terbalikkan (Umumkan Hasil, nonaktifkan akun, atur ulang kata sandi), toast, keadaan kosong. Panel yayasan dan super admin dibuat lebih sederhana. Layar umumkan hasil harus terasa **serius** (peringatan jelas, centang konfirmasi).

## 10. Tes (wajib)

Tambahkan ke `tests/Feature/` dan `tests/Feature/Keamanan/`:

1. **Matriks akses** semua rute `/admin`, `/super`, `/yayasan` per peran (tamu 302; walisantri, panitia, peran lain 403; hanya peran yang berhak 200). Admin sekolah A dan panitia sekolah A pada rute `/admin` sekolah B → 404 atau 403 sesuai kasus.
2. **Isolasi sekolah (admin):** admin A tidak bisa melihat, mengubah, atau mengumumkan gelombang, berkas, biaya, rekening, isian, konten, panitia, atau log sekolah B (uuid atau id valid) → 404. `sekolah_id` yang dikirim lewat formulir diabaikan.
3. **Super admin dan yayasan tidak melihat pendaftar:** untuk setiap rute `/super` dan `/yayasan` (semua yang ada), respons tidak memuat nama anak, NIK, nomor peserta, atau nama berkas dari data uji; tidak ada rute ke data pendaftar (uji dengan daftar rute: tidak ada rute berawalan `/super` atau `/yayasan` yang memuat `pendaftar`, `berkas`, `pembayaran` per orang).
4. **Gelombang:** validasi tanggal, kuota, dan URL grup WA (`javascript:` dan domain lain ditolak); kuota tidak bisa diturunkan di bawah jumlah Diterima; gelombang yang punya pendaftaran tidak bisa dihapus; kuota dan tautan tidak muncul di halaman publik atau respons walisantri sebelum syaratnya.
5. **Akun panitia oleh admin:** `peran` dan `sekolah_id` dipaksa; kata sandi sementara acak, tampil sekali, tidak ada di database sebagai teks asli, tidak di log aktivitas atau file log; nonaktifkan menghapus sesi dan memblokir masuk; admin tidak bisa mengelola admin, yayasan, super admin, atau panitia sekolah lain.
6. **Akun oleh super admin:** admin dan yayasan dibuat dengan peran dan sekolah benar; tidak bisa membuat super admin dari antarmuka; tidak bisa menonaktifkan diri sendiri atau super admin terakhir; atur ulang menghapus sesi.
7. **Umumkan Hasil:** membuat tepat satu baris `pengumuman`; percobaan kedua (juga dua permintaan berurutan) ditolak tanpa baris kedua; tanpa centang konfirmasi ditolak; peringatan tampil bila ada status Baru, Diverifikasi, atau Perlu perbaikan; gelombang sekolah lain 404; **sebelum** pengumuman hasil tidak bocor ke walisantri dan **sesudah** pengumuman terlihat; teks pesan tidak memuat nama anak, nomor peserta, atau hasil; hasil yang diputuskan setelah pengumuman langsung terlihat; panitia tidak bisa mengubah hasil setelah pengumuman tetapi admin bisa dengan catatan.
8. **Jenis berkas dan biaya:** tidak bisa dihapus bila dipakai; perubahan biaya tidak mengubah persetujuan pendaftaran yang sudah terkirim; nominal negatif atau bukan angka ditolak; rekening tidak muncul di halaman publik; log rekening hanya 4 digit terakhir.
9. **Isian:** isian inti tidak bisa disembunyikan atau dibuat tidak wajib; menyembunyikan isian tidak menghapus jawaban lama.
10. **Kelola Konten:** HTML dan `<script>` di semua kolom teks tidak dieksekusi (ter-escape atau dibuang); tautan `javascript:` ditolak; unggah gambar berbahaya (PHP berekstensi `.jpg`, SVG, file besar, MIME palsu) ditolak; gambar di-encode ulang tanpa EXIF; hanya disk publik konten yang dipakai, tidak ada berkas pendaftar di disk publik; admin sekolah lain tidak bisa mengubah.
11. **Log:** setiap tindakan di atas menulis `log_aktivitas`; log tidak bisa di-update atau dihapus lewat model; log tidak memuat kata sandi, token, atau nomor rekening utuh.
12. **Yayasan hanya baca:** metode selain `GET` ke rute `/yayasan` ditolak; angka rekap sesuai data uji; tidak ada tautan keluar ke data per orang.
13. **Mass assignment** pada semua formulir admin dan super admin (`peran`, `sekolah_id`, `dibuat_oleh`, `aktif`, `diumumkan_oleh`, `diumumkan_pada` tidak bisa disisipkan).
14. **XSS:** nilai berisi `<script>` pada nama gelombang, nama biaya, nama akun, dan konten ter-escape di semua halaman tahap ini.
15. **Regresi:** seluruh tes tahap 1 sampai 3 tetap lulus.

## 11. Kriteria selesai

- Semua butir `AGENTS.md` bagian 8 terpenuhi.
- Keamanan: K-03 (matriks lengkap `/admin`, `/super`, `/yayasan`), K-06, K-08 (log tak dapat diubah), K-09, K-10, K-17 (kuota, biaya, rekening tidak bocor), K-18, K-19, dan K-16 butir terkait terpenuhi dan dites.
- Dapat dicoba manual di lokal dengan data contoh: admin mengatur gelombang dan kuota, membuat akun panitia, mengisi konten, mengumumkan hasil dan melihat hasil di akun walisantri demo; super admin membuat admin dan yayasan; yayasan melihat rekap. Tuliskan langkahnya di laporan.
- Laporan akhir sesuai `AGENTS.md`.

**Berhenti di sini.** Jangan mulai tahap 5 sebelum diminta.
