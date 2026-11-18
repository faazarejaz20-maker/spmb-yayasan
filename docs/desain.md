# Acuan desain tampilan

Tidak ada gambar desain dari Stitch. Kamu (AI) yang merancang tampilannya, mengikuti panduan ini. Tujuannya: sederhana, hangat, mudah dipakai orang tua lewat HP, dan konsisten di semua halaman. Keluhan sekolah tentang situs lama: "terlalu ramai". Satu halaman, satu tindakan utama yang jelas.

## 1. Prinsip

- **HP dulu.** Walisantri hampir pasti membuka lewat HP (tautan dipasang di bio Instagram). Rancang untuk lebar 360 piksel, lalu perluas ke desktop. Panel panitia dan admin dirancang desktop dulu tetapi tetap bisa dipakai di tablet.
- **Sederhana dan lapang.** Banyak ruang kosong, sedikit elemen per layar, kartu putih, sudut membulat 12px, bayangan lembut. Tombol dan kolom isian tinggi minimal 48px (sentuh mudah).
- **Nuansa sekolah Islam modern tanpa klise:** tidak ada ornamen berat. Pola geometris sangat halus hanya boleh di latar hero beranda.
- **Bahasa Indonesia yang ramah dan jelas.** Orang tua disebut "walisantri". Kalimat pendek, tindakan berikutnya selalu terlihat. Contoh: "Perbaiki sekarang", "Unggah berkas", "Lihat hasil".
- **Tidak ada biaya atau kuota di halaman publik** (aturan keras, lihat `aturan-sistem.md` bagian 12).
- **Jangan ada foto palsu atau buatan AI.** Bila foto atau logo belum ada, tampilkan kotak abu-abu dengan tulisan "Foto menyusul" atau "Logo menyusul".

## 2. Token desain

Definisikan sebagai variabel CSS di `resources/css/app.css` (atau lewat konfigurasi tema Tailwind) dan pakai di seluruh aplikasi. Jangan menulis warna mentah di Blade.

| Token         | Nilai                 | Pemakaian                          |
| ------------- | --------------------- | ---------------------------------- |
| Primer        | `#0F766E` (hijau tua) | Tombol utama, tautan, status aktif |
| Primer gelap  | `#115E59`             | Hover                              |
| Aksen         | `#D4A017` (emas)      | Sorotan kecil dan lencana, hemat   |
| Latar         | `#F8FAF9`             | Latar halaman                      |
| Kartu         | `#FFFFFF`             | Kartu, tabel                       |
| Teks          | `#1F2937`             | Teks utama                         |
| Teks sekunder | `#6B7280`             | Keterangan                         |
| Garis         | `#E5E7EB`             | Pembatas                           |
| Bahaya        | `#B91C1C`             | Galat, tolak                       |

Font: **Plus Jakarta Sans** (di-host sendiri lewat `@fontsource-variable/plus-jakarta-sans`), ukuran isi minimal 16px di HP, judul besar dan terbaca.

Warna status (selalu dengan teks, jangan hanya warna):
| Status | Warna lencana |
| --- | --- |
| Draf | Abu-abu |
| Baru, Menunggu verifikasi | Biru |
| Menunggu pembayaran atau bukti | Kuning |
| Perlu perbaikan | Oranye |
| Diterima, Valid, Lunas, Resmi | Hijau |
| Ditolak | Merah |
| Cadangan | Ungu |
| Belum dibayar, Belum diunggah | Abu-abu |

Penanda warna sekolah (kecil, hanya agar kartu sekolah mudah dibedakan; aplikasi tetap hijau): TKIT Al Ishlah oranye, KB-TKIT Insan Mulia teal, SDIT biru, SMPIT nila.

## 3. Komponen Blade yang dibuat sekali dan dipakai ulang

Buat di `resources/views/components/`: `tombol` (primer, sekunder, bahaya), `tautan-tombol` (padanan `tombol` untuk `<a>`, dengan varian dan ukuran yang sama), `isian` (label di atas kolom, pesan galat di bawah), `pilihan`, `tanggal`, `unggah-berkas` (dengan pratinjau dan tombol "Ambil foto / Pilih file"), `lencana-status`, `kartu` (judul dengan garis pemisah), `kepala-halaman` (judul halaman seragam: eyebrow, judul, nama anak/sekolah, keterangan, tautan kembali, slot aksi), `tabel` (kepala melekat, bisa digulir horizontal di HP), `tab`, `stepper` (kemajuan langkah), `toast`, `kosong` (keadaan kosong), `foto-placeholder`, `logo` (memakai file bila ada, jika tidak "Logo menyusul"), `navigasi-bawah` (HP), `sidebar` (desktop), `modal-konfirmasi`.

Komponen `foto-placeholder` dan `logo` memeriksa apakah file di `public/images/...` ada; jika tidak, menampilkan kotak abu-abu bertuliskan "Foto menyusul" atau "Logo menyusul". Tidak boleh error bila file belum ada.

**Beberapa formulir dalam satu halaman.** Bila satu halaman memuat banyak formulir dengan nama isian yang sama (misalnya editor konten), setiap formulir mengirim hidden `_form` bertanda unik dan hanya formulir itulah yang memakai `old()` serta menampilkan `@error`. Komponen `x-isian` menyediakan prop `id`, `abaikanLama`, dan `tampilkanGalat` untuk keperluan ini; nilai bawaannya tetap seperti pemakaian biasa. Dengan begitu unggahan yang ditolak tidak menyalin isian ke formulir lain dan tidak menghapus isi formulir lain dari database. Input berkas juga memeriksa ukuran di sisi klien lewat `data-maks-berkas` dan menampilkan pesan di elemen `data-galat-berkas` (tanpa gaya atau skrip inline).

## 4. Tata letak

- **Publik dan walisantri (HP dulu):** bilah atas sederhana, isi satu kolom, navigasi bawah untuk walisantri (Beranda, Anak Saya, Pengumuman, Akun). Tombol tindakan utama menempel di bawah layar pada formulir.
- **Panitia dan admin (desktop):** sidebar kiri (logo, menu, akun), konten di kanan, tabel dengan penyaring dan pencarian. Di layar kecil sidebar menjadi menu geser.
- **Super admin dan yayasan:** sama dengan panel panitia, lebih sederhana.

#### Pola halaman dalam panel staf

- Setiap halaman dalam diawali `x-kepala-halaman`: judul `text-xl`, satu baris keterangan maksimal tiga kalimat, tautan kembali (`kembali`/`labelKembali`) pada halaman detail, edit, konfirmasi, dan ekspor, serta slot `aksi` untuk tombol utama halaman di kanan (menumpuk di bawah judul pada layar sempit).
- Sidebar desktop menempel (`sticky top-0 h-screen`) dengan daftar menu yang bisa digulir sendiri, jadi menu tetap terlihat saat isi halaman digulir panjang. Bilah atas juga menempel (`sticky top-0`).
- Tombol navigasi memakai `x-tautan-tombol`; tombol submit memakai `x-tombol`. Keduanya berbagi varian (`primer`, `sekunder`, `bahaya`, `hantu`), ukuran (`kecil`, `sedang`, `besar`), dan ring fokus `focus-visible` yang sama. Tombol submit minimal 48 px; tombol tautan kecil minimal 44 px.
- Tombol aksi baris tabel memakai ukuran `kecil` agar tinggi baris tetap rapi; aksi utama halaman selalu `sedang`.
- Penyaring dan toolbar dibungkus kartu bergaris dengan `shadow-sm`; isi halaman memakai `x-kartu` agar judul kartu selalu bergaris pemisah.
- Tabel tetap memakai `x-tabel`, sehingga guliran horizontal hanya terjadi di dalam wadah tabel, bukan di halaman.

## 5. Halaman per peran

### Publik

1. **Beranda yayasan** (HP dulu): bilah atas (logo yayasan, judul "SPMB Al-Ishlah Insan Mulia"), hero ("Penerimaan Murid Baru Tahun Ajaran 2027/2028", satu kalimat, dua tombol: **Daftar Sekarang** dan **Masuk**), "Pilih Sekolah" (4 kartu: logo, nama, jenjang, status "Pendaftaran dibuka" bila ada gelombang dibuka, panah), "Alur Pendaftaran" (empat langkah berikon: Buat akun, Isi formulir dan unggah berkas, Verifikasi dan daftar ulang, Lihat pengumuman), "Tentang Yayasan" (singkat, visi dan misi, "Selengkapnya"), kaki halaman (alamat, tombol WhatsApp dan Instagram, logo JSIT kecil bila ada, "Bantuan: hubungi panitia sekolah"). Tombol Daftar Sekarang terlihat tanpa menggulir.
2. **Halaman sekolah** (`/sekolah/{kode}`): sampul dengan logo, nama, slogan, lencana gelombang yang dibuka; tombol melekat di bawah "Daftar ke {sekolah}"; tab Tentang (profil, program unggulan sebagai kotak kecil, ekstrakurikuler sebagai chip, prestasi, fasilitas, galeri, jam belajar), Visi dan Misi, Alur dan Berkas (langkah bernomor dan daftar berkas), Jadwal (tanggal gelombang tanpa kuota), Kontak (alamat, WhatsApp, Instagram, tempat peta). Halaman KB-TKIT Insan Mulia menampilkan ketiga program (KB, TK, TPA) dengan logo program bila ada.
3. **Masuk** (nomor HP atau email, kata sandi, "Lupa kata sandi?" membuka lembar penjelasan: hubungi panitia sekolah tujuan, tombol WhatsApp; tombol "Buat Akun Baru") dan **Buat Akun** (nama, nomor HP, email opsional, kata sandi dua kali, kotak persetujuan dengan tautan Kebijakan Privasi).
4. **Kebijakan Privasi.**

### Walisantri (`/akun`)

1. **Anak Saya:** sapaan "Assalamu'alaikum, {nama}", tombol besar "+ Daftarkan Anak", kartu per anak per pendaftaran (nama anak, sekolah dan program, nomor peserta, lencana status, bilah kemajuan: Formulir, Berkas, Verifikasi, Pembayaran, Daftar Ulang, Hasil, dan tindakan berikutnya yang jelas, misalnya "Perbaiki sekarang" dengan peringatan oranye bila ada berkas perlu perbaikan), bagian **Kontak Sekolah** (satu kartu per sekolah: logo, nama, jenjang, alamat, tombol WhatsApp `wa.me`, dan Instagram resmi), lalu bagian **Prestasi Murid** (foto prestasi asli tiap sekolah dengan keterangan; sekolah yang belum punya foto menampilkan "Foto prestasi menyusul").
2. **Data Orang Tua** (ayah, ibu, wali; diisi sekali, dipakai semua anak).
3. **Formulir pendaftaran bertahap** (8 langkah, tiap langkah menyimpan draf, bilah kemajuan "Langkah n dari 8", tombol melekat "Simpan sebagai draf" dan "Lanjut"). Halaman langkah pertama juga memuat panduan **Alur pendaftaran** (delapan langkah bernomor dengan keterangan singkat), **Siapkan dari sekarang** (KK, akta kelahiran, pas foto, surat sehat bila diminta; berkas boleh difoto dengan HP, JPG/PNG/PDF maksimal 2 MB), daftar berkas nyata tiap sekolah dalam panel buka-tutup (`<details>`) dengan lencana Wajib/Opsional, **Catatan saat mengisi** (draf tersimpan, "Simpan & keluar", tanda wajib), dan tautan **Butuh bantuan?** ke bagian kontak sekolah di beranda:
    1. Pilih sekolah dan gelombang (dan program bila TKI)
    2. Data anak
    3. Alamat
    4. Kesehatan
    5. Asal sekolah (dengan spanduk hijau "Terdaftar sebagai Lulusan Yayasan" bila asal sekolah dari yayasan)
    6. Unggah berkas
    7. Biaya dan pilihan IAP (rincian, pilih nominal bila ada, centang persetujuan biaya)
    8. Pernyataan dan Kirim (ringkasan, "Data yang saya isi benar", "Saya menyetujui penggunaan data", tombol Kirim Pendaftaran)

    Layar sukses: ikon centang, "Pendaftaran terkirim", nomor peserta dalam kotak sorot, langkah berikutnya, tombol "Lihat Status" dan "Cetak Bukti Pendaftaran".

4. **Berkas:** kartu per jenis berkas (nama, pratinjau atau ikon, lencana: Sesuai, Perlu perbaikan dengan catatan panitia di kotak sorot, Belum diunggah dengan tombol "Ambil foto / Pilih file"), teks bantuan "Format JPG, PNG, atau PDF. Maksimal 2 MB.", tombol "Kirim Perbaikan".
5. **Detail status anak:** kartu kepala (nama, sekolah, nomor peserta, lencana), garis waktu (Formulir terkirim, Berkas diverifikasi, Pengumuman, Daftar ulang di sekolah, Resmi), bagian pembayaran daftar ulang (Transfer: rekening sekolah, "Unggah Bukti Transfer", status; Tunai: "Bayar di sekolah"), tautan grup WA murid baru bila ada.
6. **Hasil** (hanya setelah diumumkan): keadaan Diterima ("Alhamdulillah, {nama} diterima di {sekolah}", langkah berikutnya, daftar yang dibawa saat daftar ulang), Belum diterima (sopan, empatik), Cadangan (penjelasan). Sebelum diumumkan: "Menunggu pengumuman", tanpa petunjuk hasil.
7. **Formulir Tahap 2** (setelah Diterima dan diumumkan) dan **Akun** (ganti kata sandi, keluar).

### Panitia dan admin (desktop)

1. **Dasbor:** lima kartu angka (Total pendaftar, Menunggu verifikasi, Perlu perbaikan, Diterima, Cadangan) untuk sekolahnya.
2. **Daftar pendaftar:** pencarian (nama atau nomor), penyaring (gelombang, status, Baru atau Lulusan Yayasan, pembayaran, program), tombol "Unduh Excel" (CSV, pilih kolom) dan "+ Pendaftar Offline"; tabel dengan No. peserta, Nama, Jenis, Gelombang, Berkas (3/4 sesuai), Pembayaran, Status, Aksi; paginasi; spanduk kuning bila kuota Diterima tercapai.
3. **Detail pendaftar:** kepala (nama, nomor, lencana, "Cetak Formulir", "Unduh Berkas (ZIP)"), tab Data (bisa disunting), Berkas (pratinjau, tombol Sesuai dan Perlu perbaikan, kolom catatan), Pembayaran (bukti, Setujui atau Tolak, form "Catat pembayaran tunai"), Daftar Ulang (hadir, kekurangan, catatan wawancara dengan ikon gembok "Hanya terlihat panitia dan admin sekolah"), Riwayat; panel samping Keputusan (status, "Simpan keputusan").
4. **Tambah pendaftar offline** (modal: akun walisantri dengan kata sandi sementara otomatis, tombol Salin dan Cetak kartu akun, lalu lanjut ke formulir "Diisi oleh panitia atas nama walisantri") dan modal "Atur ulang kata sandi" dengan pola yang sama.
5. **Admin saja:** Gelombang dan Kuota (kartu per gelombang, tanggal, status, kuota dengan petunjuk "Kosongkan jika tanpa batas. Kuota tidak tampil di halaman publik.", tautan grup WA, kemajuan "Diterima 18 dari 28"), Akun Panitia (tabel, buat, atur ulang kata sandi, nonaktifkan), Pengaturan (mode penerimaan, foto sampul kartu beranda dengan pratinjau 16:10, unggah, dan hapus; daftar berkas, rincian biaya, rekening, isian formulir), Kelola Konten (daftar bagian di kiri, editor di tengah, pratinjau HP di kanan, tombol Simpan dan Terbitkan; petunjuk "Foto akan dikompres otomatis. Pastikan sekolah mengizinkan foto siswa ditampilkan."), Umumkan Hasil (ringkasan, peringatan pendaftar belum diputuskan, tombol bergaya peringatan dengan dialog konfirmasi, lalu kartu pesan WhatsApp siap salin), Rekap, Log Aktivitas.

### Super admin dan yayasan

- Super admin: daftar empat sekolah (nama, jenjang, kode, status, admin) dengan "Edit sekolah", "Buat akun admin sekolah", "Atur ulang kata sandi"; tab Akun Yayasan. Catatan di halaman: "Super admin tidak mengelola data pendaftar."
- Yayasan: Rekap Pendaftaran (penyaring tahun ajaran dan gelombang, empat kartu ringkasan per sekolah, satu grafik batang sederhana pendaftar per sekolah, tombol "Unduh Rekap (Excel)"), label "Mode lihat saja", tanpa tombol ubah.

## 6. Kualitas tampilan (diperiksa tiap tahap)

- Tidak ada guliran horizontal di 360px dan 1280px.
- Fokus papan ketik terlihat; semua kolom punya label; kontras teks memadai (minimal setara AA).
- Galat validasi tampil di samping kolom dalam bahasa Indonesia yang jelas, bukan pesan teknis.
- Keadaan kosong, memuat, dan galat dirancang (bukan halaman putih).
- Gambar memakai `loading="lazy"`, ukuran wajar, dan atribut `alt`.
- Gaya konsisten: jangan membuat gaya tombol, tabel, atau kartu yang berbeda-beda per halaman.
- Setelah tampilan beranda dan halaman sekolah jadi (tahap 6), minta pemilik proyek menilai dan memberi koreksi dalam satu putaran.
