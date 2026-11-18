# Laporan Tahap 1 — Dasar

Tanggal: 11 November 2026
Lingkup: database, akun, peran, masuk, dan kendali akses (`prompts/tahap-1-dasar.md`).

## 1. Yang selesai

- **Lingkungan**: Laravel 13.40.0, PHP 8.4.27, MySQL 8, zona waktu `Asia/Jakarta`, bahasa `id`.
- **Konfigurasi**: `.env`/`.env.example`, `config/app.php`, `config/isian.php`, `config/filesystems.php`.
- **Migrasi 20 tabel** sesuai `docs/struktur-tabel.md` (nama tabel & kolom persis).
- **Enum (12)**, **Model (20)** dengan `protected $table` eksplisit, trait `MilikSekolah` dan `PunyaUuid`, **Factory (7)**.
- **Kelas bantu**: `App\Support\NomorHp`, `App\Support\Catat`.
- **Middleware**: `PeranMiddleware`, `WajibGantiPassword`, `AkunAktif`, `SesiKedaluwarsa`, `HeaderKeamanan`.
- **Controller + FormRequest + Rute** untuk autentikasi, halaman publik, dan beranda tiap peran.
- **Blade**: 3 layout, 12 komponen, halaman publik, halaman rangka per peran, halaman galat 403/404/419/429/500.
- **Aset**: Tailwind + Vite, `@fontsource-variable/plus-jakarta-sans`, Alpine.js mode CSP (`npm run build` berhasil).
- **Seeder**: `DataAwalSeeder` (4 sekolah, tahun ajaran 2027/2028, gelombang, jenis berkas, pengaturan isian, biaya, konten) dan `DataContohSeeder` (akun demo, hanya non-produksi).
- **Perintah**: `php artisan spmb:buat-super-admin`.
- **Tes**: 68 tes lulus (113 assertion), termasuk matriks akses dan tes keamanan.

## 2. Tes yang dijalankan

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **68 passed (113 assertions)**, 0 gagal |
| `php artisan migrate:fresh --seed` | bersih di database kosong |
| `npm run build` | berhasil, `public/build/manifest.json` dibuat |
| `php artisan route:list --except-vendor` | 16 rute (lihat bagian 3) |

Cakupan tes:
- `Unit/NomorHpTest`, `Unit/UserTest` — normalisasi nomor HP, aturan peran/sekolah/kontak.
- `Feature/AutentikasiTest` — masuk dengan HP dan email, pesan galat seragam, akun nonaktif, regenerasi sesi, batas 429, tujuan per peran.
- `Feature/DaftarAkunTest` — pembuatan akun walisantri, input `peran`/`sekolah_id`/`aktif` berbahaya diabaikan, nomor ganda ditolak, persetujuan data wajib.
- `Feature/GantiPasswordTest` — penanda wajib ganti, kata sandi lama, larangan kata sandi = nomor HP.
- `Feature/MatriksAksesTest` — 22 kombinasi peran × awalan URL.
- `Feature/Keamanan/KeamananTest` — CSRF 419, riwayat/log tidak bisa diubah-dihapus, NIK terenkripsi, header keamanan, `no-store` halaman setelah masuk, halaman publik tanpa biaya/kuota, XSS ter-escape, akun dinonaktifkan di tengah sesi.
- `Feature/Keamanan/IsolasiSekolahTest` — panitia/admin hanya melihat baris sekolahnya (langsung dan lewat relasi `pendaftaran`), scope aktif/nonaktif sesuai konteks.

## 3. Daftar rute

```
GET|HEAD  /                     beranda           HalamanController@beranda
GET|HEAD  sekolah/{kode}        sekolah           HalamanController@sekolah
GET|HEAD  kebijakan-privasi     kebijakan-privasi HalamanController@kebijakanPrivasi
GET|HEAD  masuk                 masuk             AutentikasiController@formMasuk        guest
POST      masuk                 masuk.simpan      AutentikasiController@masuk            guest
GET|HEAD  daftar-akun           daftar-akun       AutentikasiController@formDaftar       guest
POST      daftar-akun           daftar-akun.simpan AutentikasiController@daftar          guest
GET|HEAD  lupa-password         lupa-password     AutentikasiController@lupaPassword     guest
POST      keluar                keluar            AutentikasiController@keluar           auth
GET|HEAD  ganti-password        ganti-password    AutentikasiController@formGantiPassword auth
POST      ganti-password        ganti-password.simpan AutentikasiController@gantiPassword auth
GET|HEAD  akun                  akun.beranda      WalisantriController@beranda           auth, peran:walisantri
GET|HEAD  panitia               panitia.beranda   PanitiaController@beranda              auth, peran:panitia,admin
GET|HEAD  admin                 admin.beranda     AdminController@beranda                auth, peran:admin
GET|HEAD  super                 super.beranda     SuperAdminController@beranda           auth, peran:super_admin
GET|HEAD  yayasan               yayasan.beranda   YayasanController@beranda              auth, peran:yayasan
```

Middleware tambahan pada grup `web`: `HeaderKeamanan`, `AkunAktif`, `SesiKedaluwarsa`, `WajibGantiPassword`.
Tamu diarahkan ke `masuk` (bukan `login`).

## 4. Penyimpangan dari dokumen dan alasannya

1. **`users` tanpa `email_verified_at` dan `remember_token`** (sesuai `docs/struktur-tabel.md`).
   - `User::getRememberTokenName()` mengembalikan `null` sehingga fitur "ingat saya" tidak dipakai dan `logout()` tidak menulis kolom yang tidak ada.
2. **`config/filesystems.php` disk `local`: `serve => false`.**
   - Bawaan Laravel mendaftarkan rute `/storage/{path}` yang menyajikan berkas tanpa otorisasi di non-produksi. Karena sistem menyimpan berkas anak, rute itu dimatikan; unduhan nanti harus lewat controller + policy.
3. **`User::$attributes` berisi nilai bawaan** (`aktif`, `wajib_ganti_password`, `email`, `no_hp`, `sekolah_id`, `terakhir_masuk`).
   - Mode ketat Laravel (`preventAccessingMissingAttributes`) melempar galat bila atribut tidak ada di memori; nilai bawaan ini menyamakan model dengan nilai kolom di database.
4. **`peran`, `sekolah_id`, `aktif`, `wajib_ganti_password` tidak masuk `$fillable`; ditulis lewat `forceCreate()`/`forceFill()`** di controller dan perintah yang terkontrol. Ini yang diminta aturan keamanan.
5. **`laravel-lang/lang` dipakai** untuk terjemahan `id` (termasuk dalam daftar paket yang diizinkan).
6. **Rute `login` tidak ada**; nama yang dipakai `masuk`. Redirect tamu dan pengguna yang sudah masuk dikonfigurasi di `bootstrap/app.php`.
7. **Admin masuk ke `/panitia` lebih dulu** karena admin mewarisi kemampuan panitia; `/admin` tetap tersedia terpisah.

## 5. Bug yang ditemukan dan diperbaiki selama tes

- **Kebocoran data antarsekolah**: `MilikSekolah` membandingkan enum `peran` dengan string sehingga scope tidak pernah aktif untuk admin/panitia. Diperbaiki (`->value`) dan dikunci dengan `IsolasiSekolahTest`.
- **Kolom sensitif hilang diam-diam**: `User::create()` dengan `peran` di luar `$fillable` membuang kolom itu di produksi. Diganti `forceCreate()` di controller pendaftaran dan perintah super admin.
- **Aset belum dibangun**: semua halaman 500 karena `Vite manifest not found`. Diperbaiki dengan `npm run build`.
- **Redirect login memakai nama rute, bukan URL**: `redirect()->intended('akun.beranda')`. Diperbaiki dengan `route(...)`.

## 6. Pertanyaan yang masih terbuka

1. **Kebijakan Privasi** masih berupa draf dan perlu ditinjau yayasan (teks di `resources/views/publik/kebijakan-privasi.blade.php`).
2. **Kontak WhatsApp tiap sekolah** di seeder memakai nomor yang ada di dokumen perancangan; mohon dikonfirmasi bila berubah.
3. **Ambang sesi** staf 120 menit dan walisantri 480 menit — sudah diterapkan; perlu konfirmasi bila ingin berbeda.
4. **Warna dan logo sekolah** belum final; logo tampil sebagai kotak "Logo menyusul" sesuai aturan.
5. **Pemeriksaan tampilan visual** pada lebar 360 px dan 1280 px perlu dilakukan manual di browser (layout sudah responsif dan memakai `max-w`, tetapi belum diuji dengan tangkapan layar).
