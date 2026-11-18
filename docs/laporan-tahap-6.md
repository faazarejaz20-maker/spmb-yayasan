# Laporan Tahap 6 — Halaman publik, pengerasan, dan siap produksi

Tanggal: 12 November 2026
Lingkup: halaman publik, CSP enforce, konfigurasi produksi, cadangan dan pemulihan, performa, audit keamanan akhir, serta `docs/penerapan.md` (`prompts/tahap-6-publik-produksi.md`).
Status: **selesai**. Proyek siap diuji bersama pihak sekolah. Tidak ada fitur bisnis baru selain halaman publik.

---

## 1. Ringkasan semua tahap

| Tahap | Lingkup | Hasil | Laporan |
| --- | --- | --- | --- |
| 1 | Dasar: 20 tabel, enum, model, autentikasi nomor HP/email, enam peran, middleware, layout, CSP report-only | Lulus | `docs/laporan-tahap-1.md` |
| 2 | Pendaftaran walisantri: delapan langkah, `config/isian.php`, orang tua/wali, berkas privat, deteksi ganda, nomor peserta, biaya | Lulus | `docs/laporan-tahap-2.md` |
| 3 | Panel panitia: pendaftar, verifikasi berkas, status hasil, kuota, pembayaran, daftar ulang/wawancara, akun offline, log | Lulus | `docs/laporan-tahap-3.md` |
| 4 | Admin sekolah, super admin, yayasan: gelombang/kuota, konten, akun staf, Umumkan Hasil, rekap, isolasi sekolah | Lulus | `docs/laporan-tahap-4.md` |
| 5 | Ekspor Excel/CSV, ZIP berkas privat, cetak formulir/bukti/kuitansi (K-20) | Lulus | `docs/laporan-tahap-5.md` |
| 6 | Halaman publik, CSP enforce, konfigurasi produksi, cadangan terenkripsi, indeks, audit akhir | Lulus | dokumen ini |

Angka akhir: **502 tes lulus, 3.719 asersi** (498 pada akhir Tahap 6, ditambah dua tes tampilan beranda di bagian 11 dan dua tes alur anak baru di bagian 12), `php artisan migrate:fresh --seed` bersih, `npm run build` berhasil, 148 rute.

---

## 2. Yang selesai di Tahap 6

### 2.1 Halaman publik

- **Beranda `/`**: hero, profil singkat yayasan, visi dan misi yayasan (kosong → "Segera hadir"), kartu empat sekolah (logo, nama, jenjang, slogan, tombol "Lihat sekolah" dan "Daftar"), alur pendaftaran enam langkah, dan kontak WhatsApp tiap sekolah.
- **Halaman sekolah `/sekolah/{kode}`**: kode kecil (`tkk`, `tki`, `sd`, `smp`), kode tak dikenal → 404. Memuat slogan, profil, visi, indikator visi, misi, program unggulan, ekstrakurikuler, prestasi, fasilitas, galeri, jam belajar, syarat dan jenis berkas (**tanpa biaya**), alur, alamat dan tautan peta biasa (tanpa embed pihak ketiga), tombol `wa.me` dan Instagram, serta tombol "Daftar sekarang". TKI menampilkan tiga program (KB, TK, TPA).
- **Status pendaftaran** hanya "Pendaftaran dibuka"/"Belum dibuka" dari ada tidaknya gelombang `dibuka` beserta tanggalnya. Tanggal buka/tutup boleh tampil; **kuota, sisa kuota, jumlah pendaftar, dan tautan grup WA tidak pernah tampil**.
- **Kebijakan privasi `/kebijakan-privasi`**: draf berbahasa Indonesia dengan label persis **"Draf, menunggu tinjauan yayasan"** (K-14).
- **`robots.txt`**: mengizinkan publik dan memblokir `/akun`, `/panitia`, `/admin`, `/super`, `/yayasan`, `/masuk`, `/daftar-akun`, `/lupa-password`, `/reset-password`. **`sitemap.xml`** memuat beranda, kebijakan privasi, dan empat halaman sekolah.
- **Meta**: `lang="id"`, judul dan `meta description` per halaman, `og:title`/`og:description`. Semua halaman masuk, akun, panel, dan cetak memakai `noindex`.
- **Gambar**: dibaca dari `public/images/logo/` dan `public/images/sekolah/{kode}/`; berkas yang tidak ada menjadi kotak abu-abu "Logo menyusul"/"Foto menyusul" tanpa galat. Setiap gambar punya `alt`, `width`, `height`, dan `loading="lazy"`. Prestasi **tidak** memakai ulang foto galeri; bila kosong tampil "Data prestasi menyusul."
- **Halaman galat 403, 404, 419, 429, 500**: berbahasa Indonesia, ramah, tanpa jejak teknis, jalur berkas, nama kelas, atau pesan exception.
- **Sumber data**: `HalamanPublikService` memakai `select` eksplisit (tanpa `rincian_biaya`, `rekening_sekolah`, `gelombang.kuota`, `gelombang.tautan_grup_wa`, atau tabel `pendaftaran`), cache 10 menit dengan kunci versi global yang dibuang saat data terkait berubah. Tidak ada data per pengguna di cache bersama.
- **Bersih-bersih**: `welcome.blade.php` bawaan Laravel dihapus karena tidak dipakai.

### 2.2 CSP enforce dan header keamanan

`Content-Security-Policy` kini **enforce** (bukan report-only) dengan:

```
default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:;
font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none';
base-uri 'self'; object-src 'none'
```

- Tidak ada `'unsafe-inline'` maupun `'unsafe-eval'`.
- Alpine memakai build **`@alpinejs/csp`** yang tidak mengevaluasi string ekspresi. Seluruh interaksi kecil (salin kartu, cetak, unggah otomatis, pilih anak) ditulis sebagai pendengar peristiwa di `resources/js/app.js`, tanpa ekspresi inline.
- Semua `<style>`, atribut `style="..."`, dan `javascript:` dihapus. Bilah kemajuan memakai `<progress>` asli; aturan cetak `@page` dipindah ke `resources/css/app.css`; lebar minimum tabel memakai kelas Tailwind literal.
- Header lain: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(self), microphone=(), geolocation=()`, `X-Frame-Options: DENY`. **HSTS** hanya dikirim bila `APP_ENV=production` **dan** permintaan lewat HTTPS.
- **Temuan audit yang diperbaiki**: halaman galat yang dirender di luar pipeline middleware (mis. 404) sebelumnya tidak mendapat CSP. Sekarang `HeaderKeamanan::pasang()` dipanggil juga dari `$exceptions->respond(...)` di `bootstrap/app.php`, sehingga 4xx/5xx mendapat header yang sama.

### 2.3 Konfigurasi produksi (K-11)

- **`.env.production.example`** berisi seluruh variabel produksi dengan komentar: `APP_ENV`, `APP_DEBUG=false`, `APP_URL` HTTPS, `LOG_LEVEL=warning`, cookie `Secure`/`HttpOnly`/`SameSite=lax`, `SESSION_DOMAIN`, basis data, SMTP, `CADANGAN_KUNCI`, `TRUSTED_PROXIES` (tanpa `*`), batas unggah. Tidak ada nilai rahasia.
- **Penjaga boot** `App\Support\KonfigurasiProduksi`: aplikasi menolak boot bila `APP_ENV=production` tetapi `APP_DEBUG=true` atau `APP_KEY` kosong.
- **`php artisan spmb:periksa-produksi`**: memeriksa debug mati, `APP_KEY`, cookie aman, HTTPS, izin `storage/` dan `bootstrap/cache`, `.env` tidak terbaca dunia, folder privat tidak dapat diakses web, `storage:link` hanya ke disk konten publik, ekstensi PHP, versi PHP ≥ 8.3, penjadwal, antrean, dan tidak ada paket dev. Keluaran LULUS/GAGAL.
- **`public/.htaccess`** diperkeras: tanpa daftar isi folder; menolak `.env*`, `.git*`, `composer.*`, `artisan`, `phpunit.xml`, serta berkas `.sql`, `.log`, `.sqlite`, `.ini`, `.bak`.
- **`docs/penerapan.md`**: panduan berbahasa awam untuk guru/pengurus — persyaratan hosting, pemasangan pertama, data yang wajib diisi, panduan satu halaman per peran, prosedur harian/darurat, pemegang aset, serta contoh konfigurasi Apache/Nginx/php.ini.
- Tidak ada rute uji, Telescope, debugbar, atau akun demo di jalur produksi. `DataContohSeeder` hanya dijalankan `migrate:fresh --seed` lokal dan tidak dipanggil `DatabaseSeeder` produksi.

### 2.4 Cadangan dan pemulihan (K-13)

- `php artisan spmb:cadangan`: dump basis data + arsip folder berkas privat, digabung menjadi satu arsip bertanggal, lalu **dienkripsi**. Format: penanda `SPMBCAD1`, **AES-256-CBC + HMAC-SHA256** (encrypt-then-MAC), kunci turunan **PBKDF2-SHA256 120.000 iterasi** dari `CADANGAN_KUNCI`. Untuk MySQL, `mysqldump` dijalankan dengan berkas opsi sementara agar kata sandi tidak tampil di argumen proses. Untuk SQLite (pengujian), dipakai `VACUUM INTO`.
- Lokasi `storage/app/cadangan/` di luar docroot. Retensi **14 harian** dan **satu per bulan selama 6 bulan**; selebihnya dihapus.
- `php artisan spmb:pulihkan {berkas}`: interaktif, menolak basis data berisi tanpa `--force`, dan menolak tanpa konfirmasi.
- Scheduler: `spmb:cadangan` pukul 01.30, `spmb:bersihkan-tmp` tiap jam, `spmb:bersihkan-token-sesi` pukul 02.15.

### 2.5 Performa dan keandalan

- Migrasi `0001_01_08_000000_tambah_indeks_tahap_6.php` menambah indeks:
  - `pendaftaran (sekolah_id, dikirim_pada)`
  - `pendaftaran (gelombang_id, status_hasil)`
  - `pendaftaran (status_hasil)`
  - `log_aktivitas (sekolah_id, dibuat_pada)`
  - `pembayaran (pendaftaran_id, status)`
- `QueryKonstanTest` memastikan jumlah query daftar pendaftar, rekap, dan log tidak bertambah saat data bertambah (permintaan pemanasan mencegah selisih satu query akibat relasi akun yang dimuat malas).
- `KonkurenTest` menguji nomor peserta berurutan tanpa duplikasi, kuota tidak terlampaui, dan pengumuman tidak ganda pada permintaan berurutan.
- Cache halaman publik dan rekap memakai invalidasi saat model terkait disimpan/dihapus; tidak ada data pribadi di cache bersama.
- **Kebersihan tes**: seluruh tes yang mengunggah berkas kini memakai `Storage::fake('local')` di `WalisantriTestCase`, dan `CadanganTest` menutup koneksi SQLite sebelum menghapus berkas. Sebelumnya sisa berkas uji menumpuk di `storage/app/private/berkas` (juga di Windows). Setelah perbaikan, menjalankan seluruh suite tidak meninggalkan berkas di `storage/app/private` maupun `storage/app/cadangan-uji-*`.

---

## 3. Hasil pengujian

| Pemeriksaan | Perintah | Hasil |
| --- | --- | --- |
| Seluruh tes | `php artisan test` | **524 lulus, 3.929 asersi** (498 + 2 tes tampilan beranda di bagian 11 + 2 tes alur anak baru di bagian 12 + 1 tes batas berkas pendaftar 2 MB di bagian 13.13 + 3 tes kontak & prestasi di bagian 13.8 + 3 tes panduan alur di bagian 13.9 + 2 tes isian formulir konten di bagian 13.11 + 2 tes foto konten di bagian 13.12 + 1 tes batas gambar tampilan 10 MB dan 10 tes foto sampul sekolah di bagian 13.13) |
| Basis data kosong | `php artisan migrate:fresh --seed` | Bersih; 15 migrasi + seeder (diuji pada basis data SQLite kosong; basis data pengembangan hanya ditambah migrasi baru tanpa dihapus) |
| Bangun aset | `npm run build` | Berhasil (JS 132,67 kB / gzip 45,88 kB; CSS 54,67 kB / gzip 10,33 kB); tidak ada perubahan CSS/JS pada 13.13 |
| Daftar rute | `php artisan route:list` | 152 rute |

Tes baru/berubah pada tahap ini:

| Berkas | Isi | Hasil |
| --- | --- | --- |
| `tests/Feature/HalamanPublikTest.php` | 13 tes: halaman publik, kode sekolah, kebijakan privasi, escaping konten, robots/sitemap, placeholder gambar, halaman galat, **query publik tidak menyentuh biaya/rekening/pendaftar/kuota/tautan WA** | Lulus |
| `tests/Feature/Keamanan/CspHalamanTest.php` | 5 tes: CSP di publik, autentikasi, akun, tiga panel, halaman cetak, 403/404/419/429 | Lulus |
| `tests/Feature/Keamanan/HeaderKeamananTest.php` | 6 tes: CSP enforce, tanpa `unsafe-inline`/`unsafe-eval`, HSTS kondisional, pindai seluruh Blade tanpa gaya/skrip inline, tidak ada rute uji/debug | Lulus |
| `tests/Feature/Keamanan/BerkasPrivatTest.php` | 3 tes: `/storage/...` dan jalur tebakan 404, folder privat di luar `public/`, tautan `public/storage` tidak menunjuk berkas privat | Lulus |
| `tests/Feature/Keamanan/QueryKonstanTest.php` | Jumlah query konstan pada data bertambah | Lulus |
| `tests/Feature/Keamanan/KonkurenTest.php` | Nomor peserta, kuota, pengumuman | Lulus |
| `tests/Feature/CadanganTest.php` | Arsip terenkripsi, kunci salah ditolak, pemulihan basis data + berkas, retensi, penolakan tanpa konfirmasi/`--force` | Lulus |
| `tests/Feature/PeriksaProduksiTest.php` | Penjaga boot dan `spmb:periksa-produksi` (GAGAL untuk konfigurasi rusak, LULUS untuk benar) | Lulus |

---

## 4. Audit keamanan akhir (butir 6)

### 4.1 `composer audit` dan `npm audit`

- **`composer audit`**: **tidak ada kerentanan keamanan**. Muncul 2 paket transitif yang sudah ditinggalkan: `dragon-code/contracts` dan `dragon-code/support`, keduanya datang bersama `laravel-lang/lang` sejak Tahap 1 (bukan dari paket baru). `composer audit` keluar dengan kode 1 karena peringatan *abandoned*, bukan karena kerentanan. Saran: pantau `laravel-lang/lang`; bila tidak lagi diperlukan, lepas untuk menghilangkan dua paket itu.
- **`npm audit`**: **0 kerentanan** (produksi maupun seluruh dependensi). Paket `alpinejs` biasa dihapus karena tidak dipakai; aplikasi memakai `@alpinejs/csp`.

### 4.2 Rute dan perlindungannya

Semua rute `web` juga memakai middleware global berikut (tidak selalu tampil di `route:list`): `HeaderKeamanan`, `AkunAktif`, `SesiKedaluwarsa`, `WajibGantiPassword`.

| Grup | Jumlah | Middleware | Policy / pembatas data |
| --- | --- | --- | --- |
| Publik (`/`, `/sekolah/{kode}`, `/kebijakan-privasi`, `/robots.txt`, `/sitemap.xml`) | 5 | `web` | Tanpa data pendaftar; `HalamanPublikService` memilih kolom publik saja |
| Autentikasi tamu (`/masuk`, `/daftar-akun`, `/lupa-password`, `/reset-password`) | 8 | `web + guest` (+`signed`, `throttle:10,1`) | Pembatasan laju masuk 5/menit; reset 3/email + 10/IP per jam |
| Akun bersama (`/ganti-password`, `/kartu-akun`, `/keluar`) | 4 | `web + auth` | Hanya akun sendiri; kartu akun dibaca sekali lalu dihapus dari sesi |
| Walisantri (`/akun/...`) | 26 | `web + auth + peran:walisantri` (+`throttle`) | `AnakPolicy`, `OrangTuaPolicy`, `PendaftaranPolicy`, `BerkasPolicy`, `PembayaranPolicy`; kepemilikan `user_id`; pelanggaran → 404 |
| Panitia (`/panitia/...`) | 43 | `web + auth + peran:panitia,admin` (+`throttle`) | `PendaftaranPolicy`, `BerkasPolicy`, `PembayaranPolicy` + global scope `sekolah_id`; sekolah lain → 404 |
| Admin (`/admin/...`) | 39 | `web + auth + peran:admin` (+`throttle`) | `PengaturanSekolahPolicy::kelola` + global scope `sekolah_id`; sekolah lain → 404 |
| Super admin (`/super/...`) | 22 | `web + auth + peran:super_admin` (+`throttle`) | `SuperAdminPolicy::kelola`; **tidak ada** akses data pendaftar; pelanggaran → 403 |
| Yayasan (`/yayasan/...`) | 4 | `web + auth + peran:yayasan` (+`throttle`) | Hanya `RekapService` agregat; tanpa nama, NIK, nomor peserta, atau pembayaran per orang |
| Health (`/up`) | 1 | — | Hanya status aplikasi, tanpa data |

Total **152 rute**. Daftar lengkap per rute ada di **Lampiran A**.

Policy yang benar-benar dipanggil di kode (bukan hanya di middleware):

- `AnakPolicy`, `OrangTuaPolicy`, `PendaftaranPolicy`, `BerkasPolicy`, `PembayaranPolicy`, `AkunWalisantriPolicy` — akses walisantri dan panitia.
- `PengaturanSekolahPolicy::kelola` — seluruh perubahan pengaturan sekolah oleh admin.
- `SuperAdminPolicy::kelola` — pengelolaan sekolah, admin, yayasan, dan konten yayasan.
- `Gate::forUser(...)->allows(...)` dipakai pada setiap detail/aksi pendaftar, berkas, pembayaran, cetak, ZIP, dan daftar ulang.

### 4.3 Pencarian pola berbahaya

| Pola | Temuan | Tindakan / alasan |
| --- | --- | --- |
| `{!! ... !!}` | Tidak ada | Semua konten admin memakai `{{ }}` |
| `DB::raw` | Tidak ada | — |
| `whereRaw` | 4 pemakaian pencarian (`PanitiaController`, `EksporService`) | Hanya fragmen `LIKE ? ESCAPE '\'` dengan nilai terikat; nama kolom tetap, bukan input |
| `->raw(` | Tidak ada | — |
| `unserialize` | Tidak ada di kode aplikasi (hanya komentar `config/cache.php`) | — |
| `eval`, `shell_exec`, `exec` | Tidak ada | `$pdo->exec()` pada dump SQLite bukan `exec()` PHP dan hanya menerima jalur yang di-quote |
| `Storage::url` untuk berkas privat | Tidak ada | Unduhan lewat controller + policy; disk privat `serve => false` |
| `$request->all()` ke `create`/`update` | Tidak ada | Semua memakai `validated()` dan `forceFill` terkontrol |
| `$guarded = []` | Tidak ada | Setiap model memakai `$fillable` eksplisit |

### 4.4 Berkas pendaftar tidak dapat diakses lewat URL

- Berkas disimpan di disk `local` (`storage/app/private`), `serve => false`, di luar `public/`.
- `GET /storage/...` dan enam jalur tebakan (termasuk `/storage/app/private/...`, `/berkas/...`, `/pembayaran/...`) diuji → **404**.
- Tidak ada `storage:link` yang menunjuk ke folder privat; tes memeriksa hal ini bila tautan ada.
- ZIP dibuat di folder sementara privat dengan nama acak, dialirkan, lalu dihapus (termasuk saat gagal). Nama di dalam ZIP memakai nomor peserta + jenis berkas, bukan nama unggahan.

### 4.5 Kebocoran tampilan

- Sebelum pengumuman, halaman akun, cetak bukti, dan data yang dikirim ke walisantri tidak memuat Diterima/Ditolak/Cadangan (`KebocoranHasilTest`, `KebocoranWalisantriTahap3Test`, `CetakBuktiTest`).
- `catatan_*` panitia tidak tampil ke walisantri.
- Kuota dan biaya tidak pernah tampil publik; tes query memastikan tabel/kolom terlarang tidak disentuh sama sekali.
- NIK disamarkan pada daftar dan cetak; NIK utuh hanya untuk pemilik dan panitia sekolah sendiri sesuai kebijakan yang berlaku.

### 4.6 Cookie dan sesi

- Cookie sesi: `HttpOnly=true`, `SameSite=lax`, `Secure` wajib di produksi (`SESSION_SECURE_COOKIE=true`), `SESSION_ENCRYPT=false` (payload sesi tidak memuat data sensitif di luar id).
- Masa tidak aktif: staf 120 menit, walisantri 480 menit (`SesiKedaluwarsa`).
- Sesi dicabut saat kata sandi diganti/diatur ulang dan saat akun dinonaktifkan (`AkunAktif`, `WajibGantiPassword`).
- Akun `wajib_ganti_password` hanya bisa membuka halaman ganti kata sandi sampai berhasil mengganti.

### 4.7 K-16 butir 1–14

| Butir | Tes | Hasil |
| --- | --- | --- |
| 1. Matriks akses per peran | `MatriksAksesTest`, `MatriksAksesPanelTest`, `WalisantriAksesTest`, `PanitiaAksesTest` | Lulus |
| 2. Isolasi sekolah | `IsolasiSekolahTest`, `IsolasiSekolahPanitiaTest`, `IsolasiSekolahAdminTest` | Lulus |
| 3. Isolasi walisantri | `IsolasiSekolahTest`, `WalisantriAksesTest` | Lulus |
| 4. Super admin/yayasan tanpa data pendaftar | `SuperYayasanTidakLihatPendaftarTest`, `YayasanHanyaBacaTest` | Lulus |
| 5. 429 setelah 5 percobaan masuk | `AutentikasiTest`, `CspHalamanTest` | Lulus |
| 6. Unggah berbahaya & berkas tak dapat diakses URL | `BerkasKeamananTest`, `BerkasPrivatTest` | Lulus |
| 7. XSS ter-escape | `XssTest`, `PanelXssTest`, `PanitiaXssTest`, `KontenKeamananTest` | Lulus |
| 8. CSRF 419 | `KeamananTest`, `CspHalamanTest` | Lulus |
| 9. Hasil tidak bocor sebelum pengumuman | `KebocoranHasilTest`, `KebocoranWalisantriTahap3Test`, `CetakBuktiTest` | Lulus |
| 10. Mass assignment | `MassAssignmentTest`, `PanelMassAssignmentTest`, `PanitiaMassAssignmentTest` | Lulus |
| 11. Halaman publik tanpa biaya/kuota | `HalamanPublikTest` (termasuk tes query) | Lulus |
| 12. NIK terenkripsi | `NikTest` | Lulus |
| 13. Ekspor aman dari formula | `EksporPendaftarTest`, `EksporRekapTest` | Lulus |
| 14. Sesi akun nonaktif/wajib ganti | `KeamananTest`, `GantiPasswordTest`, `AutentikasiTest` | Lulus |

### 4.8 Uji manual dasar (sebagai penguji, bukan penyerang)

| Percobaan | Hasil |
| --- | --- |
| IDOR mengganti `uuid` pendaftaran/berkas/pembayaran milik orang lain | 404 (policy + scope) |
| Mengubah `sekolah_id`, `peran`, `status_hasil`, `user_id` di formulir | Diabaikan; nilai dipaksa dari akun/kode |
| Unggah `.php` bernama `.jpg`, berkas > 2 MB, HEIC, berkas kosong | Ditolak dengan pesan ramah |
| XSS `<script>` pada nama anak, catatan, dan konten sekolah | Ter-escape di semua halaman |
| POST tanpa token CSRF | 419 |
| Masuk salah 5 kali | 429 |
| Membuka `/storage/...` dan jalur tebakan | 404 |

Catatan jujur: uji di atas dijalankan lewat tes otomatis dan permintaan HTTP, **bukan** melalui peramban sungguhan. Penelusuran CSP dengan DevTools peramban dan pemeriksaan tampilan 360 px/1280 px tetap menjadi langkah manual sebelum go-live (lihat bagian 7).

---

## 5. Hasil uji pemulihan (K-13)

Uji dilakukan **end-to-end pada basis data MySQL/MariaDB lokal** memakai `mysqldump`/`mysql` dari XAMPP (biner ada, tetapi tidak di `PATH`; `SPMB_MYSQLDUMP`/`SPMB_MYSQL` diarahkan ke biner tersebut). Basis data uji terpisah `ppdb_uji_pulihkan`; basis data pengembangan tidak disentuh.

1. Buat basis data uji, jalankan migrasi dan `DataAwalSeeder`, lalu buat satu anak dengan NIK `3307015204190001` dan satu berkas privat.
2. Periksa kolom `anak.nik` di basis data: tersimpan sebagai ciphertext (bukan teks polos).
3. `php artisan spmb:cadangan` → `storage/app/cadangan/spmb-20261112-135205.enc`.
4. **Hapus** basis data uji lalu buat ulang dalam keadaan kosong.
5. `php artisan spmb:pulihkan spmb-20261112-135205.enc --force --no-interaction`.
6. Hasil:

```
NIK_TERBACA=3307015204190001
NIK_MENTAH=eyJpdiI6... (ciphertext, berbeda dari teks polos)
BERKAS=ISI-BERKAS-UJI-PEMULIHAN
HASIL=LULUS
```

Artinya arsip terenkripsi dapat dipulihkan ke basis data kosong, **NIK terenkripsi terbaca kembali** dengan `APP_KEY` yang sama, dan berkas privat ikut pulih. Basis data uji, arsip, dan berkas uji dihapus setelah pemeriksaan.

Tes otomatis `CadanganTest` (SQLite) juga lulus untuk: arsip berpenanda `SPMBCAD1`, penolakan kunci salah, pemulihan basis data + berkas, retensi 14 harian/6 bulanan, penolakan tanpa konfirmasi, dan penolakan `--force` yang tidak diberikan pada basis data berisi.

**Batas uji:** lingkungan lokal memakai **MariaDB 10.11.16**, bukan MySQL 8. Perintah dan dump yang dipakai adalah biner MariaDB. Sebelum go-live, jalankan sekali lagi prosedur yang sama di server produksi (MySQL 8) dengan `mysqldump`/`mysql` milik server.

---

## 6. Hasil pemeriksaan CSP per halaman

CSP diperiksa pada respons HTTP nyata lewat `CspHalamanTest` dan `HeaderKeamananTest`, ditambah pindai statis seluruh berkas Blade untuk `<style>`, atribut `style=`, `javascript:`, dan atribut `on*=`.

| Halaman | Status HTTP | CSP enforce | Tanpa `unsafe-inline`/`unsafe-eval` | Gaya/skrip inline |
| --- | --- | --- | --- | --- |
| Beranda `/` | 200 | Ada | Ya | Tidak ada |
| Halaman sekolah `/sekolah/{kode}` | 200 | Ada | Ya | Tidak ada |
| Kebijakan privasi | 200 | Ada | Ya | Tidak ada |
| Masuk, daftar akun, lupa kata sandi | 200 | Ada | Ya | Tidak ada |
| Beranda akun `/akun` | 200 | Ada | Ya | Tidak ada |
| Cetak bukti pendaftaran | 200 | Ada | Ya | Tidak ada |
| Beranda panitia `/panitia` | 200 | Ada | Ya | Tidak ada |
| Detail pendaftar | 200 | Ada | Ya | Tidak ada |
| Cetak formulir | 200 | Ada | Ya | Tidak ada |
| Beranda admin `/admin` | 200 | Ada | Ya | Tidak ada |
| Beranda super admin `/super` | 200 | Ada | Ya | Tidak ada |
| Beranda yayasan `/yayasan` | 200 | Ada | Ya | Tidak ada |
| Galat 403 | 403 | Ada | Ya | Tidak ada |
| Galat 404 | 404 | Ada | Ya | Tidak ada |
| Galat 419 | 419 | Ada | Ya | Tidak ada |
| Galat 429 | 429 | Ada | Ya | Tidak ada |
| Galat 500 | — | Ditutup oleh jalur penangan galat yang sama; tampilan `errors.500` diuji tanpa jejak teknis | Ya | Tidak ada |

Catatan jujur: **belum ada penelusuran dengan peramban sungguhan**. Yang dilakukan adalah tes header otomatis dan pindai statis. Sebelum go-live, buka setiap jenis halaman di peramban, lihat tab Console/Network, dan pastikan tidak ada pelanggaran CSP. Bila muncul pelanggaran, perbaiki sumbernya — jangan menambah `unsafe-inline`.

---

## 7. Risiko yang tersisa

| Risiko | Dampak | Saran |
| --- | --- | --- |
| Tidak ada 2FA untuk staf | Akun staf yang kata sandinya bocor dapat dipakai masuk | Gunakan kata sandi panjang di pengelola kata sandi; pertimbangkan 2FA pada tahap berikutnya |
| Tidak ada pemindai antivirus unggahan | Berkas berbahaya dapat tersimpan (tetapi tidak dieksekusi dan tidak dapat diakses URL) | Aktifkan pemindaian ClamAV di server, atau unduh berkas hanya di komputer tepercaya |
| Reset kata sandi mandiri hanya untuk walisantri dan bergantung SMTP | Bila SMTP mati, walisantri harus lewat panitia | Pastikan SMTP dipantau; prosedur panitia sudah ada |
| Cadangan tersimpan di server yang sama secara bawaan | Server hilang/rusak = cadangan ikut hilang | Salin arsip ke luar server minimal mingguan; uji pemulihan tiap 6 bulan |
| `APP_KEY` dan `CADANGAN_KUNCI` dipegang manusia | Kehilangan `APP_KEY` = NIK lama tidak terbaca; kehilangan `CADANGAN_KUNCI` = arsip tidak dapat dipulihkan | Simpan di dua tempat terpisah dari server dan dari arsip; catat pemegangnya |
| Uji produksi memakai MariaDB 10.11, bukan MySQL 8 | Perbedaan halus dump/restore atau SQL | Jalankan `spmb:periksa-produksi` dan uji pemulihan di server MySQL 8 sebelum dibuka |
| Tidak ada pemantauan terpusat | Galat hanya terlihat di berkas log | Periksa log mingguan; pertimbangkan layanan pemantauan |
| `/up` dapat diakses publik | Hanya memberi tahu aplikasi hidup; tanpa data | Boleh dibiarkan atau tutup di server web bila tidak dipakai |
| Kebijakan privasi masih draf | Belum final secara hukum | Yayasan harus meninjau dan mengganti label |
| Ekspor Excel berisi data pribadi | Risiko kebocoran lewat berkas yang diteruskan | Simpan di komputer sekolah; jangan kirim lewat WhatsApp pribadi; log ekspor sudah dicatat |
| Tidak ada penghapusan data lewat antarmuka | Permintaan penghapusan harus manual oleh teknisi | Sepakati prosedur tertulis dengan yayasan |

---

## 8. Hal yang masih harus diisi manusia

- [ ] Rekening bank tiap sekolah (Admin → Rekening).
- [ ] Tanggal buka/tutup dan kuota tiap gelombang (Admin → Gelombang).
- [ ] Tautan grup WhatsApp tiap gelombang (hanya tampil setelah pengumuman).
- [ ] Rincian biaya pendidikan tiap sekolah (IAP, iuran, seragam, formulir).
- [ ] Jenis berkas dan status wajib tiap sekolah.
- [ ] Konten sekolah: profil, visi, misi, program, ekstrakurikuler, prestasi, fasilitas, jam belajar, alur, syarat, kontak.
- [ ] Foto dan logo sekolah di `public/images/sekolah/{kode}/` dan `public/images/logo/` (boleh menyusul; placeholder sudah tersedia). Foto sampul kartu beranda kini dapat diunggah sendiri lewat Admin → Pengaturan atau Super Admin → Sekolah.
- [ ] Profil, visi, dan misi **yayasan** (Super Admin → Konten).
- [ ] Akun panitia, admin sekolah, dan yayasan.
- [ ] Tinjauan dan persetujuan **Kebijakan Privasi** oleh yayasan, lalu ganti label "Draf, menunggu tinjauan yayasan".
- [ ] `APP_KEY` dan `CADANGAN_KUNCI` produksi, disimpan di luar server.
- [ ] Domain, HTTPS, dan `APP_URL` produksi.
- [ ] Jadwal penyalinan cadangan ke luar server dan pemegangnya.
- [ ] Uji coba satu pendaftaran lengkap dengan akun uji, lalu nonaktifkan akun uji.

---

## 9. Penyimpangan dari dokumen dan alasannya

1. **Tidak ada repositori git**, sehingga tidak ada komit per sub-tugas. Sejak Tahap 1 folder proyek bukan repositori git; seluruh pekerjaan diserahkan sebagai berkas. Bila diminta, repositori dapat diinisialisasi dan riwayat dipecah per tahap.
2. **`spmb:optimalkan-gambar` semula tidak dibuat.** Perintah ini ditandai opsional di prompt. Setelah beranda memakai foto prestasi dan kegiatan asli berukuran 10-35 MB, perintah ini ditambahkan bersama pemakaian turunan WebP; lihat bagian 11.
3. **CSP tidak ditelusuri dengan peramban sungguhan.** Penggantinya: tes header pada 18 jenis halaman + pindai statis seluruh Blade. Penelusuran peramban tetap dicantumkan sebagai langkah manual go-live.
4. **Uji pemulihan memakai MariaDB 10.11 lokal**, bukan MySQL 8 produksi. Prosedur dan perintahnya sama; uji ulang di server produksi wajib dilakukan.
5. **`/up` (health check bawaan Laravel) dipertahankan.** Rute ini tidak menampilkan data dan bukan rute uji; bila hosting tidak memakainya, dapat ditutup di server web.
6. **Paket `alpinejs` biasa dihapus** karena tidak dipakai; aplikasi memakai `@alpinejs/csp`. Ini mengurangi permukaan rantai pasok.
7. **Perbaikan di luar daftar awal:** header keamanan untuk halaman galat yang dirender di luar pipeline middleware. Ditemukan saat menulis tes CSP per halaman; diperbaiki di `HeaderKeamanan::pasang()` + `$exceptions->respond()`.
8. **Label kebijakan privasi** memakai teks persis yang diminta, sehingga halaman publik menampilkan label draf secara terbuka. Ini disengaja sesuai K-14.
9. **Isolasi penyimpanan tes**: `Storage::fake('local')` ditambahkan di `WalisantriTestCase` dan `DB::purge()` di `CadanganTest` agar suite tidak meninggalkan berkas uji. Ini perbaikan kebersihan, bukan perubahan perilaku aplikasi.

---

## 10. Pertanyaan yang masih terbuka

Pertanyaan dari tahap sebelumnya yang belum dijawab dan tidak menghambat pengujian:

1. Nilai baku `orang_tua.status_hidup` yang dipakai sekolah (saat ini teks bebas).
2. Berkas wajib yang kurang: cukup peringatan atau penghalang pengiriman (saat ini penghalang).
3. Apakah panitia boleh melihat NIK utuh, termasuk pada cetak formulir.
4. Ratifikasi kolom tambahan migrasi Tahap 4.
5. Cakupan kolom ekspor yang disetujui yayasan.
6. Batas cetak 120 per jam — apakah sesuai kebutuhan panitia.
7. Apakah `laravel-lang/lang` masih diperlukan; bila tidak, dua paket `dragon-code/*` yang abandoned dapat dihilangkan.
8. `APP_URL` produksi yang final.

---

## 11. Perbaikan tampilan beranda (12 November 2026)

Beranda sebelumnya hanya berisi teks dan kartu polos. Perbaikan ini tidak menambah fitur bisnis dan tidak mengubah aturan data publik.

- **Hero terbelah**: ajakan mendaftar di kiri, foto asli murid SDIT Insan Mulia Wonosobo memegang piala di kanan, lengkap dengan keterangan foto. Di HP foto tampil di bawah teks.
- **Ringkasan faktual**: jumlah sekolah, jenjang KB-SMP, satu akun untuk semua anak, dan pendaftaran daring. Tidak ada kuota, biaya, rekening, jumlah pendaftar, atau tautan grup WhatsApp.
- **Kartu sekolah dengan foto sampul asli**: gedung TKIT Al Ishlah, halaman KB-TKIT Insan Mulia, gedung SDIT, dan halaman SMPIT. Bila foto tidak ada, tetap tampil "Foto menyusul".
- **Galeri "Prestasi dan Kegiatan Murid"**: tujuh foto asli pilihan (piala, Popda bulu tangkis, Gebyar Apresiasi, pramuka, pembiasaan adab, upacara) dengan keterangan berbahasa Indonesia. Tidak ada foto buatan AI atau foto stok.
- **Tentang Yayasan**: profil yayasan; bila visi dan misi belum diisi admin, panel kanan menampilkan daftar empat sekolah yang bernaung di yayasan agar tidak kosong.
- **Alur dan CTA** dirapikan (langkah bernomor sejajar teks, penutup "Siap mendaftar?"), kontak panitia tetap per sekolah.

Perubahan teknis:

- `app/Support/GambarPublik.php`: menambah `sorotan()`, `sampulSekolah()`, dan `namaOptimasi()`; URL gambar kini relatif terhadap akar situs dan setiap segmennya di-encode, sehingga data cache tidak terikat nama host dan nama berkas berspasi/kurung tetap aman.
- `app/Console/Commands/OptimalkanGambar.php`: perintah `php artisan spmb:optimalkan-gambar` membuat turunan WebP (maksimum 1600 px, logo 320 px, kualitas 80, menghormati EXIF Orientation) di `public/images/optimasi/`. Berkas turunan yang lebih baru dari aslinya dilewati; `--paksa` membangun ulang. `GambarPublik` memakai turunan bila ada dan kembali ke aslinya bila belum dibuat.
- `HalamanPublikService.php`: beranda menambah `sorotan`; kartu sekolah menambah `sampul`. Query dan kolom yang dibaca tetap sama (tanpa `rincian_biaya`, `rekening_sekolah`, `pendaftaran`, `kuota`, atau `tautan_grup_wa`).
- `resources/views/publik/beranda.blade.php` dan `resources/views/layouts/publik.blade.php` (nama situs di HP dipendekkan agar tidak terpotong).

Hasil optimasi di mesin pengembangan: 34 berkas turunan, total turun dari sekitar 179 MB menjadi sekitar 5 MB. Berkas asli tetap disimpan dan tidak diubah.

Pengujian: 18 tes halaman publik/CSP lulus sebelum penambahan tes, lalu dua tes baru (foto sorotan dan penamaan berkas optimasi) ikut lulus. Tampilan dicek dengan Chrome headless pada lebar 360 px dan 1280 px: tidak ada guliran horizontal (`docScrollWidth` sama dengan lebar layar).

---

## 12. Perbaikan bug "Daftarkan anak baru" (12 November 2026)

**Gejala.** Di `/akun/daftar/baru`, walisantri yang sudah punya anak memilih **Anak baru** lalu menekan **Lanjut**, tetapi tidak terjadi apa-apa: halaman tetap di tempat dan formulir tidak terkirim. Tidak ada pesan galat yang terlihat.

**Penyebab.** Radio `anak_id` (daftar anak yang sudah ada) diberi atribut `required`. Saat walisantri memilih **Anak baru**, `resources/js/app.js` mengosongkan radio `anak_id` karena kedua pilihan itu saling meniadakan. Akibatnya grup radio `anak_id` kosong, validasi bawaan peramban menolak pengiriman ("Pilih salah satu opsi berikut."), dan permintaan tidak pernah sampai ke server. Pilihan **Anak baru** hanya bisa dipakai oleh akun yang belum punya anak sama sekali (di sana tombolnya bukan radio).

**Perbaikan.** Atribut `required` dihapus dari radio `anak_id`. Pemeriksaan tetap dilakukan di server oleh `MulaiPendaftaranRequest` (pesan "Pilih anak yang sudah terdaftar atau pilih Anak baru."), dan pesan itu tampil lewat komponen `x-toast` yang sudah dipasang di layout walisantri.

**Bukti sebelum/sesudah** (Chrome headless, sesi walisantri sungguhan):

| | `form.checkValidity()` | Pesan validasi | Setelah "Lanjut" |
| --- | --- | --- | --- |
| Sebelum | `false` | "Pilih salah satu opsi berikut." | tetap `/akun/daftar/baru` |
| Sesudah | `true` | — | `/akun/daftar/baru/anak` |

**Berkas berubah.** `resources/views/walisantri/pendaftaran/baru.blade.php`, `tests/Feature/WalisantriAksesTest.php` (dua tes: pilihan "Anak baru" tidak diblokir validasi peramban, dan memilih "Anak baru" membuka formulir anak baru). Seluruh tes: **502 lulus, 3.719 asersi**.

---

## 13. Perbaikan tampilan area walisantri dan panel staf (16 November 2026)

Perbaikan ini hanya menyentuh tampilan. Tidak ada perubahan skema, rute, aturan bisnis, atau query data baru; seluruh angka tetap berasal dari data yang sudah dipakai halaman sebelumnya.

### 13.1 Komponen bersama

- `components/ikon.blade.php`: kumpulan ikon SVG garis (24 × 24) dengan nama Indonesia, dipakai sidebar, kartu statistik, dan tombol. Tidak ada ikon dari CDN.
- `components/avatar.blade.php`: lingkaran inisial nama, sebagai pengganti foto yang belum ada.
- `components/logo-sekolah.blade.php`: memakai `GambarPublik::logo()`; bila logo belum tersedia tampil kotak "Logo menyusul", bukan gambar buatan.
- `components/kemajuan.blade.php`: bilah enam tahap (Formulir, Berkas, Verifikasi, Pembayaran, Daftar ulang, Hasil) tanpa gaya inline.
- `components/statistik.blade.php`: kartu angka dengan label, ikon berwarna, dan keterangan opsional.
- `components/kepala-halaman.blade.php`: judul halaman seragam (eyebrow, judul, nama anak/sekolah, keterangan). Sejak 16 November 2026 diperluas dengan tautan kembali (`kembali`, `labelKembali`) dan slot `aksi` untuk tombol utama halaman.
- `components/tautan-tombol.blade.php`: tombol berbasis tautan dengan varian `primer/sekunder/bahaya/hantu`, ukuran `kecil/sedang/besar`, atribut `target`/`rel`, dan ring fokus yang sama dengan `tombol`. Dipakai untuk seluruh aksi navigasi panel staf.

### 13.2 Area walisantri

- **Kerangka** (`layouts/walisantri.blade.php`): bilah aksen gradien hijau–emas, logo yayasan, nama akun, menu desktop (Beranda, Daftar anak, Orang tua), avatar, tombol keluar berikon, dan tautan lompat ke isi. Navigasi bawah HP memakai empat tujuan nyata (Beranda, Daftar, Orang tua, Akun) dengan ikon dan status aktif; sebelumnya dua tombol menuju halaman yang sama.
- **Dasbor** (`walisantri/beranda.blade.php`): kartu sapaan bergradien, ringkasan angka (anak, pendaftaran, perlu tindakan), kartu per anak dengan logo sekolah, lencana status, kemajuan enam tahap, langkah berikutnya, nomor peserta, satu tombol aksi utama, serta kartu bantuan WhatsApp sekolah. Ringkasan dihitung dari `status_hasil`, `pembayaran`, dan `daftar_ulang` yang sudah ada (`StatusTampilService::kemajuan()`).
- **Detail** (`walisantri/pendaftaran/detail.blade.php`): kepala kartu dengan logo sekolah dan nomor peserta, garis waktu lima tahap (`StatusTampilService::garisWaktu()`), kartu perbaikan, kartu hasil, daftar ulang, grup WhatsApp, dan cetak bukti.
- **Hasil** (`walisantri/pendaftaran/hasil.blade.php`): hero berwarna sesuai hasil setelah pengumuman, nomor peserta, langkah berikutnya, dan tombol pembayaran/Tahap 2. Sebelum pengumuman tetap hanya menampilkan "menunggu pengumuman" tanpa membocorkan Diterima/Ditolak/Cadangan.
- **Formulir**: pemilihan sekolah di `baru.blade.php` menjadi kartu dengan logo dan status "Dibuka"; pilihan anak menjadi kartu radio yang tersorot saat dipilih. Judul langkah, berkas, biaya, ringkasan, pembayaran, dan Tahap 2 memakai `x-kepala-halaman`; IAP juga memakai kartu radio tersorot.
- **Data orang tua** dan **pendaftaran terkirim**: kartu sapaan bergradien dan langkah berikutnya, tetap memakai layout panel saat diisi panitia offline.

### 13.3 Panel staf (panitia, admin, super admin, yayasan)

- **Kerangka** (`layouts/panel.blade.php`): bilah aksen gradien, header dua baris (peran · sekolah dan judul halaman), avatar dengan nama, tombol keluar berikon, dan area isi `max-w-7xl`.
- **Sidebar** (`components/sidebar.blade.php`): menu dikelompokkan (Operasional, Pengaturan sekolah, Pengumuman & laporan, Yayasan, Keamanan, Rekap), setiap item berikon, penanda aktif di tepi kiri, serta kartu pengguna dan tautan ganti kata sandi di bawah. Laci HP memakai kelompok dan ikon yang sama.
- **Dasbor panitia**: sapaan dan tombol verifikasi/pembayaran, lima kartu angka, kuota gelombang dengan bilah kemajuan, alur kerja tiga langkah, dan daftar "Perlu tindakan" dengan jumlah antrean.
- **Dasbor admin**: sapaan dengan tombol Umumkan hasil dan Gelombang & kuota, delapan kartu angka (total, panitia, enam status), enam pintasan pengaturan, dan tabel gelombang.
- **Dasbor super admin**: ringkasan akun per peran, empat pintasan, dan tabel sekolah dengan logo, kode, mode penerimaan, jumlah admin, dan status aktif.
- **Dasbor yayasan**: penanda "hanya lihat", tombol unduh rekap, sembilan kartu agregat berwarna, dan perbandingan antarsekolah dengan logo. Tidak ada nama anak, NIK, nomor peserta, atau tautan ke data per orang.
- **Tabel** (`components/tabel.blade.php`): tetap menggulir di dalam kartu; pada layar kecil ditambahkan keterangan "Geser tabel ke samping untuk melihat kolom lainnya."

### 13.4 Keamanan dan aksesibilitas

- Tidak ada `style` atau `<script>` inline; seluruh warna dan ukuran memakai kelas Tailwind sehingga CSP `style-src 'self'; script-src 'self'` tetap berlaku.
- Tidak ada query baru ke data pendaftar. Dasbor super admin dan yayasan tetap hanya menampilkan agregat. Nama gelombang dan nama sekolah tetap di-escape oleh Blade (diuji `PanelXssTest`).
- Semua tombol memiliki teks atau `aria-label`; navigasi aktif memakai `aria-current="page"`; tabel memakai elemen `table`, `thead`, dan `th`; bilah kemajuan memakai `<progress>`.

### 13.5 Pemeriksaan tampilan

Chrome headless (CDP, `Emulation.setDeviceMetricsOverride`) dipakai untuk membuka `/akun`, `/akun/daftar/baru`, `/akun/orang-tua`, detail dan hasil pendaftaran, `/admin`, `/panitia`, `/super`, dan `/yayasan` pada 360 × 780 px dan 1280 × 900 px. Hasilnya `document.documentElement.scrollWidth` sama dengan lebar layar di semua halaman; elemen lebar yang terdeteksi hanya tabel di dalam wadah `overflow-x-auto` dan lingkaran hias di dalam kartu `overflow-hidden`, sehingga tidak menimbulkan guliran halaman. Tangkapan layar dan skrip sementara dihapus setelah pemeriksaan.

### 13.6 Berkas yang berubah

- Baru: `components/{ikon,statistik,avatar,logo-sekolah,kemajuan,kepala-halaman}.blade.php`.
- Diubah: `layouts/{walisantri,panel}.blade.php`, `components/{sidebar,navigasi-bawah,tabel}.blade.php`, seluruh `walisantri/beranda`, `walisantri/orang-tua`, `walisantri/pendaftaran/{baru,detail,hasil,sukses,langkah,berkas,biaya,ringkasan,pembayaran,tahap-2}.blade.php`, `panitia/beranda`, `admin/beranda`, `super-admin/beranda`, `yayasan/beranda`.
- PHP: `WalisantriController` (data ringkasan, kemajuan, aksi utama), `PendaftaranController::detail()` (memuat pembayaran/daftar ulang dan kemajuan), `StatusTampilService` (`kemajuan()`, `garisWaktu()`).

### 13.7 Pengujian

Tes terarah untuk panel, CSP, isolasi sekolah, dan kebocoran data: **48 lulus (1.565 asersi)**. Seluruh tes: **510 lulus (3.790 asersi)** setelah tambahan bagian 13.8, 13.9, dan 13.11, dijalankan dengan `php artisan test --compact`.

### 13.8 Kontak sekolah dan foto prestasi di beranda walisantri (16 November 2026)

- Beranda `/akun` kini memuat bagian **Kontak sekolah**: satu kartu untuk setiap sekolah aktif (TKK, TKI, SD, SMP) berisi logo, nama, jenjang, alamat, tombol WhatsApp `wa.me` dengan nomor yang mudah dibaca, dan tautan Instagram resmi bila admin mengisinya. Semua data berasal dari kolom `sekolah` yang sudah ada.
- Bagian **Prestasi murid** menampilkan foto asli per sekolah dari `public/images/prestasi` melalui `GambarPublik::prestasiSekolah()`, lengkap dengan keterangan. TKIT Al Ishlah Kertek dan KB-TKIT Insan Mulia belum memiliki foto prestasi di folder proyek, sehingga tampil "Foto prestasi ... menyusul" — tidak ada foto karangan, stok, atau gambar buatan AI.
- Kartu bantuan satu sekolah yang lama diganti bagian kontak ini agar orang tua dapat menghubungi sekolah mana pun.
- Tidak ada kolom, rute, atau query data pendaftar baru. `pint` lulus. Tes baru `WalisantriBerandaKontakTest` (3 tes, 25 asersi) memeriksa kontak tiap sekolah aktif tampil, sekolah nonaktif tidak tampil, keterangan prestasi benar, dan setiap `src` foto benar-benar ada di `public/images`.

### 13.9 Panduan alur pendaftaran di halaman "Daftar anak" (16 November 2026)

- Halaman `/akun/daftar/baru` kini memandu walisantri yang belum terbiasa memakai web. Kartu **Alur pendaftaran** memuat delapan langkah bernomor (Pilih sekolah, Data anak, Alamat, Kesehatan, Asal sekolah, Berkas, Biaya, Kirim) dengan ikon, keterangan satu baris, dan penanda "Sekarang" di langkah pertama.
- Kartu **Siapkan dari sekarang** mengingatkan KK, akta kelahiran, pas foto anak, dan surat keterangan sehat (bila diminta). Dijelaskan juga bahwa berkas boleh difoto langsung dengan kamera HP, format JPG/PNG/PDF, maksimal 2 MB per berkas.
- Setiap kartu sekolah memiliki panel buka-tutup (`<details>`, tanpa JavaScript) berisi daftar berkas dari tabel `jenis_berkas` milik sekolah itu: hanya yang `aktif`, diurutkan `urutan`, dengan lencana **Wajib**/**Opsional** dan jumlah lembar. Sekolah yang belum menetapkan berkas menampilkan keterangan bahwa panitia akan memberi tahu.
- Kartu **Catatan saat mengisi** menjelaskan draf tersimpan setiap kali menekan "Simpan & lanjut"/"Simpan draf", tombol "Simpan & keluar", dan tanda bintang untuk isian wajib. Kartu **Butuh bantuan?** menautkan ke bagian kontak sekolah di beranda (`/akun#judul-kontak`).
- Tidak ada kolom, rute, atau aturan bisnis baru; formulir, nama input, dan perilaku pilihan "Anak baru" tidak berubah. `pint` lulus. Tes baru `WalisantriAlurDaftarTest` (3 tes, 32 asersi) memeriksa alur dan catatan tampil, berkas wajib/opsional tiap sekolah tampil, berkas nonaktif disembunyikan, dan sekolah yang gelombangnya tidak dibuka tidak ikut tampil.

### 13.10 Penyeragaman halaman dalam panel staf (16 November 2026)

Perbaikan ini hanya menyentuh tampilan (berkas `.blade.php` dan dokumen). Tidak ada rute, kolom, aturan bisnis, policy, atau query data pendaftar yang berubah; angka dan data yang tampil tetap berasal dari controller yang sama.

- **Kepala halaman:** seluruh halaman dalam staf kini memakai `x-kepala-halaman`, menggantikan blok `<h1>` + paragraf yang sebelumnya ditulis ulang di setiap berkas. Yang dirapikan: admin (gelombang, pengaturan, berkas, biaya, rekening, isian, akun panitia, pengumuman index/konfirmasi, konten, rekap, log), panitia (pendaftar index/detail, pembayaran, offline index/sekolah, ekspor), super admin (sekolah index/edit, admin, yayasan, konten, log), dan yayasan (rekap, profil).
- **Tautan kembali:** halaman detail, edit, konfirmasi, dan ekspor memakai `kembali`/`labelKembali` sehingga labelnya konsisten ("Daftar pendaftar", "Daftar sekolah", "Umumkan hasil") dan ikon panah berada di dalam satu komponen.
- **Tombol:** tombol berbasis tautan yang sebelumnya ditulis satu per satu diganti `x-tautan-tombol` (varian dan ukuran sama dengan `x-tombol`). Aksi utama halaman berada di slot `aksi` kepala halaman; aksi baris tabel memakai ukuran `kecil`; tombol "Ubah" di tabel sekolah super admin kini tombol sekunder kecil, bukan tautan teks.
- **Kartu dan toolbar:** panel penyaring diberi `shadow-sm`, judul `x-kartu` memakai garis pemisah, "Alur singkat" di pendaftaran offline memakai `x-kartu`, dan kartu detail/keputusan pendaftar memakai bayangan yang sama.
- **Aksesibilitas:** `x-tombol`, `x-tautan-tombol`, `x-tab`, dan tombol `x-modal-konfirmasi` memakai ring fokus `focus-visible`; tab dan tombol modal minimal 44 px, tombol submit tetap minimal 48 px sesuai `docs/desain.md`. Dialog konfirmasi memakai latar blur tanpa gaya inline (CSP tetap `style-src 'self'`).
- **Pemeriksaan tampilan:** 34 pemeriksaan CDP pada 360 × 780 px dan 1280 × 900 px untuk seluruh halaman admin, panitia, super admin, dan yayasan; `document.documentElement.scrollWidth` sama dengan lebar layar di semua halaman (0 halaman meluber). Tangkapan layar desktop dan mobile diperiksa, lalu skrip dan gambar sementara dihapus.
- **Susulan 16 November 2026:** sidebar desktop dibuat menempel (`sticky top-0 h-screen`) supaya menu tidak hilang saat halaman panjang digulir; sebelumnya sidebar setinggi seluruh halaman sehingga ikut tergulir. Kolom pilih berkas pada editor konten (`/admin/konten`, `/super/konten`) memakai kelas `pilih-berkas` yang mewarnai tombol bawaan browser dengan warna primer di `app.css` (tanpa gaya inline, CSP tetap). Diuji CDP: sidebar tetap di `top 0` pada guliran 1500 px dan dasar halaman, tombol "Pilih File" berwarna `rgb(15, 118, 110)`, dan halaman 360 px tidak meluber.
- **Pengujian:** tes terarah halaman staf, matriks akses, CSP, isolasi sekolah, dan kebocoran data **148 lulus (1.461 asersi)**; seluruh suite **508 lulus (3.776 asersi)**. `npm run build` berhasil.

### 13.11 Isian formulir konten tidak bocor ke formulir lain (18 November 2026)

Perbaikan ini menyentuh tampilan dan validasi sisi klien saja. Tidak ada rute, kolom, aturan bisnis, policy, atau query data pendaftar yang berubah.

- **Masalah:** halaman Kelola Konten berisi puluhan formulir dalam satu halaman, semuanya memakai nama isian yang sama (`judul`, `urutan`, `isi`). Ketika satu unggahan gambar ditolak karena lebih dari 2 MB, Laravel mengembalikan `_old_input` dan pesan galat untuk seluruh halaman. Akibatnya setiap formulir menampilkan isian yang sama dengan formulir yang gagal, dan baris lain kehilangan isi aslinya dari database.
- **Penanda formulir aktif:** setiap formulir kini mengirim hidden input `_form` berisi `teks-{bagian}`, `baris-{id}`, atau `tambah-{bagian}`. Hanya formulir yang `_form`-nya cocok yang memakai `old()` dan menampilkan `@error`; formulir lain tetap memakai nilai dari database dan tidak menampilkan pesan galat milik formulir lain. Kotak centang "Hapus gambar" juga hanya tercentang pada formulir yang gagal.
- **Komponen `x-isian`:** mendapat prop opsional `id` (ID unik per baris, misalnya `judul-12`), `abaikanLama` (pakai nilai database, bukan `old()`), dan `tampilkanGalat` (sembunyikan pesan galat). Nilai bawaan tetap sama seperti sebelumnya sehingga formulir lain di aplikasi tidak berubah perilakunya. Isian yang gagal diberi `aria-invalid="true"`.
- **Validasi ukuran di sisi klien:** input berkas pada editor konten membawa `data-maks-berkas` (dihitung dari `config('spmb.konten.maks_kb')`, kini 10 MB) dan `data-galat-berkas`. `resources/js/app.js` memeriksa berkas yang dipilih, memanggil `setCustomValidity`, dan menulis pesan `Ukuran gambar maksimal X MB. Berkas yang dipilih Y MB.` ke elemen galat di formulir itu saja. Formulir tidak terkirim sebelum berkas diperkecil, tanpa ekspresi inline (CSP `script-src 'self'` tetap).
- **Pemeriksaan tampilan (CDP):** `/admin/konten` pada 360 × 780 px dan 1280 × 900 px; `scrollWidth` sama dengan lebar layar di kedua ukuran. Dengan berkas 3 MB, `validationMessage` dan teks galat muncul di formulir yang dipilih, `form.checkValidity()` bernilai `false`, dan 10 input berkas lain tetap bersih tanpa berkas. Sidebar tetap di `top 0` setelah guliran 1500 px.
- **Pengujian:** `AdminKontenFormTest` (2 tes, 14 asersi) memeriksa isian yang gagal hanya muncul di formulir baris yang gagal, baris lain tetap memakai isi database, formulir tambah hanya terisi drafnya sendiri, dan tidak ada baris baru yang tersimpan saat unggahan ditolak. Tes mengirim cookie sesi dari respons sebelumnya agar galat validasi benar-benar terbaca saat halaman dirender, seperti browser sungguhan. `KontenKeamananTest` + `AdminKontenFormTest`: **14 lulus (73 asersi)**; seluruh suite **510 lulus (3.790 asersi)**. `npm run build` berhasil (`app-BWVixzla.css`, `app-uS6xHwm7.js`).

### 13.12 Foto konten tidak tampil di halaman sekolah (18 November 2026)

Perbaikan ini menyentuh satu helper pembaca gambar, pratinjau di editor konten, dan dokumentasi penerapan. Tidak ada rute, kolom, aturan bisnis, policy, atau query data pendaftar yang berubah.

- **Masalah:** foto prestasi yang diunggah lewat Kelola Konten tersimpan di `storage/app/public/konten/...`, sedangkan halaman sekolah menampilkan alamat `/storage/...`. Tautan `public/storage` belum dibuat di server pengembangan, sehingga permintaan gambar dijawab 404 dan browser menampilkan ikon gambar rusak berisi teks alternatif.
- **Perbaikan lingkungan:** `php artisan storage:link` dijalankan. Di Windows Laravel membuat *junction* `public/storage` → `storage/app/public`, jadi tidak perlu hak administrator. `php artisan spmb:periksa-produksi` kini melaporkan baris TAUTAN **LULUS**, dan `GET /storage/konten/4/...jpg` menjawab **200 `image/jpeg`**.
- **Perbaikan kode:** `GambarPublik::urlKonten()` mengembalikan jalur relatif `/storage/...` tanpa host dan dipakai oleh `dariKonten()`. URL absolut dari `APP_URL` bisa menunjuk host yang salah — misalnya `http://localhost` padahal aplikasi dibuka di `http://127.0.0.1:8000` — sehingga gambar gagal dimuat meski berkasnya ada di disk. Pratinjau gambar di editor konten (`/admin/konten`, `/super/konten`) memakai pembaca yang sama, dan menampilkan "Belum ada gambar." bila berkasnya benar-benar hilang.
- **Dokumentasi:** `docs/penerapan.md` bagian 2.9 menjelaskan tautan `public/storage` sebagai syarat tampilnya foto konten dan cara memeriksanya bila foto tampil rusak.
- **Pengujian:** dua tes baru — `HalamanPublikTest::test_foto_prestasi_konten_memakai_url_relatif` dan `AdminKontenFormTest::test_pratinjau_gambar_di_editor_memakai_url_relatif` — memastikan `src` berupa `/storage/...` dan bukan URL absolut `APP_URL`. Seluruh suite: **512 lulus (3.799 asersi)**. `pint` lulus untuk berkas yang diubah.

### 13.13 Admin dapat mengganti foto sampul kartu sekolah sendiri (18 November 2026)

Fitur baru atas permintaan pemilik proyek: foto sampul kartu sekolah di beranda sebelumnya hanya bisa diganti dengan menimpa berkas di `public/images/sekolah/...` lalu menjalankan `spmb:optimalkan-gambar`. Sekarang admin sekolah (dan super admin) dapat mengunggah, mengganti, dan menghapusnya lewat panel.

- **Skema:** kolom baru `sekolah.foto_sampul_path` `varchar(255)` null (migrasi `0001_01_09_000000_tambah_foto_sampul_sekolah`). Kosong berarti memakai foto bawaan `GambarPublik::SAMPUL`, jadi sekolah yang belum mengunggah tidak berubah tampilannya dan tidak ada foto yang hilang.
- **Penyimpanan:** `FotoSampulService` memakai `BerkasService::prosesGambarKonten()` yang sudah ada — hanya JPG/PNG/WebP, isi berkas diperiksa dari MIME sebenarnya, gambar ditulis ulang (EXIF/payload tersembunyi hilang), sisi terpanjang maksimum 1600 px, nama acak. Berkas disimpan di disk publik `sampul/{sekolah_id}/...` karena memang tampil untuk pengunjung. Berkas lama dihapus saat diganti atau dihapus agar tidak menumpuk, dan setiap aksi dicatat di `log_aktivitas` (`ubah_foto_sampul`, `hapus_foto_sampul`) tanpa menyimpan isi berkas.
- **Batas unggah foto tampilan 10 MB (revisi 18 November 2026):** pemilik proyek menilai batas 2 MB terlalu kecil untuk foto langsung dari kamera HP, tetapi hanya untuk foto kebutuhan tampilan sekolah; unggahan pendaftar tetap 2 MB karena itu data pribadi. Karena itu dua batas dipisahkan: **foto tampilan** (foto sampul kartu sekolah di `config('spmb.sampul')` dan gambar konten sekolah di `config('spmb.konten')`) memakai `maks_kb` bawaan **10240 KB (10 MB)** lewat `SPMB_SAMPUL_MAKS_KB` / `SPMB_KONTEN_MAKS_KB`, sedangkan **berkas pendaftar** tetap 2 MB di `config('spmb.berkas')` dan tidak disentuh. `BerkasService::prosesGambarKonten()` menerima `$maksKb` dan `$sisiMaks` opsional (null berarti memakai batas konten) dan menolak gambar di atas 40 megapiksel sebelum dimuat ke memori, sehingga unggahan besar tidak menghabiskan memori server. Yang disimpan tetap hasil tulis ulang yang sudah diperkecil: unggahan 7,78 MB (2400 × 1800 px) tersimpan sebagai JPEG 1600 × 1200 px sekitar 1,29 MB. Label "maks. 10 MB" serta `data-maks-berkas` di panel pengaturan, panel super admin, dan editor konten dihitung dari config, bukan angka tetap, dan pesan galat FormRequest juga mengikuti config.
- **Rute dan policy:** admin `PUT/DELETE /admin/pengaturan/foto-sampul` (sekolah diambil dari akun, bukan dari formulir, lalu diperiksa `PengaturanSekolahPolicy`) dan super admin `PUT/DELETE /super/sekolah/{sekolah}/foto-sampul` (`SuperAdminPolicy`). Unggahan dibatasi `throttle:20,1`. Panitia dan yayasan ditolak 403 oleh middleware peran.
- **Tampilan:** kartu **Foto sampul beranda** di `/admin/pengaturan` dan `/super/sekolah/{sekolah}/edit` memuat pratinjau rasio 16:10 berisi foto yang sedang tampil (unggahan atau bawaan), keterangan "memakai foto bawaan" bila belum ada unggahan, input `foto_sampul` dengan pemeriksaan ukuran sisi klien (`data-maks-berkas`, memakai skrip yang sudah ada), tombol simpan, dan tombol hapus lewat `x-modal-konfirmasi` (metode DELETE). Tidak ada gaya atau skrip inline, jadi CSP `style-src 'self'; script-src 'self'` tetap berlaku.
- **Halaman publik:** `GambarPublik::sampulSekolah()` menerima parameter ketiga opsional berisi jalur unggahan dan memprioritaskannya di atas foto bawaan; `HalamanPublikService::sekolahRingkas()` membaca `foto_sampul_path`. Bila berkas unggahan hilang dari disk, tampilan kembali ke foto bawaan, bukan gambar rusak.
- **Bug cache yang ikut diperbaiki:** pembuangan cache halaman publik selama ini **tidak pernah berjalan**. `AppServiceProvider::invalidasiHalamanPublik()` mendaftarkan listener lewat `Model::saved()` pada kelas dasar, sehingga event terdaftar untuk `Illuminate\Database\Eloquent\Model` dan tidak pernah dipicu oleh model turunan (`Sekolah`, `KontenSekolah`, dan seterusnya). Akibatnya admin yang mengubah konten atau foto harus menunggu cache 10 menit kedaluwarsa. Listener kini didaftarkan per model konkret di `$dipantau`, dan tes unggah foto sampul memanaskan cache beranda lebih dulu untuk memastikan foto baru langsung tampil.
- **Dokumentasi:** `docs/struktur-tabel.md` bagian 7 mencatat kolom baru; `docs/desain.md` bagian 5 menyebut kartu foto sampul; `docs/penerapan.md` bagian 2.9 menjelaskan folder `sampul/` di disk publik dan ketergantungannya pada `storage:link`.
- **Pemeriksaan tampilan:** `/admin/pengaturan` dibuka dengan Chrome headless (CDP) pada 360 × 780 px dan 1280 × 900 px. `document.documentElement.scrollWidth` bernilai 360 px dan 1265 px (lebar isi, lebih kecil dari 1280 px karena batang gulir), jadi tidak ada guliran horizontal. Tidak ada gaya atau skrip inline.
- **Pengujian:** `FotoSampulSekolahTest` (10 tes, 78 asersi) memeriksa admin mengunggah lalu foto tampil di beranda dengan URL relatif `/storage/...` (cache beranda dipanaskan lebih dulu), mengganti foto menghapus berkas lama, menghapus foto mengembalikan foto bawaan dan menghapus berkas, panitia dan yayasan 403, super admin dapat mengunggah/menghapus untuk sekolah mana pun, unggahan satu sekolah tidak menyentuh sekolah lain, berkas > 10 MB ditolak, berkas 6 MB diterima lalu tersimpan jauh lebih kecil dengan sisi terpanjang ≤ 1600 px, berkas bukan gambar (termasuk PDF bernama `.jpg`) ditolak, serta kartu unggah tampil di halaman admin dan super admin. `AdminKontenFormTest` bertambah satu tes: gambar tampilan 6 MB diterima, tersimpan ≤ 1600 px, dan editor menampilkan label "maks. 10 MB" serta `data-maks-berkas="10485760"`, sedangkan gambar 11 MB tetap ditolak dengan pesan "Ukuran gambar maksimal 10 MB.". `PanitiaBerkasTest` juga bertambah satu tes yang memastikan **berkas pendaftar di atas 2 MB tetap ditolak** dan tidak tersimpan, supaya kenaikan batas foto tampilan tidak ikut melonggarkan data pribadi. Diverifikasi juga lewat HTTP pada aplikasi berjalan: JPEG 7,78 MB (2400 × 1800) diunggah lewat panel → beranda langsung menampilkan `/storage/sampul/...` dengan hasil simpan 1600 × 1200 px (1,29 MB), hapus → kembali ke foto bawaan dan berkas terhapus. Seluruh suite: **524 lulus (3.929 asersi)**. `pint` lulus untuk seluruh berkas yang diubah. `migrate:fresh --seed` bersih pada basis data SQLite kosong; basis data pengembangan hanya ditambah kolom baru tanpa dihapus.

---

## Lampiran A - Daftar rute dan middleware

Dihasilkan dari `php artisan route:list` (152 rute). Middleware global `HeaderKeamanan`, `AkunAktif`, `SesiKedaluwarsa`, dan `WajibGantiPassword` berlaku pada semua rute `web` dan tidak diulang di setiap baris. Policy/scope per grup dijelaskan di bagian 4.2.

| Metode | URI | Nama | Middleware |
| --- | --- | --- | --- |
| GET | `/` | beranda | `web` |
| GET | `/admin` | admin.beranda | `web + auth + peran:admin` |
| GET | `/admin/berkas` | admin.berkas.index | `web + auth + peran:admin` |
| POST | `/admin/berkas` | admin.berkas.simpan | `web + auth + peran:admin` |
| PUT | `/admin/berkas/{jenisBerkas}` | admin.berkas.ubah | `web + auth + peran:admin` |
| DELETE | `/admin/berkas/{jenisBerkas}` | admin.berkas.hapus | `web + auth + peran:admin` |
| GET | `/admin/biaya` | admin.biaya.index | `web + auth + peran:admin` |
| POST | `/admin/biaya` | admin.biaya.simpan | `web + auth + peran:admin` |
| PUT | `/admin/biaya/{rincianBiaya}` | admin.biaya.ubah | `web + auth + peran:admin` |
| DELETE | `/admin/biaya/{rincianBiaya}` | admin.biaya.hapus | `web + auth + peran:admin` |
| GET | `/admin/gelombang` | admin.gelombang.index | `web + auth + peran:admin` |
| POST | `/admin/gelombang` | admin.gelombang.simpan | `web + auth + peran:admin + throttle:30,1` |
| PUT | `/admin/gelombang/{gelombang}` | admin.gelombang.ubah | `web + auth + peran:admin` |
| DELETE | `/admin/gelombang/{gelombang}` | admin.gelombang.hapus | `web + auth + peran:admin` |
| GET | `/admin/isian` | admin.isian.index | `web + auth + peran:admin` |
| PUT | `/admin/isian` | admin.isian.simpan | `web + auth + peran:admin` |
| GET | `/admin/konten` | admin.konten.index | `web + auth + peran:admin` |
| POST | `/admin/konten` | admin.konten.tambah | `web + auth + peran:admin` |
| PUT | `/admin/konten/teks` | admin.konten.teks | `web + auth + peran:admin` |
| PUT | `/admin/konten/{konten}` | admin.konten.ubah | `web + auth + peran:admin` |
| DELETE | `/admin/konten/{konten}` | admin.konten.hapus | `web + auth + peran:admin` |
| GET | `/admin/log` | admin.log.index | `web + auth + peran:admin` |
| GET | `/admin/panitia` | admin.panitia.index | `web + auth + peran:admin` |
| POST | `/admin/panitia` | admin.panitia.simpan | `web + auth + peran:admin + throttle:20,1` |
| POST | `/admin/panitia/{user}/aktifkan` | admin.panitia.aktifkan | `web + auth + peran:admin` |
| POST | `/admin/panitia/{user}/atur-ulang` | admin.panitia.atur-ulang | `web + auth + peran:admin` |
| POST | `/admin/panitia/{user}/nonaktifkan` | admin.panitia.nonaktifkan | `web + auth + peran:admin` |
| GET | `/admin/pengaturan` | admin.pengaturan | `web + auth + peran:admin` |
| PUT | `/admin/pengaturan` | admin.pengaturan.simpan | `web + auth + peran:admin` |
| PUT | `/admin/pengaturan/foto-sampul` | admin.pengaturan.foto.simpan | `web + auth + peran:admin + throttle:20,1` |
| DELETE | `/admin/pengaturan/foto-sampul` | admin.pengaturan.foto.hapus | `web + auth + peran:admin` |
| GET | `/admin/pengumuman` | admin.pengumuman.index | `web + auth + peran:admin` |
| GET | `/admin/pengumuman/{gelombang}` | admin.pengumuman.konfirmasi | `web + auth + peran:admin` |
| POST | `/admin/pengumuman/{gelombang}` | admin.pengumuman.simpan | `web + auth + peran:admin` |
| GET | `/admin/rekap` | admin.rekap.index | `web + auth + peran:admin` |
| GET | `/admin/rekap.xlsx` | admin.rekap.unduh | `web + auth + peran:admin + throttle:ekspor` |
| GET | `/admin/rekening` | admin.rekening.index | `web + auth + peran:admin` |
| POST | `/admin/rekening` | admin.rekening.simpan | `web + auth + peran:admin` |
| PUT | `/admin/rekening/{rekeningSekolah}` | admin.rekening.ubah | `web + auth + peran:admin` |
| DELETE | `/admin/rekening/{rekeningSekolah}` | admin.rekening.hapus | `web + auth + peran:admin` |
| GET | `/akun` | walisantri.beranda | `web + auth + peran:walisantri` |
| GET | `/akun/berkas/{berkas}` | walisantri.berkas.tampil | `web + auth + peran:walisantri + throttle:60,1` |
| GET | `/akun/daftar/baru` | walisantri.daftar.baru | `web + auth + peran:walisantri` |
| POST | `/akun/daftar/baru` | walisantri.daftar.baru.simpan | `web + auth + peran:walisantri + throttle:20,1` |
| GET | `/akun/daftar/baru/anak` | walisantri.daftar.anak-baru | `web + auth + peran:walisantri` |
| PUT | `/akun/daftar/baru/anak` | walisantri.daftar.anak-baru.simpan | `web + auth + peran:walisantri + throttle:20,1` |
| GET | `/akun/orang-tua` | walisantri.orang-tua | `web + auth + peran:walisantri` |
| PUT | `/akun/orang-tua` | walisantri.orang-tua.simpan | `web + auth + peran:walisantri` |
| GET | `/akun/pembayaran/{pembayaran}/bukti` | walisantri.pembayaran.bukti | `web + auth + peran:walisantri + throttle:60,1` |
| GET | `/akun/pembayaran/{pembayaran}/cetak/kuitansi` | walisantri.pembayaran.cetak.kuitansi | `web + auth + peran:walisantri + throttle:cetak` |
| GET | `/akun/pendaftaran/{pendaftaran}` | walisantri.pendaftaran.detail | `web + auth + peran:walisantri` |
| GET | `/akun/pendaftaran/{pendaftaran}/berkas` | walisantri.pendaftaran.berkas.index | `web + auth + peran:walisantri` |
| POST | `/akun/pendaftaran/{pendaftaran}/berkas` | walisantri.pendaftaran.berkas.simpan | `web + auth + peran:walisantri + throttle:20,1` |
| DELETE | `/akun/pendaftaran/{pendaftaran}/berkas/{berkas}` | walisantri.pendaftaran.berkas.hapus | `web + auth + peran:walisantri` |
| GET | `/akun/pendaftaran/{pendaftaran}/cetak/bukti` | walisantri.pendaftaran.cetak.bukti | `web + auth + peran:walisantri + throttle:cetak` |
| GET | `/akun/pendaftaran/{pendaftaran}/hasil` | walisantri.pendaftaran.hasil | `web + auth + peran:walisantri` |
| POST | `/akun/pendaftaran/{pendaftaran}/kirim` | walisantri.pendaftaran.kirim | `web + auth + peran:walisantri + throttle:10,1` |
| POST | `/akun/pendaftaran/{pendaftaran}/kirim-perbaikan` | walisantri.pendaftaran.kirim-perbaikan | `web + auth + peran:walisantri + throttle:10,1` |
| PUT | `/akun/pendaftaran/{pendaftaran}/langkah/7` | walisantri.pendaftaran.biaya.simpan | `web + auth + peran:walisantri + throttle:20,1` |
| GET | `/akun/pendaftaran/{pendaftaran}/langkah/{n}` | walisantri.pendaftaran.langkah | `web + auth + peran:walisantri` |
| PUT | `/akun/pendaftaran/{pendaftaran}/langkah/{n}` | walisantri.pendaftaran.langkah.simpan | `web + auth + peran:walisantri + throttle:20,1` |
| GET | `/akun/pendaftaran/{pendaftaran}/pembayaran` | walisantri.pendaftaran.pembayaran.index | `web + auth + peran:walisantri` |
| POST | `/akun/pendaftaran/{pendaftaran}/pembayaran` | walisantri.pendaftaran.pembayaran.simpan | `web + auth + peran:walisantri + throttle:20,1` |
| GET | `/akun/pendaftaran/{pendaftaran}/sukses` | walisantri.pendaftaran.sukses | `web + auth + peran:walisantri` |
| GET | `/akun/pendaftaran/{pendaftaran}/tahap-2` | walisantri.pendaftaran.tahap2 | `web + auth + peran:walisantri` |
| PUT | `/akun/pendaftaran/{pendaftaran}/tahap-2` | walisantri.pendaftaran.tahap2.simpan | `web + auth + peran:walisantri + throttle:20,1` |
| GET | `/daftar-akun` | daftar-akun | `web + guest` |
| POST | `/daftar-akun` | daftar-akun.simpan | `web + guest` |
| GET | `/ganti-password` | ganti-password | `web + auth` |
| POST | `/ganti-password` | ganti-password.simpan | `web + auth` |
| GET | `/kartu-akun` | kartu-akun.staf | `web + auth` |
| GET | `/kebijakan-privasi` | kebijakan-privasi | `web` |
| POST | `/keluar` | keluar | `web + auth` |
| GET | `/lupa-password` | lupa-password | `web + guest` |
| POST | `/lupa-password` | lupa-password.simpan | `web + guest + throttle:10,1` |
| GET | `/masuk` | masuk | `web + guest` |
| POST | `/masuk` | masuk.simpan | `web + guest` |
| GET | `/panitia` | panitia.beranda | `web + auth + peran:panitia,admin` |
| GET | `/panitia/berkas/{berkas}` | panitia.berkas.tampil | `web + auth + peran:panitia,admin + throttle:60,1` |
| GET | `/panitia/ekspor` | panitia.ekspor.index | `web + auth + peran:panitia,admin` |
| POST | `/panitia/ekspor` | panitia.ekspor.unduh | `web + auth + peran:panitia,admin + throttle:ekspor` |
| GET | `/panitia/kartu-akun` | panitia.akun.kartu | `web + auth + peran:panitia,admin` |
| GET | `/panitia/offline` | panitia.offline.index | `web + auth + peran:panitia,admin` |
| POST | `/panitia/offline/akun` | panitia.offline.akun | `web + auth + peran:panitia,admin + throttle:20,1` |
| GET | `/panitia/offline/anak-baru` | panitia.offline.anak-baru | `web + auth + peran:panitia,admin` |
| PUT | `/panitia/offline/anak-baru` | panitia.offline.anak-baru.simpan | `web + auth + peran:panitia,admin + throttle:20,1` |
| POST | `/panitia/offline/cari` | panitia.offline.cari | `web + auth + peran:panitia,admin + throttle:20,1` |
| GET | `/panitia/offline/orang-tua` | panitia.offline.orang-tua | `web + auth + peran:panitia,admin` |
| PUT | `/panitia/offline/orang-tua` | panitia.offline.orang-tua.simpan | `web + auth + peran:panitia,admin + throttle:20,1` |
| GET | `/panitia/offline/sekolah` | panitia.offline.sekolah | `web + auth + peran:panitia,admin` |
| POST | `/panitia/offline/sekolah` | panitia.offline.sekolah.simpan | `web + auth + peran:panitia,admin + throttle:20,1` |
| GET | `/panitia/offline/{pendaftaran}/berkas` | panitia.offline.berkas.index | `web + auth + peran:panitia,admin` |
| POST | `/panitia/offline/{pendaftaran}/berkas` | panitia.offline.berkas.simpan | `web + auth + peran:panitia,admin + throttle:20,1` |
| DELETE | `/panitia/offline/{pendaftaran}/berkas/{berkas}` | panitia.offline.berkas.hapus | `web + auth + peran:panitia,admin` |
| GET | `/panitia/offline/{pendaftaran}/biaya` | panitia.offline.biaya | `web + auth + peran:panitia,admin` |
| PUT | `/panitia/offline/{pendaftaran}/biaya` | panitia.offline.biaya.simpan | `web + auth + peran:panitia,admin + throttle:20,1` |
| POST | `/panitia/offline/{pendaftaran}/kirim` | panitia.offline.kirim | `web + auth + peran:panitia,admin + throttle:10,1` |
| GET | `/panitia/offline/{pendaftaran}/langkah/{n}` | panitia.offline.langkah | `web + auth + peran:panitia,admin` |
| PUT | `/panitia/offline/{pendaftaran}/langkah/{n}` | panitia.offline.langkah.simpan | `web + auth + peran:panitia,admin + throttle:20,1` |
| GET | `/panitia/offline/{pendaftaran}/ringkasan` | panitia.offline.ringkasan | `web + auth + peran:panitia,admin` |
| GET | `/panitia/pembayaran` | panitia.pembayaran.index | `web + auth + peran:panitia,admin` |
| GET | `/panitia/pembayaran/{pembayaran}` | panitia.pembayaran.tampil | `web + auth + peran:panitia,admin + throttle:60,1` |
| GET | `/panitia/pembayaran/{pembayaran}/cetak/kuitansi` | panitia.pembayaran.cetak.kuitansi | `web + auth + peran:panitia,admin + throttle:cetak` |
| POST | `/panitia/pembayaran/{pembayaran}/setujui` | panitia.pembayaran.setujui | `web + auth + peran:panitia,admin` |
| POST | `/panitia/pembayaran/{pembayaran}/tolak` | panitia.pembayaran.tolak | `web + auth + peran:panitia,admin` |
| GET | `/panitia/pendaftar` | panitia.pendaftar.index | `web + auth + peran:panitia,admin` |
| GET | `/panitia/pendaftar/{pendaftaran}` | panitia.pendaftar.detail | `web + auth + peran:panitia,admin` |
| POST | `/panitia/pendaftar/{pendaftaran}/atur-ulang-password` | panitia.pendaftar.atur-ulang-password | `web + auth + peran:panitia,admin` |
| POST | `/panitia/pendaftar/{pendaftaran}/berkas` | panitia.pendaftar.berkas.simpan | `web + auth + peran:panitia,admin + throttle:20,1` |
| GET | `/panitia/pendaftar/{pendaftaran}/berkas.zip` | panitia.pendaftar.zip | `web + auth + peran:panitia,admin + throttle:zip` |
| POST | `/panitia/pendaftar/{pendaftaran}/berkas/{berkas}/periksa` | panitia.pendaftar.berkas.periksa | `web + auth + peran:panitia,admin` |
| PUT | `/panitia/pendaftar/{pendaftaran}/catatan` | panitia.pendaftar.catatan | `web + auth + peran:panitia,admin` |
| GET | `/panitia/pendaftar/{pendaftaran}/cetak/bukti` | panitia.pendaftar.cetak.bukti | `web + auth + peran:panitia,admin + throttle:cetak` |
| GET | `/panitia/pendaftar/{pendaftaran}/cetak/formulir` | panitia.pendaftar.cetak.formulir | `web + auth + peran:panitia,admin + throttle:cetak` |
| PUT | `/panitia/pendaftar/{pendaftaran}/daftar-ulang` | panitia.pendaftar.daftar-ulang | `web + auth + peran:panitia,admin` |
| PUT | `/panitia/pendaftar/{pendaftaran}/data` | panitia.pendaftar.data | `web + auth + peran:panitia,admin + throttle:20,1` |
| POST | `/panitia/pendaftar/{pendaftaran}/keputusan` | panitia.pendaftar.keputusan | `web + auth + peran:panitia,admin` |
| POST | `/panitia/pendaftar/{pendaftaran}/mengundurkan-diri` | panitia.pendaftar.mengundurkan-diri | `web + auth + peran:panitia,admin` |
| POST | `/panitia/pendaftar/{pendaftaran}/pembayaran-tunai` | panitia.pendaftar.pembayaran-tunai | `web + auth + peran:panitia,admin` |
| POST | `/panitia/pendaftar/{pendaftaran}/pindah-anak` | panitia.pendaftar.pindah-anak | `web + auth + peran:panitia,admin` |
| POST | `/reset-password` | password.reset.simpan | `web + guest + throttle:10,1` |
| GET | `/reset-password/{token}` | password.reset | `web + guest + signed` |
| GET | `/robots.txt` | robots | `web` |
| GET | `/sekolah/{kode}` | sekolah | `web` |
| GET | `/sitemap.xml` | sitemap | `web` |
| GET | `/super` | super.beranda | `web + auth + peran:super_admin` |
| GET | `/super/admin` | super.admin.index | `web + auth + peran:super_admin` |
| POST | `/super/admin` | super.admin.simpan | `web + auth + peran:super_admin + throttle:20,1` |
| POST | `/super/admin/{user}/aktifkan` | super.admin.aktifkan | `web + auth + peran:super_admin` |
| POST | `/super/admin/{user}/atur-ulang` | super.admin.atur-ulang | `web + auth + peran:super_admin` |
| POST | `/super/admin/{user}/nonaktifkan` | super.admin.nonaktifkan | `web + auth + peran:super_admin` |
| GET | `/super/konten` | super.konten.index | `web + auth + peran:super_admin` |
| POST | `/super/konten` | super.konten.tambah | `web + auth + peran:super_admin` |
| PUT | `/super/konten/teks` | super.konten.teks | `web + auth + peran:super_admin` |
| PUT | `/super/konten/{konten}` | super.konten.ubah | `web + auth + peran:super_admin` |
| DELETE | `/super/konten/{konten}` | super.konten.hapus | `web + auth + peran:super_admin` |
| GET | `/super/log` | super.log.index | `web + auth + peran:super_admin` |
| GET | `/super/sekolah` | super.sekolah.index | `web + auth + peran:super_admin` |
| PUT | `/super/sekolah/{sekolah}` | super.sekolah.simpan | `web + auth + peran:super_admin` |
| PUT | `/super/sekolah/{sekolah}/foto-sampul` | super.sekolah.foto.simpan | `web + auth + peran:super_admin + throttle:20,1` |
| DELETE | `/super/sekolah/{sekolah}/foto-sampul` | super.sekolah.foto.hapus | `web + auth + peran:super_admin` |
| GET | `/super/sekolah/{sekolah}/edit` | super.sekolah.edit | `web + auth + peran:super_admin` |
| GET | `/super/yayasan` | super.yayasan.index | `web + auth + peran:super_admin` |
| POST | `/super/yayasan` | super.yayasan.simpan | `web + auth + peran:super_admin + throttle:20,1` |
| POST | `/super/yayasan/{user}/aktifkan` | super.yayasan.aktifkan | `web + auth + peran:super_admin` |
| POST | `/super/yayasan/{user}/atur-ulang` | super.yayasan.atur-ulang | `web + auth + peran:super_admin` |
| POST | `/super/yayasan/{user}/nonaktifkan` | super.yayasan.nonaktifkan | `web + auth + peran:super_admin` |
| GET | `/up` | — | — |
| GET | `/yayasan` | yayasan.beranda | `web + auth + peran:yayasan` |
| GET | `/yayasan/profil` | yayasan.profil | `web + auth + peran:yayasan` |
| GET | `/yayasan/rekap` | yayasan.rekap | `web + auth + peran:yayasan` |
| GET | `/yayasan/rekap.xlsx` | yayasan.rekap.unduh | `web + auth + peran:yayasan + throttle:ekspor` |

---

## Lampiran B — Berkas baru/berubah pada Tahap 6

Kode aplikasi:

- `config/spmb.php` (bagian cadangan dan produksi), `app/Support/GambarPublik.php`, `app/Support/KonfigurasiProduksi.php`
- `app/Services/HalamanPublikService.php`, `PemeriksaanProduksiService.php`, `CadanganService.php`, `FotoSampulService.php`
- `app/Http/Controllers/HalamanController.php`, `app/Http/Middleware/HeaderKeamanan.php` (termasuk `pasang()` untuk halaman galat)
- `app/Http/Requests/FotoSampulRequest.php`
- `app/Console/Commands/{Cadangkan,PulihkanCadangan,PeriksaProduksi,BersihkanTokenSesi,OptimalkanGambar}.php`
- `app/Providers/AppServiceProvider.php` (invalidasi cache publik, penjaga produksi, proksi, batas laju)
- `bootstrap/app.php` (header untuk respons galat), `routes/web.php`, `routes/console.php`
- `database/migrations/0001_01_08_000000_tambah_indeks_tahap_6.php`
- `database/migrations/0001_01_09_000000_tambah_foto_sampul_sekolah.php`
- `resources/js/app.js` (tanpa ekspresi inline), `resources/css/app.css` (aturan cetak)

Tampilan:

- `resources/views/layouts/publik.blade.php`
- `resources/views/publik/{beranda,sekolah,kebijakan-privasi}.blade.php`
- `resources/views/errors/*.blade.php`, layout panel/akun/cetak (`noindex`)
- `resources/views/publik/masuk.blade.php` dan halaman autentikasi lain dirapikan
- `public/images/optimasi/` (turunan WebP dari foto publik, dibuat oleh `spmb:optimalkan-gambar`)

Konfigurasi dan dokumen:

- `.env.production.example`
- `public/.htaccess`
- `docs/penerapan.md`
- `docs/laporan-tahap-6.md` (dokumen ini)

Tes:

- `tests/Feature/HalamanPublikTest.php`
- `tests/Feature/FotoSampulSekolahTest.php`
- `tests/Feature/Keamanan/{CspHalamanTest,HeaderKeamananTest,BerkasPrivatTest,QueryKonstanTest,KonkurenTest}.php`
- `tests/Feature/{CadanganTest,PeriksaProduksiTest}.php`

---

**Berhenti di sini.** Proyek siap diuji bersama pihak sekolah.
