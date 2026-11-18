# Paket OpenCode — Sistem SPMB Yayasan Al-Ishlah Insan Mulia

Paket ini berisi aturan proyek, dokumen rujukan, dan enam prompt tahap untuk membangun sistem SPMB (Laravel 13, MySQL) dengan OpenCode di VS Code.

## Isi paket

| File | Fungsi |
| --- | --- |
| `AGENTS.md` | Aturan tetap proyek. Dibaca otomatis oleh OpenCode bila diletakkan di akar proyek. |
| `docs/aturan-sistem.md` | Aturan bisnis lengkap: peran, status, kuota, pengumuman, berkas, biaya. |
| `docs/struktur-tabel.md` | 20 tabel dan kolomnya (nama harus persis). |
| `docs/keamanan.md` | Persyaratan keamanan K-01 sampai K-20. |
| `docs/data-awal.md` | Data untuk seeder (sekolah, kontak, berkas, biaya contoh). |
| `docs/desain.md` | Panduan desain UI/UX (AI merancang dari sini). |
| `docs/visi-misi.md` | Visi dan misi tiap sekolah. |
| `prompts/tahap-1` sampai `tahap-6` | Tugas per tahap. |

## Cara memakai

1. Buat folder proyek kosong, lalu salin `AGENTS.md`, folder `docs/`, dan folder `prompts/` ke akarnya.
2. Siapkan gambar: letakkan di `public/images/logo/` dan `public/images/sekolah/{kode-kecil}/{gedung,fasilitas,ekskul,prestasi}/` (nama logo ada di `docs/data-awal.md`). Gunakan JPG untuk foto gedung, PNG untuk logo; hindari HEIC dan file yang sangat besar.
3. Buka folder itu di VS Code dan jalankan OpenCode.
4. Kirim ke OpenCode: *"Baca AGENTS.md lalu kerjakan prompts/tahap-1-dasar.md."* Hanya satu tahap per sesi.
5. Setelah OpenCode melapor selesai, **periksa sendiri**: jalankan `php artisan test`, `php artisan migrate:fresh --seed`, lalu coba manual sesuai langkah di laporannya.
6. Baru lanjut ke tahap berikutnya. Jangan melompati tahap.

| Tahap | File | Hasil |
| --- | --- | --- |
| 1 | `tahap-1-dasar.md` | Setup, database, login, peran, seeder, kerangka halaman |
| 2 | `tahap-2-pendaftaran-walisantri.md` | Pendaftaran oleh walisantri, berkas, status |
| 3 | `tahap-3-panitia.md` | Panel panitia: verifikasi, keputusan, pembayaran, daftar ulang, pendaftar offline |
| 4 | `tahap-4-admin-super-admin.md` | Admin sekolah, super admin, yayasan, Umumkan Hasil, konten |
| 5 | `tahap-5-ekspor-cetak.md` | Excel/CSV, ZIP berkas, cetak |
| 6 | `tahap-6-publik-produksi.md` | Halaman publik, CSP, produksi, cadangan, panduan penerapan |

## Tips

- Bila OpenCode kehabisan konteks di tengah tahap, mulai sesi baru dan katakan: *"Lanjutkan tahap N. Baca AGENTS.md, file tahap, dan laporan terakhir; periksa apa yang sudah ada sebelum menulis."*
- Commit ke git setelah tiap tahap lulus tes (jangan commit `.env`).
- Bila OpenCode mengusulkan mengubah nama tabel atau menambah paket di luar daftar, minta alasannya dulu.

## Yang masih perlu dari manusia

Rekening sekolah, tanggal dan kuota gelombang, biaya TKI/SMP/IAP SD, formulir SMPIT, visi misi yayasan, logo yayasan dan izin pemakaian logo JSIT, konfirmasi mode penerimaan tiap sekolah, hosting dan domain, tinjauan kebijakan privasi oleh yayasan.
