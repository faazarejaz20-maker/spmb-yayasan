# Tahap 5 — Ekspor, unduh berkas (ZIP), dan cetak

Prasyarat: tahap 1 sampai 4 selesai dan `php artisan test` lulus. Kerjakan hanya tahap ini. Jangan membangun halaman publik atau konfigurasi produksi (tahap 6).

Tahap ini menambahkan tombol yang sengaja ditunda di tahap 2 sampai 4:
- **Ekspor Excel dan CSV** (panitia, admin, dan rekap yayasan).
- **Unduh Berkas (ZIP)** per pendaftar.
- **Cetak**: formulir pendaftaran, bukti pendaftaran (walisantri dan panitia), kuitansi pembayaran, dan rekap.

Ini tahap yang paling berisiko membocorkan data massal, jadi keamanannya (K-20) adalah inti pekerjaannya.

## 0. Sebelum menulis kode

1. Baca ulang `AGENTS.md`, `docs/aturan-sistem.md`, `docs/keamanan.md` (K-03, K-05, K-06, K-07, K-08, K-20), `docs/desain.md` (bagian cetak), dan laporan tahap 1 sampai 4.
2. Tulis rencana singkat (daftar file), lalu langsung kerjakan.

## 1. Pilihan teknis (ikuti ini)

- **Excel dan CSV:** paket `openspout/openspout` (streaming, hemat memori, tanpa ekstensi aneh). Tambahkan lewat Composer, jalankan `composer audit` setelahnya dan laporkan hasilnya.
- **ZIP:** ekstensi bawaan PHP `ZipArchive`. Periksa ekstensi `zip` aktif; bila tidak, hentikan dan laporkan.
- **Cetak:** halaman HTML khusus cetak (Blade, CSS `@media print`, ukuran A4) yang dibuka di tab baru dan dicetak lewat tombol Cetak browser (pengguna dapat memilih "Simpan sebagai PDF"). **Jangan menambah pustaka PDF** (dompdf dan sejenisnya) dan jangan membuat PDF di server; ini mengurangi permukaan serangan dan ketergantungan. Skrip cetak (`window.print()`) dipasang lewat Alpine (CSP), bukan atribut `onclick`.
- Semua file yang dihasilkan **tidak disimpan permanen**. Ekspor dialirkan langsung ke pengguna; ZIP dibuat di folder sementara privat lalu dihapus.

## 2. Prinsip yang wajib dipatuhi

- **Cakupan data:** ekspor dan ZIP selalu dibatasi pada **sekolah pengguna** lewat global scope `MilikSekolah` dan Policy. `sekolah_id` tidak pernah diterima dari input. Peran `super_admin` **tidak punya** rute ekspor data pendaftar. Yayasan hanya mendapat ekspor **agregat** (bagian 5).
- **Hanya pendaftaran terkirim** (scope `terkirim()`); draf tidak ikut.
- **Setiap unduhan dicatat di `log_aktivitas`**: siapa, kapan, jenis, sekolah, saring yang dipakai, **jumlah baris**, dan **daftar kolom**. Isi data tidak dicatat.
- **Batasan laju:** ikuti K-09: ekspor 5 per menit per pengguna, ZIP 5 per menit per pengguna, unduh berkas 60 per menit; cetak 120 per jam per pengguna. Lebih dari itu → 429.
- **Ekspor besar:** dibatasi maksimum 5.000 baris per unduhan (lebih dari itu → pesan "Persempit dengan penyaring"). Gunakan `lazy()`/`cursor()` agar memori tidak membengkak.
- Header respons unduhan: `Content-Disposition: attachment; filename="..."`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store, private`. Nama file dibuat dari data terkontrol (`spmb-{kode-sekolah}-{gelombang-slug}-{tanggal}.xlsx`), **bukan** dari input pengguna.

## 3. Ekspor Excel dan CSV (panitia dan admin)

Rute (awalan `/panitia`, middleware `auth`, `peran:panitia,admin`):

| Metode dan jalur | Fungsi |
| --- | --- |
| `GET /panitia/ekspor` | Halaman pilihan kolom, penyaring, dan format |
| `POST /panitia/ekspor` | Menghasilkan unduhan (POST agar parameter tidak tersimpan di riwayat atau log server) |

- **Penyaring:** gelombang, status hasil, status murid, Baru atau Lulusan Yayasan, program (TKI), status pembayaran. Sama dengan penyaring di daftar pendaftar, divalidasi dengan daftar putih.
- **Kolom:** pilihan lewat kotak centang dari **daftar putih** terkontrol (`config/ekspor.php`), dibagi jadi: Identitas (nomor peserta, nama, jenis kelamin, tempat dan tanggal lahir, asal sekolah, jenis, program, gelombang), Kontak orang tua (nama wali, nomor HP, alamat), Status (status hasil, status murid, tanggal kirim), Berkas (jumlah sesuai dari total), Pembayaran (status, total disetujui), Daftar ulang (hadir, selesai).
  - **Kolom sensitif** (NIK, no. KK, catatan kesehatan) **tidak ikut bawaan** dan hanya tersedia bagi `admin` (bukan `panitia`), dengan centang konfirmasi "Saya paham file ini memuat data pribadi sensitif dan akan menjaganya". NIK dan no. KK diekspor sebagai **teks** (agar tidak menjadi notasi ilmiah).
  - **Tidak pernah diekspor:** `catatan_wawancara`, `catatan_panitia`, `kekurangan`, kata sandi, token, jalur berkas di disk.
- **Format:** `.xlsx` (bawaan) atau `.csv` (UTF-8 dengan BOM, pemisah koma, agar terbuka benar di Excel).
- **Perlindungan formula injection (wajib):** setiap sel teks yang diawali `=`, `+`, `-`, `@`, tab, atau carriage return diberi awalan `'` (atau dipaksa bertipe string) sebelum ditulis. Terapkan di satu fungsi (`EksporSanitizer`) yang dipakai semua ekspor, dan tes dengan nama seperti `=HYPERLINK("http://evil","klik")` dan `+cmd|' /C calc'!A0`.
- Lembar kedua "Ringkasan" memuat tanggal ekspor, pelaku, penyaring yang dipakai, dan jumlah baris (tanpa data pribadi).
- Tombol "Unduh Excel" ditambahkan di daftar pendaftar (tahap 3) dan membawa penyaring yang sedang aktif ke halaman ekspor.

## 4. Unduh Berkas (ZIP)

Rute: `GET /panitia/pendaftar/{pendaftaran}/berkas.zip` (nama `panitia.pendaftar.zip`), hanya `panitia` dan `admin` sekolah itu; sekolah lain → 404.

- Proses: kumpulkan semua berkas pendaftar itu (yang statusnya apa pun), buat ZIP di **folder sementara privat** (`storage/app/private/tmp/` dengan nama acak), alirkan ke pengguna (`response()->download(...)->deleteFileAfterSend(true)`), dan **hapus segera, termasuk bila gagal** (blok `try/finally`). Tambahkan perintah terjadwal `spmb:bersihkan-tmp` yang menghapus file di folder itu yang lebih tua dari 1 jam (didaftarkan ke scheduler).
- **Nama di dalam ZIP** dibuat dari data terkontrol: `{nomor_peserta}_{kode_jenis_berkas}_{urutan}.{ekstensi}` dengan ekstensi dari MIME hasil `finfo` yang tersimpan (daftar putih `jpg`, `png`, `pdf`), **bukan** dari nama unggahan asli. Tidak boleh ada `..`, `/`, atau `\` pada nama entri (tes zip-slip).
- Berkas dibaca lewat disk privat dengan `Storage`, **bukan** dengan jalur yang disusun dari input pengguna. Berkas yang hilang dari disk dilewati dan dicatat; jangan membuat galat 500.
- Batas ukuran: gagalkan bila total > 40 MB dengan pesan jelas.
- Tombol "Unduh Berkas (ZIP)" ditambahkan di kepala halaman detail pendaftar (tahap 3). Log: siapa, pendaftaran, jumlah berkas.

## 5. Rekap agregat (admin dan yayasan)

- **Admin:** tombol "Unduh Rekap (Excel)" di `/admin/rekap` (tahap 4). Isi persis angka agregat yang tampil di halaman (tanpa nama orang).
- **Yayasan:** tombol "Unduh Rekap (Excel)" di `/yayasan/rekap`, empat sekolah, agregat saja. Rute `GET /yayasan/rekap.xlsx`; middleware `peran:yayasan`. Tidak ada ekspor per orang bagi yayasan (K-18).
- Pakai `EksporSanitizer` yang sama dan catat di log.

## 6. Cetak

Semua halaman cetak: Blade `layouts/cetak` (A4, margin 15 mm, tanpa sidebar dan menu, kop sekolah dengan logo bila ada, tanggal cetak, huruf yang terbaca), diperiksa Policy, `Cache-Control: no-store`, dan dibuka di tab baru (`target="_blank" rel="noopener"`). **Tidak ada tautan publik dan tidak ada token di URL**; semua melalui sesi login dan `uuid`.

| Halaman | Rute | Siapa | Isi |
| --- | --- | --- | --- |
| **Formulir pendaftaran** | `GET /panitia/pendaftar/{pendaftaran}/cetak/formulir` | panitia, admin | Seluruh data anak dan orang tua, isian tahap 1 (dan tahap 2 bila ada), pilihan IAP, daftar berkas dan statusnya, kolom tanda tangan wali dan panitia. NIK utuh (halaman ini untuk arsip sekolah). Tanpa `catatan_panitia` dan `catatan_wawancara`. |
| **Bukti pendaftaran** | `GET /akun/anak/{anak}/pendaftaran/{pendaftaran}/cetak/bukti` dan `GET /panitia/pendaftar/{pendaftaran}/cetak/bukti` | walisantri pemilik (setelah terkirim), panitia, admin | Nama sekolah, nomor peserta, nama anak, gelombang, tanggal kirim, daftar berkas yang diunggah, status **tampilan walisantri** dari `StatusTampilService` (jadi **tidak membocorkan hasil sebelum pengumuman**), kode QR opsional **dilewati** (tidak dibuat). Catatan "Simpan bukti ini; bawa saat daftar ulang". NIK disamarkan (empat digit terakhir saja). |
| **Kuitansi pembayaran** | `GET /panitia/pembayaran/{pembayaran}/cetak/kuitansi` | panitia, admin; dan `GET /akun/.../pembayaran/{pembayaran}/cetak/kuitansi` untuk walisantri | Hanya untuk pembayaran `disetujui`. Nomor kuitansi turunan terkontrol (`KWT-{kode}-{tahun}-{id 5 digit}`), nama anak, nomor peserta, komponen, nominal, metode, tanggal, nama penerima (panitia). Tanda "Bukan bukti lunas SPP". |
| **Kartu akun walisantri** | dari tahap 3 dan 4 | — | Sudah ada; pastikan memakai `layouts/cetak`. |

- Tombol "Cetak Bukti Pendaftaran" muncul di halaman detail walisantri (tahap 2) **hanya** untuk pendaftaran yang sudah terkirim. Tombol "Cetak Formulir" dan "Cetak Kuitansi" muncul di panel panitia.
- Kuitansi walisantri hanya untuk pembayaran miliknya; milik anak lain atau akun lain → 404.
- Semua nilai di-escape. Nama dengan karakter khusus atau `<script>` tidak boleh merusak halaman.

## 7. Keamanan (K-20) — ringkasan yang harus teruji

1. Tidak ada file ekspor atau ZIP yang tertinggal di disk setelah permintaan selesai atau gagal.
2. Isi ZIP tidak mengandung jalur berbahaya dan nama diturunkan dari data terkontrol.
3. Ekspor dibatasi sekolah pengguna; super admin tidak punya rute ekspor pendaftar; yayasan hanya agregat.
4. Formula injection dinetralkan di semua sel teks (xlsx dan csv).
5. Kolom sensitif hanya untuk admin dengan konfirmasi; kolom terlarang tidak bisa dipilih meskipun dikirim manual (daftar putih).
6. Setiap unduhan tercatat di log (jumlah baris dan kolom).
7. Batas laju dan batas ukuran berlaku.
8. Cetak lewat Policy; hasil seleksi tidak bocor di bukti pendaftaran sebelum pengumuman.

## 8. Tes (wajib)

Tambahkan ke `tests/Feature/` dan `tests/Feature/Keamanan/`:

1. **Matriks akses** rute ekspor, ZIP, dan cetak per peran (tamu 302; walisantri hanya cetak miliknya; super admin 403 untuk ekspor pendaftar; yayasan hanya rekap; panitia dan admin sekolah sendiri 200).
2. **Isolasi sekolah:** panitia dan admin sekolah A tidak bisa mengekspor data, mengunduh ZIP, atau mencetak milik sekolah B → hasil ekspor tidak memuat baris sekolah B; ZIP dan cetak sekolah B → 404.
3. **Ekspor:** hanya pendaftaran terkirim; draf tidak ikut; penyaring bekerja; kolom di luar daftar putih diabaikan atau ditolak; kolom sensitif ditolak untuk `panitia` dan tersedia untuk `admin` hanya dengan konfirmasi; `catatan_wawancara`, `catatan_panitia`, `kekurangan` tidak pernah ada di file; NIK sebagai teks.
4. **Formula injection:** sel berawalan `=`, `+`, `-`, `@`, tab, CR dinetralkan di xlsx dan csv (baca ulang file hasil dan periksa).
5. **Batas:** lebih dari 5.000 baris ditolak dengan pesan; laju ekspor dan ZIP memberi 429.
6. **ZIP:** isi sesuai berkas pendaftar itu saja; nama entri dari data terkontrol; pendaftar dengan nama berkas unggahan asli `../../x.php` tidak menghasilkan entri berbahaya; folder sementara kosong setelah unduhan dan setelah kegagalan yang disimulasikan; berkas hilang dari disk tidak menyebabkan 500; total > 40 MB ditolak.
7. **Cetak bukti:** sebelum pengumuman, halaman bukti tidak memuat kata Diterima, Ditolak, atau Cadangan; NIK disamarkan; walisantri lain 404; draf tidak punya tombol atau rute cetak bukti (404 atau 403).
8. **Cetak formulir:** tidak memuat `catatan_panitia` dan `catatan_wawancara`; XSS ter-escape.
9. **Kuitansi:** hanya untuk pembayaran `disetujui`; nomor kuitansi terkontrol dan unik; milik orang lain 404.
10. **Log:** setiap ekspor, ZIP, dan cetak menulis `log_aktivitas` dengan jumlah baris dan kolom, tanpa isi data.
11. **Rekap:** unduhan admin dan yayasan hanya memuat angka, tanpa nama atau nomor peserta; yayasan tidak bisa mengunduh ekspor per orang.
12. **Perintah `spmb:bersihkan-tmp`:** menghapus file lama dan menyisakan yang baru.
13. **Regresi:** seluruh tes tahap 1 sampai 4 tetap lulus.

## 9. Kriteria selesai

- Semua butir `AGENTS.md` bagian 8 terpenuhi.
- Keamanan: K-20 terpenuhi dan teruji; K-03 (matriks diperluas), K-07, K-08 terpenuhi.
- `composer audit` bersih (atau temuan dilaporkan); tidak ada pustaka PDF baru.
- Dapat dicoba manual: unduh Excel dengan penyaring, buka di Excel atau LibreOffice (nama berisi `=` aman), unduh ZIP berkas, cetak formulir, bukti, dan kuitansi, lalu simpan sebagai PDF lewat dialog cetak browser. Tuliskan langkahnya di laporan.
- Laporan akhir sesuai `AGENTS.md`.

**Berhenti di sini.** Jangan mulai tahap 6 sebelum diminta.
