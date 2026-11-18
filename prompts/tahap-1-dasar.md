# Tahap 1 — Dasar: database, akun, peran, masuk, hak akses

Kerjakan hanya tahap ini. Jangan membangun formulir pendaftaran, unggah berkas, panel panitia, atau halaman publik lengkap. Itu tahap 2 sampai 6.

## 0. Sebelum menulis kode

1. Baca penuh: `AGENTS.md`, `docs/aturan-sistem.md`, `docs/struktur-tabel.md`, `docs/keamanan.md`, `docs/data-awal.md`, `docs/desain.md`, `docs/visi-misi.md`.
2. Periksa lingkungan: `php -v` (harus 8.3 atau lebih baru), `composer -V`, `node -v`, `npm -v`, ekstensi PHP (`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` atau `imagick`, `zip`, `intl`). Bila ada yang kurang, berhenti dan laporkan apa yang kurang.
3. Bila folder proyek belum berisi aplikasi Laravel, buat dengan `composer create-project laravel/laravel .` di folder ini (tanpa starter kit bila pilihan itu muncul), lalu pastikan `php artisan --version` menunjukkan Laravel 13. Jangan menimpa folder `docs/`, `prompts/`, `AGENTS.md`, dan `public/images/`.
4. Tulis rencana singkat (daftar file yang akan dibuat atau diubah), lalu langsung lanjut mengerjakan; tidak perlu menunggu persetujuan kecuali ada hal di dokumen yang bertentangan atau tidak jelas.

## 1. Konfigurasi dasar

- `.env.example` lengkap dan aman: MySQL, `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`, `APP_FALLBACK_LOCALE=id`, `SESSION_DRIVER=database`, `SESSION_LIFETIME=480`, `SESSION_SECURE_COOKIE` (true di produksi), `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `MAIL_MAILER=log`. Tanpa nilai rahasia sungguhan. `.gitignore` memuat `.env`, `storage` isi, `vendor`, `node_modules`, `public/build`.
- Paket bahasa Indonesia: pasang `laravel-lang/lang` (cek cocok dengan Laravel 13), publikasikan `id`. Semua pesan validasi dan autentikasi dalam bahasa Indonesia.
- Front-end: Tailwind lewat Vite, Alpine.js, dan font Plus Jakarta Sans lewat `@fontsource-variable/plus-jakarta-sans` (di-host sendiri). Untuk Alpine pakai build CSP (`@alpinejs/csp`). Tidak ada CDN.
- `AppServiceProvider`: paksa `https` bila `APP_ENV=production`; `Model::preventLazyLoading()` di lingkungan bukan produksi; `Model::shouldBeStrict()` bila tersedia.
- Atur proxy tepercaya lewat konfigurasi Laravel (nilai dari `.env`), bukan `*` secara membabi buta.
- Zona waktu dan format tanggal Indonesia (`Carbon::setLocale('id')`).

## 2. Migrasi (semua 20 tabel, sekarang, supaya tahap berikutnya tidak mengubah skema)

Ikuti `docs/struktur-tabel.md` persis, termasuk urutan migrasi, bagian 7 "Penambahan terbaru" (`pendaftaran.program`, `gelombang.tautan_grup_wa`, kolom `uuid`, kolom terenkripsi bertipe `text`), unique, indeks, dan `restrictOnDelete()`.

Catatan:
- Tabel bawaan Laravel yang tetap dibuat: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`. Tabel `users` bawaan diganti dengan struktur di dokumen (tanpa `email_verified_at` dan `remember_token`; fitur "ingat saya" dimatikan).
- `riwayat_status` dan `log_aktivitas`: kolom waktu `dibuat_pada` saja, tanpa `updated_at`.
- Kolom `uuid` diisi otomatis saat model dibuat (trait `PunyaUuid` memakai `Str::uuid()`), dan model memakai `uuid` sebagai `getRouteKeyName()` pada `anak`, `pendaftaran`, `berkas`, `pembayaran`.
- Semua enum disimpan sebagai `string` dengan batasan nilai di aplikasi (Enum PHP), bukan tipe `enum` MySQL, agar mudah diubah.
- Jalankan `php artisan migrate:fresh` dan periksa tidak ada galat.

## 3. Enum, model, dan trait

- Enum di `app/Enums/` (nilai persis seperti di dokumen): `PeranPengguna` (`super_admin`, `admin`, `panitia`, `yayasan`, `walisantri`), `StatusHasil` (`Baru`, `Diverifikasi`, `Perlu perbaikan`, `Diterima`, `Ditolak`, `Cadangan`; simpan dengan nilai teks yang sama), `StatusMurid`, `ModePenerimaan`, `MetodePembayaran`, `StatusPembayaran`, `StatusBerkas`, `StatusGelombang`, `JenisRiwayat`, `HubunganOrangTua`, `TipeBiaya`. Tiap enum punya metode `label()` dalam bahasa Indonesia.
- Satu model per tabel di `app/Models/`: `protected $table` eksplisit, `$fillable` eksplisit (kolom sensitif seperti `peran`, `sekolah_id`, `status_hasil`, `user_id` **tidak** masuk `$fillable` pada model yang diisi dari formulir pengguna; isi lewat kode terkontrol), `casts` (Enum, tanggal, `boolean`, `encrypted` untuk `anak.nik`, `anak.no_kk`, `orang_tua.nik`), `$hidden` untuk `password`, `nik`, `no_kk`, dan relasi Eloquent lengkap dua arah.
- `RiwayatStatus` dan `LogAktivitas`: nonaktifkan `UPDATED_AT`; lempar pengecualian pada `updating`, `deleting`, dan `saving` untuk record yang sudah ada (hanya boleh ditambah).
- Trait `app/Models/Concerns/MilikSekolah`: global scope yang membatasi `sekolah_id` sama dengan `sekolah_id` pengguna yang sedang masuk **bila** pengguna itu `panitia` atau `admin`. Bila tidak ada pengguna (konsol, seeder, antrean), scope tidak aktif. Sediakan `scopeTanpaSekolah()` yang hanya boleh dipakai di tempat yang disebut jelas di komentar. Pasang pada `Pendaftaran`, `Berkas` (lewat pendaftaran), `Pembayaran` (lewat pendaftaran), `Gelombang`, `RincianBiaya`, `RekeningSekolah`, `JenisBerkas`, `KontenSekolah`, `PengaturanIsian`, `Pengumuman`, `LogAktivitas`. Untuk model yang tidak punya `sekolah_id` langsung, buat scope lewat relasi `pendaftaran`.
- Model `User`: validasi tersimpan di `saving` bahwa `panitia` dan `admin` wajib punya `sekolah_id`, dan peran lain wajib kosong; salah satu dari `no_hp` atau `email` wajib terisi. Pelanggaran melempar pengecualian.
- Factory untuk semua model yang dipakai tes (minimal `Sekolah`, `TahunAjaran`, `User` dengan state per peran, `Gelombang`, `Anak`, `Pendaftaran`).

## 4. Katalog isian formulir (`config/isian.php`)

Array kunci → `label`, `tipe` (`teks`, `teks_panjang`, `angka`, `tanggal`, `pilihan`, `centang`), `bagian`, `sumber` (`kolom` bila kolom tetap di `anak`, `jawaban` bila disimpan di `jawaban_tambahan`), `pilihan` (bila `tipe = pilihan`), dan `inti` (true bila tidak boleh disembunyikan). Isinya:

| Bagian | Kunci |
| --- | --- |
| `identitas` | `nama_lengkap` (inti), `nama_panggilan`, `jenis_kelamin` (inti), `tempat_lahir` (inti), `tanggal_lahir` (inti), `nik`, `no_kk`, `nisn`, `agama`, `anak_ke`, `jumlah_saudara`, `bahasa_sehari_hari` |
| `alamat` | `alamat`, `rt`, `rw`, `desa`, `kecamatan`, `kabupaten`, `provinsi`, `kode_pos`, `jarak_ke_sekolah`, `transportasi`, `tinggal_dengan` |
| `kesehatan` | `tinggi_badan`, `berat_badan`, `golongan_darah`, `riwayat_penyakit`, `alergi`, `kebutuhan_khusus` |
| `asal_sekolah` | `asal_sekolah`, `jenis_asal_sekolah` |
| `tahap2` (sumber `jawaban`) | `hobi` (teks), `cita_cita` (teks), `kemampuan_khusus` (teks_panjang), `lembar_psikologi` (teks_panjang), `surat_pernyataan` (centang) |

Label dalam bahasa Indonesia yang jelas. Seeder `pengaturan_isian` (bagian 8) memakai katalog ini.

## 5. Autentikasi (tulis sendiri dengan fitur bawaan Laravel)

Halaman dan tindakan:
- `GET/POST /masuk`: satu kolom "Nomor HP atau email" dan kata sandi. Deteksi: bila berisi `@`, cari lewat email; bila tidak, normalisasi sebagai nomor HP. Pesan galat **sama** untuk akun tidak ada, kata sandi salah, dan akun nonaktif ("Nomor HP/email atau kata sandi tidak sesuai"). Pembatasan laju 5 per menit per (identitas + IP) dengan pesan 429 yang sopan. `session()->regenerate()` setelah berhasil. Tidak ada "ingat saya". Perbarui `terakhir_masuk`. Arahkan setelah masuk sesuai peran: walisantri ke `/akun`, panitia dan admin ke `/panitia`, super admin ke `/super`, yayasan ke `/yayasan`.
- `GET/POST /daftar-akun` (walisantri): nama lengkap, nomor HP, email (opsional), kata sandi dua kali, kotak persetujuan penggunaan data (wajib). Peran diisi **di kode** sebagai `walisantri`; input `peran`, `sekolah_id`, `aktif`, `wajib_ganti_password` dari formulir diabaikan sepenuhnya. Nomor HP unik (setelah normalisasi). Batas 10 per jam per IP. Setelah berhasil langsung masuk.
- `POST /keluar`: `invalidate()` sesi dan regenerasi token.
- `GET/POST /akun/ganti-password` (juga dipakai staf; rute di luar awalan peran tertentu tapi wajib `auth`): kata sandi lama wajib (kecuali bila `wajib_ganti_password = true`, setelah reset sementara), kata sandi baru dua kali, aturan di `docs/aturan-sistem.md` bagian 2. Setelah berhasil `wajib_ganti_password = false`, sesi lain milik akun itu diakhiri (`Auth::logoutOtherDevices` atau hapus baris `sessions` milik user), dan dicatat di log.
- `GET /lupa-password`: halaman penjelasan statis (hubungi panitia sekolah tujuan; tombol WhatsApp ke nomor sekolah dari data). Reset mandiri lewat email dikerjakan di tahap 3, **jangan dibuat sekarang**.
- Layanan `NomorHp` (`app/Support/NomorHp.php`): `normalisasi()` (hanya angka; `08...` menjadi `628...`; `+62`, spasi, dan tanda hubung dibuang; menolak yang bukan 9–14 digit setelah normalisasi) dan `tampil()` (`0856-9542-2700`). Dipakai di mana pun nomor HP disimpan atau dicari.

Perintah artisan `php artisan spmb:buat-super-admin`: interaktif (nama, nomor HP, kata sandi dua kali dengan `secret()`), validasi sama dengan aturan kata sandi, membuat `super_admin` dengan `wajib_ganti_password = true`. Menolak membuat bila nomor sudah ada.

## 6. Middleware, kebijakan, dan keamanan

- `PeranMiddleware` (alias `peran`): `peran:panitia,admin` dst. Peran tidak cocok → 403. Belum masuk → ke `/masuk`.
- `WajibGantiPassword`: selama `wajib_ganti_password = true`, semua rute kecuali ganti kata sandi dan keluar diarahkan ke halaman ganti kata sandi.
- `AkunAktif`: pada setiap permintaan, bila `aktif = false`, keluarkan paksa dan arahkan ke masuk.
- `SesiKedaluwarsa`: simpan `waktu_terakhir_aktif` di sesi; bila lewat 120 menit untuk staf (`panitia`, `admin`, `super_admin`, `yayasan`) atau 480 menit untuk walisantri, keluarkan paksa dengan pesan "Sesi berakhir, silakan masuk lagi".
- `HeaderKeamanan` untuk semua respons web: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(self), microphone=(), geolocation=()`, HSTS (produksi), dan `Content-Security-Policy-Report-Only` dengan dasar di K-01 (jangan enforce dulu). Halaman setelah masuk memakai `Cache-Control: no-store, private`.
- Pendaftaran `RateLimiter` bernama untuk: masuk, daftar akun (dan siapkan nama untuk reset email, unggah, unduh, ekspor, atur ulang sandi agar tahap berikutnya tinggal memakai; lihat K-09).
- Halaman galat 403, 404, 419, 429, 500 dalam bahasa Indonesia, tanpa jejak tumpukan, memakai layout aplikasi.
- Pembantu `app/Support/Catat.php` (`Catat::aksi($aksi, $objek = null, $data = [], $sekolahId = null)`) menulis ke `log_aktivitas` dengan `user_id`, `sekolah_id`, alamat IP, tanpa data sensitif. Panggil untuk: masuk berhasil, masuk gagal (tanpa kata sandi; catat identitas dalam bentuk disamarkan), keluar, ganti kata sandi, daftar akun.

## 7. Tampilan dan rute rangka

Ikuti `docs/desain.md` untuk token, font, dan prinsip.
- Token desain sebagai variabel CSS di `resources/css/app.css`.
- Layout: `layouts/publik` (bilah atas sederhana), `layouts/walisantri` (HP dulu, navigasi bawah: Beranda, Anak Saya, Pengumuman, Akun), `layouts/panel` (sidebar kiri, dipakai panitia, admin, super admin, yayasan; menu sesuai peran).
- Komponen Blade dasar yang dibutuhkan tahap ini di `resources/views/components/`: `tombol`, `isian`, `kartu`, `lencana-status`, `toast`, `kosong`, `logo`, `foto-placeholder`, `modal-konfirmasi`, `sidebar`, `navigasi-bawah`. `logo` dan `foto-placeholder` tidak boleh galat bila file belum ada (tampil "Logo menyusul" atau "Foto menyusul").
- Halaman sungguhan: masuk, daftar akun, ganti kata sandi, lupa kata sandi, kebijakan privasi (draf, tandai "Draf, perlu ditinjau yayasan"), dan halaman galat.
- Halaman **rangka** (stub) supaya rute dan akses bisa dites; tiap halaman hanya menampilkan judul, nama pengguna, peran, dan keadaan kosong "Segera hadir di tahap berikutnya": `/` (beranda sementara, nama yayasan dan tombol Daftar Sekarang dan Masuk), `/akun`, `/panitia`, `/admin`, `/super`, `/yayasan`. Halaman sekolah `/sekolah/{kode}` menampilkan nama sekolah dan slogan dari data, tanpa biaya dan kuota.
- Awalan dan middleware rute persis seperti tabel di `AGENTS.md` bagian 4. `/akun` hanya `walisantri`; `/panitia` hanya `panitia` dan `admin`; `/admin` hanya `admin`; `/super` hanya `super_admin`; `/yayasan` hanya `yayasan`.

## 8. Seeder

Ikuti `docs/data-awal.md`.
- `DataAwalSeeder` (aman untuk produksi, idempoten, bisa dijalankan berulang tanpa data ganda): `tahun_ajaran` 2027/2028; empat `sekolah` (`kode`, `nama`, `jenjang`, `alamat`, `kontak` = nomor WhatsApp format `62...`, `mode_penerimaan = tanpa_seleksi`, `aktif`); `konten_sekolah` (slogan, profil singkat, visi, indikator visi, misi dari `docs/visi-misi.md` yang disalin persis ke berkas data seeder, program unggulan, ekskul, kontak termasuk Instagram, alur, syarat, sesuai data); `jenis_berkas`; dua `gelombang` draf per sekolah; `pengaturan_isian` per sekolah dari katalog; `rincian_biaya` hanya baris yang diketahui (TKK: IAP 500000 tetap; SD: Biaya formulir 50000 tetap, kelompok Formulir). Rekening kosong. Jangan mengarang nilai lain.
- `DataContohSeeder` (**hanya lokal**: cek `app()->environment('production')` di awal dan `throw`): satu super admin, per sekolah satu admin dan dua panitia, tiga akun walisantri dengan beberapa anak fiktif dalam berbagai status. Kata sandi dibuat acak per jalankan dan dicetak ke terminal; tidak ditulis di kode.
- `DatabaseSeeder` memanggil `DataAwalSeeder`, dan `DataContohSeeder` hanya bila bukan produksi.

## 9. Tes (wajib, di `tests/Feature/` dan `tests/Feature/Keamanan/`)

1. Unit: `NomorHp::normalisasi` dan `tampil` (berbagai format, yang tidak valid ditolak).
2. Model `User`: aturan `sekolah_id` per peran dan syarat nomor HP atau email.
3. Masuk: berhasil dengan nomor HP dan dengan email; salah kata sandi dan akun tidak ada memberi pesan yang sama; akun nonaktif tidak bisa masuk; sesi diregenerasi; 429 setelah 5 percobaan gagal.
4. Daftar akun: berhasil membuat `walisantri`; mengirim `peran=admin`, `sekolah_id`, `aktif`, `wajib_ganti_password` tidak berpengaruh; nomor HP ganda ditolak; persetujuan wajib.
5. `WajibGantiPassword`: akun dengan penanda hanya bisa ke ganti kata sandi dan keluar.
6. **Matriks akses**: untuk `/akun`, `/panitia`, `/admin`, `/super`, `/yayasan`, dan tiap peran (tamu, walisantri, panitia sekolah A, admin sekolah A, panitia sekolah B, super admin, yayasan): hanya peran yang berhak mendapat 200; tamu 302 ke masuk; peran lain 403. Tulis sebagai data provider supaya mudah ditambah di tahap berikutnya.
7. `MilikSekolah`: panitia sekolah A tidak mendapat baris sekolah B pada setiap model yang memakainya; tanpa pengguna (konsol) scope tidak aktif.
8. `riwayat_status` dan `log_aktivitas`: `update` dan `delete` melempar pengecualian.
9. Enkripsi: nilai `anak.nik` yang dibaca lewat `DB::table` bukan teks polos, dan lewat model terbaca benar.
10. Header keamanan ada di respons; halaman setelah masuk `no-store`.
11. CSRF: POST ke `/masuk` tanpa token mendapat 419.
12. Halaman publik (`/`, `/sekolah/{kode}`, `/masuk`, `/daftar-akun`, `/kebijakan-privasi`) tidak memuat kata "kuota", "IAP", "Rp", atau data biaya.
13. XSS: nama pengguna berisi `<script>alert(1)</script>` tampil ter-escape di halaman yang menampilkan nama.
14. Sesi: akun nonaktif di tengah sesi ditolak di permintaan berikutnya; sesi melewati batas waktu mengeluarkan pengguna.
15. Seeder: `DataAwalSeeder` dua kali berurutan tidak menggandakan data; `DataContohSeeder` melempar pengecualian di lingkungan produksi.
16. `php artisan migrate:fresh --seed` bersih di database kosong.

## 10. Kriteria selesai

- Semua butir di bagian 8 `AGENTS.md` terpenuhi.
- Butir keamanan tahap ini terpenuhi dan dites: K-01 (CSP masih report-only), K-02, K-03 (kerangka dan matriks akses), K-06, K-08 (mulai), K-09 (masuk dan daftar akun), K-12, K-14 (kebijakan privasi dan persetujuan), K-15, K-16 butir 1, 3 (kerangka), 4, 5, 7, 8, 10, 12, 14.
- `php artisan route:list` ditempel di laporan dan tiap rute selain halaman publik punya `auth` dan `peran`.
- Layar masuk dan daftar akun dicek di lebar 360 dan 1280 piksel.
- Laporan akhir: ringkasan yang dibuat, hasil `php artisan test`, paket yang ditambahkan beserta alasannya, hal yang menyimpang dari dokumen, dan pertanyaan terbuka.

**Berhenti di sini.** Jangan mulai tahap 2 sebelum diminta.
