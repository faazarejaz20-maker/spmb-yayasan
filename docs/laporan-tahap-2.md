# Laporan Tahap 2 — Pendaftaran oleh walisantri

Tanggal: 11 November 2026
Lingkup: seluruh pengalaman walisantri di bawah awalan `/akun` (`prompts/tahap-2-pendaftaran-walisantri.md`).

## 1. Yang selesai

- **Konfigurasi** `config/spmb.php`: batas berkas (2 MB, JPG/PNG/PDF, sisi maksimum 2400 px), folder privat berkas dan bukti pembayaran, awalan nomor peserta (`TKK`, `SD`, `SMP`, `KB`, `TK`, `TPA`), rentang usia per jenjang.
- **Migrasi korektif** `0001_01_06_000000_perbaiki_status_hasil_pendaftaran.php`: `pendaftaran.status_hasil` wajib terisi dengan nilai bawaan `Baru`. Pendaftaran draf dikenali dari `dikirim_pada` yang masih `null`, bukan dari status hasil.
- **Model** `Pendaftaran` dan `Anak` diperketat: kolom sensitif dikeluarkan dari `$fillable`, ditambah scope `terkirim()`, serta metode `masihDraf()`, `sudahDikirim()`, `bolehDiubah()`.
- **Exception**: `DataGandaException`, `SyaratKirimException` (membawa daftar kekurangan + tautan langkah), `BerkasTidakValidException`.
- **Kelas bantu** `App\Support\Samarkan` (penyamaran NIK dan nomor HP).
- **Service**:
  - `AturanIsian` — satu sumber katalog isian (`config/isian.php`) dikurangi pengaturan sekolah; isian tersembunyi tidak pernah diterima, isian inti selalu wajib.
  - `StatusTampil`/`StatusTampilService` — satu-satunya sumber label status untuk walisantri; hasil Diterima/Ditolak/Cadangan dipaksa `null` sebelum ada baris `pengumuman`.
  - `DeteksiGandaService` — nama lengkap (dinormalisasi) + tanggal lahir + sekolah; pesan tidak membocorkan identitas pendaftar lain.
  - `NomorPesertaService` — `AWALAN-TAHUN-URUTAN4` dengan `Cache::lock`, urut per awalan per tahun ajaran.
  - `PendaftaranService` — mulai pendaftaran, simpan langkah, simpan biaya, periksa syarat, kirim (transaksi + `lockForUpdate` + retry sekali), kirim perbaikan, ringkasan.
  - `BerkasService` — validasi tegas (nama berkas, ekstensi, `finfo`, apakah isi cocok dengan ekstensi, awalan `%PDF`), gambar dibaca ulang dengan GD (EXIF/rotasi, perkecil, hapus metadata), disimpan di disk privat dengan nama UUID.
  - `PembayaranService` — unggah bukti transfer hanya untuk pendaftar Diterima yang sudah diumumkan.
- **Policy** `AnakPolicy`, `OrangTuaPolicy`, `PendaftaranPolicy`, `BerkasPolicy`, `PembayaranPolicy`.
- **FormRequest** `OrangTuaRequest`, `MulaiPendaftaranRequest`, `AnakBaruRequest`, `LangkahPendaftaranRequest`, `BiayaPendaftaranRequest`, `KirimPendaftaranRequest`, `UnggahBerkasRequest`, `BuktiPembayaranRequest`, `Tahap2Request`.
- **Controller** `WalisantriController`, `OrangTuaController`, `PendaftaranController`, `BerkasController`, `PembayaranController`, `Tahap2Controller`.
- **Rute** `/akun` (39 rute total aplikasi), dengan throttle: simpan formulir/unggah 20/menit, unduh 60/menit, kirim 10/menit; nama rute berawalan `walisantri.`.
- **Tampilan** (Blade + Tailwind + Alpine, tanpa SPA): beranda akun, data orang tua, mulai pendaftaran, formulir bertahap 2–5, unggah berkas, biaya + pilihan IAP, ringkasan & kirim, sukses, detail status, hasil seleksi, pembayaran daftar ulang, formulir Tahap 2. Komponen baru: `stepper`, `tab`, `unggah-berkas`, `pilihan`, `tanggal`, `isian` (textarea/angka); `lencana-status`, `kosong`, `toast` diperbaiki.
- **Seeder** `DataContohSeeder` membuka satu gelombang per sekolah dan membuat empat akun walisantri contoh yang mencakup keempat keadaan: draf, terkirim (TKI program KB), Diterima + pengumuman + daftar ulang + bukti pembayaran + `status_murid` Resmi, dan Perlu perbaikan dengan catatan berkas.

## 2. Tes yang dijalankan

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **157 passed (570 assertions)**, 0 gagal |
| `php artisan migrate:fresh --seed` | bersih di database kosong MySQL, 20 migrasi + 2 seeder |
| `npm run build` | berhasil (`public/build/manifest.json`, CSS 53 kB, JS 131 kB) |
| `php artisan route:list --except-vendor` | 39 rute (lihat bagian 3) |
| Pemeriksaan manual HTTP (server lokal) | 4 akun walisantri contoh: semua halaman miliknya 200; pembayaran/Tahap 2 hanya 200 untuk pendaftar Diterima yang sudah diumumkan; halaman draf/terkirim/perlu-perbaikan menampilkan label yang benar; NIK tersamar `3307********xxxx` |

Pemetaan 18 butir tes wajib Tahap 2:

| Butir | Berkas tes |
| --- | --- |
| 1–2 Matriks akses & isolasi walisantri | `tests/Feature/WalisantriAksesTest.php` |
| 3 Mass assignment kolom sensitif | `tests/Feature/Keamanan/MassAssignmentTest.php` |
| 4 Isian disembunyikan/wajib | `tests/Feature/IsianFormulirTest.php` |
| 5 Deteksi data ganda | `tests/Feature/DeteksiGandaTest.php` |
| 6 Nomor peserta | `tests/Feature/NomorPesertaTest.php` |
| 7–8 Aturan kirim & kunci setelah kirim | `tests/Feature/KirimPendaftaranTest.php` |
| 9–10 Unggahan berbahaya & unggah ulang | `tests/Feature/Keamanan/BerkasKeamananTest.php` |
| 11–12 Kebocoran hasil & data internal | `tests/Feature/Keamanan/KebocoranHasilTest.php` |
| 13 Biaya & pilihan IAP | `tests/Feature/BiayaTest.php` |
| 14 Pembayaran daftar ulang | `tests/Feature/PembayaranTest.php` |
| 15 XSS | `tests/Feature/Keamanan/XssTest.php` |
| 16 NIK terenkripsi & tersamar | `tests/Feature/Keamanan/NikTest.php` |
| 17 Pembatasan laju | `tests/Feature/RateLimitTest.php` |
| 18 Grup WhatsApp | `tests/Feature/GrupWaTest.php` |

Tes Tahap 1 (68) tetap lulus tanpa perubahan, termasuk `MatriksAksesTest`, `KeamananTest`, dan `IsolasiSekolahTest`.

## 3. Daftar rute

```
GET|HEAD  /                                   beranda
GET|HEAD  sekolah/{kode}                      sekolah
GET|HEAD  kebijakan-privasi                   kebijakan-privasi
GET|HEAD  masuk                               masuk                                    guest
POST      masuk                               masuk.simpan                             guest
GET|HEAD  daftar-akun                         daftar-akun                              guest
POST      daftar-akun                         daftar-akun.simpan                       guest
GET|HEAD  lupa-password                       lupa-password                            guest
POST      keluar                              keluar                                   auth
GET|HEAD  ganti-password                      ganti-password                           auth
POST      ganti-password                      ganti-password.simpan                    auth
GET|HEAD  akun                                walisantri.beranda                       auth, peran:walisantri
GET|HEAD  akun/orang-tua                      walisantri.orang-tua                     auth, peran:walisantri
PUT       akun/orang-tua                      walisantri.orang-tua.simpan              auth, peran:walisantri
GET|HEAD  akun/daftar/baru                    walisantri.daftar.baru                   auth, peran:walisantri
POST      akun/daftar/baru                    walisantri.daftar.baru.simpan            auth, peran:walisantri, throttle:20,1
GET|HEAD  akun/daftar/baru/anak               walisantri.daftar.anak-baru              auth, peran:walisantri
PUT       akun/daftar/baru/anak               walisantri.daftar.anak-baru.simpan       auth, peran:walisantri, throttle:20,1
GET|HEAD  akun/pendaftaran/{pendaftaran}      walisantri.pendaftaran.detail            auth, peran:walisantri
GET|HEAD  akun/pendaftaran/{pendaftaran}/sukses  walisantri.pendaftaran.sukses         auth, peran:walisantri
GET|HEAD  akun/pendaftaran/{pendaftaran}/hasil   walisantri.pendaftaran.hasil          auth, peran:walisantri
GET|HEAD  akun/pendaftaran/{pendaftaran}/langkah/{n}  walisantri.pendaftaran.langkah    auth, peran:walisantri
PUT       akun/pendaftaran/{pendaftaran}/langkah/{n}  walisantri.pendaftaran.langkah.simpan  auth, peran:walisantri, throttle:20,1
PUT       akun/pendaftaran/{pendaftaran}/langkah/7    walisantri.pendaftaran.biaya.simpan    auth, peran:walisantri, throttle:20,1
POST      akun/pendaftaran/{pendaftaran}/kirim            walisantri.pendaftaran.kirim             auth, peran:walisantri, throttle:10,1
POST      akun/pendaftaran/{pendaftaran}/kirim-perbaikan  walisantri.pendaftaran.kirim-perbaikan   auth, peran:walisantri, throttle:10,1
GET|HEAD  akun/pendaftaran/{pendaftaran}/berkas           walisantri.pendaftaran.berkas.index      auth, peran:walisantri
POST      akun/pendaftaran/{pendaftaran}/berkas           walisantri.pendaftaran.berkas.simpan     auth, peran:walisantri, throttle:20,1
DELETE    akun/pendaftaran/{pendaftaran}/berkas/{berkas}  walisantri.pendaftaran.berkas.hapus      auth, peran:walisantri
GET|HEAD  akun/berkas/{berkas}                            walisantri.berkas.tampil                 auth, peran:walisantri, throttle:60,1
GET|HEAD  akun/pendaftaran/{pendaftaran}/pembayaran       walisantri.pendaftaran.pembayaran.index  auth, peran:walisantri
POST      akun/pendaftaran/{pendaftaran}/pembayaran       walisantri.pendaftaran.pembayaran.simpan auth, peran:walisantri, throttle:20,1
GET|HEAD  akun/pembayaran/{pembayaran}/bukti              walisantri.pembayaran.bukti              auth, peran:walisantri, throttle:60,1
GET|HEAD  akun/pendaftaran/{pendaftaran}/tahap-2          walisantri.pendaftaran.tahap2            auth, peran:walisantri
PUT       akun/pendaftaran/{pendaftaran}/tahap-2          walisantri.pendaftaran.tahap2.simpan     auth, peran:walisantri, throttle:20,1
GET|HEAD  panitia                            panitia.beranda                          auth, peran:panitia,admin
GET|HEAD  admin                              admin.beranda                            auth, peran:admin
GET|HEAD  super                              super.beranda                            auth, peran:super_admin
GET|HEAD  yayasan                            yayasan.beranda                          auth, peran:yayasan
```

Middleware tambahan pada grup `web`: `HeaderKeamanan`, `AkunAktif`, `SesiKedaluwarsa`, `WajibGantiPassword`.

## 4. Paket tambahan

Tidak ada paket baru pada Tahap 2. Semua kebutuhan (validasi gambar/EXIF, PDF, throttle, kunci cache) memakai bawaan Laravel 13, PHP 8.4 (GD + fileinfo), dan paket Tahap 1 (`@fontsource-variable/plus-jakarta-sans`, `laravel-lang/lang`).

## 5. Penyimpangan dari dokumen dan alasannya

1. **Alur "anak baru" memakai sesi dua langkah.**
   `POST /akun/daftar/baru` tanpa `anak_id` menyimpan `sekolah_id`, `gelombang_id`, dan `program` di sesi `pendaftaran_baru`, lalu mengarahkan ke `GET /akun/daftar/baru/anak` (langkah 2). Setelah `PUT`, sistem membuat `anak` + pendaftaran draf dan lanjut ke langkah 3. Alasannya: formulir langkah 2 memerlukan data anak yang belum ada, dan spesifikasi meminta satu formulir bertahap; alternatifnya adalah formulir khusus yang menduplikasi langkah 2. Sesi dibuang bila gelombang sudah tidak dibuka.
2. **Nilai `orang_tua.status_hidup` ditetapkan `Masih hidup` / `Sudah wafat`.**
   `docs/struktur-tabel.md` tidak menyebut daftar nilainya. Pilihan ini dipakai di formulir dan seeder; mohon dikonfirmasi (lihat bagian 7).
3. **Biaya tanpa rincian boleh dikirim tanpa centang persetujuan.**
   Bila sekolah belum menetapkan `rincian_biaya`, `setuju_biaya_pada` boleh kosong supaya walisantri tidak terhalang. Bila ada rincian, centang wajib dan pilihan IAP wajib sah.
4. **Simpan biaya punya rute sendiri** `PUT /akun/pendaftaran/{pendaftaran}/langkah/7` (nama `walisantri.pendaftaran.biaya.simpan`), terpisah dari `PUT .../langkah/{n}` yang dibatasi `n = 2|3|4|5`. Alasannya: langkah 7 memakai `BiayaPendaftaranRequest` yang berbeda dan menolak penyimpanan bila rincian ada tetapi belum disetujui.
5. **Nama rute berawalan `walisantri.`, bukan `akun.`.**
   Awalan URL tetap `/akun` sesuai aturan. Nama `walisantri.` dipilih agar jelas milik peran walisantri dan konsisten dengan grup `panitia.`/`admin.`.
6. **Peringatan usia hanya `flash` (`peringatan_usia`), tidak memblokir kirim.**
   Rentang usia per jenjang adalah kebijakan sekolah, bukan aturan keras. Pesan muncul di layout walisantri pada permintaan berikutnya.
7. **`StatusHasil::Baru` dipakai sebagai status hasil pendaftaran terkirim.**
   Sebelum Tahap 3, belum ada alur verifikasi panitia, sehingga pendaftaran terkirim berstatus `Baru` dan tampil sebagai "Menunggu verifikasi". `Diverifikasi` disiapkan untuk Tahap 3.
8. **`Pendaftaran::$fillable` dan `Anak::$fillable` dipersempit.**
   `nomor_peserta`, `status_hasil`, `status_murid`, `dikirim_pada`, `diputuskan_oleh`, `diputuskan_pada`, `catatan_panitia`, dan `user_id` diisi lewat `forceFill()` di service/seeder. Dengan `Model::shouldBeStrict()`, pengisian langsung dari formulir melempar `MassAssignmentException` (diuji).
9. **Repositori ini bukan repositori git.**
   `AGENTS.md` meminta satu komit per sub-tugas; perintah `git commit` tidak dapat dijalankan karena folder kerja tidak diinisialisasi sebagai repo git. Tidak ada `.env`, `storage/`, `vendor/`, atau `node_modules/` yang ikut tersimpan.

## 6. Bug yang ditemukan dan diperbaiki selama tes

- **Gelombang tanpa `tanggal_tutup` dianggap sudah ditutup.** `null < 'YYYY-MM-DD'` bernilai `true` di PHP, sehingga `gelombangDibuka()` menolak gelombang tanpa batas akhir. Diperbaiki dengan memeriksa `!== null` sebelum membandingkan; diuji lewat `NomorPesertaTest`.
- **Atribut HTML bocor ke elemen pembungkus.** Komponen `isian`, `pilihan`, dan `tanggal` mencetak `$attributes` di `<div>` sehingga muncul `<div type="text" autocomplete="username">` (terlihat di halaman masuk). Diperbaiki: hanya `class` yang diteruskan ke `div`; atribut lain tetap ke `input`/`select`.
- **`lampirkanBerkas` pada tes membuat jenis berkas ganda** per sekolah sehingga prasyarat kirim selalu kurang; pembantu tes diperbaiki agar memakai jenis wajib yang sudah ada.

## 7. Pertanyaan yang masih terbuka

1. **Nilai resmi `orang_tua.status_hidup`.** Saat ini `Masih hidup` / `Sudah wafat`. Bila sekolah memakai istilah lain (mis. `Hidup`/`Meninggal`), cukup ubah `OrangTuaRequest`, formulir, dan seeder.
2. **Jumlah `jumlah_lembar` per jenis berkas** belum dipakai untuk membatasi unggahan; saat ini satu berkas per jenis. Apakah perlu multi-halaman/gabungan PDF?
3. **Ukuran maksimum berkas 2 MB dan sisi 2400 px** (dari `config/spmb.php`) perlu dikonfirmasi ke sekolah; foto ponsel sering lebih besar.
4. **Teks persetujuan data dan Kebijakan Privasi** masih draf dan perlu ditinjau yayasan.
5. **Pemeriksaan visual 360 px dan 1280 px** sudah dilakukan lewat markup dan uji HTTP (layout `max-w-2xl`, `px-4`, navigasi bawah `lg:hidden`, stepper berubah menjadi bilah kemajuan di layar kecil), tetapi belum dengan tangkapan layar di peramban sungguhan.
6. **Kuota belum diperiksa saat kirim.** `Gelombang.kuota` disimpan tetapi belum dipakai; pembatasan kuota dan `ModePenerimaan` direncanakan pada Tahap 3/4 bersama panel panitia dan Umumkan Hasil.
