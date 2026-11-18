# Panduan Penerapan SPMB

Dokumen ini untuk guru atau pengurus yayasan yang akan memasang dan merawat
sistem SPMB. Tidak perlu bisa menulis kode, tetapi perlu nyaman mengetik
perintah di terminal (SSH) dan mengikuti urutan langkah. Bagian 7 memuat contoh
konfigurasi server untuk teknisi hosting.

> **Penting:** jangan pernah mengunggah berkas `.env` ke grup WhatsApp, surel,
> repositori, atau folder publik. Berkas itu berisi kata sandi basis data dan
> kunci enkripsi.

---

## 1. Persyaratan hosting

| Kebutuhan | Keterangan |
| --- | --- |
| PHP | **8.3 atau lebih baru** |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd`, `intl`, `tokenizer`, `xml`, `ctype`, `json` |
| Basis data | **MySQL 8** (atau MariaDB 10.6+), satu basis data khusus aplikasi |
| HTTPS | Sertifikat SSL aktif. Wajib; cookie sesi hanya dikirim lewat HTTPS |
| Docroot | Harus menunjuk ke folder **`public/`**, bukan akar proyek |
| Akses | SSH + Composer di server, atau bantuan teknisi hosting |
| Cron | Satu entri cron tiap menit untuk penjadwal Laravel |
| Ruang disk | Minimal 5 GB; cadangan butuh ruang ±3× ukuran basis data + berkas |
| Node.js | **Tidak wajib di server.** Aset bisa dibangun di komputer lalu diunggah |

Shared hosting tanpa SSH/Composer **tidak disarankan**. Aplikasi ini butuh
`composer install` dan perintah `php artisan`, jadi pilih VPS atau hosting yang
menyediakan SSH.

---

## 2. Pemasangan pertama

Kerjakan berurutan. Contoh perintah memakai Linux; di Windows hanya untuk
pengembangan, bukan produksi.

### 2.1 Siapkan folder dan docroot

```bash
# Kode di luar folder publik
mkdir -p /var/www/spmb
cd /var/www/spmb

# Docroot diarahkan ke /var/www/spmb/public
```

Pada panel hosting, cari menu **Document Root** dan isi `public`. Jangan
menaruh kode di `public_html` langsung.

### 2.2 Unggah kode

Unggah seluruh proyek **kecuali** `node_modules/`, `vendor/` (dipasang di
server), dan `.env` milik komputer pengembang.

### 2.3 Buat basis data

Buat basis data dan satu pengguna khusus (jangan memakai `root`):

```sql
CREATE DATABASE ppdb_alinsan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ppdb_user'@'localhost' IDENTIFIED BY 'kata-sandi-panjang';
GRANT ALL PRIVILEGES ON ppdb_alinsan.* TO 'ppdb_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2.4 Isi `.env`

```bash
cp .env.production.example .env
nano .env
chmod 600 .env
```

Isi minimal: `APP_URL`, `DB_*`, `MAIL_*`, `CADANGAN_KUNCI`, dan
`TRUSTED_PROXIES` bila di belakang proksi. Setiap variabel dijelaskan lewat
komentar di dalam berkas contoh.

### 2.5 Pasang pustaka PHP

```bash
composer install --no-dev --optimize-autoloader
```

Perintah ini **tidak** memasang paket pengujian. Bila muncul pertanyaan,
jawab `no` untuk memasang paket tambahan.

### 2.6 Buat kunci aplikasi

```bash
php artisan key:generate --force
```

**Salin nilai `APP_KEY` dari `.env`** dan simpan di tempat aman **di luar
server**. Tanpa kunci ini, NIK yang tersimpan terenkripsi tidak bisa dibaca
lagi dan semua sesi menjadi tidak sah.

### 2.7 Siapkan tabel dan data awal

```bash
php artisan migrate --force
php artisan db:seed --class=DataAwalSeeder --force
```

`DataAwalSeeder` mengisi empat sekolah, tahun ajaran 2027/2028, gelombang
kosong, jenis berkas, rincian biaya awal, dan konten profil/visi/misi sekolah.
Jangan memakai `db:seed` tanpa `--class` di produksi karena akan menjalankan
data contoh.

### 2.8 Bangun aset tampilan

Di komputer pengembang (butuh Node.js):

```bash
npm ci
npm run build
```

Unggah folder `public/build/` hasilnya ke server. Folder `public/images/`
(logo dan foto sekolah) juga ikut diunggah.

Setelah foto ditambah atau diganti, jalankan perintah berikut di server agar
halaman publik memakai turunan WebP yang ringan (foto asli bisa 10-35 MB):

```bash
php artisan spmb:optimalkan-gambar
```

Turunan disimpan di `public/images/optimasi/` dan dibuat ulang hanya bila foto
aslinya lebih baru. Gunakan `--paksa` untuk membangun ulang semuanya. Bila
turunan belum ada, halaman tetap memakai foto asli (tidak error).

### 2.9 Tautan berkas publik dan izin folder

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

Pastikan pemilik folder sama dengan pengguna web server (`www-data` pada
Ubuntu). `storage/` **tidak boleh** berada di dalam `public/`.

Tautan itu yang membuat foto konten sekolah (prestasi, galeri, fasilitas) dapat
dibuka di `/storage/konten/...`. Bila foto sudah diunggah tetapi tampil sebagai
gambar rusak di halaman sekolah, periksa tautannya dengan
`php artisan spmb:periksa-produksi` lalu jalankan ulang `php artisan storage:link`.
Di Windows, perintah itu membuat *junction* sehingga tidak perlu hak admin.

Foto sampul kartu sekolah yang diunggah admin lewat **Pengaturan → Foto sampul
beranda** juga disimpan di disk publik (`storage/app/public/sampul/{id}/...`),
jadi tautan yang sama diperlukan. Bila sekolah belum mengunggah foto sendiri,
tampilan memakai foto bawaan di `public/images/sekolah/...` (lihat
`GambarPublik::SAMPUL`).

Foto untuk kebutuhan tampilan sekolah menerima berkas sampai **10 MB**: foto
sampul kartu beranda (`SPMB_SAMPUL_MAKS_KB`) dan gambar konten sekolah seperti
prestasi, galeri, fasilitas, dan ekstrakurikuler (`SPMB_KONTEN_MAKS_KB`), karena
foto kamera HP sering 3–10 MB. Hasil yang disimpan tetap diperkecil ke sisi
terpanjang 1600 px, jadi ruang disk yang terpakai kecil. Berkas pendaftar
(KK, akta, pas foto) tetap dibatasi **2 MB** (`SPMB_BERKAS_MAKS_KB`) karena itu
data pribadi anak, bukan bahan tampilan. Batas PHP pada bagian 7.1 harus lebih
besar dari batas foto tampilan, dan `memory_limit` cukup untuk memuat gambar
besar sesaat sebelum diperkecil.

### 2.10 Buat akun super admin pertama

```bash
php artisan spmb:buat-super-admin
```

Perintah interaktif ini meminta nama, nomor HP, dan kata sandi. Jangan membuat
kata sandi bawaan; buat kata sandi panjang lalu simpan di pengelola kata sandi
yayasan.

### 2.11 Cron: penjadwal dan antrean

Tambahkan ke crontab pengguna web (`crontab -e`):

```cron
# Penjadwal Laravel: cadangan harian, pembersih berkas sementara, token/sesi
* * * * * cd /var/www/spmb && php artisan schedule:run >> /dev/null 2>&1

# Antrean tanpa Supervisor (hosting tanpa Supervisor). Berhenti sendiri
# setelah 50 detik agar tidak menumpuk dengan jadwal menit berikutnya.
* * * * * cd /var/www/spmb && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Bila tersedia Supervisor, jalankan satu pekerja tetap:

```ini
[program:spmb-queue]
command=php /var/www/spmb/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/spmb
autostart=true
autorestart=true
user=www-data
numprocs=1
```

Jadwal yang sudah terdaftar: `spmb:cadangan` (01.30), `spmb:bersihkan-tmp`
(tiap jam), `spmb:bersihkan-token-sesi` (02.15).

### 2.12 Cache konfigurasi dan tampilan

```bash
php artisan optimize
```

Perintah ini menjalankan `config:cache`, `route:cache`, `view:cache`, dan
`event:cache`. **Setiap kali `.env` diubah**, jalankan:

```bash
php artisan optimize:clear
php artisan optimize
```

### 2.13 Pemeriksaan akhir

```bash
php artisan spmb:periksa-produksi
```

Semua baris harus **LULUS**. Bila ada yang GAGAL, perbaiki dulu; jangan
membuka pendaftaran.

Buka `https://alamat-aplikasi/` di peramban. Halaman beranda harus tampil dan
tombol "Daftar" berfungsi. Masuk sebagai super admin, lalu lanjut ke bagian 3.

---

## 3. Yang harus diisi sebelum dibuka

| Data | Diisi oleh | Tempat di aplikasi |
| --- | --- | --- |
| Tanggal buka/tutup tiap gelombang | Admin sekolah | Admin → Gelombang |
| Kuota tiap gelombang (boleh kosong = tanpa batas) | Admin sekolah | Admin → Gelombang |
| Tautan grup WhatsApp (hanya tampil setelah pengumuman) | Admin sekolah | Admin → Gelombang |
| Rekening bank tiap sekolah | Admin sekolah | Admin → Rekening |
| Rincian biaya (IAP, iuran, seragam, formulir) | Admin sekolah | Admin → Biaya |
| Jenis berkas dan status wajib | Admin sekolah | Admin → Berkas |
| Konten sekolah: profil, visi, misi, program, ekskul, prestasi, fasilitas, jam belajar, alur, syarat, kontak | Admin sekolah | Admin → Konten |
| Foto dan logo sekolah | Admin/teknisi | Unggah ke `public/images/sekolah/{kode}/` dan `public/images/logo/` |
| Profil, visi, dan misi **yayasan** | Super admin | Super Admin → Konten (tanpa sekolah) |
| Akun panitia dan admin sekolah | Super admin / admin | Super Admin → Admin, Admin → Panitia |
| Akun yayasan (hanya melihat rekap) | Super admin | Super Admin → Yayasan |
| Tinjauan **Kebijakan Privasi** | Yayasan | Ganti label "Draf, menunggu tinjauan yayasan" setelah disetujui |

Foto dan logo yang belum tersedia akan tampil sebagai kotak abu-abu
"Foto menyusul"/"Logo menyusul". Itu wajar, bukan galat.

Sebelum membuka pendaftaran, lakukan uji coba satu pendaftaran lengkap memakai
akun uji, lalu nonaktifkan akun uji tersebut.

---

## 4. Panduan singkat per peran

### 4.1 Walisantri (orang tua/wali)

1. Buka alamat SPMB, pilih **Daftar**, isi nama, nomor HP, dan kata sandi.
2. Masuk, lalu isi **Data Orang Tua** satu kali (dipakai untuk semua anak).
3. Pilih **Daftar Anak Baru**, lalu ikuti delapan langkah: sekolah, data anak,
   alamat, kesehatan, asal sekolah, berkas, biaya, kirim.
4. Unggah foto/scan berkas (JPG, PNG, atau PDF, maksimal 2 MB per berkas).
5. Setelah terkirim, simpan **nomor peserta** dan cetak bukti pendaftaran.
6. Pantau status di akun. Bila panitia meminta perbaikan, unggah ulang berkas
   lalu kirim perbaikan.
7. Hasil seleksi baru terlihat **setelah admin mengumumkan**. Bila diterima,
   lakukan daftar ulang dan pembayaran sesuai petunjuk.
8. Lupa kata sandi: hubungi panitia sekolah untuk kata sandi sementara, lalu
   ganti saat masuk berikutnya.

### 4.2 Panitia SPMB

1. Masuk ke menu **Panitia**. Dasbor menampilkan jumlah pendaftar sekolah Anda.
2. Buka **Pendaftar** untuk mencari nama/nomor peserta, memeriksa data, dan
   menandai berkas sesuai atau perlu perbaikan.
3. Verifikasi pendaftar yang berkas wajibnya sudah lengkap. Bila ada yang
   kurang, minta perbaikan lewat catatan (tampil di akun walisantri).
4. Catat pembayaran tunai atau setujui/tolak bukti transfer.
5. Catat daftar ulang dan hasil wawancara (hanya terlihat panitia/admin).
6. Tandai murid yang mengundurkan diri bila ada.
7. Unduh ZIP berkas atau ekspor Excel hanya bila benar-benar perlu, dan simpan
   di komputer sekolah yang aman. Jangan meneruskan lewat WhatsApp pribadi.
8. Membuat akun walisantri untuk pendaftar offline (datang ke sekolah) lewat
   menu **Offline**, lalu isi data atas nama mereka.

### 4.3 Admin sekolah

Admin dapat melakukan semua tugas panitia, ditambah:

1. **Gelombang**: atur tanggal buka/tutup, kuota, dan tautan grup WhatsApp.
2. **Berkas, Biaya, Rekening**: atur syarat sekolah dan biaya pendidikan.
3. **Konten**: isi profil, visi/misi, program, galeri, dan prestasi sekolah.
4. **Panitia**: buat, nonaktifkan, dan atur ulang kata sandi akun panitia.
5. **Umumkan Hasil**: tombol ini membuka hasil seleksi untuk walisantri pada
   satu gelombang. Pastikan semua keputusan sudah final sebelum menekan.
   Setelah diumumkan, perubahan hasil hanya bisa dilakukan admin dengan
   catatan alasan.
6. **Rekap** dan **Log**: pantau angka agregat dan riwayat tindakan.

### 4.4 Super admin

1. **Sekolah**: ubah nama, alamat, kontak, Instagram, dan mode penerimaan.
2. **Admin**: buat akun admin sekolah dan atur ulang kata sandinya.
3. **Yayasan**: buat akun yayasan (hanya melihat rekap).
4. **Konten**: isi profil, visi, dan misi yayasan untuk beranda publik.
5. **Log**: melihat riwayat tindakan lintas sekolah.
6. Super admin **tidak** dapat membuka data pendaftar per anak. Itu memang
   dipisahkan.

### 4.5 Yayasan

1. Masuk ke menu **Yayasan**.
2. Lihat rekap agregat tiap sekolah dan unduh rekap Excel bila perlu.
3. Halaman ini hanya menampilkan angka, bukan nama atau data pribadi anak.
4. Untuk tindakan lain (mengubah data, mengumumkan hasil), hubungi admin
   sekolah atau super admin.

---

## 5. Prosedur harian dan darurat

### 5.1 Cadangan harian

Sistem membuat cadangan otomatis setiap hari pukul 01.30 ke
`storage/app/cadangan/` dalam bentuk arsip **terenkripsi**. Retensi: 14 cadangan
harian terakhir dan satu cadangan per bulan selama 6 bulan.

Periksa sekali seminggu:

```bash
php artisan spmb:cadangan     # membuat cadangan manual (opsional)
ls -lh storage/app/cadangan   # pastikan ada berkas .enc terbaru
```

**Salin arsip cadangan ke luar server** (Google Drive yayasan, hard disk
terpisah, atau penyimpanan awan) minimal sekali seminggu. Cadangan yang hanya
ada di server yang sama tidak menolong bila server rusak atau diserang.

### 5.2 Menguji pemulihan (sekali sebelum dibuka, lalu tiap 6 bulan)

Di server uji dengan basis data **kosong**:

```bash
php artisan spmb:pulihkan nama-arsip.enc
```

Perintah meminta konfirmasi. Pada basis data yang sudah berisi, perintah
menolak kecuali memakai `--force`; gunakan `--force` hanya bila Anda yakin dan
sudah membuat cadangan terbaru.

Setelah pemulihan, periksa:

1. Bisa masuk sebagai super admin.
2. Data pendaftar dan NIK terbaca (berarti `APP_KEY` benar).
3. Berkas privat (scan KK, akta, bukti transfer) dapat dibuka dari panel.
4. Halaman publik dan panel tampil normal.

### 5.3 Lupa kata sandi

| Peran | Cara |
| --- | --- |
| Walisantri | Panitia membuka data pendaftar → **Atur ulang kata sandi**. Kata sandi sementara wajib diganti saat masuk. Bila SMTP aktif, walisantri juga bisa memakai tautan **Lupa kata sandi** |
| Panitia/admin | Admin sekolah atau super admin mengatur ulang dari panel akun |
| Admin/super admin/yayasan | Dari server: `php artisan spmb:atur-ulang-password 08xxxxxxxxxx` |

Tidak ada OTP WhatsApp. Jangan pernah mengirim kata sandi lewat WhatsApp grup.

### 5.4 Menonaktifkan akun yang bocor

1. Admin sekolah: Admin → Panitia → **Nonaktifkan**. Super admin: Super
   Admin → Admin/Yayasan → **Nonaktifkan**.
2. Untuk walisantri: hubungi panitia; akun dapat dinonaktifkan dari panel.
3. Setelah dinonaktifkan, pengguna tidak bisa masuk walaupun kata sandinya
   benar. Sesi yang sedang berjalan juga ditolak pada permintaan berikutnya.
4. Buat kata sandi baru hanya setelah situasi aman.

### 5.5 Bila dicurigai ada kebocoran data

1. **Jangan menghapus apa pun.** Hentikan sementara akses dengan
   `php artisan down` bila perlu.
2. Simpan salinan log: `storage/logs/laravel-*.log`, lalu periksa
   **Admin → Log** / **Super Admin → Log** untuk tindakan yang tidak wajar.
3. Nonaktifkan akun yang dicurigai (bagian 5.4).
4. Buat cadangan segera: `php artisan spmb:cadangan`.
5. Ganti kata sandi basis data, `CADANGAN_KUNCI`, dan `APP_KEY` **hanya setelah**
   berkonsultasi dengan teknisi; mengganti `APP_KEY` membuat NIK lama tidak
   terbaca.
6. Laporkan ke yayasan. Bila data pribadi anak benar-benar bocor, ikuti
   ketentuan perlindungan data pribadi yang berlaku dan beri tahu orang tua
   yang terdampak.
7. Setelah pulih, catat kronologi singkat untuk evaluasi.

---

## 6. Siapa memegang apa

Kosongkan nama dan isi bersama yayasan. Jangan menulis kata sandi di dokumen
ini; simpan di pengelola kata sandi.

| Aset | Pemegang | Catatan |
| --- | --- | --- |
| Akun super admin utama | ________________ | Minimal dua orang, satu cadangan |
| `APP_KEY` (dari `.env`) | ________________ | Simpan di luar server; tanpa ini NIK tidak terbaca |
| `CADANGAN_KUNCI` | ________________ | Simpan terpisah dari server **dan** dari arsip cadangan |
| Akses hosting/SSH | ________________ | Dibatasi, dicabut saat petugas berganti |
| Akses basis data | ________________ | Pengguna khusus, bukan `root` |
| Salinan cadangan luar server | ________________ | Diperiksa tiap bulan |
| Akun email/SMTP pengirim | ________________ | Untuk reset kata sandi mandiri |
| Tinjauan kebijakan privasi | ________________ | Yayasan |

Aturan penting:

- `APP_KEY` dan `CADANGAN_KUNCI` **tidak boleh** disimpan di server yang sama
  dengan arsip cadangan. Bila server hilang, keduanya harus masih bisa
  diambil dari tempat terpisah.
- Bila `CADANGAN_KUNCI` hilang, arsip cadangan **tidak dapat dipulihkan**.
- Bila `APP_KEY` hilang, data NIK pada basis data lama **tidak dapat dibaca**.

---

## 7. Konfigurasi server web (untuk teknisi)

### 7.1 php.ini

```ini
upload_max_filesize = 14M
post_max_size = 20M
memory_limit = 512M
max_execution_time = 120
display_errors = Off
log_errors = On
expose_php = Off
```

Batas unggah aplikasi 2 MB per berkas pendaftar (`SPMB_BERKAS_MAKS_KB`),
sedangkan foto kebutuhan tampilan sekolah — foto sampul kartu beranda
(`SPMB_SAMPUL_MAKS_KB`) dan gambar konten sekolah (`SPMB_KONTEN_MAKS_KB`) —
10 MB. Batas PHP dibuat lebih besar dari batas foto tampilan agar pesan galat
tetap ramah dan bukan galat 413 dari server. `memory_limit` 512 MB memberi
ruang untuk memuat gambar besar sekali saat diperkecil; aplikasi menolak gambar
di atas 40 megapiksel sebelum dimuat.

### 7.2 Apache (virtual host)

```apache
<VirtualHost *:443>
    ServerName spmb.contoh.sch.id
    DocumentRoot /var/www/spmb/public

    <Directory /var/www/spmb/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    # Jangan pernah menyajikan folder aplikasi walau docroot salah.
    <DirectoryMatch "^/var/www/spmb/(storage|vendor|bootstrap|database|app|config|routes|tests)">
        Require all denied
    </DirectoryMatch>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/spmb.contoh.sch.id/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/spmb.contoh.sch.id/privkey.pem

    ErrorLog  ${APACHE_LOG_DIR}/spmb-error.log
    CustomLog ${APACHE_LOG_DIR}/spmb-access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName spmb.contoh.sch.id
    Redirect permanent / https://spmb.contoh.sch.id/
</VirtualHost>
```

Berkas `public/.htaccess` sudah menolak `.env`, `composer.*`, `.git`, `.sql`,
`.log`, `artisan`, dan mematikan daftar isi folder.

### 7.3 Nginx

```nginx
server {
    listen 80;
    server_name spmb.contoh.sch.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name spmb.contoh.sch.id;
    root /var/www/spmb/public;

    ssl_certificate     /etc/letsencrypt/live/spmb.contoh.sch.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/spmb.contoh.sch.id/privkey.pem;

    index index.php;
    charset utf-8;

    # Blokir berkas sensitif dan folder aplikasi.
    location ~ /\.(?!well-known) { deny all; }
    location ~* \.(env|git|sql|log|sqlite|ini|bak)$ { deny all; }
    location ^~ /storage/ { deny all; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.ht { deny all; }
}
```

Catatan: `location ^~ /storage/ { deny all; }` benar untuk aplikasi ini karena
berkas pendaftar **tidak** disajikan lewat `/storage/`. Logo/foto publik berada
di `/images/`. Bila Anda menambahkan `storage:link` untuk konten publik,
izinkan hanya `/storage/konten/` dan tetap tolak `/storage/berkas/`.

### 7.4 Yang tidak boleh ada di produksi

- Rute uji seperti `/test`, `/phpinfo`, atau Laravel Telescope.
- `APP_DEBUG=true`, debugbar, atau `dd()`/`dump()`.
- Akun contoh dari `DataContohSeeder`.
- Folder `node_modules/` di server.
- Berkas `.env` di dalam `public/`.
- `storage:link` yang menunjuk ke `storage/app/private`.

---

## 8. Perawatan rutin

| Kapan | Kegiatan |
| --- | --- |
| Tiap hari | Pastikan situs terbuka; cek log bila ada laporan galat |
| Tiap minggu | Salin cadangan ke luar server; periksa ruang disk |
| Tiap bulan | Periksa daftar akun aktif; cabut akun yang tidak dipakai |
| Tiap 6 bulan | Uji pemulihan cadangan di server uji; perbarui kata sandi petugas |
| Saat ada pembaruan kode | `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan optimize`, lalu `php artisan spmb:periksa-produksi` |

Bila aplikasi menampilkan "Terjadi Kesalahan" (500), jalankan:

```bash
tail -n 100 storage/logs/laravel-$(date +%F).log
```

Jangan mengaktifkan `APP_DEBUG=true` di produksi untuk mencari penyebab; itu
menampilkan jejak kode dan data ke pengunjung.
