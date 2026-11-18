# Tahap 6 — Halaman publik, pengerasan, dan siap produksi

Prasyarat: tahap 1 sampai 5 selesai dan `php artisan test` lulus. Ini tahap terakhir. Tidak ada fitur bisnis baru kecuali halaman publik; sisanya pengerasan keamanan, performa, cadangan, dan dokumen penerapan.

## 0. Sebelum menulis kode

1. Baca ulang `AGENTS.md`, `docs/aturan-sistem.md` (bagian 12 dan 13), `docs/desain.md` (bagian publik), `docs/visi-misi.md`, `docs/data-awal.md`, `docs/keamanan.md` seluruhnya, dan laporan tahap 1 sampai 5.
2. Tulis rencana singkat, lalu kerjakan.

## 1. Halaman publik

Rute tanpa login, awalan `/`. Semua isi dibaca dari database (`sekolah`, `konten_sekolah`) dan berkas di `public/images/`. **Jangan mengarang isi**; bila kosong tampilkan "Menyusul" atau "Foto menyusul".

| Jalur | Isi |
| --- | --- |
| `GET /` | Beranda yayasan: hero, profil singkat yayasan, visi dan misi yayasan (bila kosong tampil "Segera hadir"), kartu empat sekolah (logo, nama, jenjang, slogan, tombol "Lihat sekolah" dan "Daftar"), alur pendaftaran enam langkah, kontak WhatsApp tiap sekolah |
| `GET /sekolah/{kode}` | Halaman sekolah (kode kecil: `tkk`, `tki`, `sd`, `smp`; kode tak dikenal → 404): slogan, profil, visi, indikator visi, misi, program unggulan, ekstrakurikuler, prestasi, fasilitas, galeri, jam belajar, **syarat dan berkas yang disiapkan** (nama jenis berkas dan wajib atau tidak, **tanpa biaya**), alur, alamat dan peta tautan (tautan biasa, tanpa embed pihak ketiga), tombol WhatsApp (`wa.me`) dan Instagram, tombol "Daftar sekarang" yang membawa ke daftar akun atau masuk. Untuk TKI tampilkan tiga program (KB, TK, TPA) |
| `GET /kebijakan-privasi` | Draf kebijakan privasi (K-14), diberi label "Draf, menunggu tinjauan yayasan" |
| `GET /masuk`, `/daftar`, `/lupa-password` | Sudah ada dari tahap 1 dan 3; rapikan tampilan sesuai `docs/desain.md` |
| Halaman galat | 403, 404, 419, 429, 500 berbahasa Indonesia yang ramah dan **tanpa jejak teknis** |

- **Status pendaftaran** di halaman sekolah: tampilkan "Pendaftaran dibuka" atau "Belum dibuka" berdasarkan ada tidaknya gelombang berstatus `dibuka` dan tanggalnya, **tanpa** tanggal rinci kuota, tanpa jumlah pendaftar, tanpa sisa kuota (K-17). Tanggal buka dan tutup gelombang boleh tampil bila admin mengisinya (informasi publik), tetapi **kuota dan tautan grup WA tidak**.
- **Biaya tidak pernah tampil publik.** View publik tidak boleh menyentuh model `RincianBiaya`, `RekeningSekolah`, kolom `gelombang.kuota`, `gelombang.tautan_grup_wa`, atau hitungan pendaftar. Pisahkan lewat view model atau query `select` eksplisit.
- Konten dari admin hanya teks biasa; tampil dengan `{{ }}` (escape). Gambar dari disk publik konten (tahap 4), selalu dengan `alt`. Nomor WhatsApp ditampilkan `0856-9542-2700` dan tautan `https://wa.me/62...` dari kolom sekolah.
- Logo dan foto dibaca dari `public/images/logo/` dan `public/images/sekolah/{kode}/...` (lihat `docs/data-awal.md`); bila berkas tidak ada, tampil placeholder tanpa galat. Gambar diberi `loading="lazy"`, `width` dan `height`, dan bila besar dibuat versi ringan lewat perintah `spmb:optimalkan-gambar` (opsional, memakai GD, hanya untuk berkas di `public/images`).
- Judul halaman, `meta description`, `og:title`, `og:description` per halaman, `lang="id"`, `robots.txt` (izinkan publik; blokir `/akun`, `/panitia`, `/admin`, `/super`, `/yayasan`), dan `sitemap.xml` sederhana untuk halaman publik. Tambahkan `noindex` pada semua halaman login dan panel.
- Desain: ikuti `docs/desain.md`; responsif dari 360 px, kontras cukup, fokus terlihat, bisa dipakai dengan keyboard, tombol cukup besar untuk layar sentuh.
- Tidak ada pelacak, iklan, font atau skrip dari pihak ketiga (K-14, K-15).

## 2. Content Security Policy (naikkan ke enforce)

Di tahap 1 CSP berjalan **report-only**. Sekarang:

1. Tinjau laporan pelanggaran selama pengembangan (log) dan perbaiki sumbernya, bukan melonggarkan kebijakan.
2. Ubah menjadi `Content-Security-Policy` (enforce) dengan: `default-src 'self'`; `script-src 'self'` (+ nonce bila perlu, **tanpa** `'unsafe-inline'` dan `'unsafe-eval'`; gunakan build Alpine yang kompatibel CSP); `style-src 'self'` (nonce untuk gaya inline yang tak terhindarkan); `img-src 'self' data:`; `font-src 'self'`; `connect-src 'self'`; `form-action 'self'`; `frame-ancestors 'none'`; `base-uri 'self'`; `object-src 'none'`.
3. Pastikan semua halaman (publik, akun, tiga panel, halaman cetak, galat) berjalan tanpa pelanggaran: telusuri satu per satu dengan peramban dan tuliskan hasilnya di laporan.
4. Tambahkan tes bahwa header ada di respons halaman publik dan panel, dan bahwa tidak ada `unsafe-inline` atau `unsafe-eval`.
5. Header lain dipastikan ada: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (matikan kamera, mikrofon, geolokasi), `X-Frame-Options: DENY`, dan **HSTS** hanya bila `APP_ENV=production` dan permintaan lewat HTTPS.

## 3. Konfigurasi produksi (K-11)

- Siapkan `.env.production.example` (tanpa nilai rahasia) berisi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_DOMAIN`, database, mail, `TRUSTED_PROXIES` bila di belakang proxy. Jelaskan setiap variabel dengan komentar.
- Aplikasi **menolak berjalan** (galat jelas saat boot) bila `APP_ENV=production` tetapi `APP_DEBUG=true` atau `APP_KEY` kosong.
- Perintah `php artisan spmb:periksa-produksi` yang memeriksa dan melaporkan: debug mati, `APP_KEY` ada, cookie aman, HTTPS, izin `storage/` dan `bootstrap/cache`, `.env` tidak terbaca dunia, folder `storage` tidak dapat diakses dari web, `storage:link` hanya menunjuk disk konten publik, ekstensi PHP yang dibutuhkan, scheduler dan antrean berjalan, versi PHP ≥ 8.3, tidak ada paket dev terpasang. Keluaran berupa daftar LULUS atau GAGAL.
- Pustaka dev ada di `require-dev`; pasang produksi dengan `composer install --no-dev --optimize-autoloader`. Tidak ada `dd`, `dump`, debugbar, atau ide-helper di kode produksi.
- Dokumentasikan penjalanan `php artisan config:cache`, `route:cache`, `view:cache`, `event:cache`, dan `optimize`, serta `npm run build`.
- **Antrean dan scheduler:** dokumentasikan cron `* * * * * php artisan schedule:run` dan pekerja antrean (`queue:work --stop-when-empty` lewat cron, bila hosting tidak punya Supervisor). Daftarkan di scheduler: `spmb:cadangan` (harian), `spmb:bersihkan-tmp` (tiap jam), pembersihan token reset kedaluwarsa dan sesi lama.
- Dokumentasikan konfigurasi server web: docroot hanya `public/`, blokir `.env`, `.git`, `storage`, `vendor`, `composer.*`, tanpa listing direktori, batas unggah (`upload_max_filesize` ≥ 2M, `post_max_size` wajar), HTTPS dan pengalihan HTTP→HTTPS, contoh untuk Apache (`.htaccess` di `public/`) dan Nginx.
- Pastikan **tidak ada rute uji** (`/test`, `/phpinfo`), route Telescope atau debug, atau akun demo di produksi.

## 4. Cadangan dan pemulihan (K-13)

- `php artisan spmb:cadangan`: dump basis data (`mysqldump` aman, kata sandi lewat berkas opsi sementara, bukan argumen baris perintah) dan arsip folder berkas privat, digabung menjadi satu arsip ber-tanggal, **dienkripsi** (OpenSSL AES-256 dengan kata sandi dari `.env` `CADANGAN_KUNCI`, atau `age` bila tersedia; tulis keputusannya di laporan), disimpan di `storage/app/cadangan/` di luar docroot dengan izin ketat. Retensi: 14 harian dan 6 bulanan; yang lebih lama dihapus.
- `php artisan spmb:pulihkan {berkas}` (interaktif, minta konfirmasi, menolak berjalan tanpa `--force` bila basis data tidak kosong).
- Jalankan **uji pemulihan** sekali: cadangan dipulihkan ke basis data kosong, lalu aplikasi berhasil membaca kolom terenkripsi (NIK) dan berkas privat. Tuliskan hasilnya di laporan. Tulis tes otomatis untuk bagian yang bisa diuji (arsip dibuat, terenkripsi, retensi bekerja).
- Dokumentasikan: simpan salinan di luar server, simpan `APP_KEY` dan `CADANGAN_KUNCI` **terpisah** dari server dan cadangan (tanpa `APP_KEY`, NIK tidak bisa dibaca lagi).

## 5. Performa dan keandalan

- Periksa indeks basis data untuk kolom yang sering dicari dan disaring (`pendaftaran.sekolah_id`, `gelombang_id`, `status_hasil`, `dikirim_pada`, `nomor_peserta` unique, `users.no_hp` unique, `pengumuman` unique, `log_aktivitas` pada `sekolah_id` dan `created_at`). Tambahkan migrasi untuk yang kurang.
- Cari dan perbaiki N+1 di daftar pendaftar, rekap, dan log (tes jumlah query pada data contoh 500 pendaftar).
- Cache hasil halaman publik dan hitungan rekap seperlunya, dengan **invalidasi** saat konten atau data berubah; jangan menyimpan data per pengguna di cache bersama.
- Transaksi dan kunci pada nomor peserta, kuota, dan pengumuman sudah ada; tambahkan **tes konkuren** sederhana (dua proses atau dua permintaan berurutan) untuk memastikan tidak ada duplikasi.
- Penanganan galat: halaman 500 tidak membuka jejak; galat ditulis ke log dengan `LOG_LEVEL` produksi; unggahan gagal dan disk penuh menghasilkan pesan sopan, bukan 500.

## 6. Audit keamanan akhir

Kerjakan dan laporkan, tanpa melonggarkan apa pun hanya agar tes lulus:

1. Jalankan `composer audit` dan `npm audit`; perbaiki yang bisa, jelaskan yang tersisa.
2. **Telusuri seluruh rute** (`php artisan route:list`): untuk setiap rute catat middleware dan Policy yang melindunginya; tidak ada rute data tanpa `auth` dan tanpa Policy atau scope. Lampirkan tabelnya di laporan.
3. Cari `{!!`, `DB::raw`, `whereRaw`, `->raw(`, `unserialize`, `eval`, `shell_exec`, `exec`, `Storage::url` untuk berkas privat, `$request->all()` yang dipakai langsung ke `create` atau `update`, dan `$guarded = []`. Perbaiki atau jelaskan satu per satu.
4. Periksa bahwa berkas pendaftar **tidak mungkin** terakses lewat URL (coba `GET /storage/...` dan tebak jalur; harus 404) dan bahwa tidak ada `storage:link` ke disk privat.
5. Periksa seluruh tampilan untuk kebocoran: sebelum pengumuman (`StatusTampilService`), `catatan_*`, kuota, biaya publik, dan NIK pada daftar dan cetak.
6. Periksa cookie sesi (`Secure`, `HttpOnly`, `SameSite=Lax`), masa sesi per peran, sesi dihapus saat ganti dan atur ulang kata sandi.
7. Jalankan **seluruh** tes K-16 butir 1 sampai 14 dan pastikan semuanya ada dan lulus.
8. Pindai manual pengujian dasar (sebagai penguji bukan penyerang): coba IDOR dengan mengganti `uuid`, ubah `sekolah_id` di formulir, unggah berkas berbahaya, kirim XSS, lewati CSRF, dan 429 pada masuk. Tuliskan hasilnya.
9. Tuliskan **daftar risiko tersisa** yang jujur (misalnya: tidak ada 2FA, tidak ada pemindai antivirus berkas, reset email hanya untuk walisantri, hosting dan backup di luar kendali aplikasi) beserta saran.

## 7. Dokumen penerapan (`docs/penerapan.md`)

Buat panduan untuk orang yang bukan pengembang (guru atau pengurus), singkat dan bertahap:
1. Persyaratan hosting (PHP 8.3+, MySQL 8, ekstensi, SSH, Composer, HTTPS, docroot `public/`).
2. Langkah pasang pertama (unggah, `.env`, `composer install --no-dev`, `npm run build` di komputer lalu unggah `public/build` bila hosting tak punya Node, `key:generate`, `migrate --force`, `db:seed --class=DataAwalSeeder`, `storage:link`, `spmb:buat-super-admin`, cron, cache).
3. Hal yang **harus** diisi sebelum dibuka: rekening tiap sekolah, tanggal dan kuota gelombang, tautan grup WA, konten sekolah, foto dan logo, biaya, visi misi yayasan, tinjauan kebijakan privasi oleh yayasan.
4. Panduan singkat per peran (walisantri, panitia, admin, super admin, yayasan): maksimal satu halaman tiap peran, bahasa awam.
5. Prosedur harian dan darurat: cadangan, pemulihan, lupa kata sandi per peran, menonaktifkan akun yang bocor, apa yang dilakukan bila dicurigai ada kebocoran data.
6. Siapa memegang apa: akun super admin, `APP_KEY`, `CADANGAN_KUNCI`, akses hosting. (Kosongkan namanya; yayasan yang mengisi.)

## 8. Tes (wajib)

Tambahkan ke `tests/Feature/` dan `tests/Feature/Keamanan/`:
1. Halaman publik (`/`, tiap `/sekolah/{kode}`, `/kebijakan-privasi`) 200 untuk tamu; kode sekolah tak dikenal 404; halaman tidak memuat nominal biaya, kuota, tautan grup WA, jumlah pendaftar, atau nomor rekening (isi data uji dengan nilai khas lalu cari di HTML).
2. Konten berisi `<script>` ter-escape; gambar tanpa berkas tidak menyebabkan galat.
3. `robots.txt` memblokir jalur panel; halaman login dan panel ber-`noindex`.
4. CSP enforce ada di respons dan tidak memuat `unsafe-inline` atau `unsafe-eval`; header keamanan lain ada; HSTS hanya saat produksi dan HTTPS.
5. Aplikasi menolak boot di produksi dengan debug aktif atau `APP_KEY` kosong.
6. `spmb:periksa-produksi` melaporkan GAGAL untuk konfigurasi yang sengaja dirusak dan LULUS untuk konfigurasi benar.
7. Cadangan: arsip dibuat, terenkripsi (tidak terbaca tanpa kunci), retensi menghapus yang lama; `spmb:pulihkan` menolak tanpa konfirmasi atau `--force` pada basis data berisi.
8. Halaman galat 403, 404, 419, 429, 500 tidak membocorkan jejak, jalur berkas, atau nama kelas.
9. Jumlah query halaman daftar pendaftar dan rekap konstan terhadap jumlah data (tes N+1).
10. Tes konkuren nomor peserta, kuota, dan pengumuman: tidak ada duplikasi atau pelampauan.
11. `/storage/...` tidak bisa membuka berkas pendaftar; `GET` jalur tebakan 404.
12. Regresi: seluruh tes tahap 1 sampai 5 tetap lulus. Jalankan `php artisan test` dan `php artisan route:list` di akhir dan lampirkan ringkasannya.

## 9. Kriteria selesai

- Semua butir `AGENTS.md` bagian 8 terpenuhi.
- Keamanan: K-11, K-13, K-14, K-15, K-16 (semua butir), K-17 terpenuhi dan teruji; CSP enforce berjalan di semua halaman tanpa pelanggaran.
- `docs/penerapan.md` ada dan dapat diikuti orang awam.
- Laporan akhir memuat: ringkasan semua tahap, hasil `composer audit` dan `npm audit`, tabel rute dan perlindungannya, hasil uji pemulihan, hasil pemeriksaan CSP per halaman, daftar risiko tersisa, dan daftar hal yang masih harus diisi manusia (rekening, kuota, tanggal, konten, foto, logo, visi misi yayasan, tinjauan privasi).

**Berhenti di sini.** Proyek siap diuji bersama pihak sekolah.
