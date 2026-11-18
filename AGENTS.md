# AGENTS.md — Sistem SPMB Yayasan Al-Ishlah Insan Mulia

Baca file ini penuh setiap kali memulai sesi. File ini adalah aturan tetap proyek. Rujukan lain ada di folder `docs/`, dan tugas per tahap ada di folder `prompts/`.

## 1. Apa yang dibangun

Sistem penerimaan murid baru (SPMB) berbasis web untuk Yayasan Al-Ishlah Insan Mulia, Wonosobo. Satu aplikasi, satu database, melayani empat sekolah:

| Kode | Sekolah | Catatan |
| --- | --- | --- |
| TKK | TKIT Al Ishlah Kertek | TK |
| TKI | KB-TKIT Insan Mulia | Satu sekolah dengan tiga program: KB, TK, TPA |
| SD | SDIT Insan Mulia Wonosobo | SD |
| SMP | SMPIT Insan Mulia Wonosobo | SMP, satu lokasi dengan SDIT |

Tahun ajaran yang dibuka: 2027/2028. Ini sistem sungguhan yang akan dipakai sekolah, bukan prototipe. Kualitas, keamanan, dan ketepatan data lebih penting daripada banyaknya fitur.

Pengguna: pengunjung, walisantri (orang tua atau wali), panitia SPMB, admin sekolah, super admin, yayasan. Aturan lengkap ada di `docs/aturan-sistem.md`.

## 2. Teknologi (tetap, jangan diganti)

- Laravel 13, PHP 8.3 atau lebih baru, MySQL 8.
- Blade untuk tampilan, Tailwind CSS (lewat Vite), Alpine.js untuk interaksi kecil.
- **Jangan** memakai Livewire, Inertia, React, Vue, atau SPA. Halaman dirender server.
- **Jangan** memakai paket peran seperti Spatie. Peran adalah kolom `users.peran`.
- Antrean: driver `database`. Email: SMTP, dan driver `log` saat lokal. Zona waktu `Asia/Jakarta`, bahasa `id`.
- Font di-host sendiri lewat paket `@fontsource-variable/plus-jakarta-sans`, bukan dari Google Fonts.
- Paket tambahan yang boleh dipakai, hanya bila perlu dan setelah dicek cocok dengan Laravel 13 lewat `composer require` (jangan menebak versi): ekspor Excel (`openspout/openspout` atau `maatwebsite/excel`), terjemahan Indonesia (`laravel-lang/lang`). Paket lain: tanya dulu.
- Untuk starter autentikasi, boleh memakai Laravel Breeze (stack Blade) bila cocok dengan Laravel 13. Kalau tidak cocok, tulis autentikasi sendiri dengan fitur bawaan Laravel. Login memakai nomor HP **atau** email, bukan hanya email.
- Bila ragu tentang API Laravel 13, baca dokumentasi resmi atau kode di `vendor/`. Jangan menebak.

## 3. Aturan kerja

1. Kerjakan **satu tahap sekali**, sesuai file di `prompts/`. Jangan mengerjakan tahap berikutnya sebelum diminta.
2. Di awal tiap tahap: baca `AGENTS.md`, `docs/aturan-sistem.md`, `docs/struktur-tabel.md`, `docs/keamanan.md`, dan file tahap itu. Lalu tulis rencana singkat (daftar file yang akan dibuat atau diubah) sebelum menulis kode.
3. Nama tabel dan kolom **harus persis** seperti di `docs/struktur-tabel.md`. Jangan mengganti nama, menambah kolom, atau menggabung tabel tanpa alasan tertulis. Bila menurutmu ada kesalahan di dokumen, hentikan dan laporkan, jangan diam-diam mengubah.
4. Tetapkan `protected $table` secara eksplisit di setiap model (pluralisasi Laravel akan salah untuk kata Indonesia, misalnya `anak` menjadi `anaks`).
5. Tulis tes untuk setiap aturan akses dan aturan bisnis penting. Tahap dianggap selesai hanya bila semua tes lulus dan `php artisan migrate:fresh --seed` berjalan bersih di database kosong.
6. Semua teks yang tampil ke pengguna dalam bahasa Indonesia. Nama kelas, metode, dan variabel boleh bahasa Indonesia mengikuti nama tabel (misalnya `Pendaftaran`, `StatusHasil`) atau bahasa Inggris bila istilah teknis. Pilih satu gaya dan konsisten.
7. Jangan membuat fitur di luar daftar. Ada daftar "Tidak dibangun" di bawah.
8. Tampilkan hasil kerja dengan `git commit` kecil dan bermakna (satu komit per sub-tugas). Jangan memasukkan `.env`, `storage/`, `vendor/`, `node_modules/`, atau data pribadi ke git.
9. Di akhir tahap, tulis laporan singkat: apa yang selesai, tes yang dijalankan beserta hasilnya, hal yang menyimpang dari dokumen dan alasannya, dan pertanyaan yang masih terbuka.

## 4. Struktur kode

```
app/
  Enums/            PeranPengguna, StatusHasil, StatusMurid, ModePenerimaan, MetodePembayaran, StatusPembayaran, StatusBerkas, StatusGelombang
  Models/           satu model per tabel
  Policies/         satu policy per model yang berisi data pendaftar
  Http/
    Controllers/    Publik/, Walisantri/, Panitia/, Admin/, SuperAdmin/, Yayasan/
    Middleware/     PeranMiddleware (alias `peran`), WajibGantiPassword, HeaderKeamanan
    Requests/       satu FormRequest per formulir
  Services/         NomorPesertaService, KuotaService, PengumumanService, DeteksiGandaService, BerkasService, EksporService, CetakService
  Models/Concerns/  MilikSekolah (global scope sekolah_id)
config/isian.php    katalog isian formulir (kunci, label, tipe, bagian)
resources/views/    layouts/, components/, publik/, walisantri/, panitia/, admin/, super-admin/, yayasan/
database/seeders/   lihat docs/data-awal.md
tests/Feature/      satu file per area, ditambah tests/Feature/Keamanan/
```

Awalan URL dan middleware:

| Awalan | Peran yang boleh | Isi |
| --- | --- | --- |
| `/` | Semua | Beranda, halaman sekolah, masuk, daftar akun |
| `/akun` | walisantri | Anak saya, formulir, berkas, biaya, hasil |
| `/panitia` | panitia, admin | Pendaftar, verifikasi, pembayaran, daftar ulang, unduh |
| `/admin` | admin | Gelombang, kuota, akun panitia, konten, pengaturan, Umumkan Hasil, rekap, log |
| `/super` | super_admin | Sekolah, akun admin, akun yayasan |
| `/yayasan` | yayasan | Rekap dan unduh rekap (hanya lihat) |

Admin mewarisi semua kemampuan panitia. Super admin dan yayasan **tidak** punya rute apa pun yang menampilkan data pendaftar.

## 5. Keputusan yang sudah final (jangan dipertanyakan, jangan diubah)

- Satu akun walisantri boleh punya banyak anak. Satu keluarga boleh punya dua akun (ayah dan ibu), datanya berdiri sendiri.
- Data ayah, ibu, dan wali disimpan di `orang_tua` milik akun walisantri dan dipakai ulang untuk semua anak di akun itu.
- Dua tingkat status: `status_hasil` (hasil seleksi) dan `status_murid` (Resmi atau Mengundurkan diri). Lihat `docs/aturan-sistem.md`.
- Hasil Diterima, Ditolak, atau Cadangan **tidak boleh terlihat** oleh walisantri sebelum admin menekan Umumkan Hasil untuk sekolah dan gelombang itu.
- Tidak ada biaya pendaftaran di web. Biaya pendidikan (IAP, iuran, seragam, biaya formulir) hanya tampil setelah login, tidak pernah di halaman publik. **Kuota juga tidak pernah tampil di halaman publik.**
- Pembayaran daftar ulang: transfer (unggah bukti, panitia menyetujui atau menolak) atau tunai (panitia menginput). Tidak ada payment gateway.
- Wawancara dilakukan bersamaan dengan daftar ulang di sekolah. Catatannya disimpan di `daftar_ulang.catatan_wawancara`, hanya terlihat panitia dan admin.
- Lupa kata sandi: panitia mengatur ulang dengan kata sandi sementara (wajib diganti saat masuk berikutnya). Reset mandiri lewat email tersedia sebagai tambahan bila SMTP aktif. **Tidak ada OTP WhatsApp.**
- Pengumuman ke walisantri lewat akun. Tombol "Bagikan ke WhatsApp" hanya membuat tautan `wa.me` dengan teks siap salin. **Tidak ada pengiriman WhatsApp otomatis.**
- Pendaftar offline: panitia membuat akun walisantri (nomor HP + kata sandi sementara), lalu mengisi data atas nama walisantri.
- Data ganda dideteksi dari kombinasi nama lengkap + tanggal lahir + sekolah. Anak kembar tidak terblokir karena namanya berbeda.
- Nomor peserta: `AWALAN-TAHUN-URUTAN4` (contoh `SD-2027-0001`). Awalan: `TKK`, `SD`, `SMP`, dan untuk KB-TKIT Insan Mulia memakai program (`KB`, `TK`, `TPA`). Urutan per awalan per tahun ajaran, dibuat saat pendaftaran dikirim, tanpa duplikat meski dua orang mengirim bersamaan.
- Perbedaan antarsekolah (gelombang, berkas, biaya, rekening, kuota, mode penerimaan, isian formulir) adalah **data pengaturan**, bukan kode terpisah per sekolah.
- Berkas anak tidak pernah disimpan di folder publik. Detail di `docs/keamanan.md`.
- Foto dan logo yang belum ada tampil sebagai kotak abu-abu "Foto menyusul" atau "Logo menyusul". Jangan membuat foto palsu.
- Penambahan terbaru yang belum ada di dokumen Word perancangan, tetapi berlaku (lihat `docs/struktur-tabel.md` bagian "Penambahan"): kolom `pendaftaran.program`, kolom `gelombang.tautan_grup_wa`, kolom `uuid` pada tabel yang URL-nya dipakai pengguna, dan kolom NIK yang dienkripsi.

## 6. Tidak dibangun (jangan dikerjakan)

- Pembayaran online atau payment gateway, pengiriman WhatsApp atau SMS otomatis, OTP.
- Impor data siswa lama atau sinkronisasi ke Dapodik atau SIMAK (hanya ekspor Excel/CSV).
- Pencatatan SPP bulanan.
- Aplikasi mobile, API publik, multi-bahasa.
- Penghapusan data pendaftar lewat antarmuka (data hanya diubah statusnya, bukan dihapus).

## 7. Keamanan adalah syarat, bukan tambahan

Sistem menyimpan data pribadi anak dan keluarga (NIK, nomor KK, alamat, kesehatan, foto dokumen). Semua poin K-01 sampai K-20 di `docs/keamanan.md` **wajib** dipenuhi dan dites pada tahap yang disebut di file itu. Bila ada konflik antara kemudahan dan keamanan, pilih keamanan dan laporkan.

Aturan singkat yang berlaku di setiap baris kode:
- Tolak secara bawaan: setiap rute di luar halaman publik butuh login, peran yang tepat, dan policy.
- Setiap query data pendaftar di sisi panitia atau admin dibatasi `sekolah_id` milik akun.
- Jangan percaya input apa pun. Validasi lewat FormRequest, jangan memakai `$request->all()` untuk mengisi model.
- Jangan pernah mencatat kata sandi, NIK utuh, atau isi berkas ke log.
- Jangan membuat kata sandi bawaan di seeder untuk produksi.

## 8. Definisi selesai untuk setiap tahap

- [ ] `php artisan migrate:fresh --seed` berhasil di database kosong.
- [ ] `php artisan test` lulus, termasuk tes akses dan keamanan tahap itu.
- [ ] `php artisan route:list` ditampilkan di laporan dan tiap rute punya middleware yang benar.
- [ ] Tampilan dicek di lebar 360 piksel (HP) dan 1280 piksel (desktop) tanpa guliran horizontal.
- [ ] Tidak ada `dd()`, `dump()`, `console.log`, atau kode percobaan yang tertinggal.
- [ ] Laporan akhir tahap ditulis (lihat aturan kerja nomor 9).
