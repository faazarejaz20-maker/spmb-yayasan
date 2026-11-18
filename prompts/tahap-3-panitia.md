# Tahap 3 — Panel panitia

Prasyarat: tahap 1 dan 2 selesai dan `php artisan test` lulus. Kerjakan hanya tahap ini. Jangan membangun Umumkan Hasil, pengaturan admin (gelombang, kuota, akun panitia, konten, biaya, rekening), super admin, yayasan, ekspor Excel, ZIP, atau cetak (tahap 4 sampai 6).

Tahap ini membangun semua yang dikerjakan **panitia** di bawah awalan `/panitia`. Admin sekolah mewarisi seluruhnya (rute `/panitia` dapat dibuka `panitia` dan `admin`). Termasuk: dasbor, daftar dan detail pendaftar, pemeriksaan berkas, keputusan, pembayaran, daftar ulang, pendaftar offline, kata sandi walisantri, dan perpindahan anak antar akun.

## 0. Sebelum menulis kode

1. Baca ulang `AGENTS.md`, `docs/aturan-sistem.md` (seluruhnya, terutama bagian 1, 2, 4, 5, 8, 9, 10), `docs/struktur-tabel.md`, `docs/keamanan.md` (K-03, K-06, K-07, K-08, K-09, K-10, K-16), `docs/desain.md` (bagian panitia dan admin), dan laporan tahap 1 dan 2.
2. Tulis rencana singkat (daftar file), lalu langsung kerjakan.

## 1. Prinsip rancangan yang wajib dipatuhi

- **Gunakan ulang service tahap 2** (`PendaftaranService`, `BerkasService`, `AturanIsian`, `DeteksiGandaService`, `NomorPesertaService`, `StatusTampilService`). Jangan menyalin logika. Perluas bila perlu, dan pastikan tes tahap 2 tetap lulus.
- **Semua query data pendaftar** di sisi panitia memakai global scope `MilikSekolah` dan Policy. Setiap rute berparameter memakai `uuid`. Akses ke data sekolah lain menghasilkan **404**.
- **Panitia dan admin hanya melihat pendaftaran yang sudah dikirim** (scope `terkirim()`). Draf walisantri tidak tampil di daftar panitia (draf yang dibuat panitia untuk pendaftar offline dikelola dari halaman pendaftar offline, lihat bagian 8).
- **Tidak ada penghapusan data pendaftar** lewat antarmuka. Hanya perubahan status. Tidak ada tombol hapus di mana pun.
- **Setiap perubahan** oleh panitia ditulis ke `log_aktivitas` (ringkasan perubahan; untuk NIK dan data sensitif cukup menyebut nama kolom yang berubah, bukan nilainya) dan, untuk status, ke `riwayat_status`.
- Teks dari walisantri (nama, alamat, catatan) selalu di-escape. `{!! !!}` dilarang.

## 2. Kebijakan akses dan rute

Perluas `PendaftaranPolicy`, `BerkasPolicy`, `PembayaranPolicy`, dan buat `AkunWalisantriPolicy`.

`AkunWalisantriPolicy::kelola(User $staf, User $walisantri)` benar bila akun walisantri itu **dibuat oleh staf dari sekolah yang sama** (`dibuat_oleh` menunjuk `users` dengan `sekolah_id` sama), **atau** punya minimal satu pendaftaran terkirim ke sekolah staf itu. Selain itu ditolak. Aturan ini dipakai untuk atur ulang kata sandi, perpindahan anak, dan mendaftarkan anak atas nama akun. Staf tidak pernah boleh mengelola akun staf (termasuk panitia lain) dari sini.

Rute (awalan `/panitia`, nama rute berawalan `panitia.`, middleware `auth`, `peran:panitia,admin`):

| Metode dan jalur | Fungsi |
| --- | --- |
| `GET /panitia` | Dasbor |
| `GET /panitia/pendaftar` | Daftar pendaftar (cari, saring, urut, halaman) |
| `GET /panitia/pendaftar/{pendaftaran}` | Detail pendaftar (tab) |
| `PUT /panitia/pendaftar/{pendaftaran}/data` | Ubah data anak dan orang tua |
| `POST /panitia/pendaftar/{pendaftaran}/berkas/{berkas}/periksa` | Tandai berkas sesuai atau perlu perbaikan |
| `POST /panitia/pendaftar/{pendaftaran}/berkas` | Panitia mengunggah berkas atas nama walisantri |
| `GET /panitia/berkas/{berkas}` | Menampilkan berkas (lewat controller dan policy) |
| `POST /panitia/pendaftar/{pendaftaran}/keputusan` | Ubah status hasil |
| `PUT /panitia/pendaftar/{pendaftaran}/catatan` | Catatan internal (`catatan_panitia`) |
| `GET /panitia/pembayaran` | Antrean bukti transfer menunggu |
| `POST /panitia/pembayaran/{pembayaran}/setujui`, `.../tolak` | Setujui atau tolak bukti |
| `GET /panitia/pembayaran/{pembayaran}` | Menampilkan bukti (lewat controller dan policy) |
| `POST /panitia/pendaftar/{pendaftaran}/pembayaran-tunai` | Catat pembayaran tunai |
| `PUT /panitia/pendaftar/{pendaftaran}/daftar-ulang` | Catat daftar ulang dan wawancara |
| `POST /panitia/pendaftar/{pendaftaran}/mengundurkan-diri` | Catat mengundurkan diri |
| `GET/POST /panitia/offline/...` | Pendaftar offline (bagian 8) |
| `POST /panitia/pendaftar/{pendaftaran}/atur-ulang-password` | Atur ulang kata sandi walisantri |
| `POST /panitia/pendaftar/{pendaftaran}/pindah-anak` | Pindahkan anak ke akun walisantri lain |

## 3. Dasbor

Lima kartu angka untuk sekolahnya, dari pendaftaran terkirim: Total pendaftar, Menunggu verifikasi (Baru), Perlu perbaikan, Diterima, Cadangan. Di bawahnya, kemajuan kuota per gelombang yang dibuka ("Diterima 18 dari 28"; tanpa batas bila kuota kosong) dan antrean "Bukti transfer menunggu" (jumlah, tautan). Panitia boleh melihat kuota (perlu untuk memutuskan); **kuota tidak pernah tampil ke walisantri atau publik**.

## 4. Daftar pendaftar

- Pencarian: nama anak atau nomor peserta (query berparameter; `LIKE` dengan karakter `%` dan `_` di-escape).
- Penyaring: gelombang, status hasil, Baru atau Lulusan Yayasan, status pembayaran, program (bila TKI), kelengkapan berkas.
- Urutan lewat **daftar putih** kolom (`nomor_peserta`, `nama`, `dikirim_pada`, `status_hasil`); bawaan menurut `dikirim_pada` naik bila mode `tanpa_seleksi`, terbaru dulu bila `seleksi`. Halaman 25 baris.
- Kolom: No. peserta, Nama, Jenis (Baru atau Lulusan Yayasan, program), Gelombang, Berkas (misalnya "3/4 sesuai"), Pembayaran, Status, Aksi. **NIK tidak ditampilkan di daftar.**
- Spanduk kuning bila kuota Diterima gelombang tercapai. Tombol "+ Pendaftar Offline". (Tombol "Unduh Excel" **belum dibuat**; itu tahap 5.)
- Hindari N+1 (eager loading), dan tes dengan banyak data tidak boleh lambat.

## 5. Detail pendaftar

Kepala: nama, nomor peserta, lencana status, nama sekolah dan program, tombol "Atur ulang kata sandi" dan "Pindahkan anak". (Tombol "Cetak Formulir" dan "Unduh Berkas (ZIP)" **belum dibuat**; tahap 5.) Tab:

1. **Data**: data anak dan orang tua, bisa disunting. Bentuk isian dari `AturanIsian` dan pengaturan sekolah, sama dengan formulir walisantri. NIK utuh tampil di sini untuk panitia dan admin. Simpan mencatat ringkasan perubahan ke log. Panitia boleh mengubah kapan saja tanpa izin admin.
2. **Berkas**: pratinjau (lewat `GET /panitia/berkas/{berkas}`), tombol "Sesuai" dan "Perlu perbaikan" dengan kolom catatan wajib bila perlu perbaikan, tombol unggah atas nama walisantri (memakai `BerkasService`, pelaku = panitia). Setiap penandaan mengisi `status`, `catatan`, `diperiksa_oleh`, `diperiksa_pada` dan dicatat di log.
3. **Pembayaran**: daftar pembayaran (transfer dan tunai), pratinjau bukti, tombol Setujui dan Tolak (alasan wajib), formulir "Catat pembayaran tunai".
4. **Daftar ulang**: hadir, kekurangan, catatan wawancara dengan ikon gembok dan tulisan "Hanya terlihat panitia dan admin sekolah", tombol "Selesai, jadikan murid resmi", dan "Catat mengundurkan diri".
5. **Riwayat**: seluruh `riwayat_status` dan log terkait pendaftaran ini, hanya baca.

Panel samping **Keputusan**: status saat ini, pilihan status berikutnya yang diizinkan (bagian 6), kolom catatan, tombol "Simpan keputusan". Di bawahnya kolom "Catatan internal" (`catatan_panitia`, tidak tampil ke walisantri).

## 6. Keputusan status (`app/Services/StatusHasilService.php`)

Satu-satunya tempat yang mengubah `status_hasil`. Menerima `Pendaftaran`, status tujuan, `User $pelaku`, dan catatan. Semuanya dalam satu transaksi. Aturan perpindahan persis tabel di `docs/aturan-sistem.md` bagian 4, ditambah:

- **Diverifikasi**: ditolak bila ada berkas berstatus `perlu_perbaikan`. Bila ada berkas wajib yang masih `menunggu` atau belum ada, tampilkan peringatan dan minta centang konfirmasi (tidak diblokir).
- **Perlu perbaikan**: catatan **wajib**, ditulis untuk walisantri (bahasa sopan dan jelas). Catatan ini disimpan di `riwayat_status.catatan` pada perpindahan itu dan **itulah satu-satunya catatan yang tampil ke walisantri** (bersama catatan per berkas). Catatan untuk transisi lain tidak pernah tampil ke walisantri. **Perbarui halaman detail status walisantri di tahap 2** agar menampilkan catatan ini (dari baris `riwayat_status` terakhir dengan `ke = 'Perlu perbaikan'`) ketika status sedang Perlu perbaikan, dan tambahkan tesnya.
- **Diterima, Ditolak, Cadangan**: hanya dari `Diverifikasi` atau `Cadangan`. Setelah **sudah diumumkan** (`PengumumanService::sudahDiumumkan($pendaftaran)` memeriksa baris `pengumuman` untuk sekolah dan gelombang itu; buat metodenya sekarang, tahap 4 yang membuat barisnya), perubahan antara Diterima, Ditolak, dan Cadangan **hanya boleh oleh `admin`** dan dengan catatan wajib. Sebelum diumumkan, panitia boleh.
- **Kuota** (`app/Services/KuotaService.php`): kuota terisi = jumlah pendaftaran pada gelombang itu dengan `status_hasil = Diterima` dan `status_murid` bukan `Mengundurkan diri`. Bila kuota diisi dan terisi sudah sama, **Diterima ditolak** dengan pesan "Kuota penuh, pilih Cadangan atau minta admin menaikkan kuota". Pemeriksaan **di server, di dalam transaksi**, dengan mengunci baris `gelombang` (`lockForUpdate`) sebelum menghitung, supaya dua panitia yang menerima bersamaan tidak melewati kuota. Tombol Diterima juga dinonaktifkan di tampilan (hanya kemudahan, bukan pengaman).
- Mode `tanpa_seleksi`: tampilkan petunjuk "Urutan daftar: ke-{n} dari gelombang ini" dan sarankan Cadangan bila kuota penuh; keputusan tetap di tangan panitia.
- Setiap perubahan menulis `riwayat_status` (jenis `hasil`, dari, ke, oleh, catatan) dan `log_aktivitas`. Gagal di tengah → batal semua.

## 7. Pembayaran dan daftar ulang

- **Antrean pembayaran** (`/panitia/pembayaran`): bukti transfer berstatus `menunggu` untuk sekolahnya, urut terlama, dengan tautan ke detail pendaftar.
- **Setujui**: status `disetujui`, `diproses_oleh` = pelaku. **Tolak**: status `ditolak`, `catatan_penolakan` wajib, tampil ke walisantri. Yang sudah diproses tidak bisa diproses ulang kecuali admin (dengan catatan). Log setiap tindakan.
- **Tunai**: panitia mengisi nominal, tanggal, dan (opsional) komponen biaya (`rincian_biaya_id`). Dibuat langsung `disetujui`, `metode = tunai`, `diproses_oleh` = pelaku. Nominal harus angka positif.
- **Daftar ulang** (`DaftarUlangService`): hanya bila pendaftar `Diterima` **dan** ada minimal satu pembayaran `disetujui`. Isi `hadir`, `kekurangan`, `catatan_wawancara`, `selesai`. Saat `selesai` menjadi benar: `status_murid = Resmi` (riwayat jenis `murid`, log). Satu baris `daftar_ulang` per pendaftaran (unique). Hanya admin yang boleh membatalkan "selesai" (mengembalikan `status_murid` ke kosong), dengan catatan wajib.
- **Mengundurkan diri**: hanya bila `Diterima` atau `Resmi`; `status_murid = Mengundurkan diri`; membebaskan kuota; riwayat dan log.
- `catatan_wawancara`, `kekurangan`, dan `catatan_panitia` **tidak boleh** terbawa ke tampilan, respons, atau data apa pun untuk walisantri (periksa lagi tes tahap 2).

## 8. Pendaftar offline

1. **Tombol "+ Pendaftar Offline"** membuka pilihan: "Akun baru" atau "Cari akun (nomor HP)".
   - **Akun baru**: nama, nomor HP (dinormalisasi, harus belum terdaftar), email opsional. Sistem membuat `walisantri` dengan kata sandi sementara acak, `wajib_ganti_password = true`, `dibuat_oleh` = pelaku. Kata sandi sementara ditampilkan **satu kali** (flash sesi) dengan tombol Salin dan **Cetak kartu akun** (halaman cetak sederhana: nama sekolah, nama pengguna, kata sandi sementara, alamat situs, catatan "Segera ganti kata sandi"). Kata sandi sementara **tidak disimpan dan tidak pernah dicatat di log**.
   - **Cari akun**: pencarian hanya nomor HP persis dan hanya mencari akun yang lolos `AkunWalisantriPolicy::kelola`. Bila tidak ditemukan atau tidak lolos, tampilkan pesan yang sama: "Akun tidak ditemukan. Buat akun baru, atau minta walisantri masuk dan mendaftar sendiri." Jangan membocorkan keberadaan akun di luar lingkup.
2. Setelah akun siap, panitia mengisi data orang tua, memilih sekolah, gelombang, dan program, mengisi data anak (8 langkah, **memakai service dan tampilan yang sama** dengan walisantri tetapi pelaku = panitia, spanduk "Diisi oleh panitia atas nama walisantri"), mengunggah berkas (hasil pindai atau foto), memilih IAP, menyetujui biaya, lalu Kirim. Semua aturan Kirim di tahap 2 tetap berlaku (data ganda, kelengkapan, nomor peserta). `pendaftaran.dibuat_oleh` = panitia. Pernyataan "data benar" dan persetujuan penggunaan data dicentang oleh panitia atas konfirmasi lisan walisantri, dan dicatat di log.
3. Draf offline yang belum dikirim ditampilkan di bagian "Draf offline" pada halaman daftar pendaftar (hanya draf yang dibuat staf sekolah itu), supaya bisa dilanjutkan.
4. Karena `users` tidak punya `uuid`, alur langkah memakai `uuid` pendaftaran draf setelah dibuat. Untuk tahap sebelum pendaftaran draf ada (setelah akun dibuat, sebelum memilih sekolah), simpan identitas akun di **sesi** (bukan di URL), dan bersihkan setelah selesai.

## 9. Kata sandi walisantri dan perpindahan anak

- **Atur ulang kata sandi** (`/panitia/pendaftar/{pendaftaran}/atur-ulang-password`): hanya akun yang lolos `AkunWalisantriPolicy::kelola`; peran harus `walisantri` (akun staf **tidak** bisa diatur ulang dari sini). Dialog konfirmasi. Kata sandi sementara 10 karakter dari huruf dan angka tanpa `0 O 1 l I`, dibuat dengan `random_int`, ditampilkan sekali dengan Salin dan Cetak kartu akun, `wajib_ganti_password = true`, **semua sesi aktif akun itu dihapus**. Batas 20 per jam per panitia. Dicatat di log (tanpa kata sandi).
- **Reset mandiri lewat email** (melengkapi halaman `/lupa-password` dari tahap 1): hanya untuk akun `walisantri` yang punya email. Pakai mekanisme broker kata sandi Laravel dengan tabel `password_reset_tokens`, token berlaku 60 menit dan sekali pakai. Formulir meminta email; respons **selalu sama** ("Jika email terdaftar, tautan telah dikirim") baik terdaftar atau tidak, termasuk waktu respons yang tidak jauh berbeda. Email hanya memuat tautan bertanda tangan, tidak memuat kata sandi. Batas 3 per jam per email dan 10 per jam per IP. Surat memakai antrean (`database`); lokal memakai `MAIL_MAILER=log`. Staf (panitia, admin, super admin, yayasan) tidak bisa memakai jalur ini; mereka diatur ulang oleh atasannya (tahap 4). Setelah reset, kata sandi baru mengikuti aturan kata sandi dan sesi lama dihapus. Halaman `/lupa-password` tetap menampilkan penjelasan "hubungi panitia" dan tombol WhatsApp sekolah, ditambah opsi email.
- **Perintah pemulihan** `php artisan spmb:atur-ulang-password {nomor_hp}` (interaktif, kata sandi baru dua kali, `wajib_ganti_password = true`, menghapus sesi akun itu) untuk situasi super admin lupa kata sandi. Hanya bisa dijalankan di server.
- **Pindahkan anak** (`.../pindah-anak`): memilih akun walisantri tujuan lewat nomor HP persis; kedua akun harus lolos `AkunWalisantriPolicy::kelola` dan **semua pendaftaran anak itu harus berada di sekolah panitia** (bila anak punya pendaftaran di sekolah lain, tolak dengan penjelasan). Mengubah `anak.user_id`. Dialog konfirmasi, dicatat di log (akun asal dan tujuan, tanpa data sensitif).

## 10. Tampilan

Ikuti `docs/desain.md` bagian panel panitia dan admin: sidebar kiri (logo sekolah, menu: Dasbor, Pendaftar, Pembayaran, akun), tabel dengan kepala melekat dan bisa digulir horizontal di layar kecil, penyaring dan pencarian, lencana status, modal konfirmasi untuk tindakan yang tidak bisa dibatalkan, toast, keadaan kosong. Panel dirancang desktop dulu tetapi tetap terbaca di tablet. Lengkapi komponen `tabel`, `tab`, `modal-konfirmasi`, dan lencana status untuk status pembayaran dan berkas.

## 11. Tes (wajib)

Tambahkan ke `tests/Feature/` dan `tests/Feature/Keamanan/`:
1. **Matriks akses** semua rute `/panitia/...` diperluas per peran: tamu 302; walisantri, super admin, yayasan 403; panitia dan admin sekolah sendiri 200.
2. **Isolasi sekolah:** panitia dan admin sekolah A membuka, mengubah, menandai berkas, memutuskan, menyetujui bukti, dan mengunduh berkas dan bukti milik sekolah B (uuid valid) → 404 untuk semua.
3. **Draf walisantri** tidak tampil di daftar dan tidak bisa dibuka lewat rute panitia.
4. **Daftar:** pencarian, penyaring, urutan dengan kolom di luar daftar putih diabaikan; NIK tidak muncul di daftar; `%` dan `_` pada pencarian diperlakukan literal.
5. **Matriks perpindahan status:** semua perpindahan yang diizinkan berhasil, yang tidak diizinkan ditolak; Diterima, Ditolak, dan Cadangan hanya dari `Diverifikasi` atau `Cadangan`; Diverifikasi ditolak bila ada berkas `perlu_perbaikan`; setelah diumumkan, panitia ditolak mengubah hasil dan admin berhasil dengan catatan; tanpa catatan ditolak.
6. **Kuota:** Diterima ditolak saat penuh; kuota kosong berarti tanpa batas; mengundurkan diri membebaskan kuota; tes bahwa pemeriksaan memakai transaksi dengan kunci (misalnya dua permintaan berurutan pada kuota 1 hanya satu yang berhasil).
7. **Perlu perbaikan:** catatan wajib; walisantri melihat catatan itu dan hanya itu (tidak ada `catatan_panitia`, dan tidak ada catatan dari transisi lain); setelah perbaikan dikirim, status kembali `Baru`.
8. **Berkas:** penandaan menyimpan `diperiksa_oleh` dan `diperiksa_pada`; catatan wajib untuk perlu perbaikan; unggah atas nama walisantri memakai validasi yang sama (file berbahaya ditolak).
9. **Pembayaran:** setujui, tolak (alasan wajib), tunai langsung `disetujui`; tidak bisa diproses ulang oleh panitia; antrean hanya sekolahnya.
10. **Daftar ulang:** ditolak bila belum Diterima atau belum ada pembayaran `disetujui`; selesai membuat `status_murid = Resmi`; hanya admin bisa membatalkan.
11. **Pendaftar offline:** akun baru memakai kata sandi sementara acak (dua kali percobaan menghasilkan nilai berbeda), `wajib_ganti_password = true`, `dibuat_oleh` benar; kata sandi tidak ada di database dalam bentuk asli, tidak di `log_aktivitas`, tidak di file log; pencarian akun di luar lingkup memberi pesan yang sama dengan "tidak ditemukan"; pendaftaran yang dikirim panitia memenuhi semua aturan Kirim (data ganda, nomor peserta).
12. **Atur ulang kata sandi:** akun walisantri dalam lingkup berhasil dan sesi lamanya terhapus; akun staf dan akun di luar lingkup ditolak; batas 20 per jam memberi 429; dicatat di log tanpa kata sandi.
13. **Reset email:** respons sama untuk email terdaftar dan tidak terdaftar; token kedaluwarsa setelah 60 menit dan sekali pakai; batas laju 429; akun staf tidak bisa memakainya; surat tidak memuat kata sandi.
14. **Pindahkan anak:** hanya antara akun dalam lingkup dan bila semua pendaftaran anak di sekolah panitia; ditolak bila anak punya pendaftaran di sekolah lain.
15. **Mass assignment:** mengirim `sekolah_id`, `user_id`, `status_hasil`, `nomor_peserta`, `peran`, `dibuat_oleh` lewat formulir panitia tidak mengubah nilai itu.
16. **Log:** setiap tindakan di atas menulis baris `log_aktivitas`; ubah data tidak memuat nilai NIK; `riwayat_status` bertambah pada tiap perubahan status.
17. **XSS:** nama, alamat, dan catatan berisi `<script>` ter-escape di semua halaman panitia.
18. **Kebocoran ke walisantri:** ulangi tes tahap 2 (hasil sebelum pengumuman, `catatan_panitia`, `catatan_wawancara`, `kekurangan`, kuota) setelah semua fitur tahap 3 ada.

## 12. Kriteria selesai

- Semua butir `AGENTS.md` bagian 8 terpenuhi.
- Keamanan: K-03 (matriks lengkap untuk `/panitia`), K-06, K-07 (NIK disamarkan di daftar), K-08 (log tiap tindakan), K-09 (atur ulang kata sandi, reset email), K-10, K-16 butir 1 sampai 4, 10, 14 (ulang) terpenuhi dan dites.
- Dapat dicoba manual di lokal dengan data contoh: masuk sebagai panitia, memeriksa berkas, memutuskan, menyetujui bukti, mencatat daftar ulang, membuat pendaftar offline dan mencetak kartu akun. Tuliskan langkahnya di laporan.
- Laporan akhir sesuai `AGENTS.md`.

**Berhenti di sini.** Jangan mulai tahap 4 sebelum diminta.
