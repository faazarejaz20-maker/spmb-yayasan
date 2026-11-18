# Data awal (untuk seeder)

Data dari catatan tim dan materi sekolah. **Tandai** bagian yang masih perkiraan; admin sekolah dapat mengubahnya lewat web. Jangan mengarang nilai yang tidak tercantum di sini: bila tidak ada, biarkan kosong atau gunakan "menyusul".

Seeder dibagi dua:
- `DataAwalSeeder` (aman untuk produksi): sekolah, tahun ajaran, konten sekolah, jenis berkas, gelombang draf, pengaturan isian, rincian biaya contoh. Tidak berisi data pribadi dan tidak membuat akun.
- `DataContohSeeder` (hanya lokal, menolak berjalan bila `APP_ENV=production`): akun demo untuk pengembangan dan tes, data pendaftar fiktif.

Visi dan misi lengkap ada di `docs/visi-misi.md` (masuk ke `konten_sekolah` bagian `visi`, `indikator_visi`, `misi`). Visi dan misi yayasan belum ada.

## 1. Tahun ajaran
`nama = 2027/2028`, `aktif = true`.

## 2. Sekolah

| Kode | Nama | Jenjang | Mode penerimaan | WhatsApp (tampil publik, dikonfirmasi) | Instagram |
| --- | --- | --- | --- | --- | --- |
| TKK | TKIT Al Ishlah Kertek | TK | tanpa_seleksi | 6285234575505 (Usth Galih) | tkit_alishlah.wsb |
| TKI | KB-TKIT Insan Mulia | KB, TK, TPA | tanpa_seleksi | 6285695422700 (Ustadzah Saminah) | tkitinsanmulia.wsb |
| SD | SDIT Insan Mulia Wonosobo | SD | tanpa_seleksi | 628984255748 (Usth Shofa) | sditinsanmuli.wsb |
| SMP | SMPIT Insan Mulia Wonosobo | SMP | tanpa_seleksi | 6285747471648 (Ust. Titan) | smpitimwonosobo |

Simpan nomor dalam format `62...` tanpa tanda; tampilkan sebagai `0856-9542-2700` dan buat tautan `https://wa.me/62...`. `mode_penerimaan` awal `tanpa_seleksi` untuk semua sekolah (**perlu dipastikan sekolah**; admin bisa mengubah).

Alamat:
- TKK: Jl. Raya Wonosobo Km.03, Capar, Ngadikusuman, Kertek, Wonosobo (depan patung Carica).
- TKI: Jl. Tumenggung Jogonegoro, Kp. Bumi Godean, Wonolelo, Wonosobo (depan SMA 1 Wonosobo).
- SD dan SMP (satu lokasi): Jl. Kyai Walik, Jlegong, Pagerkukuh, Wonosobo.

Slogan:
- TKK: (belum ada slogan resmi; kosongkan)
- TKI: "Cinta Allah dan Rasul Sejak Dini". Terakreditasi A. Layanan KBIT, TKIT, TPA IT full day. Kurikulum Merdeka diintegrasikan dengan kurikulum khas JSIT.
- SD: "Menggali Potensi, Membentuk Generasi Rabbani"
- SMP: "Generasi Madani Berkarakter Qur'ani"

## 3. Konten halaman sekolah (`konten_sekolah`)

Bagian yang dipakai: `slogan`, `profil`, `visi`, `indikator_visi`, `misi`, `program_unggulan`, `ekskul`, `prestasi`, `fasilitas`, `galeri`, `jam_belajar`, `kontak`, `alur`, `syarat`. Isi awal:

**SD (SDIT Insan Mulia Wonosobo)**
- Program unggulan: Tahfidz Al Quran (target hafal minimal 2 juz), BTAQ Qiroaty, BPI, POMG dan Parenting, Outing Class, Kelas Inspirasi. Enam karakter siswa SIT.
- Ekstrakurikuler (14 totalnya; hanya 7 yang sudah diketahui): Pramuka, TIK, Tilawah, Taekwondo, Rebana, Panahan, Futsal. Tujuh sisanya: **menyusul** (admin mengisi).
- Jam belajar: menyusul. Fasilitas, prestasi, galeri: tampil "Foto menyusul" bila belum ada.

**SMP (SMPIT Insan Mulia)**
- Full day school, keunggulan akselerasi tahfidz, BTAQ Qiroaty, BPI, Outing Class, POMG, Parenting.

**TKK (TKIT Al Ishlah Kertek)**
- Program unggulan: dhuha, hafalan, BTAQ, little camp, berkebun, bermain di alam. Ekskul: tahfidz, melukis, menari. (Dalam materi sempat ada typo "Sekolah Alam": jangan dipakai sebagai nama sekolah.)

**TKI (KB-TKIT Insan Mulia)**
- Tiga program: KB (KBIT), TK (TKIT), TPA (TPA IT), semuanya full day school.

Foto dan logo: baca dari `public/images/logo/` dan `public/images/sekolah/{kode-kecil}/{gedung,fasilitas,ekskul,prestasi}/`. Bila tidak ada, tampil "Foto menyusul". Nama file logo yang diharapkan: `logo/yayasan.png`, `logo/jsit.png`, `logo/tkit-al-ishlah.png`, `logo/tkit-insan-mulia-kb.png`, `logo/tkit-insan-mulia-tk.png`, `logo/sdit.png`, `logo/smpit.png`.

## 4. Alur dan syarat di halaman sekolah (teks umum)
Alur: Buat akun, Isi formulir dan unggah berkas, Verifikasi oleh panitia, Pengumuman hasil di akun, Daftar ulang dan wawancara di sekolah bagi yang diterima, Bergabung ke grup WhatsApp murid baru.

## 5. Gelombang (draf; admin yang mengisi tanggal)
Untuk tiap sekolah buat dua gelombang berstatus `draf`: "Gelombang 1" dan "Gelombang 2", tanggal kosong, kuota kosong, tanpa tautan grup WA. Catatan dari formulir tahun sebelumnya (perkiraan, bukan nilai bawaan): SD gelombang 1 November dan gelombang 2 Januari; TKI gelombang I 1 Desember–28 Februari dan gelombang II 2 Maret–30 Juni (hanya bila kuota I belum terpenuhi); TKK tidak punya jadwal gelombang tetap.

## 6. Jenis berkas (`jenis_berkas`) — perkiraan dari formulir

| Sekolah | Berkas | Wajib | Lembar |
| --- | --- | --- | --- |
| Semua | Akta kelahiran | ya | 1 |
| Semua | Kartu Keluarga (KK) | ya | 1 |
| SD | Pas foto 3x4 | ya | 1 |
| TKK, TKI | Pas foto 3x4 | ya | 2 |
| SMP (**perkiraan, formulir SMP belum ada**) | Surat keterangan sehat, Ijazah atau SKL, Sertifikat kejuaraan | sehat: ya; ijazah/SKL: tidak (menyusul); sertifikat: tidak | 1 |

Admin dapat menambah atau mengubah per sekolah.

## 7. Rincian biaya (`rincian_biaya`) — hanya contoh yang diketahui
- TKK: IAP Rp500.000, tetap, tanpa pilihan (`tipe = tetap`, `kelompok = IAP`). Tanpa biaya formulir.
- SD, TKI, SMP: **menyusul** (admin yang mengisi lewat menu Biaya). "Biaya formulir" dihapus dari data awal (11-11-2026) atas keputusan yayasan. Uang gedung gratis untuk lulusan SDIT ke SMPIT (catatan teks, bukan perhitungan otomatis).
Seeder hanya memuat baris di atas; jangan membuat angka lain.

## 8. Rekening sekolah
Kosong. Nomor rekening menyusul dari sekolah; admin yang mengisi.

## 9. Pengaturan isian (`pengaturan_isian`)
Semua isian Tahap 1 `tampil = true`. `wajib = true` untuk: nama lengkap, jenis kelamin, tempat lahir, tanggal lahir, alamat, desa, kecamatan, kabupaten, provinsi. Isian lain `wajib = false`. `nisn` hanya tampil untuk SMP. Isian Tahap 2 (hobi, cita-cita, kemampuan khusus, lembar psikologi, surat pernyataan) tampil untuk TKK dan TKI; untuk SD dan SMP tampil hobi dan cita-cita saja (perkiraan).

## 10. Akun
- **Produksi:** tidak ada akun dari seeder. Super admin dibuat dengan `php artisan spmb:buat-super-admin`.
- **Lokal (DataContohSeeder, hanya bila bukan produksi):** satu super admin, satu admin dan dua panitia per sekolah, tiga akun walisantri dengan beberapa anak fiktif dalam berbagai status. Kata sandi demo dibuat acak per jalankan dan dicetak ke terminal, bukan ditulis tetap di kode.
