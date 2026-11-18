# Persyaratan keamanan (wajib)

Sistem menyimpan data pribadi anak dan keluarga: NIK, nomor KK, alamat, data kesehatan, dan foto dokumen. Data anak termasuk data pribadi yang dilindungi (UU Pelindungi Data Pribadi, UU No. 27 Tahun 2022), jadi pengelolaannya harus hati-hati. Semua butir di bawah **wajib** dipenuhi dan dites. Kolom "Tahap" menyebut tahap pertama yang harus memenuhinya; setelah itu berlaku terus.

Bila ada butir yang tidak bisa dipenuhi, hentikan dan laporkan. Jangan menurunkan standar diam-diam.

## K-01 Transport dan header (tahap 1)
- Produksi hanya lewat HTTPS. Di `AppServiceProvider` paksa skema `https` bila `APP_ENV=production`. Atur proxy tepercaya sesuai hosting.
- Middleware `HeaderKeamanan` untuk semua respons: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(self), microphone=(), geolocation=()`, dan `Strict-Transport-Security` (produksi saja).
- Content-Security-Policy: mulai sebagai `Content-Security-Policy-Report-Only` di tahap 1, ubah menjadi enforce di tahap 6 setelah semua halaman dites. Dasarnya: `default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; frame-ancestors 'none'; form-action 'self'`. Gunakan build CSP dari Alpine (`@alpinejs/csp`) agar tidak butuh `unsafe-eval`. Tidak ada skrip atau gaya dari CDN luar.
- Respons halaman yang sudah login dan semua unduhan: `Cache-Control: no-store, private`.

## K-02 Autentikasi (tahap 1)
- Kata sandi di-hash (bcrypt atau argon2 bawaan Laravel). Aturan kata sandi di `docs/aturan-sistem.md` bagian 2.
- Batasi percobaan masuk: maksimal 5 kali per menit per kombinasi (nomor HP atau email) + alamat IP, lalu kunci sementara dengan pesan umum. Pesan galat masuk **sama** untuk akun tidak ada dan kata sandi salah.
- `session()->regenerate()` setelah masuk, `invalidate()` dan regenerasi token setelah keluar. Cookie sesi: `secure` (produksi), `http_only`, `same_site=lax`. Driver sesi `database`.
- Masa sesi: 120 menit tidak aktif untuk panitia, admin, super admin, dan yayasan; walisantri boleh lebih lama (misalnya 8 jam). Fitur "ingat saya" dimatikan untuk semua peran staf.
- Akun `aktif = false` tidak bisa masuk dan sesinya yang berjalan ditolak pada permintaan berikutnya.
- Middleware `WajibGantiPassword` memblokir semua rute (kecuali ganti kata sandi dan keluar) selama `wajib_ganti_password = true`.
- Pengubahan kata sandi mewajibkan kata sandi lama, kecuali setelah reset sementara.

## K-03 Otorisasi: tolak secara bawaan (tahap 1, diperluas tiap tahap)
- Semua rute di luar halaman publik dibungkus `auth` dan middleware `peran:...`.
- Setiap model yang berisi data pendaftar punya Policy (`view`, `update`, dan seterusnya) yang memeriksa peran **dan** kepemilikan: walisantri hanya miliknya (`anak.user_id`), panitia dan admin hanya `sekolah_id` yang sama.
- Pakai trait `MilikSekolah` (global scope) pada query panitia dan admin sehingga data sekolah lain tidak pernah ikut terambil, bahkan bila ada kesalahan di controller. Gunakan route model binding yang dibatasi (`scopeBindings`) atau cek eksplisit.
- `super_admin` dan `yayasan` **tidak punya satu pun** rute yang memuat data pendaftar per orang. Yayasan hanya mendapat angka agregat.
- Matriks akses dites otomatis (lihat K-16).

## K-04 Identifikasi objek yang tidak bisa ditebak (tahap 2)
- URL untuk anak, pendaftaran, berkas, dan pembayaran memakai `uuid`, bukan `id` berurutan. Ini lapisan tambahan; policy tetap wajib di setiap akses (jangan menganggap uuid sudah cukup).
- Jangan menaruh data pribadi (nama, NIK, nomor HP) di URL atau query string.

## K-05 Unggah berkas (tahap 2)
- Simpan di disk **privat** (`storage/app/private/berkas/...`), bukan `public`. Nama file acak (uuid) tanpa nama asli pengguna. Nama asli boleh disimpan di kolom bila perlu, tidak dipakai sebagai path.
- Validasi: ekstensi dan tipe MIME dari isi file (bukan hanya dari header), hanya `jpg`, `jpeg`, `png`, `pdf`; ukuran maksimal 2 MB. Batas ini **tidak** dinaikkan meskipun foto tampilan sekolah naik ke 10 MB, karena berkas pendaftar berisi data pribadi. Tolak HEIC dengan pesan jelas. Untuk gambar, baca ulang dengan pustaka gambar dan simpan ulang (menghapus metadata EXIF dan payload tersembunyi); untuk PDF, cek tanda tangan file `%PDF`.
- Tolak file dengan banyak ekstensi (`a.php.jpg`), nama berbahaya, atau isi yang tidak cocok dengan ekstensinya. Jangan pernah menyajikan file unggahan dengan tipe yang dikirim pengguna; tentukan `Content-Type` dari hasil deteksi sendiri.
- Unduhan hanya lewat controller yang memeriksa policy, dengan `Content-Disposition` dan `X-Content-Type-Options: nosniff`. Tidak ada tautan langsung ke storage. `php artisan storage:link` hanya untuk folder publik berisi gambar konten sekolah, **bukan** berkas pendaftar.
- Pembatasan laju unggah: 20 per menit per pengguna.
- Gambar konten sekolah (Kelola Konten, disk publik `konten/`): aturan validasi yang sama (jpg, png, webp), nama acak, dikecilkan otomatis. Ukuran unggahan maksimal 10 MB (`SPMB_KONTEN_MAKS_KB`) karena foto tampilan sering berasal dari kamera HP; isi berkas tetap diperiksa dari MIME sebenarnya dan selalu ditulis ulang.
- Foto sampul kartu sekolah (Pengaturan, disk publik `sampul/`): jpg, png, atau webp, ukuran unggahan maksimal 10 MB (`SPMB_SAMPUL_MAKS_KB`) karena foto kamera HP sering 3–10 MB. Pemeriksaan tetap ketat: MIME dari isi berkas, gambar di atas 40 megapiksel ditolak sebelum dimuat ke memori, dan gambar selalu dibaca ulang lalu ditulis ulang sehingga EXIF serta payload tersembunyi hilang. Hasil simpan diperkecil ke sisi terpanjang 1600 px, jadi batas 10 MB hanya berlaku pada berkas unggahan, bukan pada berkas yang disajikan.

## K-06 Masukan dan keluaran (setiap tahap)
- Semua masukan lewat `FormRequest` dengan aturan eksplisit. Model memakai `$fillable` (bukan `$guarded = []`). Jangan memakai `$request->all()` untuk mengisi model.
- Hanya Eloquent atau query berparameter. Dilarang menyusun SQL dari masukan pengguna. Untuk `orderBy` dari masukan, gunakan daftar putih kolom.
- Blade memakai `{{ }}` (otomatis di-escape). `{!! !!}` dilarang, kecuali untuk isi yang sudah dibersihkan dan dibatasi (lihat di bawah).
- Kelola Konten: isi teks biasa. Bila perlu format, pakai Markdown terbatas yang dirender dan dibersihkan (hapus HTML mentah) atau editor sederhana dengan HTML yang diloloskan lewat daftar putih tag (`p, br, strong, em, ul, ol, li, a[href]`) memakai HTML Purifier. Tautan hanya `http`, `https`, `mailto`, `tel`, dan `wa.me`. Jangan izinkan `javascript:` atau atribut `on*`.
- Ekspor Excel dan CSV: lindungi dari injeksi rumus. Sel teks yang diawali `=`, `+`, `-`, `@`, tab, atau karakter kontrol diberi awalan tanda kutip `'`.
- Redirect hanya ke rute internal (tidak ada parameter `redirect` bebas).

## K-07 Perlindungan data sensitif (tahap 2)
- `anak.nik`, `anak.no_kk`, `orang_tua.nik` dienkripsi (cast `encrypted`). Daftar dan tabel menampilkan NIK dalam bentuk disamarkan (4 digit terakhir); NIK utuh hanya di halaman detail untuk peran yang berhak dan di ekspor.
- Kolom sensitif disembunyikan secara bawaan (`$hidden`) pada model, agar tidak terbawa serialisasi.
- Jangan menulis NIK, nomor KK, kata sandi, token, atau isi berkas ke log aplikasi, pesan galat, atau `log_aktivitas`. Atur `APP_DEBUG=false` di produksi dan sediakan halaman galat 403, 404, 419, 429, 500 tanpa jejak tumpukan.
- Backup: lihat K-13. `APP_KEY` dijaga ketat; kehilangan `APP_KEY` berarti kolom terenkripsi tidak bisa dibaca lagi, jadi simpan salinannya terpisah dari server.

## K-08 Jejak audit (tahap 1 mulai, lengkap di tahap 4)
- Daftar peristiwa yang dicatat ada di `docs/aturan-sistem.md` bagian 14. Tabel `log_aktivitas` dan `riwayat_status` hanya bisa ditambah: model melarang `update` dan `delete`, dan tidak ada rute yang mengubahnya.
- Log memuat siapa (`user_id`), sekolah, aksi, objek, ringkasan, waktu, dan alamat IP. Admin sekolah hanya membaca log sekolahnya.

## K-09 Pembatasan laju (tahap 1 dan 2)
Gunakan `RateLimiter` dengan pesan sopan (HTTP 429):
| Aksi | Batas |
| --- | --- |
| Masuk | 5 per menit per (identitas + IP) |
| Daftar akun | 10 per jam per IP |
| Reset email | 3 per jam per email dan 10 per jam per IP |
| Unggah berkas | 20 per menit per pengguna |
| Unduh berkas dan ZIP | 60 per menit (berkas), 5 per menit (ZIP) per pengguna |
| Ekspor | 5 per menit per pengguna |
| Atur ulang kata sandi walisantri oleh panitia | 20 per jam per panitia |

## K-10 Kata sandi sementara dan reset (tahap 3)
- Kata sandi sementara dibuat dengan generator acak yang aman secara kriptografis (`random_int`), ditampilkan satu kali (flash session, tidak disimpan, tidak di-log), lalu `wajib_ganti_password = true`.
- Setiap atur ulang menghapus sesi aktif akun itu.
- Pesan reset email tidak membocorkan apakah email terdaftar. Token berlaku 60 menit dan sekali pakai.
- Panitia hanya bisa mengatur ulang akun walisantri yang punya pendaftaran ke sekolahnya, atau yang ia buat sendiri. Panitia tidak bisa mengatur ulang akun staf.

## K-11 Konfigurasi produksi (tahap 6, dan di dokumen penerapan)
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` terisi, `LOG_LEVEL=warning` atau `error`.
- `.env` tidak di git, izin file ketat (0600). Docroot hanya `public/`. Folder `.git`, `storage`, `vendor`, `.env` tidak bisa diakses dari web. Tidak ada listing direktori.
- `php artisan config:cache`, `route:cache`, `view:cache`, dan `optimize`.
- `composer audit` dan `npm audit` dijalankan dan hasilnya dilaporkan di akhir tahap 6. `composer.lock` dan `package-lock.json` masuk git.
- Cookie dan sesi sesuai K-02. Pustaka dev (`dd`, debugbar, ide-helper) tidak terpasang di produksi (`require-dev`).

## K-12 Akun awal dan seeder (tahap 1)
- Tidak ada kata sandi bawaan di produksi. Super admin pertama dibuat dengan perintah artisan interaktif `php artisan spmb:buat-super-admin` (meminta nama, nomor HP, kata sandi, dengan `wajib_ganti_password = true`).
- Data contoh dan akun demo (walisantri, panitia, admin contoh) hanya dibuat oleh seeder terpisah yang menolak berjalan bila `APP_ENV=production`.
- Seeder tidak memuat data pribadi sungguhan.

## K-13 Cadangan dan pemulihan (tahap 6)
- Perintah `php artisan spmb:cadangan` membuat cadangan basis data dan folder berkas privat (arsip ber-tanggal, dienkripsi bila memungkinkan) di lokasi di luar folder web, dengan retensi (mis. 14 hari harian dan 6 bulanan) dan penjadwalan harian lewat scheduler.
- Dokumentasikan cara memulihkan dan jalankan uji pemulihan sekali di akhir tahap 6 (cadangan dipulihkan ke basis data kosong, aplikasi bisa membaca data terenkripsi).
- Rekomendasikan penyimpanan salinan di luar server.

## K-14 Privasi (tahap 1 dan 2)
- Halaman Kebijakan Privasi (draf yang jelas dalam bahasa Indonesia: data apa dikumpulkan, untuk apa, siapa yang bisa melihat, berapa lama disimpan, hak wali untuk meminta koreksi, kontak sekolah). Tandai sebagai draf yang perlu ditinjau pihak yayasan.
- Persetujuan eksplisit (kotak centang) saat membuat akun dan saat Kirim pendaftaran (`setuju_data_pada`).
- Kumpulkan hanya data yang dipakai sekolah. Isian opsional yang tidak dipakai bisa disembunyikan admin (`pengaturan_isian`).
- Tidak ada pelacak pihak ketiga, iklan, atau analitik eksternal.

## K-15 Rantai pasok dan dependensi (setiap tahap)
- Pasang paket seminimal mungkin. Setiap paket baru dicatat di laporan tahap beserta alasannya. Paket tidak terawat atau tanpa dukungan Laravel 13 tidak dipakai.
- `composer.lock` dan `package-lock.json` di git. Tidak ada skrip atau gaya dari CDN, semuanya dibangun lewat Vite.

## K-16 Tes keamanan otomatis (tahap 1 mulai, dilengkapi tiap tahap)
Tulis tes fitur (`tests/Feature/Keamanan/`) berikut, dan tambahkan sesuai fitur di tahap itu:
1. **Matriks akses:** untuk setiap rute dan tiap peran (tamu, walisantri, panitia sekolah A, admin sekolah A, panitia sekolah B, super admin, yayasan) pastikan hanya peran yang berhak yang mendapat 200, sisanya 302 ke masuk atau 403.
2. **Isolasi sekolah:** panitia dan admin sekolah A mencoba membuka, mengubah, dan mengunduh pendaftaran, berkas, dan pembayaran sekolah B (dengan uuid yang valid): harus ditolak.
3. **Isolasi walisantri:** walisantri A mencoba data walisantri B: ditolak.
4. **Super admin dan yayasan** tidak bisa membuka rute data pendaftar.
5. **Pembatasan laju masuk** menghasilkan 429 setelah 5 percobaan.
6. **Unggah berbahaya:** file `.php` yang diberi ekstensi `.jpg`, file lebih dari 2 MB, HEIC, dan file kosong ditolak. Berkas tidak bisa diambil dari URL publik.
7. **XSS:** nama anak dan catatan berisi `<script>` tampil ter-escape di semua halaman.
8. **CSRF:** permintaan POST tanpa token mendapat 419.
9. **Hasil bocor:** sebelum pengumuman, respons halaman dan data yang dikirim ke walisantri tidak memuat nilai Diterima, Ditolak, atau Cadangan.
10. **Mass assignment:** mengirim `peran`, `sekolah_id`, `status_hasil`, atau `user_id` dari formulir tidak mengubah nilai itu.
11. **Halaman publik:** tidak memuat kata atau data biaya dan kuota.
12. **Enkripsi:** `anak.nik` di database bukan teks polos.
13. **Ekspor:** sel berawalan `=` diberi awalan aman.
14. **Sesi:** akun nonaktif dan akun `wajib_ganti_password` mengikuti aturan K-02.

## K-17 Halaman publik bersih (tahap 6)
- View publik tidak boleh mengakses `rincian_biaya`, `gelombang.kuota`, atau hitungan pendaftar. Jadikan ini tes (K-16 butir 11).
- Konten publik dari admin dibersihkan (K-06).

## K-18 Peran yayasan dan super admin (tahap 4)
- Yayasan hanya menerima angka agregat per sekolah dan status. Tidak ada tautan dari rekap ke data orang.
- Super admin tidak melihat pendaftar; ia hanya mengelola sekolah dan akun.

## K-19 WhatsApp dan email (tahap 4)
- Hanya tautan `wa.me` dengan teks tanpa data pribadi anak dan tanpa hasil seleksi.
- Email (reset) tidak memuat kata sandi, hanya tautan bertanda tangan dengan masa berlaku.

## K-20 ZIP dan cetak (tahap 5)
- ZIP dibuat di folder sementara privat dengan nama acak, dialirkan ke pengguna, lalu dihapus segera (termasuk bila gagal). Nama file di dalam ZIP dibuat dari data terkontrol (nomor peserta + jenis berkas), bukan dari nama unggahan, untuk menghindari manipulasi path.
- Cetak formulir dan bukti memakai halaman yang diperiksa policy; hasil PDF tidak disimpan permanen kecuali diperlukan, dan bila disimpan berada di disk privat.
- Ekspor dibatasi pada sekolah pengguna dan dicatat di log (siapa, kapan, berapa baris, kolom apa).

## Catatan penerapan (bukan kode, untuk tim)
- Pilih hosting yang mendukung PHP 8.3 atau lebih baru, SSH, Composer, dan HTTPS (sertifikat otomatis), dengan docroot diarahkan ke `public/`.
- Ganti semua kata sandi awal, batasi akses SSH dengan kunci, dan batasi akses panel hosting ke orang yang perlu saja.
- Tentukan siapa yang memegang akun super admin dan `APP_KEY` di pihak yayasan.
- Sebelum dipakai nyata, minta orang lain mencoba menembus akses antarsekolah dan unggahan sesuai butir K-16.
