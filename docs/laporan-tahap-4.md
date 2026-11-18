# Laporan Tahap 4 — Admin sekolah, super admin, dan yayasan

Tanggal: 11 November 2026
Lingkup: tiga panel `/admin` (peran `admin`), `/super` (peran `super_admin`), `/yayasan` (peran `yayasan`), serta **Kelola Konten** (`prompts/tahap-4-admin-super-admin.md`). Ekspor Excel/ZIP dan cetak ditunda ke tahap 5; halaman publik dan konfigurasi produksi ditunda ke tahap 6. Penyimpanan konten sudah selesai di tahap ini sesuai prompt.

## 1. Yang selesai

### Panel admin sekolah (`/admin`)

- **Isolasi**: middleware `auth`, `peran:admin`; seluruh query dibatasi `sekolah_id` akun lewat global scope `MilikSekolah`; `sekolah_id` **tidak pernah** diterima dari input (dipaksa dari `$request->user()`; dua tes mass assignment membuktikan nilai formulir diabaikan). Data sekolah lain → **404**. Admin mewarisi seluruh rute panitia (tahap 3) dan kedua kelompok menu tampil dalam satu sidebar.
- **Gelombang & kuota** (`GelombangController` + `GelombangService`): daftar, tambah, ubah: nama, `tanggal_buka`, `tanggal_tutup`, `kuota` (boleh kosong = tanpa batas), `tautan_grup_wa` (hanya `https://chat.whatsapp.com/...`; skema lain seperti `javascript:` ditolak), status `draf`/`dibuka`/`ditutup`. Gelombang `dibuka` wajib punya tanggal; tanggal tutup tidak boleh sebelum tanggal buka; kuota bilangan bulat ≥ 0. Menurunkan kuota di bawah jumlah Diterima ditolak dengan penjelasan (`KuotaService::terisi`), perubahan kuota memakai `lockForUpdate` di dalam transaksi. `sekolah_id` dan `tahun_ajaran_id` (tahun aktif) dipaksa oleh service. Gelombang yang sudah punya pendaftaran atau pengumuman tidak bisa dihapus; yang belum dipakai bisa dihapus dengan konfirmasi. Perubahan dicatat di log.
- **Pengaturan** (`PengaturanController` + `PengaturanSekolahPolicy`): mode penerimaan `seleksi`/`tanpa_seleksi` untuk sekolahnya; log `ubah_mode_penerimaan` (lama → baru) hanya bila nilainya benar-benar berubah.
- **Akun panitia** (`PanitiaController` + `AkunStafService`): admin membuat, menonaktifkan, mengaktifkan kembali, dan mengatur ulang kata sandi akun panitia **sekolahnya sendiri**. `peran` dipaksa `panitia`, `sekolah_id` dipaksa sekolah admin, `dibuat_oleh` diisi dari pelaku; input lain diabaikan (dites). Kata sandi sementara 10 karakter dari `random_int` (tanpa `0 O 1 l I`), `wajib_ganti_password = true`, **tampil sekali** lewat halaman `/kartu-akun` yang membaca sesi `kartu_akun` sekali lalu menghapusnya, dengan tombol Salin dan Cetak. Tidak disimpan dan tidak dicatat di log (dites). Menonaktifkan atau mengatur ulang menghapus **semua sesi** akun itu. Batas 20 pengaturan ulang per jam per admin (`RateLimiter atur-ulang-staf:{userId}`). Admin tidak bisa mengelola admin lain, super admin, yayasan, atau panitia sekolah lain (404).
- **Jenis berkas** (`JenisBerkasController`): nama, wajib, jumlah lembar, petunjuk; tambah, ubah, urutkan, nonaktifkan. Jenis berkas yang sudah punya unggahan tidak boleh dihapus; menonaktifkan tidak menghapus unggahan yang sudah ada; mengubah dari tidak wajib menjadi wajib tidak menggagalkan pendaftaran yang sudah terkirim (dites).
- **Rincian biaya & rekening** (`RincianBiayaController`, `RekeningController`): kelompok (`IAP`, `Formulir`, lainnya), nama, nominal bilangan bulat rupiah ≥ 0, `tipe` (`tetap`/`pilihan`), catatan, `aktif`. Perubahan biaya **tidak mengubah** `pilihan_iap` dan `setuju_biaya_pada` pendaftaran yang sudah terkirim (dites). Rekening: bank, nomor, atas nama, `aktif` (boleh lebih dari satu); yang aktif tampil ke walisantri sekolah itu; rekening sekolah lain tidak tampil (dites); di log hanya **4 digit terakhir** (dites).
- **Isian formulir** (`IsianController` + `AturanIsian`): daftar isian dari `config/isian.php` dengan saklar `tampil`/`wajib` per sekolah (`pengaturan_isian`). Isian inti (`nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`) tidak bisa disembunyikan atau dibuat tidak wajib; menyembunyikan isian tidak menghapus jawaban lama (dites); kunci di luar katalog diabaikan.
- **Umumkan Hasil** (`PengumumanController` + `PengumumanService::umumkan`): daftar gelombang dengan status "Belum diumumkan"/"Sudah diumumkan pada …"; layar konfirmasi memuat ringkasan jumlah Diterima, Ditolak, Cadangan, dan **peringatan** (bukan pemblokir) bila masih ada Baru, Diverifikasi, atau Perlu perbaikan; centang konfirmasi wajib. Proses dalam satu transaksi membuat satu baris `pengumuman` (`sekolah_id` dari akun, `gelombang_id`, `diumumkan_oleh`, `diumumkan_pada`, `teks_pesan`); unique `(sekolah_id, gelombang_id)` membuat percobaan kedua (termasuk dua permintaan berurutan) ditolak tanpa baris kedua (dites). Gelombang sekolah lain → 404. `teks_pesan` dibuat sistem ("Assalamu'alaikum, hasil SPMB {sekolah} {gelombang} sudah diumumkan…") tanpa nama anak, nomor peserta, atau hasil (dites), dengan tombol Salin dan "Bagikan ke WhatsApp" (`wa.me` + teks ter-URL-encode). Tidak ada tarik pengumuman. Panitia tidak bisa membuka layar konfirmasi; hasil tidak terlihat walisantri sebelum pengumuman dan terlihat sesudahnya; hasil yang diputuskan setelah pengumuman langsung terlihat; panitia tidak bisa mengubah hasil setelah pengumuman, admin bisa dengan catatan (semua dites). Log `umumkan_hasil` memuat jumlah per hasil.
- **Rekap sekolah** (`RekapController` + `RekapService::untukSekolah`): kartu angka dan agregat per gelombang: pendaftar terkirim, per status hasil, Baru vs Lulusan Yayasan, per program (TKI), pembayaran (menunggu/disetujui/ditolak), daftar ulang selesai/belum, mengundurkan diri, kuota terpakai. Hanya angka, tanpa daftar nama. Tombol unduh menunggu tahap 5.
- **Log aktivitas** (`LogController`): log `log_aktivitas` sekolah sendiri, filter tanggal/pelaku/jenis aksi, 50 baris per halaman, terbaru dulu, hanya baca. Model `LogAktivitas` menolak `update` dan `delete` di level aplikasi (K-08, dites). Setiap perubahan pengaturan, akun, biaya, berkas, isian, konten, dan pengumuman menulis log; log bersih dari kata sandi, token, dan nomor rekening utuh (dites).
- **Kelola konten** (`KontenController` + `KontenService`): lihat bagian Kelola Konten di bawah; admin hanya konten sekolahnya sendiri (404 untuk sekolah lain, dan tidak bisa menyentuh konten yayasan — dites).

### Panel super admin (`/super`)

- **Dasbor** (`SuperAdminController`): jumlah sekolah, jumlah akun staf aktif per peran, status tiap sekolah (punya admin aktif atau belum). Tanpa data pendaftar; angka agregat pendaftar hanya sebagai total per sekolah tanpa tautan detail.
- **Sekolah** (`SekolahController` + `SekolahRequest`): ubah nama, singkatan, alamat, nomor WhatsApp (format `62...`, divalidasi), Instagram, mode awal, `aktif`. Kode sekolah dan awalan nomor peserta **tidak dapat diubah** (dites, meski dikirim lewat formulir). Tidak ada tambah/hapus sekolah dari antarmuka.
- **Akun admin sekolah** (`AdminController`) dan **akun yayasan** (`YayasanController`), keduanya lewat `AkunStafService`: membuat, menonaktifkan, mengaktifkan, mengatur ulang kata sandi. `peran` dan `sekolah_id` dipaksa (admin per sekolah; yayasan tanpa `sekolah_id`); percobaan menyisipkan `peran=super_admin` diabaikan dan **tidak ada rute** untuk membuat super admin (dites). Super admin tidak bisa mengelola dirinya sendiri dan super admin terakhir yang aktif tidak dapat dinonaktifkan (dites). Menonaktifkan/mengatur ulang menghapus sesi akun (dites). Kata sandi sementara tampil sekali lewat `/kartu-akun`. Setiap tindakan dicatat log.
- **Konten yayasan** (`KontenController`): profil, visi, misi, tautan sosial yayasan lewat `konten_sekolah` dengan `sekolah_id` kosong. Super admin **hanya** boleh mengelola konten yayasan, bukan konten sekolah (dites). Visi/misi belum ada → dibiarkan kosong ("Segera hadir" diurus tampilannya di tahap 6).
- **Log global** (`LogController`): semua `log_aktivitas`, filter sekolah/pelaku/tanggal, hanya baca, terbaru dulu; nilai `data` pada log disaring sebelum tampil (kunci rahasia disembunyikan).
- Tidak ada impersonasi. **Tidak ada satu pun rute, query, tampilan, atau respons super admin yang memuat data pendaftar per orang** — dites dengan menyusuri seluruh rute `/super` mencari nama anak, NIK, nomor peserta, dan nama berkas dari data uji, serta memeriksa daftar rute (`SuperYayasanTidakLihatPendaftarTest`).

### Panel yayasan (`/yayasan`)

- `YayasanController` (beranda + rekap) dan `ProfilController`. Middleware `auth`, `peran:yayasan`; **hanya baca**: seluruh rute menerima `GET`/`HEAD` saja (dites lewat daftar rute dan permintaan `POST` yang ditolak).
- **Rekap** (`RekapService::untukYayasan`): kartu dan tabel agregat empat sekolah: pendaftar terkirim per gelombang per sekolah, per status hasil, Baru vs Lulusan Yayasan, pembayaran (jumlah), daftar ulang selesai, mengundurkan diri, kuota terpakai vs kuota. Hasil yang belum diumumkan boleh dihitung sebagai angka agregat dengan label jelas. Angka rekap diverifikasi terhadap data uji (dites), dan halaman dipastikan tidak memuat nama per orang (dites).
- Menu: Rekap, Profil akun, dan Ganti kata sandi (tautan global `/ganti-password` di sidebar). Tombol unduh rekap menunggu tahap 5.

### Kelola Konten (bagian 6 prompt — menyimpan konten selesai di tahap ini)

- Tabel `konten_sekolah` per bagian `docs/data-awal.md` bagian 3: `slogan`, `profil`, `visi`, `indikator_visi`, `misi`, `program_unggulan`, `ekskul`, `prestasi`, `fasilitas`, `galeri`, `jam_belajar`, `kontak`, `alur`, `syarat`. Satu halaman dengan bagian lipat; daftar yang bisa bertambah (ekskul, program unggulan, prestasi, fasilitas, galeri) punya tambah/ubah/urut/hapus baris.
- **Keamanan teks (K-06)**: konten teks biasa. Tag HTML dibuang dengan `strip_tags` sebelum simpan **dan** tetap di-escape saat tampil (pertahanan berlapis; dites). Tautan eksternal hanya `https://`; `javascript:` dan `data:` ditolak (dites). Tidak ada editor HTML bebas dan tidak ada `{!! !!}` untuk konten ini.
- **Gambar** (galeri, ekskul, prestasi, fasilitas): unggah lewat pipeline yang sama dengan `BerkasService` (finfo MIME, hanya JPG/PNG/WebP, maksimal 2 MB, **di-encode ulang** untuk membuang EXIF dan muatan tersembunyi, nama acak, dimensi dibatasi). PHP berekstensi `.jpg`, SVG, file besar, dan berkas dengan isi tidak cocok ditolak (dites). Disimpan di disk **publik** khusus konten `konten/{sekolah}/`; berkas pendaftar **tidak pernah** masuk disk publik (dites). Menghapus baris menghapus gambarnya dari disk (dites). Teks pengganti gambar memakai judul baris konten.
- Konten kosong tidak diisi data karangan (tampilan "Menyusul" diurus tahap 6). Setiap simpan dicatat log. Admin hanya konten sekolahnya; super admin hanya konten yayasan (dites).

### Lintas panel

- **Migrasi baru** `0001_01_07_000000_penyesuaian_tahap_4` (lihat penyimpangan nomor 1).
- **K-08 lengkap**: seluruh peristiwa Tahap 4 menulis `log_aktivitas`; model log tak dapat diubah/dihapus; tidak ada rute ubah/hapus log; log bersih dari rahasia.
- **Kartu akun staf**: rute `GET /kartu-akun` (`kartu-akun.staf`) dipakai bersama oleh admin dan super admin setelah membuat/mengatur ulang akun staf; isinya dibaca sekali dari sesi lalu dihapus.
- Sidebar (`components/sidebar.blade.php`) kini melayani keempat peran; admin melihat menu panitia dan menu admin dalam satu daftar.

## 2. Tes yang dijalankan

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **373 passed (2302 assertions)**, 0 gagal |
| `php artisan migrate:fresh --seed` | bersih di database kosong MySQL: 13 berkas migrasi, 2 seeder |
| `npm run build` | berhasil (vite 7.3.8); CSS 55,99 kB, JS 131,82 kB |
| `php artisan view:cache` lalu `view:clear` | seluruh Blade terkompilasi tanpa galat |
| `php artisan route:list --except-vendor` | 135 rute (lihat bagian 3) |
| Pemeriksaan `dd()`/`dump()`/`console.log` | bersih di `app/`, `tests/`, `resources/views/` |

Pemetaan 15 butir tes wajib Tahap 4 (lihat tabel di bawah):

| Butir | Berkas tes | Jumlah |
| --- | --- | --- |
| 1 Matriks akses `/admin`, `/super`, `/yayasan` per peran | `tests/Feature/Keamanan/MatriksAksesPanelTest.php` | 6 |
| 2 Isolasi sekolah (gelombang, pengumuman, berkas, biaya, rekening, konten, staf, log, `sekolah_id` diabaikan, mode penerimaan) | `tests/Feature/Keamanan/IsolasiSekolahAdminTest.php` | 11 |
| 3 Super admin dan yayasan tidak melihat pendaftar | `tests/Feature/Keamanan/SuperYayasanTidakLihatPendaftarTest.php` | 3 |
| 4 Gelombang (tanggal, kuota, URL grup WA, hapus, tidak bocor ke walisantri, log) | `tests/Feature/AdminGelombangTest.php` | 11 |
| 5 Akun panitia oleh admin | `tests/Feature/AdminAkunPanitiaTest.php` | 9 |
| 6 Akun oleh super admin | `tests/Feature/SuperAkunTest.php` | 9 |
| 7 Umumkan Hasil (satu baris, tolak ulang, konfirmasi, peringatan, 404, teks aman, terlihat setelahnya, peran) | `tests/Feature/UmumkanHasilTest.php` | 12 |
| 8 Jenis berkas dan biaya (hapus terpakai, persetujuan tak berubah, nominal, rekening, log 4 digit) | `tests/Feature/AdminBerkasBiayaRekeningTest.php` | 11 |
| 9 Isian (inti terkunci, sembunyi tidak menghapus jawaban, kunci asing diabaikan, log) | `tests/Feature/AdminIsianPanelTest.php` | 5 |
| 10 Kelola Konten (XSS, tautan, unggahan berbahaya, EXIF, disk publik, isolasi) | `tests/Feature/Keamanan/KontenKeamananTest.php` | 12 |
| 11 Log (setiap aksi menulis log, immutable, bersih dari rahasia, terbaru dulu) | `tests/Feature/Keamanan/AdminLogKeamananTest.php` | 6 |
| 12 Yayasan hanya baca (metode, angka rekap, tanpa data per orang) | `tests/Feature/YayasanHanyaBacaTest.php` | 5 |
| 13 Mass assignment seluruh formulir admin/super | `tests/Feature/Keamanan/PanelMassAssignmentTest.php` | 9 |
| 14 XSS di halaman tahap ini | `tests/Feature/Keamanan/PanelXssTest.php` | 6 |
| 15 Regresi seluruh tes Tahap 1–3 | seluruh suite | 373 tes, 2302 asersi |
| Tambahan: bagian asal sekolah (langkah 5) — ditemukan galat 500 saat mencoba manual, lalu diperbaiki | `tests/Feature/AsalSekolahTest.php` | 2 |
| Tambahan: data wali bisa dikosongkan — ditemukan saat mencoba manual, lalu diperbaiki | `tests/Feature/OrangTuaWaliTest.php` | 3 |

Total tes baru Tahap 4: 115 butir wajib + 2 tambahan + 3 tambahan = 120; 253 tes Tahap 1–3 tetap lulus tanpa perubahan (253 + 120 = 373).

Butir keamanan `docs/keamanan.md` yang diselesaikan dan dites pada tahap ini: **K-03** (matriks lengkap tiga panel), **K-06** (masukan/keluaran konten), **K-08** (jejak audit lengkap), **K-09** (batas laju pengaturan ulang 20/jam), **K-10** (kata sandi sementara staf), **K-16** (tes otomatis di atas), **K-17** (kuota, biaya, rekening tidak bocor ke walisantri/publik — penyaringan penuh halaman publik tetap di tahap 6), **K-18** (yayasan/super admin tidak melihat pendaftar), **K-19** (tautan `wa.me` tanpa data pribadi dan tanpa hasil).

## 3. Daftar rute

Total **135 rute**. Rute panel Tahap 4 ada 60: 36 `/admin`, 20 `/super`, 3 `/yayasan`, dan 1 `kartu-akun.staf`. Tiga rute beranda (`admin`, `super`, `yayasan`) semula rintisan Tahap 2 dan kini menjadi panel penuh. Semua rute panel memakai middleware `web, auth, peran:<peran>`; rute berparameter memakai `uuid` atau `id` sesuai objek, dan data sekolah lain menghasilkan **404**.

```
GET|HEAD  admin ...................................................... admin.beranda › Admin\AdminController@beranda
GET|HEAD  admin/berkas ...................................... admin.berkas.index › Admin\JenisBerkasController@index
POST      admin/berkas .................................... admin.berkas.simpan › Admin\JenisBerkasController@simpan
PUT       admin/berkas/{jenisBerkas} .......................... admin.berkas.ubah › Admin\JenisBerkasController@ubah
DELETE    admin/berkas/{jenisBerkas} ........................ admin.berkas.hapus › Admin\JenisBerkasController@hapus
GET|HEAD  admin/biaya ....................................... admin.biaya.index › Admin\RincianBiayaController@index
POST      admin/biaya ..................................... admin.biaya.simpan › Admin\RincianBiayaController@simpan
PUT       admin/biaya/{rincianBiaya} .......................... admin.biaya.ubah › Admin\RincianBiayaController@ubah
DELETE    admin/biaya/{rincianBiaya} ........................ admin.biaya.hapus › Admin\RincianBiayaController@hapus
GET|HEAD  admin/gelombang .................................. admin.gelombang.index › Admin\GelombangController@index
POST      admin/gelombang ................................ admin.gelombang.simpan › Admin\GelombangController@simpan
PUT       admin/gelombang/{gelombang} ........................ admin.gelombang.ubah › Admin\GelombangController@ubah
DELETE    admin/gelombang/{gelombang} ...................... admin.gelombang.hapus › Admin\GelombangController@hapus
GET|HEAD  admin/isian .............................................. admin.isian.index › Admin\IsianController@index
PUT       admin/isian ............................................ admin.isian.simpan › Admin\IsianController@simpan
GET|HEAD  admin/konten ........................................... admin.konten.index › Admin\KontenController@index
POST      admin/konten ......................................... admin.konten.tambah › Admin\KontenController@tambah
PUT       admin/konten/teks .................................. admin.konten.teks › Admin\KontenController@simpanTeks
PUT       admin/konten/{konten} .................................... admin.konten.ubah › Admin\KontenController@ubah
DELETE    admin/konten/{konten} .................................. admin.konten.hapus › Admin\KontenController@hapus
GET|HEAD  admin/log .................................................... admin.log.index › Admin\LogController@index
GET|HEAD  admin/panitia ........................................ admin.panitia.index › Admin\PanitiaController@index
POST      admin/panitia ...................................... admin.panitia.simpan › Admin\PanitiaController@simpan
POST      admin/panitia/{user}/aktifkan .................. admin.panitia.aktifkan › Admin\PanitiaController@aktifkan
POST      admin/panitia/{user}/atur-ulang ............. admin.panitia.atur-ulang › Admin\PanitiaController@aturUlang
POST      admin/panitia/{user}/nonaktifkan ......... admin.panitia.nonaktifkan › Admin\PanitiaController@nonaktifkan
GET|HEAD  admin/pengaturan ..................................... admin.pengaturan › Admin\PengaturanController@index
PUT       admin/pengaturan ............................. admin.pengaturan.simpan › Admin\PengaturanController@simpan
GET|HEAD  admin/pengumuman ............................... admin.pengumuman.index › Admin\PengumumanController@index
GET|HEAD  admin/pengumuman/{gelombang} ......... admin.pengumuman.konfirmasi › Admin\PengumumanController@konfirmasi
POST      admin/pengumuman/{gelombang} ................ admin.pengumuman.simpan › Admin\PengumumanController@umumkan
GET|HEAD  admin/rekap .............................................. admin.rekap.index › Admin\RekapController@index
GET|HEAD  admin/rekening ..................................... admin.rekening.index › Admin\RekeningController@index
POST      admin/rekening ................................... admin.rekening.simpan › Admin\RekeningController@simpan
PUT       admin/rekening/{rekeningSekolah} ..................... admin.rekening.ubah › Admin\RekeningController@ubah
DELETE    admin/rekening/{rekeningSekolah} ................... admin.rekening.hapus › Admin\RekeningController@hapus
```

```
GET|HEAD  super ............................................ super.beranda › SuperAdmin\SuperAdminController@beranda
GET|HEAD  super/admin ......................................... super.admin.index › SuperAdmin\AdminController@index
POST      super/admin ....................................... super.admin.simpan › SuperAdmin\AdminController@simpan
POST      super/admin/{user}/aktifkan ................... super.admin.aktifkan › SuperAdmin\AdminController@aktifkan
POST      super/admin/{user}/atur-ulang .............. super.admin.atur-ulang › SuperAdmin\AdminController@aturUlang
POST      super/admin/{user}/nonaktifkan .......... super.admin.nonaktifkan › SuperAdmin\AdminController@nonaktifkan
GET|HEAD  super/konten ...................................... super.konten.index › SuperAdmin\KontenController@index
POST      super/konten .................................... super.konten.tambah › SuperAdmin\KontenController@tambah
PUT       super/konten/teks ............................. super.konten.teks › SuperAdmin\KontenController@simpanTeks
PUT       super/konten/{konten} ............................... super.konten.ubah › SuperAdmin\KontenController@ubah
DELETE    super/konten/{konten} ............................. super.konten.hapus › SuperAdmin\KontenController@hapus
GET|HEAD  super/log ............................................... super.log.index › SuperAdmin\LogController@index
GET|HEAD  super/sekolah ................................... super.sekolah.index › SuperAdmin\SekolahController@index
GET|HEAD  super/sekolah/{sekolah}/edit ...................... super.sekolah.edit › SuperAdmin\SekolahController@edit
PUT       super/sekolah/{sekolah} ....................... super.sekolah.simpan › SuperAdmin\SekolahController@simpan
GET|HEAD  super/yayasan ................................... super.yayasan.index › SuperAdmin\YayasanController@index
POST      super/yayasan ................................. super.yayasan.simpan › SuperAdmin\YayasanController@simpan
POST      super/yayasan/{user}/aktifkan ............. super.yayasan.aktifkan › SuperAdmin\YayasanController@aktifkan
POST      super/yayasan/{user}/atur-ulang ........ super.yayasan.atur-ulang › SuperAdmin\YayasanController@aturUlang
POST      super/yayasan/{user}/nonaktifkan .... super.yayasan.nonaktifkan › SuperAdmin\YayasanController@nonaktifkan
```

```
GET|HEAD  yayasan .............................................. yayasan.beranda › Yayasan\YayasanController@beranda
GET|HEAD  yayasan/rekap ............................................ yayasan.rekap › Yayasan\YayasanController@rekap
GET|HEAD  yayasan/profil ........................................... yayasan.profil › Yayasan\ProfilController@index
GET|HEAD  kartu-akun .................................................. kartu-akun.staf › KartuAkunController@tampil
```

Catatan hitungan: laporan Tahap 3 menulis "78 rute"; hitung ulang pada kondisi sekarang menunjukkan 75 rute sebelum Tahap 4 (37 panitia + 24 walisantri + 14 publik/autentikasi termasuk 4 rute tamu lupa/reset kata sandi + 3 rintisan beranda). Daftar rute pada laporan Tahap 3 memang tidak lengkap satu-dua baris; tidak ada rute lama yang dihapus pada Tahap 4 (seluruh rute panitia, walisantri, publik, dan autentikasi terverifikasi tetap ada).

## 4. Paket tambahan

Tidak ada paket baru pada Tahap 4. Semua kebutuhan memakai bawaan Laravel 13, PHP 8.4, dan paket Tahap 1 (`@fontsource-variable/plus-jakarta-sans`, `laravel-lang/lang`). Paket ekspor Excel (`openspout/openspout` atau `maatwebsite/excel`) baru dipasang di tahap 5 bila memang diperlukan.

## 5. Penyimpangan dari dokumen dan alasannya

1. **Migrasi `0001_01_07_000000_penyesuaian_tahap_4` menambah kolom yang belum ada di `docs/struktur-tabel.md`.**
   Prompt Tahap 4 memintanya secara eksplisit: `sekolah.singkatan` dan `sekolah.instagram` (halaman sekolah super admin, bagian 5), `jenis_berkas.petunjuk` (bagian 2.4), `rincian_biaya.catatan` dan `rincian_biaya.aktif` (bagian 2.5). Semuanya nullable/bernilai bawaan sehingga aman. Selain itu `konten_sekolah.sekolah_id` dilonggarkan menjadi nullable karena bagian 5 meminta konten yayasan disimpan di tabel yang sama dengan `sekolah_id` kosong (tanpa menghapus kunci asing). Mohon kolom-kolom ini disahkan ke `docs/struktur-tabel.md`.
2. **Teks pengganti gambar konten memakai kolom `judul` baris konten.**
   Prompt bagian 6 meminta "alt teks untuk tiap gambar", tetapi `docs/struktur-tabel.md` tidak punya kolom `alt`. Daripada menambah kolom lagi, judul baris konten dipakai sebagai alt (judul baris wajib dan di-escape). Bila sekolah ingin alt yang berbeda dari judul, perlu kolom tambahan.
3. **Data sekolah lain menghasilkan 404 juga bila pelakunya peran salah.**
   Binding model dengan scope `MilikSekolah` selesai lebih dulu daripada middleware `peran`, jadi misalnya panitia sekolah B yang membuka rute admin sekolah A mendapat 404, bukan 403. Prompt mengizinkan "404 atau 403 sesuai kasus" untuk kasus lintas sekolah; matriks peran di rute tanpa parameter tetap 403. Ini lebih aman karena keberadaan data tidak diungkap.
4. **Rute baru `GET /kartu-akun` (nama `kartu-akun.staf`).**
   Sama dengan laporan Tahap 3 (rute `panitia/kartu-akun`): kata sandi sementara staf harus tampil sekali, dan halaman kartu kini dipakai bersama oleh admin dan super admin. Tidak ada di daftar rute prompt tetapi diperlukan bagian 2.3 dan 5.
5. **Repositori ini bukan repositori git** (berlanjut dari laporan Tahap 3).
   `git commit` tidak dapat dijalankan karena folder kerja tidak diinisialisasi sebagai repo. Tidak ada `.env`, `storage/`, `vendor/`, atau `node_modules/` yang disertakan ke mana pun.
6. **K-17 baru terpenuhi setengah jalan.**
   Kuota, biaya, dan rekening sudah dibuktikan tidak bocor ke halaman publik, respons walisantri, dan respons sebelum syaratnya; penyempurnaan menyeluruh untuk halaman publik tetap pada tahap 6 sesuai pembagian `docs/keamanan.md`.

## 6. Bug yang ditemukan dan diperbaiki selama tes

- **Bagian asal sekolah (langkah 5) selalu menghentikan halaman (500) — ditemukan saat mencoba manual di peramban.**
  `PendaftaranService::simpanLangkah()` menyisipkan `lulusan_yayasan` ke data formulir lalu `fill()` ke model `Anak`, padahal `lulusan_yayasan` hanya kolom `pendaftaran`, bukan `anak` — mass assignment ketat menolaknya. Jalur walisantri (`PUT /akun/pendaftaran/{p}/langkah/5`) dan jalur offline panitia (`PUT /panitia/offline/{p}/langkah/5`) sama-sama terkena. Diperbaiki mengikuti pola yang sudah benar di `simpanDataOlehStaf()`: `lulusan_yayasan` dihitung dari asal sekolah yang baru disimpan dan ditulis dengan `forceFill()` pada pendaftaran.
  **Pelajaran pengujian**: tes alur offline sebelumnya hanya `assertSessionHasNoErrors()`, yang tetap lulus meski responsnya 500 (sesi tanpa galat ≠ respons sukses), sehingga galat ini lolos dari seluruh suite. Asersi kini diperkuat `assertRedirect()` eksplisit, ditambah `tests/Feature/AsalSekolahTest.php` (2 tes): nilai dihitung benar (nama sekolah yayasan → true, lainnya → false) dan `lulusan_yayasan` tidak dapat dipalsukan dari formulir.
- **Penyesuaian tampilan unggah berkas atas umpan balik pengguna (setelah laporan pertama).**
  Langkah 6 sebelumnya menampilkan dua tombol ("Unggah" dan "Ambil & unggah") plus kotak pilih berkas bawaan peramban. Disederuhkan menjadi **satu tombol** (`components/unggah-berkas.blade.php` dan tab berkas panitia): mengetuknya membuka pemilih sistem — galeri atau pengelola berkas di HP, file manager di desktop — dan formulir terkirim otomatis begitu berkas dipilih (JS `data-unggah-otomatis` di `resources/js/app.js`, tanpa ekspresi inline, tetap aman CSP). Atribut `capture` dihapus; `accept` tetap JPG/PNG/PDF sesuai validasi server, sehingga pemilih sistem menawarkan galeri, kamera, dan berkas sekaligus. Seluruh tes tetap lulus (370 tes, 2287 asersi).
- **"Biaya formulir" dihapus dari data awal atas permintaan pengguna (setelah laporan pertama).**
  `DataAwalSeeder` sebelumnya memuat baris `rincian_biaya` "Biaya formulir" Rp50.000 untuk SD (`kelompok = Formulir`), sehingga halaman biaya walisantri SD menampilkan satu komponen itu. Baris dihapus dari seeder; SD, TKI, dan SMP kini mulai tanpa komponen biaya dan admin yang mengisinya lewat menu Rincian biaya. Halaman biaya sudah menangani kondisi kosong dengan rapi ("Rincian biaya menyusul") dan aturan kirim tidak menuntut persetujuan biaya bila rincian kosong, jadi tidak ada perubahan kode lain. Kemampuan admin menambahkan biaya apa pun (termasuk "Biaya formulir") bila kelak dibutuhkan **tetap ada**. `docs/data-awal.md` bagian 7 diperbarui mengikuti keputusan ini. Baris yang sudah ada di database lokal uji dinonaktifkan (`aktif = false`), bukan dihapus, agar data uji tidak ikut hilang; `migrate:fresh --seed` berikutnya tidak akan membuatnya lagi. Seluruh tes tetap lulus (370 tes, 2287 asersi).
- **Data wali tidak bisa dikosongkan — halaman menuntut nama wali (ditemukan saat mencoba manual di peramban).**
  Dropdown "Status" pada formulir orang tua/wali dibuka dengan bawaan "Masih hidup" dan selalu mengirim nilai itu, sehingga bagian wali yang dikosongkan tetap dianggap "diisi" oleh pemeriksaan `OrangTuaRequest` → galat "Nama wajib diisi bila data wali diisi". Pola simpan yang sama juga membuat **baris wali hantu** (baris tanpa nama, hanya status) dan tidak mengosongkan baris wali lama ketika semua isian dihapus.
  Diperbaiki dengan kelas bantu bersama `app/Support/DataOrangTua.php` yang dipakai ketiga alur penyimpanan (walisantri, offline panitia, panitia ubah data): kolom `status_hidup` tidak dihitung sebagai data di validasi maupun penyimpanan, bagian yang benar-benar kosong **menghapus barisnya** (`orang_tua.nama` tidak boleh kosong, jadi baris tanpa data tidak sah; tidak ada tabel lain yang merujuk `orang_tua`), dan ringkasan pendaftaran tidak menampilkan baris tanpa nama. Tes baru `tests/Feature/OrangTuaWaliTest.php` (3 tes): wali kosong tidak menuntut nama dan tidak membuat baris, wali lama yang dikosongkan benar-benar hilang, wali yang diisi tetap tersimpan. Seluruh suite: 373 tes, 2302 asersi.
- **`GelombangService` 500 "Undefined array key tanggal_buka" saat menyimpan gelombang draf tanpa tanggal.**
  Validasi memang mengizinkan tanggal kosong untuk status `draf`, tetapi service membaca `$data['tanggal_buka']` tanpa pemeriksaan kunci. Ditemukan oleh tes isolasi `sekolah_id` gelombang; diperbaiki dengan `($data['tanggal_buka'] ?? '') ?: null` (dan pasangannya `tanggal_tutup`).
- **`/admin/log` dan `/super/log` 500 begitu ada log.**
  Kedua controller memilih kolom parsial User (`get(['id', 'nama'])`), sedangkan model `User` memuat relasi `sekolah` bersama model (`$with = ['sekolah']`) dan aplikasi berjalan dalam mode atribut ketat, sehingga eager load gagal karena `sekolah_id` tidak ikut dipilih. Diperbaiki dengan menambah `sekolah_id` ke kedua select, disertai komentar penjelasan.
- **Penyesuaian arah asersi pada tes (bukan bug kode):**
  bawaan pabrik `Sekolah` adalah `mode_penerimaan = tanpa_seleksi`, sehingga tes perubahan mode memakai nilai `seleksi` agar perubahan benar-benar terjadi; rute `GET` di Laravel selalu menerima `HEAD`, sehingga asersi "hanya GET" pada rute yayasan menerima `GET|HEAD`; batas bawah jumlah log diperlongir menjadi ≥ 3; asersi urutan log "terbaru dulu" dikoreksi posisinya.

## 7. Cara mencoba manual di lokal

1. Jalankan `php artisan migrate:fresh --seed`. Seeder mencetak kata sandi acak untuk: Super admin (`6280000000000`), Yayasan (`6280000000001`), admin dan panitia tiap sekolah, serta empat akun walisantri.
2. **Admin SD**: masuk dengan nomor HP admin SD. Sidebar memuat menu panitia (Dasbor panitia, Pendaftar, Pembayaran, Pendaftar offline) dan menu admin (Dasbor admin, Gelombang & kuota, Pengaturan sekolah, Akun panitia, Jenis berkas, Rincian biaya, Rekening sekolah, Isian formulir, Umumkan hasil, Rekap sekolah, Kelola konten, Log aktivitas).
3. **Gelombang & kuota**: buat gelombang baru (status `draf`, tanpa tanggal boleh), ubah ke `dibuka` (wajib tanggal), isi kuota dan tautan grup `https://chat.whatsapp.com/...`; coba tautan `javascript:` → ditolak. Coba turunkan kuota di bawah jumlah Diterima pada gelombang demo → ditolak dengan pesan.
4. **Akun panitia**: buat akun → kartu akun muncul sekali (Salin/Cetak); nonaktifkan → akun tak bisa masuk; aktifkan kembali → bisa; atur ulang kata sandi → kartu baru sekali, sesi lama mati. Coba kelola panitia sekolah lain lewat URL → 404.
5. **Jenis berkas, biaya, rekening, isian**: tambah jenis berkas berpetunjuk, rincian biaya IAP, rekening; coba hapus jenis berkas/biaya/rekening yang sudah dipakai → ditolak. Di Isian formulir sembunyikan isian non-inti (inti terkunci).
6. **Umumkan Hasil**: buka `admin/pengumuman` → pilih gelombang → layar konfirmasi memuat ringkasan dan peringatan bila ada yang belum final → centang pernyataan → Umumkan. Salin teks atau tekan "Bagikan ke WhatsApp" (membuka `wa.me` dengan teks siap kirim). Coba umumkan lagi → ditolak. Masuk sebagai walisantri demo terkait → hasil kini tampil di akunnya.
7. **Rekap & log**: `admin/rekap` hanya angka; `admin/log` bisa disaring tanggal/pelaku/aksi, 50 baris per halaman, tanpa tombol ubah/hapus.
8. **Kelola konten**: `admin/konten` → isi slogan/profil/program unggulan/ekskul/prestasi/fasilitas/galeri/jam belajar/kontak/alur/syarat; unggah gambar galeri; coba tempel `<script>` pada teks → tersimpan bersih dan tampil ter-escape.
9. **Super admin** (`6280000000000`): dasbor tanpa data pendaftar; `super/sekolah` ubah singkatan/Instagram/WA (kode dan awalan nomor peserta terkunci); `super/admin` buat akun admin SD → kartu sekali; `super/yayasan` buat akun yayasan; `super/konten` isi profil/visi/misi yayasan (visi/misi dibiarkan kosong); `super/log` log global.
10. **Yayasan** (`6280000000001`): `yayasan/rekap` angka agregat empat sekolah termasuk kuota terpakai vs kuota dan label "belum diumumkan"; coba `POST` ke rute `/yayasan` apa pun → ditolak; profil hanya data akun sendiri.
11. **Cek lebar layar**: 360 px (sidebar menjadi laci menu, kartu angka menumpuk 2 kolom, formulir 1 kolom) dan 1280 px (sidebar tetap, grid 3–4 kolom). Pemeriksaan statis markup sudah dilakukan; lihat bagian 8 butir 5.

## 8. Pertanyaan yang masih terbuka

1. **Nilai resmi `orang_tua.status_hidup`** masih `Masih hidup` / `Sudah wafat` (belum dijawab sejak Tahap 2).
2. **Berkas wajib yang belum siap** boleh dilewati dengan centang konfirmasi (keputusan Tahap 3). Apakah ini tetap peringatan, atau nanti dijadikan penghalang keras?
3. **NIK utuh** tampil untuk panitia/admin di tab Data (keputusan Tahap 3). Mohon konfirmasi apakah perlu dibatasi hanya admin.
4. **Pengesahan kolom migrasi `penyesuaian_tahap_4`** ke `docs/struktur-tabel.md`: `sekolah.singkatan`, `sekolah.instagram`, `jenis_berkas.petunjuk`, `rincian_biaya.catatan`, `rincian_biaya.aktif`, dan `konten_sekolah.sekolah_id` nullable.
5. **Pemeriksaan visual 360 px dan 1280 px** masih berupa analisis statis markup (tidak ada `<table>` di panel baru, semua grid mobile-first, tanpa lebar tetap); belum dengan tangkapan layar peramban sungguhan.
6. **Alamat situs pada teks pengumuman** memakai URL aplikasi saat ini (`APP_URL` lokal). Domain produksi perlu dipastikan saat tahap 6.
7. **Teks "Segera hadir" untuk visi/misi yayasan** yang masih kosong perlu disetujui yayasan; isinya sendiri diisi lewat `super/konten` kapan saja tanpa perubahan kode.
8. **Nama sebenarnya yang dipakai panitia/admin** untuk tombol Umumkan Hasil dan warna tampilan layar konfirmasi mengikuti `docs/desain.md`; bila yayasan ingin redaksi peringatan yang lebih tegas, ubah teks saja tanpa sentuh logika.
