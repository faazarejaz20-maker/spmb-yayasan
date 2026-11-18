# Laporan Tahap 3 — Panel panitia

Tanggal: 11 November 2026
Lingkup: seluruh panel panitia di bawah awalan `/panitia` (`prompts/tahap-3-panitia.md`). Admin sekolah mewarisi seluruh rute panitia.

## 1. Yang selesai

- **Service** (memperluas yang sudah ada, tanpa menyalin logika Tahap 2):
  - `PengumumanService::sudahDiumumkan()` — satu-satunya pemeriksa baris `pengumuman` untuk sekolah + gelombang.
  - `KuotaService` — `terisi()`, `penuh()`, `sisa()`, `diterima()`; terisi = `status_hasil = Diterima` dan `status_murid` bukan `Mengundurkan diri`.
  - `StatusHasilService` — `pilihan()` (status yang boleh dipilih + alasan tombol nonaktif), `ubah()` (seluruh matriks perpindahan, kewajiban catatan, pengecekan berkas, kuota, kunci baris, riwayat, log), `catatanPerbaikan()` untuk walisantri.
  - `DaftarUlangService` — `catat()`, `batalSelesai()` (hanya admin, catatan wajib), `mengundurkanDiri()`.
  - `AkunWalisantriService` — `buatAkun()`, `aturUlang()`, `pindahAnak()`, dibantu `Support\KataSandiSementara` (10 karakter huruf/angka tanpa `0 O 1 l I`, `random_int`).
  - `PembayaranService` diperluas: antrean bukti, setujui, tolak (alasan wajib), catat tunai, dan pemrosesan ulang oleh admin dengan catatan.
  - `BerkasService` diperluas: `periksa()`, unggah atas nama walisantri, `adaPerluPerbaikan()`, `wajibBelumSiap()`.
  - `PendaftaranService` diperluas untuk pelaku staf: simpan data, periksa syarat, kirim pendaftaran offline, dan ringkasan bersama.
- **Policy** `AkunWalisantriPolicy` (baru) serta perluasan `PendaftaranPolicy`, `BerkasPolicy`, dan `PembayaranPolicy` untuk panitia/admin sekolah sendiri.
- **FormRequest** baru: `DataPendaftarRequest`, `PeriksaBerkasRequest`, `KeputusanRequest`, `CatatanPanitiaRequest`, `PembayaranTunaiRequest`, `TolakPembayaranRequest`, `DaftarUlangRequest`, `MengundurkanDiriRequest`, `OfflineAkunRequest`, `OfflineCariRequest`, `OfflineSekolahRequest`, `OfflineAnakBaruRequest`, `PindahAnakRequest`.
- **Controller** `Panitia\PanitiaController` (dasbor, daftar, detail, data, catatan), `BerkasController`, `KeputusanController`, `PembayaranController`, `DaftarUlangController`, `AkunController`, dan `OfflineController` (alur 8 langkah).
- **Tampilan** `layouts/panel`, `x-sidebar`, dasbor (5 kartu, kuota per gelombang, antrean tindakan), daftar pendaftar (cari, saring, urut, halaman 25, spanduk kuota, draf offline), detail 5 tab (data, berkas, pembayaran, daftar ulang, riwayat), antrean pembayaran, halaman pendaftar offline, dan kartu akun. Komponen `tabel`, `tab`, `modal-konfirmasi`, `lencana-status` dilengkapi untuk status pembayaran/berkas. Tombol salin dan cetak kartu memakai JavaScript tanpa ekspresi inline (aman CSP); saat dicetak sidebar, header, dan tombol disembunyikan. Kehadiran kedua tombol diuji pada `PanitiaOfflineTest`.
- **Halaman walisantri** detail status kini menampilkan catatan `Perlu perbaikan` dari baris `riwayat_status` terakhir (hanya saat status sedang Perlu perbaikan) beserta catatan per berkas.
- **Atur ulang kata sandi mandiri lewat email**: `POST /lupa-password`, `GET/POST /reset-password`, notifikasi antrean `ResetKataSandi` dengan tautan bertanda tangan 60 menit, token sekali pakai dari broker Laravel, hanya akun `walisantri` dan aktif, sesi lama dihapus, serta batas 3/jam per email dan 10/jam per IP.
- **Perintah** `php artisan spmb:atur-ulang-password {nomor_hp}`: interaktif, hanya untuk akun staf, menetapkan `wajib_ganti_password = true`, menghapus sesi akun.
- **Seeder** `DataContohSeeder` tetap menyediakan empat keadaan (draf, terkirim, diterima + pengumuman + pembayaran menunggu + daftar ulang, perlu perbaikan) dan mencetak kredensial panitia/admin di setiap jalankan.
- **Tanpa penghapusan data**: tidak ada tombol atau rute hapus pendaftar. Data hanya berubah status.

## 2. Tes yang dijalankan

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **253 passed (1224 assertions)**, 0 gagal |
| `php artisan migrate:fresh --seed` | bersih di database kosong MySQL: 20 migrasi, 2 seeder |
| `npm run build` | berhasil; CSS 69,11 kB, JS 131,82 kB |
| `php artisan view:cache` | seluruh Blade berhasil dikompilasi |
| `php artisan route:list --except-vendor` | 78 rute, 37 di antaranya `/panitia` (lihat bagian 3) |
| `php -l` pada seluruh berkas tes baru | tidak ada kesalahan sintaks |

Pemetaan 18 butir tes wajib Tahap 3 (96 tes baru; 157 tes Tahap 1–2 tetap lulus):

| Butir | Berkas tes | Jumlah |
| --- | --- | --- |
| 1 Matriks akses `/panitia`, 3 Draf walisantri | `tests/Feature/PanitiaAksesTest.php` | 6 |
| 2 Isolasi sekolah (buka, ubah, berkas, keputusan, bukti, unduh) | `tests/Feature/Keamanan/IsolasiSekolahPanitiaTest.php` | 3 |
| 4 Daftar: cari, saring, urut, NIK, wildcard literal | `tests/Feature/PanitiaDaftarTest.php` | 5 |
| 5–7 Matriks status, kuota, perlu perbaikan | `tests/Feature/StatusHasilPanitiaTest.php` | 14 |
| 8 Berkas: pemeriksaan, catatan wajib, unggah atas nama | `tests/Feature/PanitiaBerkasTest.php` | 8 |
| 9 Pembayaran: setujui, tolak, tunai, antrean | `tests/Feature/PanitiaPembayaranTest.php` | 8 |
| 10 Daftar ulang dan pengunduran diri | `tests/Feature/PanitiaDaftarUlangTest.php` | 9 |
| 11 Pendaftar offline | `tests/Feature/PanitiaOfflineTest.php` | 6 |
| 12 Atur ulang kata sandi, 14 Pindahkan anak | `tests/Feature/PanitiaAkunTest.php` | 9 |
| 13 Reset kata sandi lewat email | `tests/Feature/ResetKataSandiEmailTest.php` | 7 |
| 15 Mass assignment formulir panitia | `tests/Feature/Keamanan/PanitiaMassAssignmentTest.php` | 6 |
| 16 Log aktivitas dan riwayat status | `tests/Feature/Keamanan/PanitiaLogTest.php` | 4 |
| 17 XSS di seluruh halaman panitia | `tests/Feature/Keamanan/PanitiaXssTest.php` | 4 |
| 18 Kebocoran data internal ke walisantri | `tests/Feature/Keamanan/KebocoranWalisantriTahap3Test.php` | 4 |
| Tambahan: perintah pemulihan kata sandi staf | `tests/Feature/AturUlangPasswordConsoleTest.php` | 3 |

Tes Tahap 1–2 tetap lulus tanpa perubahan, termasuk `MatriksAksesTest`, `KeamananTest`, `IsolasiSekolahTest`, `KebocoranHasilTest`, `MassAssignmentTest`, `NikTest`, dan `RateLimitTest`.

## 3. Daftar rute

Seluruh rute `/panitia` memakai middleware `web, auth, peran:panitia,admin`. Rute berparameter memakai `uuid`; akses ke data sekolah lain menghasilkan **404**.

```
GET|HEAD  panitia                                                    panitia.beranda
GET|HEAD  panitia/pendaftar                                          panitia.pendaftar.index
GET|HEAD  panitia/pendaftar/{pendaftaran}                            panitia.pendaftar.detail
PUT       panitia/pendaftar/{pendaftaran}/data                       panitia.pendaftar.data              throttle:20,1
PUT       panitia/pendaftar/{pendaftaran}/catatan                    panitia.pendaftar.catatan
POST      panitia/pendaftar/{pendaftaran}/berkas/{berkas}/periksa    panitia.pendaftar.berkas.periksa
POST      panitia/pendaftar/{pendaftaran}/berkas                     panitia.pendaftar.berkas.simpan     throttle:20,1
GET|HEAD  panitia/berkas/{berkas}                                    panitia.berkas.tampil               throttle:60,1
POST      panitia/pendaftar/{pendaftaran}/keputusan                  panitia.pendaftar.keputusan
GET|HEAD  panitia/pembayaran                                         panitia.pembayaran.index
GET|HEAD  panitia/pembayaran/{pembayaran}                            panitia.pembayaran.tampil           throttle:60,1
POST      panitia/pembayaran/{pembayaran}/setujui                    panitia.pembayaran.setujui
POST      panitia/pembayaran/{pembayaran}/tolak                      panitia.pembayaran.tolak
POST      panitia/pendaftar/{pendaftaran}/pembayaran-tunai           panitia.pendaftar.pembayaran-tunai
PUT       panitia/pendaftar/{pendaftaran}/daftar-ulang               panitia.pendaftar.daftar-ulang
POST      panitia/pendaftar/{pendaftaran}/mengundurkan-diri          panitia.pendaftar.mengundurkan-diri
GET|HEAD  panitia/kartu-akun                                         panitia.akun.kartu
POST      panitia/pendaftar/{pendaftaran}/atur-ulang-password        panitia.pendaftar.atur-ulang-password
POST      panitia/pendaftar/{pendaftaran}/pindah-anak                panitia.pendaftar.pindah-anak
GET|HEAD  panitia/offline                                            panitia.offline.index
POST      panitia/offline/akun                                       panitia.offline.akun                throttle:20,1
POST      panitia/offline/cari                                       panitia.offline.cari                throttle:20,1
GET|HEAD  panitia/offline/orang-tua                                  panitia.offline.orang-tua
PUT       panitia/offline/orang-tua                                  panitia.offline.orang-tua.simpan    throttle:20,1
GET|HEAD  panitia/offline/sekolah                                    panitia.offline.sekolah
POST      panitia/offline/sekolah                                    panitia.offline.sekolah.simpan      throttle:20,1
GET|HEAD  panitia/offline/anak-baru                                  panitia.offline.anak-baru
PUT       panitia/offline/anak-baru                                  panitia.offline.anak-baru.simpan    throttle:20,1
GET|HEAD  panitia/offline/{pendaftaran}/langkah/{n}                  panitia.offline.langkah
PUT       panitia/offline/{pendaftaran}/langkah/{n}                  panitia.offline.langkah.simpan      throttle:20,1
GET|HEAD  panitia/offline/{pendaftaran}/berkas                       panitia.offline.berkas.index
POST      panitia/offline/{pendaftaran}/berkas                       panitia.offline.berkas.simpan       throttle:20,1
DELETE    panitia/offline/{pendaftaran}/berkas/{berkas}              panitia.offline.berkas.hapus
GET|HEAD  panitia/offline/{pendaftaran}/biaya                        panitia.offline.biaya
PUT       panitia/offline/{pendaftaran}/biaya                        panitia.offline.biaya.simpan        throttle:20,1
GET|HEAD  panitia/offline/{pendaftaran}/ringkasan                    panitia.offline.ringkasan
POST      panitia/offline/{pendaftaran}/kirim                        panitia.offline.kirim               throttle:10,1
```

Rute atur ulang kata sandi mandiri (peran tamu):

```
GET|HEAD  lupa-password               lupa-password              guest
POST      lupa-password               lupa-password.simpan       guest, throttle:10,1
GET|HEAD  reset-password/{token}      password.reset             guest, signed
POST      reset-password              password.reset.simpan      guest, throttle:10,1
```

Middleware tambahan pada grup `web` tetap: `HeaderKeamanan`, `AkunAktif`, `SesiKedaluwarsa`, `WajibGantiPassword`.

## 4. Paket tambahan

Tidak ada paket baru pada Tahap 3. Antrean memakai driver `database`, email memakai SMTP/`log` sesuai konfigurasi Tahap 1, dan token reset memakai broker kata sandi bawaan Laravel. Tidak ada Livewire, Inertia, React, Vue, SPA, atau paket peran.

## 5. Penyimpangan dari dokumen dan alasannya

1. **Rute tambahan `GET /panitia/kartu-akun`.**
   Kata sandi sementara harus ditampilkan satu kali. Nilainya disimpan di sesi `kartu_akun`, lalu halaman ini membacanya sekali dan menghapusnya. Rute ini tidak ada di tabel rute prompt, tetapi diperlukan oleh bagian 8 dan 9.
2. **Alur offline dipecah menjadi beberapa rute dan memakai sesi sebelum draf ada.**
   Prompt meminta `GET/POST /panitia/offline/...`. Sebelum pendaftaran draf terbentuk, identitas akun dan pilihan sekolah disimpan di sesi (`offline_user_id`, `pendaftaran_baru`), bukan di URL. Setelah draf ada, seluruh langkah memakai `uuid` pendaftaran.
3. **Draf offline hanya dibuka lewat rute offline.**
   `GET /panitia/pendaftar/{pendaftaran}` menolak draf (404). Draf hanya dapat dilanjutkan lewat `/panitia/offline/...` dan hanya oleh staf sekolah pembuatnya. Ini menjaga aturan "panitia hanya melihat pendaftaran terkirim" tanpa mengunci alur offline.
4. **`PendaftaranPolicy::lihat()` untuk staf tidak membatasi draf.**
   Pembatasan draf dilakukan di controller/rute offline, karena policy yang sama dipakai untuk mengelola draf offline. Tidak ada halaman panitia biasa yang menampilkan draf.
5. **Reset email menetapkan `wajib_ganti_password = false`.**
   Walisantri memilih sendiri kata sandi barunya, sehingga tidak perlu dipaksa mengganti lagi. Sesi lama tetap dihapus. Token tetap berlaku 60 menit dan sekali pakai.
6. **Batas laju reset email dua lapis.**
   `RateLimiter` menegakkan 3 permintaan/jam per email dan 10/jam per IP; rute juga memakai `throttle:10,1`. Batas per email yang lebih ketat yang diuji (permintaan keempat menghasilkan 429).
7. **Kartu akun dicetak lewat peramban.**
   Tombol "Cetak kartu" memanggil `window.print()`; tidak ada PDF server. Tombol "Salin" memakai Clipboard API dengan fallback `execCommand`, ditulis tanpa ekspresi inline agar tetap patuh CSP `script-src 'self'`.
8. **Pemeriksaan kuota mengunci baris `gelombang`.**
   Di dalam transaksi, baris `pendaftaran` dan `gelombang` dikunci (`lockForUpdate`) sebelum menghitung. Tes "dua permintaan berurutan pada kuota 1" dijalankan berurutan karena SQLite tes tidak mendukung konkurensi sungguhan; penguncian tetap diterapkan untuk MySQL produksi.
9. **Pemrosesan ulang pembayaran oleh admin memakai kolom `catatan` sebagai alasan.**
   Panitia tidak dapat memproses ulang pembayaran yang sudah diputuskan; admin dapat, dengan catatan wajib, dan tindakan itu dicatat di log.
10. **Perpindahan anak mencatat id akun, bukan nama.**
    Log `pindah_anak` hanya memuat `akun_asal` dan `akun_tujuan` berupa id, tanpa nama atau data sensitif.
11. **Perintah reset staf hanya lewat CLI.**
    `spmb:atur-ulang-password {nomor_hp}` tidak punya padanan web, sesuai batas Tahap 3 (pengaturan akun staf ada di Tahap 4). Perintah menolak akun non-staf.
12. **Repositori ini bukan repositori git.**
    `AGENTS.md` meminta satu komit per sub-tugas; `git commit` tidak dapat dijalankan karena folder kerja tidak diinisialisasi sebagai repo. Tidak ada `.env`, `storage/`, `vendor/`, atau `node_modules/` yang disertakan.

## 6. Bug yang ditemukan dan diperbaiki selama tes

- **Penghapusan sesi gagal bila tabel `sessions` tidak ada.** Lingkungan tes SQLite tidak memiliki tabel itu, sehingga atur ulang kata sandi melempar galat. Penghapusan sesi sekarang dijaga `Schema::hasTable('sessions')`.
- **Pembatalan daftar ulang yang sudah selesai tidak meninggalkan jejak.** `DaftarUlangService::batalSelesai()` kini menulis log `batal_daftar_ulang` dan riwayat status murid.
- **Nama pembantu tes bertabrakan dengan PHPUnit.** `status()` tidak bisa dipakai karena `TestCase::status()` final; pembantu diganti `aturStatus()`.
- **Kartu akun belum punya tombol Salin dan Cetak.** Ditemukan saat menyusun laporan; ditambahkan bersama gaya cetak (`print:hidden` pada sidebar, header, dan tombol) serta diuji.

## 7. Cara mencoba manual di lokal

1. Jalankan `php artisan migrate:fresh --seed`. Seeder mencetak nomor HP dan kata sandi acak untuk admin/panitia tiap sekolah serta empat akun walisantri.
2. Masuk dengan akun **Panitia SD**. Dasbor menampilkan lima kartu angka, kemajuan kuota gelombang SD, dan antrean tindakan.
3. Buka **Pendaftar** → cari `Fatimah` atau `SD-2027-0001`. Di detail:
   - tab **Berkas**: tandai "Sesuai" atau "Perlu perbaikan" (catatan wajib);
   - panel **Keputusan**: ubah status sesuai pilihan yang aktif;
   - tab **Pembayaran**: setujui bukti transfer `menunggu`, atau catat pembayaran tunai;
   - tab **Daftar ulang**: isi kehadiran dan catatan wawancara, lalu "Selesai, jadikan murid resmi";
   - tab **Riwayat**: periksa `riwayat_status` dan `log_aktivitas`.
4. Buka **Pembayaran** untuk melihat antrean bukti transfer sekolah itu.
5. Buka **Pendaftar offline** → "Buat akun baru" → kartu akun muncul sekali; coba **Salin kata sandi** dan **Cetak kartu** → lanjutkan data orang tua, pilih gelombang, isi anak baru, langkah 3–5, unggah berkas, biaya, lalu **Kirim**. Periksa nomor peserta `SD-2027-xxxx`.
6. Dari detail pendaftar, coba **Atur ulang kata sandi** (kartu tampil sekali) dan **Pindahkan anak** ke akun lain yang masih dalam lingkup sekolah.
7. Untuk reset email: isi email pada `/lupa-password` milik akun walisantri yang punya email, jalankan `php artisan queue:work` (antrean `database`), lalu buka tautan dari `storage/logs/laravel.log` (`MAIL_MAILER=log`).
8. Buka pendaftar `Abdullah Hakim` (`SD-2027-0002`) untuk melihat keadaan "Perlu perbaikan", lalu masuk sebagai walisantri Empat untuk memastikan catatan panitia tampil dan data internal tidak tampil.

## 8. Pertanyaan yang masih terbuka

1. **Nilai resmi `orang_tua.status_hidup`** masih `Masih hidup` / `Sudah wafat` (belum dijawab sejak Tahap 2).
2. **Kuota dan pengumuman** baru berupa data/service. Pembuatan baris `pengumuman`, pengaturan kuota, dan tombol Umumkan Hasil tetap menunggu Tahap 4. Mohon konfirmasi bahwa kuota hanya diubah admin sekolah.
3. **Berkas wajib yang belum siap** boleh diverifikasi dengan centang konfirmasi. Apakah sekolah ingin ini tetap sebagai peringatan, atau nanti dijadikan penghalang keras?
4. **Kata sandi sementara 10 karakter** dan kartu cetak web sudah sesuai prompt; perlu konfirmasi apakah sekolah membutuhkan ukuran kartu tertentu (mis. A6) atau cukup cetak halaman.
5. **Email reset** membutuhkan SMTP aktif dan pekerja antrean. Perlu dipastikan alamat pengirim dan isi surat disetujui yayasan.
6. **Pemeriksaan visual 360 px dan 1280 px** dilakukan lewat markup dan kelas responsif (`min-w-0`, `lg:`, tabel `overflow-x-auto`, grid yang menumpuk), belum dengan tangkapan layar peramban sungguhan.
7. **NIK utuh** tampil di tab Data untuk panitia/admin sesuai prompt; NIK tetap disamarkan di daftar. Mohon konfirmasi apakah seluruh panitia boleh melihat NIK utuh atau perlu dibatasi admin.
