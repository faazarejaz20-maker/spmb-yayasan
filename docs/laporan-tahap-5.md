# Laporan Tahap 5 — Ekspor, unduh berkas (ZIP), dan cetak

Tanggal: 12 November 2026
Lingkup: ekspor Excel/CSV (panitia, admin, rekap yayasan), unduh berkas per pendaftar sebagai ZIP, serta halaman cetak formulir, bukti pendaftaran, dan kuitansi (`prompts/tahap-5-ekspor-cetak.md`). Halaman publik dan konfigurasi produksi tetap ditunda ke tahap 6. Keamanan **K-20** adalah inti tahap ini.

## 1. Yang selesai

### Paket dan konfigurasi

- **`openspout/openspout` v5.13.0** dipasang lewat `composer require` (streaming, hemat memori). `composer audit` dijalankan: **tidak ada kerentanan keamanan**; hanya dua paket transitif yang sudah lama ditinggalkan (`dragon-code/contracts`, `dragon-code/support`) yang datang bersama `laravel-lang/lang` sejak Tahap 1 — bukan dari paket baru (lihat bagian 4).
- **ZIP memakai `ZipArchive` bawaan PHP.** Ekstensi `zip` aktif di server uji (PHP 8.4.27); diperiksa lebih dulu. Catatan teknis: konstanta `ZipArchive::CLOSED` **tidak ada** di build ini, sehingga status tertutup dilacak dengan variabel lokal `$tertutup` dan `@$zip->close()` di blok `finally`.
- **Tidak ada pustaka PDF baru.** Cetak berupa halaman Blade dengan `@media print` ukuran A4 margin 15 mm; pengguna menyimpan sebagai PDF lewat dialog cetak peramban.
- `config/ekspor.php`: daftar putih kolom per grup (Identitas, Kontak orang tua, Status, Berkas, Pembayaran, Daftar ulang), grup sensitif (NIK, no. KK, riwayat penyakit, alergi, kebutuhan khusus), teks konfirmasi, `batas_baris` 5.000, `zip_maks_mb` 40, `tmp_folder` = `tmp`, `tmp_umur_menit` 60.

### Ekspor Excel dan CSV

- Rute `GET /panitia/ekspor` (halaman pilihan kolom + penyaring + format) dan `POST /panitia/ekspor` (menghasilkan unduhan). POST dipakai agar parameter tidak tersimpan di riwayat peramban atau log server.
- `EksporService`: query dibatasi **global scope `MilikSekolah` + `where('pendaftaran.sekolah_id', ...)` eksplisit**, hanya scope `terkirim()` (draf tidak ikut), `lazy()` per 1.000 baris, maksimum `batas_baris` (lebih → pesan "Persempit dengan penyaring"), tolak bila 0 baris.
- Kolom hanya dari **daftar putih** `config/ekspor.php`. Kunci di luar daftar (termasuk `catatan_panitia`, `catatan_wawancara`, `kekurangan`, `password`, `token`, jalur disk) tidak dapat dipilih meski dikirim manual.
- **Kolom sensitif** hanya untuk `admin` **dan** hanya bila mencentang konfirmasi; untuk `panitia` selalu diabaikan. NIK dan no. KK ditulis sebagai **teks** (seluruh nilai sel ditulis sebagai string, sehingga Excel tidak mengubahnya menjadi notasi ilmiah; tidak memakai prefiks `'` yang akan tampil di sel).
- **`EksporSanitizer`** satu-satunya jalur netralisasi formula injection: sel teks berawalan `=`, `+`, `-`, `@`, tab, atau CR diberi awalan `'`. Dipakai semua ekspor (pendaftar, rekap admin, rekap yayasan).
- CSV: UTF-8 **dengan BOM**, pemisah koma (bawaan openspout). XLSX: lembar **Data** + lembar kedua **Ringkasan** (tanggal, pelaku, penyaring, jumlah baris, daftar kolom — tanpa data pribadi).
- Nama file dari data terkontrol: `spmb-{kode-sekolah}-{gelombang}-{tanggal}.{ext}`.
- Header: `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store, private`, `Content-Type` sesuai format.
- File sementara di `storage/app/private/tmp/` dengan nama UUID; dihapus setelah terkirim (`deleteFileAfterSend(true)`) **dan** bila penulisan gagal (`try/catch` + `@unlink`).
- Tombol "Unduh Excel" di daftar pendaftar membawa penyaring aktif ke halaman ekspor.

### Unduh berkas (ZIP)

- Rute `GET /panitia/pendaftar/{pendaftaran}/berkas.zip` (`panitia.pendaftar.zip`), middleware `throttle:zip`; hanya panitia/admin sekolah itu; sekolah lain, draf, atau bukan peran → **404/403**.
- `ZipBerkasService`: kumpulkan semua berkas pendaftar (status apa pun), **hanya ekstensi daftar putih `jpg`/`png`/`pdf`**, buat ZIP di folder sementara privat bernama UUID, alirkan dengan `response()->download(...)->deleteFileAfterSend(true)`, dan hapus di `finally` bila gagal.
- **Nama entri** `{nomor_peserta}_{kode_jenis_berkas}_{urutan}.{ekstensi}` dibangun dari data terkontrol (nomor peserta, slug nama jenis berkas, urutan 1-based), **bukan** nama unggahan. Nama yang mengandung `..`, `/`, atau `\` ditolak (pertahanan ganda zip-slip).
- Berkas yang hilang dari disk atau bertipe tak dikenal **dilewati** dan dicatat (`unduh_zip_berkas_hilang`), bukan galat 500. Bila tidak ada satu pun yang dapat dipakai → pesan sopan di halaman detail.
- Batas ukuran total 40 MB → pesan jelas. Log `unduh_zip` (siapa, pendaftaran, jumlah berkas).

### Rekap agregat

- Admin: `GET /admin/rekap.xlsx` → angka persis seperti halaman rekap, tanpa nama/nomor peserta.
- Yayasan: `GET /yayasan/rekap.xlsx` (middleware `peran:yayasan`) → agregat empat sekolah. **Tidak ada** ekspor per orang untuk yayasan (K-18) dan **tidak ada** rute ekspor pendaftar untuk `super_admin`.
- Keduanya memakai `EksporSanitizer` dan menulis log `ekspor_rekap` (jenis `sekolah`/`yayasan`, jumlah baris, daftar kolom).

### Cetak

- `layouts/cetak` (`resources/views/layouts/cetak.blade.php`): A4, margin 15 mm, kop sekolah (`$sekolah` dari controller, cadangan nama yayasan), tanggal cetak, tombol **Cetak** dan **Tutup** lewat JS eksternal (`data-cetak` / `data-tutup-cetak` di `resources/js/app.js`, **tanpa** atribut `onclick`, aman CSP), tanpa sidebar/menu.
- Semua halaman cetak: diperiksa Policy + `sudahDikirim()`, header `Cache-Control: no-store, private` dan `nosniff`, dibuka `target="_blank" rel="noopener"`, **tanpa token di URL** (hanya sesi + `uuid`).
- **Formulir** (`GET /panitia/pendaftar/{pendaftaran}/cetak/formulir`): seluruh data anak dan orang tua, isian tahap 1, tahap 2 bila ada, biaya pendidikan + pilihan IAP, daftar berkas dan status, kolom tanda tangan wali dan panitia. NIK **utuh** (arsip sekolah), tetapi **tanpa** `catatan_panitia` dan `catatan_wawancara`.
- **Bukti pendaftaran** (`GET /panitia/pendaftar/{p}/cetak/bukti` dan `GET /akun/pendaftaran/{p}/cetak/bukti`): nomor peserta, nama anak, sekolah, gelombang, tanggal kirim, daftar berkas, status **tampilan walisantri** dari `StatusTampilService` (hasil Diterima/Ditolak/Cadangan **tidak bocor** sebelum pengumuman), NIK **disamarkan** (empat digit terakhir), catatan "Simpan bukti ini; bawa saat daftar ulang". QR dilewati sesuai prompt.
- **Kuitansi** (`GET /panitia/pembayaran/{pembayaran}/cetak/kuitansi` dan `GET /akun/pembayaran/{pembayaran}/cetak/kuitansi`): hanya pembayaran **`disetujui`** (selain itu 403), nomor turunan terkontrol `KWT-{kode}-{tahun}-{id 5 digit}` (tahun dari `gelombang.tahunAjaran`), komponen, nominal, metode, tanggal, nama penerima, dan tanda **"Bukan bukti lunas SPP"**. Kuitansi walisantri hanya miliknya; milik akun lain → 404.
- Kartu akun staf dan walisantri kini memakai `layouts/cetak`.

### Batas laju, log, dan jaring pengaman

- `AppServiceProvider::batasLajuUnduhan()`: `ekspor` 5/menit, `zip` 5/menit, `cetak` 120/jam — per pengguna (cadangan per IP), di atas batas → **429**.
- Setiap ekspor, ZIP, dan cetak menulis `log_aktivitas` berisi siapa, sekolah, jenis, penyaring, **jumlah baris**, dan **daftar kolom** — tanpa isi data pribadi.
- Perintah `spmb:bersihkan-tmp` menghapus file `tmp/` yang lebih tua dari `tmp_umur_menit`, didaftarkan `Schedule::command(BersihkanTmp::class)->hourly()` di `routes/console.php`.

## 2. Tes yang dijalankan

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **454 passed (2601 assertions)**, 0 gagal |
| `php artisan migrate:fresh --seed` | bersih di database kosong MySQL: 13 berkas migrasi, 2 seeder |
| `npm run build` | berhasil (vite 7.3.8); CSS 72,69 kB, JS 132,36 kB |
| `php artisan view:cache` lalu `view:clear` | seluruh Blade terkompilasi tanpa galat |
| `php artisan route:list --except-vendor` | **145 rute** (10 rute baru, lihat bagian 3) |
| `composer audit` | tanpa kerentanan; 2 paket transitif *abandoned* (lihat bagian 4) |
| Pemeriksaan `dd()`/`dump()`/`console.log` | bersih di `app/`, `resources/views/`, `resources/js/` |

Baseline sebelum tahap ini: **373 tes / 2302 asersi**. Tahap 5 menambah **81 tes baru** (22 ekspor + 14 ZIP + 13 bukti + 9 formulir + 12 kuitansi + 8 rekap + 3 perintah); total suite kini **454 tes / 2601 asersi**.

Pemetaan 13 butir tes wajib Tahap 5:

| Butir | Berkas tes | Jumlah |
| --- | --- | --- |
| 1 Matriks akses ekspor, ZIP, cetak per peran | `tests/Feature/Keamanan/EksporPendaftarTest.php`, `ZipBerkasTest.php`, `CetakBuktiTest.php`, `CetakFormulirTest.php`, `CetakKuitansiTest.php` | tercakup di butir 2–9 |
| 2 Isolasi sekolah (ekspor tanpa baris sekolah B; ZIP dan cetak sekolah B 404) | `EksporPendaftarTest` (3), `ZipBerkasTest` (1), `CetakBuktiTest` (1), `CetakFormulirTest` (1), `CetakKuitansiTest` (1) | 7 |
| 3 Ekspor: hanya terkirim, penyaring, daftar putih, sensitif, kolom terlarang, NIK teks | `EksporPendaftarTest` | 9 |
| 4 Formula injection xlsx dan csv | `EksporPendaftarTest` | 3 |
| 5 Batas baris dan laju 429 (ekspor, ZIP) | `EksporPendaftarTest` (2), `ZipBerkasTest` (1) | 3 |
| 6 ZIP: isi, nama entri, zip-slip, berkas hilang, >40 MB, folder sementara bersih | `ZipBerkasTest` | 14 |
| 7 Cetak bukti: tidak bocor sebelum pengumuman, NIK disamarkan, pemilik lain 404, draf 404 | `CetakBuktiTest` | 13 |
| 8 Cetak formulir: tanpa catatan internal, XSS ter-escape | `CetakFormulirTest` | 9 |
| 9 Kuitansi: hanya disetujui, nomor terkontrol & unik, milik orang lain 404 | `CetakKuitansiTest` | 12 |
| 10 Log ekspor, ZIP, dan cetak tanpa isi data | `EksporPendaftarTest` (1), `ZipBerkasTest` (1), `CetakBuktiTest` (1), `CetakFormulirTest` (1), `CetakKuitansiTest` (1), `EksporRekapTest` (1) | 6 |
| 11 Rekap admin/yayasan hanya angka; yayasan tanpa ekspor per orang | `EksporRekapTest` | 8 |
| 12 Perintah `spmb:bersihkan-tmp` | `tests/Feature/BersihkanTmpCommandTest.php` | 3 |
| 13 Regresi seluruh tes Tahap 1–4 | seluruh suite | 373 tes, 2302 asersi |

Butir keamanan `docs/keamanan.md` yang diselesaikan dan dites pada tahap ini: **K-03** (matriks diperluas ke rute ekspor, ZIP, cetak, rekap), **K-05/K-07** (berkas hanya lewat disk privat, NIK disamarkan di bukti, NIK utuh hanya di formulir arsip dan ekspor admin berkonfirmasi), **K-06** (daftar putih kolom, escaping), **K-08** (log setiap unduhan/cetak), **K-09** (batas laju ekspor/ZIP/cetak), **K-18** (yayasan agregat saja; super admin tanpa rute ekspor), **K-20** (seluruh delapan butir).

## 3. Daftar rute

Total **145 rute** (sebelumnya 135). Sepuluh rute baru Tahap 5:

```
GET|HEAD  admin/rekap.xlsx                                    admin.rekap.unduh            web,auth,peran:admin,throttle:ekspor
GET|HEAD  akun/pembayaran/{pembayaran}/cetak/kuitansi          walisantri.pembayaran.cetak.kuitansi  web,auth,peran:walisantri,throttle:cetak
GET|HEAD  akun/pendaftaran/{pendaftaran}/cetak/bukti           walisantri.pendaftaran.cetak.bukti    web,auth,peran:walisantri,throttle:cetak
GET|HEAD  panitia/ekspor                                      panitia.ekspor.index         web,auth,peran:panitia,admin
POST      panitia/ekspor                                      panitia.ekspor.unduh         web,auth,peran:panitia,admin,throttle:ekspor
GET|HEAD  panitia/pembayaran/{pembayaran}/cetak/kuitansi       panitia.pembayaran.cetak.kuitansi     web,auth,peran:panitia,admin,throttle:cetak
GET|HEAD  panitia/pendaftar/{pendaftaran}/berkas.zip           panitia.pendaftar.zip        web,auth,peran:panitia,admin,throttle:zip
GET|HEAD  panitia/pendaftar/{pendaftaran}/cetak/bukti          panitia.pendaftar.cetak.bukti         web,auth,peran:panitia,admin,throttle:cetak
GET|HEAD  panitia/pendaftar/{pendaftaran}/cetak/formulir       panitia.pendaftar.cetak.formulir      web,auth,peran:panitia,admin,throttle:cetak
GET|HEAD  yayasan/rekap.xlsx                                  yayasan.rekap.unduh          web,auth,peran:yayasan,throttle:ekspor
```

Tidak ada rute `super_admin` yang memuat data pendaftar. Semua rute berparameter memakai `uuid` objek (`Pendaftaran`, `Pembayaran`) dan data sekolah lain menghasilkan 404.

## 4. Paket tambahan dan `composer audit`

- `openspout/openspout` **v5.13.0** (satu paket baru, sesuai pilihan di prompt). Tidak ada pustaka PDF, tidak ada paket peran, tidak ada paket lain.
- `composer audit`: **"No security vulnerability advisories found."**
- Dua paket **abandoned** (tanpa kerentanan, sudah ada sejak Tahap 1): `dragon-code/contracts` dan `dragon-code/support`, keduanya ketergantungan transitif `laravel-lang/lang` (terjemahan Indonesia). Tidak ada penggantinya yang aman untuk dipasang sekarang; akan dipantau saat pembaruan dependensi. Bukan berasal dari paket Tahap 5.

## 5. Penyimpangan dari dokumen dan alasannya

1. **Rute cetak walisantri memakai pola rute Tahap 2, bukan pola prompt.**
   Prompt menulis `GET /akun/anak/{anak}/pendaftaran/{pendaftaran}/cetak/bukti`; implementasi memakai `GET /akun/pendaftaran/{pendaftaran}/cetak/bukti` dan `GET /akun/pembayaran/{pembayaran}/cetak/kuitansi`, konsisten dengan seluruh rute walisantri yang sudah ada (mis. `/akun/pendaftaran/{p}/berkas`). Kepemilikan tetap diperiksa tiga lapis: Policy, `sudahDikirim()`, dan `pendaftaran.anak.user_id === pengguna`. Fungsinya identik; `uuid` pendaftaran sudah unik per anak sehingga `{anak}` tidak menambah keamanan.
2. **Nama entri ZIP selalu menyertakan `_urutan`.**
   Prompt menulis `{nomor_peserta}_{kode_jenis_berkas}_{urutan}.{ekstensi}`; diimplementasikan apa adanya (urutan 1-based selalu ada), bukan hanya ketika satu jenis muncul lebih dari sekali. Hasilnya nama selalu unik dan dapat diprediksi, sesuai bunyi prompt.
3. **NIK/no. KK "sebagai teks" dicapai dengan menulis seluruh nilai sel sebagai string.**
   openspout menulis nilai string sebagai teks (inline string) tanpa prefiks tampil. Pendekatan prefiks `'` (yang dipakai `EksporSanitizer` untuk formula) akan tampil di sel dan tidak dipakai untuk kolom teks biasa.
4. **Deteksi "ikut sensitif" memakai kolom yang dicentang.**
   Formulir ekspor tidak punya input `sensitif` terpisah; `EksporRequest::ikutSensitif()` menganggap permintaan menyertakan data sensitif bila ada kunci sensitif di `kolom[...]`. Konfirmasi `konfirmasi_sensitif` tetap wajib untuk admin, dan `panitia` tetap tidak mendapatkannya.
5. **`ZipArchive::CLOSED` tidak ada di build PHP ini.**
   Penggantinya variabel lokal `$tertutup` + `@$zip->close()` di `finally`. Perilaku (ZIP tertutup rapi, file sementara dapat dihapus) diuji.
6. **`CetakService::dataKuitansi()` melakukan eager-load `pendaftaran.gelombang.tahunAjaran`.**
   Diperlukan untuk menghitung tahun pada nomor kuitansi tanpa lazy loading (aplikasi memakai `preventLazyLoading()` di luar produksi). Tidak ada perubahan struktur data.
7. **`layouts/cetak` mengambil kop sekolah dari `$sekolah`.**
   Controller cetak mengirim `sekolah`; halaman lain yang memakai layout ini (kartu akun) memakai nama yayasan sebagai cadangan.
8. **Repositori ini bukan repositori git** (berlanjut dari laporan Tahap 3 dan 4).
   `git commit` tidak dapat dijalankan karena folder kerja tidak diinisialisasi sebagai repo. Tidak ada `.env`, `storage/`, `vendor/`, atau `node_modules/` yang disertakan ke mana pun.

## 6. Bug yang ditemukan dan diperbaiki selama tes

- **`EksporRequest::format()` fatal error.** Nama metode `format()` bertabrakan dengan `Illuminate\Http\Request::format($default = 'html')` dan membuat PHP berhenti ("Declaration ... must be compatible"). Diganti `formatEkspor()` dan pemanggilnya di `EksporController` disesuaikan.
- **Berkas berekstensi di luar daftar putih membuat ZIP gagal 500.** Sebelumnya ekstensi diperiksa saat menamai entri, tetapi berkasnya tetap dihitung "dipakai" sehingga ZIP bisa kosong lalu dialirkan sebagai file tak ada. Kini ekstensi daftar putih diperiksa sejak tahap pengumpulan (`ekstensiSah()`), berkas tersebut dilewati dan dicatat seperti berkas hilang.
- **`EksporService::rekapSekolah()` mengirim bentuk data yang salah** (`[['sekolah' => $rekap]]` alih-alih `[$rekap]`), sehingga nama sekolah pada rekap terbaca sebagai array. `tulisRekap()` kini menerima model `Sekolah` maupun array.
- **Jumlah jenis berkas dihitung per baris ekspor** (N+1). Dihitung sekali per ekspor dan diteruskan sebagai parameter `$totalJenis`; kolom `berkas_sesuai` menampilkan `x/total` yang benar.
- **Berkas sementara tertinggal bila penulisan ekspor gagal di tengah jalan.** Ditambah `try/catch` yang menghapus file sementara sebelum melempar galat.
- **Asersi tes rekap salah bentuk** (menganggap `bacaXlsx()` mengembalikan daftar baris, padahal peta `nama lembar => baris`). Diperbaiki di tes, bukan kode.

## 7. Cara mencoba manual di lokal

1. Jalankan `php artisan migrate:fresh --seed`. Seeder mencetak kata sandi acak untuk admin/panitia tiap sekolah dan empat akun walisantri.
2. **Ekspor dengan penyaring.** Masuk sebagai **admin SD** (nomor HP dari seeder) → menu **Pendaftar** → atur penyaring (mis. status, gelombang) → tombol **Unduh Excel** → di halaman ekspor centang beberapa kolom, pilih `.xlsx`, unduh. Buka di Excel atau LibreOffice: lembar **Data** berisi baris sesuai penyaring, lembar **Ringkasan** berisi tanggal, pelaku, penyaring, dan jumlah baris. Ulangi dengan format `.csv` — terbuka rapi di Excel (UTF-8 BOM, kolom terpisah koma).
3. **Formula injection.** Ubah sementara nama satu anak lewat panel panitia menjadi `=HYPERLINK("http://evil","klik")`, lalu ekspor. Di Excel/LibreOffice sel tampil sebagai teks berawalan `'`, bukan rumus yang berjalan. Kembalikan nama setelah uji.
4. **Kolom sensitif.** Di halaman ekspor sebagai **admin**, bagian "Data pribadi sensitif" hanya muncul untuk admin; centang NIK tanpa konfirmasi → NIK tidak ikut; centang konfirmasi → NIK ikut dan tetap teks (tidak menjadi notasi ilmiah). Masuk sebagai **panitia**: bagian sensitif tidak ada, dan mengirim `kolom[nik]` manual tetap diabaikan.
5. **Batas baris.** Sementara turunkan `config/ekspor.batas_baris` (mis. 1) lalu ekspor tanpa penyaring → pesan "Persempit dengan penyaring".
6. **ZIP.** Buka detail pendaftar yang punya berkas → **Unduh Berkas (ZIP)**. Periksa nama entri `{nomor_peserta}_{jenis}_{urutan}.{ext}`. Hapus satu berkas dari `storage/app/private/berkas/...` lalu unduh lagi → ZIP tetap terbentuk (berkas hilang dilewati) dan muncul catatan di log. Periksa `storage/app/private/tmp/` kosong setelah unduhan.
7. **Cetak formulir.** Di detail pendaftar → **Cetak Formulir** (tab baru) → tombol **Cetak** → pilih "Simpan sebagai PDF". Periksa NIK utuh ada, catatan panitia/wawancara tidak ada.
8. **Cetak bukti.** Masuk sebagai **walisantri** pemilik pendaftaran terkirim → detail → **Cetak Bukti Pendaftaran** → simpan PDF. NIK tampil `************0001`; sebelum admin menekan Umumkan Hasil, kata "Diterima/Ditolak/Cadangan" tidak muncul. Setelah Umumkan Hasil, status tampil.
9. **Kuitansi.** Sebagai panitia, setujui satu pembayaran → **Cetak Kuitansi** → nomor `KWT-SD-2027-000NN`, tanda "Bukan bukti lunas SPP". Pembayaran yang masih menunggu/ditolak → 403.
10. **Rekap.** `admin/rekap` → **Unduh Rekap (Excel)**: hanya angka. Masuk **yayasan** → `yayasan/rekap` → **Unduh Rekap (Excel)**: agregat empat sekolah, tanpa nama/nomor peserta. Coba `/panitia/ekspor` sebagai yayasan → 403.
11. **Jaring pengaman.** `php artisan spmb:bersihkan-tmp` → menghapus file `tmp/` lebih dari satu jam; `php artisan schedule:list` menampilkan jadwal per jam.
12. **Cek lebar layar**: halaman ekspor dan cetak dicek pada 360 px (kartu dan formulir satu kolom) dan 1280 px. Halaman cetak memakai lebar A4 tetap dan menyembunyikan tombol saat dicetak (`print:hidden`).

## 8. Pertanyaan yang masih terbuka

1. **Nilai resmi `orang_tua.status_hidup`** masih `Masih hidup` / `Sudah wafat` (belum dijawab sejak Tahap 2).
2. **Berkas wajib yang belum siap** boleh dilewati dengan centang konfirmasi (keputusan Tahap 3). Apakah tetap peringatan atau dijadikan penghalang keras?
3. **NIK utuh untuk panitia/admin** di tab Data (keputusan Tahap 3) — dan kini juga di cetak formulir untuk panitia. Mohon konfirmasi apakah cetak formulir perlu dibatasi hanya admin, atau tetap panitia+admin seperti sekarang.
4. **Pengesahan kolom migrasi Tahap 4** ke `docs/struktur-tabel.md`: `sekolah.singkatan`, `sekolah.instagram`, `jenis_berkas.petunjuk`, `rincian_biaya.catatan`, `rincian_biaya.aktif`, dan `konten_sekolah.sekolah_id` nullable.
5. **Kolom ekspor di luar daftar prompt**: `config/ekspor.php` memuat juga `jenis`, `program`, dan `kelengkapan` penyaring agar sama dengan daftar pendaftar Tahap 3. Bila yayasan ingin daftar kolom lebih sempit, cukup sunting `config/ekspor.php`.
6. **Batas laju cetak 120/jam** terasa longgar untuk satu pengguna; bila ingin lebih ketat (mis. 60/jam), ubah satu baris di `AppServiceProvider::batasLajuUnduhan()`.
7. **Pemeriksaan visual 360 px dan 1280 px** masih berupa analisis statis markup dan uji coba manual; belum dengan tangkapan layar peramban otomatis.
8. **`dragon-code/*` (abandoned)** perlu diganti saat `laravel-lang/lang` diperbarui; belum ada tindakan sekarang karena tanpa kerentanan.
9. **Alamat situs pada teks pengumuman dan nama file ekspor** memakai `APP_URL` saat ini; domain produksi dipastikan di tahap 6.
