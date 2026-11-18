# Tahap 2 — Pendaftaran oleh walisantri

Prasyarat: tahap 1 selesai dan `php artisan test` lulus. Kerjakan hanya tahap ini. Jangan membangun panel panitia, panel admin, Umumkan Hasil, ekspor, cetak, atau halaman publik lengkap (tahap 3 sampai 6).

Tahap ini membangun semua yang dialami walisantri di bawah awalan `/akun`: data orang tua, formulir bertahap, unggah berkas, biaya dan pilihan IAP, pengiriman pendaftaran, status, hasil, pembayaran (unggah bukti), dan formulir Tahap 2. Persetujuan bukti bayar oleh panitia dan keputusan diterima atau tidak dikerjakan di tahap 3; di tahap ini keadaan tersebut dibuat lewat factory dan seeder contoh untuk keperluan tes.

## 0. Sebelum menulis kode

1. Baca ulang `AGENTS.md`, `docs/aturan-sistem.md` (terutama bagian 2 sampai 11), `docs/struktur-tabel.md`, `docs/keamanan.md` (K-04, K-05, K-06, K-07, K-09, K-16), `docs/desain.md` (bagian walisantri), dan laporan tahap 1.
2. Tulis rencana singkat (daftar file), lalu langsung kerjakan.
3. Pastikan kolom `pendaftaran.status_hasil` bernilai bawaan `Baru` dan tidak boleh null. Pendaftaran yang masih draf dikenali dari `dikirim_pada` kosong, bukan dari status kosong. Bila migrasi tahap 1 belum begitu, tambahkan migrasi perbaikan dan catat di laporan.

## 1. Prinsip rancangan yang wajib dipatuhi

- **Logika di Service, bukan di Controller.** Tahap 3 akan memakai logika yang sama untuk pendaftar offline (panitia mengisi atas nama walisantri), jadi service menerima `User $pelaku` dan `User $pemilik` (akun walisantri pemilik data) dan memeriksa izin lewat Policy. Controller tipis: validasi lewat FormRequest, panggil service, kembalikan tampilan.
- **Satu sumber kebenaran untuk apa yang boleh dilihat walisantri tentang status.** Buat `app/Services/StatusTampilService.php` yang menerima `Pendaftaran` dan mengembalikan objek ringan (`label`, `warna`, `langkahBerikutnya`, `sudahDiumumkan`, `hasil` yang `null` sebelum diumumkan). **Semua tampilan walisantri hanya memakai objek ini.** View walisantri tidak boleh membaca `$pendaftaran->status_hasil` secara langsung. Aturannya di `docs/aturan-sistem.md` bagian 4 ("Tampilan status ke walisantri") dan bagian 6.
- **Draf.** Pendaftaran dengan `dikirim_pada` kosong adalah draf, tampil "Draf, belum dikirim". Semua daftar sisi panitia (tahap 3) nanti hanya memuat yang `dikirim_pada` tidak kosong; sediakan scope `terkirim()` di model `Pendaftaran` sekarang.
- **Isi formulir dibentuk dari pengaturan**, bukan ditulis tetap per sekolah: katalog `config/isian.php` dan tabel `pengaturan_isian` (tampil dan wajib per sekolah).

## 2. Kebijakan akses (Policy) dan rute

Policy dan pendaftaran di `AuthServiceProvider` atau lewat auto-discovery Laravel 13: `AnakPolicy`, `OrangTuaPolicy`, `PendaftaranPolicy`, `BerkasPolicy`, `PembayaranPolicy`.
- Walisantri hanya boleh melihat dan mengubah miliknya (`anak.user_id`, atau lewat pendaftaran → anak).
- Walisantri boleh mengubah data pendaftaran hanya bila draf (`dikirim_pada` kosong) atau `status_hasil = Perlu perbaikan`. Selain itu hanya melihat.
- Akses ke milik orang lain menghasilkan **404** (jangan membocorkan keberadaan), bukan 403, untuk rute berparameter `uuid`.
- Semua rute memakai `uuid` (K-04), `auth`, `peran:walisantri`. Jangan menaruh nama, NIK, atau nomor HP di URL.

Rute (awalan `/akun`, nama rute berawalan `walisantri.`):

| Metode dan jalur | Fungsi |
| --- | --- |
| `GET /akun` | Anak Saya (daftar kartu) |
| `GET/PUT /akun/orang-tua` | Data orang tua |
| `GET /akun/daftar/baru` | Langkah 1: pilih sekolah, gelombang, program, dan anak |
| `GET/PUT /akun/pendaftaran/{pendaftaran}/langkah/{n}` | Langkah 2 sampai 8 (n = 2..8) |
| `POST /akun/pendaftaran/{pendaftaran}/kirim` | Kirim pendaftaran |
| `GET /akun/pendaftaran/{pendaftaran}` | Detail status |
| `GET/POST /akun/pendaftaran/{pendaftaran}/berkas` dan `DELETE .../berkas/{berkas}` | Daftar dan unggah berkas |
| `GET /akun/berkas/{berkas}` | Menampilkan atau mengunduh berkas (lewat controller) |
| `POST /akun/pendaftaran/{pendaftaran}/kirim-perbaikan` | Kirim perbaikan (status Perlu perbaikan kembali ke Baru) |
| `GET /akun/pendaftaran/{pendaftaran}/hasil` | Hasil (hanya bermakna setelah diumumkan) |
| `GET/POST /akun/pendaftaran/{pendaftaran}/pembayaran` | Rekening, rincian, unggah bukti transfer |
| `GET/PUT /akun/pendaftaran/{pendaftaran}/tahap-2` | Formulir Tahap 2 (setelah Diterima dan diumumkan) |

## 3. Data orang tua

- Satu formulir dengan tiga bagian (ayah, ibu, wali). Kolom sesuai `docs/struktur-tabel.md` bagian 5. Wali bersifat opsional.
- Disimpan di `orang_tua` milik akun (unique `user_id` + `hubungan`). Dipakai untuk semua anak di akun itu.
- NIK dienkripsi (cast), tampil disamarkan bila di daftar, utuh di formulir milik sendiri.
- Syarat pengiriman pendaftaran: minimal ayah atau ibu terisi **nama dan nomor HP** (nomor dinormalisasi lewat `NomorHp`).
- Bila syarat belum terpenuhi saat melanjutkan langkah, arahkan ke halaman ini dengan pesan jelas, dan kembalikan ke langkah semula setelah disimpan.

## 4. Formulir bertahap (8 langkah, urutan di `docs/desain.md`)

Setiap langkah menyimpan draf. Bilah kemajuan "Langkah n dari 8". Tombol melekat di bawah layar HP: "Simpan sebagai draf" dan "Lanjut". Pengguna bisa kembali ke langkah sebelumnya.

1. **Pilih sekolah dan gelombang** (dan **program** bila sekolah TKI: KB, TK, atau TPA, wajib), serta pilih anak yang sudah ada atau "Anak baru". Hanya gelombang berstatus `dibuka` dan, bila `tanggal_buka`/`tanggal_tutup` diisi, yang sedang berlangsung. Bila tidak ada, tampilkan "Pendaftaran belum dibuka". Bila anak sudah punya pendaftaran di sekolah dan gelombang itu, arahkan ke pendaftaran yang ada, bukan membuat baru.
   - Bila memilih anak yang sudah ada, baris `pendaftaran` draf dibuat langsung. Bila "Anak baru", pilihan disimpan di sesi dan baris `anak` serta `pendaftaran` draf dibuat saat langkah 2 pertama kali disimpan.
2. **Data anak** (bagian `identitas` di katalog).
3. **Alamat** (`alamat`).
4. **Kesehatan** (`kesehatan`).
5. **Asal sekolah** (`asal_sekolah`). Spanduk hijau "Terdaftar sebagai Lulusan Yayasan" bila asal sekolah dipilih dari daftar sekolah yayasan (isi `pendaftaran.lulusan_yayasan`; tetap boleh teks bebas).
6. **Unggah berkas** (bagian 6 di bawah).
7. **Biaya dan pilihan IAP** (bagian 7).
8. **Pernyataan dan Kirim** (bagian 8).

Aturan isian:
- Bentuk daftar isian per langkah dari `config/isian.php` dikurangi isian dengan `tampil = false` di `pengaturan_isian` sekolah tujuan. Isian `inti` selalu tampil dan wajib.
- Buat `app/Services/AturanIsian.php` yang menghasilkan aturan validasi dari katalog dan pengaturan: `wajib` → `required`, selain itu `nullable`; tipe → aturan (`tanggal` → `date|before:today`, `angka` → `integer` dengan batas wajar, `pilihan` → `in:`, NIK dan KK → 16 digit angka bila diisi, tinggi dan berat badan dalam rentang wajar, dan seterusnya). **Isian yang disembunyikan tidak boleh diterima dari masukan walau dikirim paksa**: hanya kunci yang tampil yang diambil (`$request->safe()->only(...)`), sisanya dibuang.
- Peran, `user_id`, `sekolah_id`, `status_hasil`, `nomor_peserta`, dan sejenisnya tidak pernah berasal dari masukan; isi di kode.
- Tanggal lahir menyesuaikan jenjang secara lunak (peringatan, bukan penolakan): misalnya SD biasanya 6 sampai 8 tahun. Jangan memblokir karena usia, cukup tampilkan peringatan "Periksa kembali tanggal lahir".

### Deteksi data ganda
`app/Services/DeteksiGandaService.php`. Cocok bila ada anak lain (akun mana pun) dengan nama yang sama setelah dinormalisasi (huruf kecil, spasi berlebih dibuang) **dan** tanggal lahir yang sama, yang punya pendaftaran terkirim di **sekolah dan tahun ajaran yang sama** dengan `status_hasil` bukan `Ditolak`. Pendaftaran milik anak yang sama tidak dihitung. Dijalankan saat menyimpan langkah 2 dan lagi saat Kirim. Bila cocok: tolak dengan pesan umum "Data anak ini sudah terdaftar di sekolah tersebut. Jika menurut Anda ini keliru, hubungi panitia", **tanpa menyebut akun atau data pemilik lain**, dengan tombol WhatsApp ke sekolah. Batasi laju simpan langkah 2 (20 per menit per pengguna) agar tidak dipakai untuk menebak data orang lain. Anak kembar tidak terblokir karena namanya berbeda.

## 5. Nomor peserta dan pengiriman

`app/Services/NomorPesertaService.php`: `AWALAN-TAHUN-URUTAN4`. Awalan dari kode sekolah (`TKK`, `SD`, `SMP`) atau, untuk TKI, dari `pendaftaran.program` (`KB`, `TK`, `TPA`). `TAHUN` adalah empat digit pertama `tahun_ajaran.nama` (2027 untuk 2027/2028). Urutan per awalan per tahun, mulai 0001. Hindari duplikat saat dua orang mengirim bersamaan: bungkus pembuatan nomor dengan `Cache::lock("nomor-peserta:{awalan}-{tahun}", 10)->block(5, ...)` (driver cache `database` mendukung kunci atom), baca urutan terakhir, simpan, dan sebagai jaring pengaman tangkap pelanggaran unique lalu ulangi sekali. Nomor dibuat hanya saat Kirim.

`PendaftaranService::kirim()` (di dalam satu transaksi, lewat Policy):
1. Prasyarat: gelombang masih `dibuka` dan dalam tanggalnya; data orang tua minimal terisi; semua isian wajib terisi; semua jenis berkas wajib sudah ada berkas; ada persetujuan biaya (`setuju_biaya_pada`) dan, bila ada pilihan IAP, `pilihan_iap` terisi dan nilainya salah satu dari pilihan sekolah itu; pernyataan data benar dicentang; lolos deteksi data ganda lagi. Bila gagal, kembalikan daftar yang kurang dengan tautan ke langkah terkait.
2. Isi `nomor_peserta`, `dikirim_pada`, `setuju_data_pada`, `status_hasil = Baru`; tulis `riwayat_status` (jenis `hasil`, dari kosong ke `Baru`, oleh pelaku) dan `log_aktivitas`.
3. Setelah terkirim, walisantri tidak bisa mengubah data (hanya bila nanti `Perlu perbaikan`).

`kirimPerbaikan()`: hanya bila `Perlu perbaikan`; semua berkas yang ditandai `perlu_perbaikan` sudah diganti; status kembali `Baru` dengan riwayat dan log.

Layar sukses: ikon centang, "Pendaftaran terkirim", nomor peserta dalam kotak sorot, langkah berikutnya, tombol "Lihat Status". (Tombol "Cetak Bukti Pendaftaran" dibuat di tahap 5, jangan dibuat sekarang.)

## 6. Berkas

`app/Services/BerkasService.php`, mengikuti K-05 dan `docs/aturan-sistem.md` bagian 8.
- Jenis berkas yang diminta berasal dari `jenis_berkas` sekolah tujuan (aktif, urut). Tampilkan kartu per jenis: nama, pratinjau atau ikon, lencana (Belum diunggah, Menunggu, Sesuai, Perlu perbaikan dengan catatan panitia di kotak sorot), tombol "Ambil foto / Pilih file" (`<input type="file" accept="image/*,application/pdf">`; untuk foto tambahkan `capture` hanya pada tombol "Ambil foto"). Bantuan: "Format JPG, PNG, atau PDF. Maksimal 2 MB."
- Validasi tegas di server: ukuran maksimal dari konfigurasi (`config/spmb.php`, bawaan 2048 KB); ekstensi hanya `jpg`, `jpeg`, `png`, `pdf`; deteksi MIME dari isi (`finfo`) harus cocok dengan ekstensi; tolak banyak ekstensi (`a.php.jpg`), file kosong, dan HEIC (pesan: "Foto format HEIC belum didukung. Ubah ke JPG atau PNG, atau ambil foto langsung lewat tombol Ambil foto."). Untuk PDF cek tanda `%PDF`. Untuk JPG dan PNG, baca ulang dengan GD atau Imagick, putar sesuai orientasi EXIF, simpan ulang (membuang metadata dan muatan tersembunyi); batasi sisi terpanjang sekitar 2400 piksel.
- Simpan di disk privat `storage/app/private/berkas/{uuid-pendaftaran}/{uuid}.{ext}`. Nama asli tidak dipakai sebagai path.
- Mengunggah ulang jenis yang sama menggantikan berkas lama (hapus file lama, status berkas kembali `menunggu`, catat di log). Hapus berkas hanya sebelum terkirim atau saat Perlu perbaikan.
- Tampil dan unduh hanya lewat `GET /akun/berkas/{berkas}` yang memeriksa `BerkasPolicy`. Header: `Content-Type` dari tipe yang kita simpan (bukan dari pengguna), `Content-Disposition: inline`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store, private`. Tidak ada tautan langsung ke storage.
- Batas laju: unggah 20 per menit per pengguna, unduh atau tampil 60 per menit.
- Catat ke log: unggah, ganti, hapus (tanpa isi berkas).

## 7. Biaya dan pilihan IAP (langkah 7 dan halaman pembayaran)

- Rincian biaya (`rincian_biaya` sekolah dan tahun ajaran gelombang itu) **hanya** tampil kepada walisantri yang punya pendaftaran ke sekolah itu (Policy). Tidak pernah di halaman publik. Kelompokkan menurut `kelompok`. Baris `tipe = pilihan` dalam kelompok yang sama menjadi pilihan nominal (radio); `tipe = tetap` hanya ditampilkan.
- Walisantri memilih satu nominal IAP bila ada pilihan, mencentang "Saya telah membaca dan menyetujui rincian biaya", lalu sistem mengisi `pilihan_iap` dan `setuju_biaya_pada`. Nilai yang dikirim harus salah satu nilai di `rincian_biaya` (jangan percaya nominal dari masukan).
- Bila sekolah belum punya rincian biaya (contoh TKI dan SMP, menyusul), tampilkan "Rincian biaya akan diinformasikan oleh sekolah" dan izinkan lanjut tanpa persetujuan biaya; `setuju_biaya_pada` dibiarkan kosong. Bila sekolah punya baris "Biaya formulir", tampilkan dengan keterangan "dibayar saat daftar ulang".
- Teks di halaman ini jangan menyebut "biaya pendaftaran"; web tidak menarik biaya apa pun saat mendaftar.

## 8. Langkah akhir, status, dan hasil

- Langkah 8: ringkasan seluruh data (dengan NIK disamarkan), dua kotak centang ("Data yang saya isi benar", "Saya menyetujui penggunaan data sesuai Kebijakan Privasi"), tombol "Kirim Pendaftaran" dengan dialog konfirmasi ("Setelah dikirim, data tidak dapat diubah kecuali diminta diperbaiki oleh panitia").
- **Anak Saya** (`/akun`): sapaan "Assalamu'alaikum, {nama}", tombol besar "+ Daftarkan Anak", kartu per pendaftaran (nama anak, sekolah dan program, nomor peserta bila ada, lencana dari `StatusTampilService`, bilah kemajuan: Formulir, Berkas, Verifikasi, Pembayaran, Daftar Ulang, Hasil, dan satu tindakan berikutnya yang jelas), kartu Bantuan dengan WhatsApp sekolah.
- **Detail status**: kepala, garis waktu (Formulir terkirim, Berkas diverifikasi, Pengumuman, Daftar ulang di sekolah, Resmi), catatan perbaikan panitia (bila `Perlu perbaikan`, tampilkan **catatan berkas** yang ditandai; catatan internal `catatan_panitia` **tidak pernah** tampil), tautan grup WA bila `gelombang.tautan_grup_wa` terisi dan pendaftaran Diterima (sudah diumumkan) atau Resmi.
- **Hasil** dan seluruh tampilan status: gunakan tabel "Tampilan status ke walisantri" di `docs/aturan-sistem.md` bagian 4. Pengumuman dianggap sudah ada bila ada baris `pengumuman` untuk `sekolah_id` dan `gelombang_id` pendaftaran itu. Tahap 4 membuat baris itu lewat tombol; di tahap ini buat lewat factory pada tes.
  - Sebelum diumumkan: hasil Diterima, Ditolak, dan Cadangan **tidak boleh bocor** lewat teks, warna lencana, ikon, judul halaman, bilah kemajuan, URL, atau data yang dikirim ke tampilan. Tampilkan "Terverifikasi, menunggu pengumuman".
  - Setelah diumumkan: Diterima ("Alhamdulillah, {nama} diterima di {sekolah}", langkah berikutnya dan daftar yang dibawa saat daftar ulang), Belum diterima (kalimat sopan dan empatik), Cadangan (penjelasan).
  - Jangan pernah menampilkan ke walisantri: `catatan_panitia`, `daftar_ulang.catatan_wawancara`, `daftar_ulang.kekurangan`, `diputuskan_oleh`, atau kuota.

## 9. Pembayaran (sisi walisantri) dan Tahap 2

Tersedia hanya bila pendaftaran `Diterima` dan sudah diumumkan.
- **Pembayaran:** tampilkan rekening aktif sekolah (`rekening_sekolah`; bila kosong: "Informasi rekening akan diberikan sekolah"), daftar biaya yang ditagih saat daftar ulang, dan dua cara: **Transfer** (isi nominal dan tanggal transfer, unggah bukti JPG, PNG, atau PDF maksimal 2 MB; membuat baris `pembayaran` `metode = transfer`, `status = menunggu`, tanpa `diproses_oleh`) dan **Tunai** (hanya keterangan "Bayar langsung di sekolah saat daftar ulang"; tidak membuat baris, karena panitia yang menginput). Bukti disimpan dengan aturan yang sama dengan berkas (privat, uuid, divalidasi, hanya lewat controller dan `PembayaranPolicy`). Status ditampilkan ke walisantri (Menunggu, Disetujui, Ditolak dengan `catatan_penolakan`). Walisantri **tidak bisa** mengubah status. Setelah ditolak boleh mengunggah bukti baru (baris baru). Persetujuan oleh panitia dikerjakan di tahap 3.
- **Formulir Tahap 2** (`bagian = tahap2` di katalog): hobi, cita-cita, kemampuan khusus, lembar psikologi, surat pernyataan, sesuai `pengaturan_isian` sekolah. Disimpan di `jawaban_tambahan` (unique `pendaftaran_id`, `bagian`, `kunci`). Boleh diisi dan diubah walisantri sebelum daftar ulang selesai.

## 10. Tampilan

Ikuti `docs/desain.md`: HP dulu (360 px), tombol dan kolom minimal 48 px, satu tindakan utama per layar, bahasa ramah, status selalu disertai teks. Buat komponen yang belum ada: `stepper`, `unggah-berkas`, `pilihan`, `tanggal`, `tab`, dan lengkapi `lencana-status` untuk semua status. Keadaan kosong, memuat, dan galat dirancang (bukan halaman putih). Galat validasi dalam bahasa Indonesia di samping kolom. Gambar memakai `loading="lazy"` dan `alt`.

## 11. Tes (wajib)

Tambahkan ke `tests/Feature/` dan `tests/Feature/Keamanan/`:
1. **Matriks akses** rute `/akun/...` diperluas: tamu 302, peran selain walisantri 403.
2. **Isolasi walisantri:** walisantri A tidak bisa melihat, mengubah, atau mengunduh anak, pendaftaran, berkas, dan pembayaran walisantri B (uuid valid) → 404.
3. **Mass assignment:** mengirim `user_id`, `sekolah_id`, `status_hasil`, `nomor_peserta`, `dikirim_pada`, `diputuskan_oleh` dari formulir tidak mengubah nilai.
4. **Isian tersembunyi:** isian `tampil = false` yang dikirim paksa dibuang; isian wajib yang kosong ditolak.
5. **Data ganda:** nama dan tanggal lahir sama di sekolah dan tahun ajaran yang sama (akun lain) ditolak dengan pesan umum yang tidak menyebut pemilik; pendaftar dengan status Ditolak tidak memblokir; kembar dengan nama berbeda lolos.
6. **Nomor peserta:** format benar per awalan (termasuk TKI dengan program KB, TK, TPA); urutan naik; tes konkurensi sederhana (dua pengiriman berurutan cepat tidak menghasilkan nomor sama); unique tertahan.
7. **Kirim:** gagal bila orang tua belum diisi, berkas wajib kurang, biaya belum disetujui (bila ada rincian), pernyataan tidak dicentang, atau gelombang tidak dibuka; berhasil mengisi `dikirim_pada`, `status_hasil = Baru`, `riwayat_status`, dan `log_aktivitas`. Pilihan IAP di luar daftar ditolak.
8. **Ubah setelah kirim:** walisantri tidak bisa mengubah data setelah terkirim kecuali `Perlu perbaikan`; `kirimPerbaikan` mengembalikan ke `Baru`.
9. **Berkas berbahaya:** `.php` berekstensi `.jpg`, `a.php.jpg`, file lebih dari 2 MB, HEIC, file kosong, dan PDF palsu ditolak; JPG yang sah disimpan ulang tanpa EXIF; file tidak bisa diambil dari URL publik; nama file di disk acak.
10. **Berkas diunggah ulang** menggantikan yang lama dan status kembali `menunggu`.
11. **Hasil bocor (kritis):** pendaftaran `Diterima`, `Ditolak`, dan `Cadangan` **tanpa** pengumuman → respons semua halaman walisantri tidak memuat kata "Diterima", "Ditolak", "Cadangan" sebagai hasil, kelas warna atau ikon hasil, dan data yang dikirim ke view tidak memuat nilai status asli. Dengan pengumuman → hasil tampil benar. Hasil yang diputuskan setelah pengumuman langsung tampil.
12. **Data internal tidak bocor:** `catatan_panitia`, `catatan_wawancara`, `kekurangan`, dan kuota tidak muncul di halaman walisantri.
13. **Biaya:** halaman biaya hanya bisa dibuka walisantri yang punya pendaftaran ke sekolah itu; halaman publik tidak memuat biaya (ulang tes tahap 1 untuk semua halaman publik).
14. **Pembayaran:** unggah bukti membuat baris `menunggu`; walisantri tidak bisa mengubah status; tidak tersedia sebelum Diterima dan diumumkan.
15. **XSS:** nama anak, alamat, dan catatan berisi `<script>` ter-escape di semua halaman.
16. **NIK:** di database bukan teks polos; di daftar disamarkan.
17. **Batas laju:** unggah dan simpan langkah 2 melewati batas memberi 429.
18. **Grup WA:** tautan hanya tampil untuk Diterima (diumumkan) atau Resmi, dan hanya bila terisi.

## 12. Kriteria selesai

- Semua butir `AGENTS.md` bagian 8 terpenuhi (`migrate:fresh --seed`, `php artisan test`, `route:list`, cek 360 dan 1280 piksel, tidak ada kode percobaan, laporan).
- Keamanan: K-04, K-05, K-06, K-07, K-09 (unggah, simpan langkah 2), K-14 (persetujuan saat Kirim), K-16 butir 2, 3, 6, 7, 9, 10, 11, 12 terpenuhi dan dites.
- Walisantri dapat menjalani alur penuh secara manual di lokal: daftar akun, isi orang tua, daftarkan anak lewat 8 langkah, unggah berkas, pilih IAP, kirim, melihat status. Tuliskan langkah mencobanya di laporan.
- Laporan akhir sesuai `AGENTS.md`: yang dibuat, hasil tes, paket tambahan dan alasannya, penyimpangan dari dokumen, pertanyaan terbuka.

**Berhenti di sini.** Jangan mulai tahap 3 sebelum diminta.
