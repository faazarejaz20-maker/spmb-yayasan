<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\GelombangController as AdminGelombangController;
use App\Http\Controllers\Admin\IsianController as AdminIsianController;
use App\Http\Controllers\Admin\JenisBerkasController as AdminJenisBerkasController;
use App\Http\Controllers\Admin\KontenController as AdminKontenController;
use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Admin\PanitiaController as AdminPanitiaController;
use App\Http\Controllers\Admin\PengaturanController as AdminPengaturanController;
use App\Http\Controllers\Admin\PengumumanController as AdminPengumumanController;
use App\Http\Controllers\Admin\RekapController as AdminRekapController;
use App\Http\Controllers\Admin\RekeningController as AdminRekeningController;
use App\Http\Controllers\Admin\RincianBiayaController as AdminRincianBiayaController;
use App\Http\Controllers\AutentikasiController;
use App\Http\Controllers\HalamanController;
use App\Http\Controllers\KartuAkunController;
use App\Http\Controllers\Panitia\AkunController as PanitiaAkunController;
use App\Http\Controllers\Panitia\BerkasController as PanitiaBerkasController;
use App\Http\Controllers\Panitia\CetakController as PanitiaCetakController;
use App\Http\Controllers\Panitia\DaftarUlangController as PanitiaDaftarUlangController;
use App\Http\Controllers\Panitia\EksporController as PanitiaEksporController;
use App\Http\Controllers\Panitia\KeputusanController;
use App\Http\Controllers\Panitia\OfflineController;
use App\Http\Controllers\Panitia\PanitiaController;
use App\Http\Controllers\Panitia\PembayaranController as PanitiaPembayaranController;
use App\Http\Controllers\Panitia\ZipController as PanitiaZipController;
use App\Http\Controllers\SuperAdmin\AdminController as SuperAdminAdminController;
use App\Http\Controllers\SuperAdmin\KontenController as SuperAdminKontenController;
use App\Http\Controllers\SuperAdmin\LogController as SuperAdminLogController;
use App\Http\Controllers\SuperAdmin\SekolahController as SuperAdminSekolahController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Http\Controllers\SuperAdmin\YayasanController as SuperAdminYayasanController;
use App\Http\Controllers\Walisantri\BerkasController;
use App\Http\Controllers\Walisantri\CetakController as WalisantriCetakController;
use App\Http\Controllers\Walisantri\OrangTuaController;
use App\Http\Controllers\Walisantri\PembayaranController;
use App\Http\Controllers\Walisantri\PendaftaranController;
use App\Http\Controllers\Walisantri\Tahap2Controller;
use App\Http\Controllers\Walisantri\WalisantriController;
use App\Http\Controllers\Yayasan\ProfilController as YayasanProfilController;
use App\Http\Controllers\Yayasan\YayasanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman publik
|--------------------------------------------------------------------------
*/
Route::get('/', [HalamanController::class, 'beranda'])->name('beranda');
Route::get('/sekolah/{kode}', [HalamanController::class, 'sekolah'])->name('sekolah');
Route::get('/kebijakan-privasi', [HalamanController::class, 'kebijakanPrivasi'])->name('kebijakan-privasi');
Route::get('/robots.txt', [HalamanController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [HalamanController::class, 'sitemap'])->name('sitemap');

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AutentikasiController::class, 'formMasuk'])->name('masuk');
    Route::post('/masuk', [AutentikasiController::class, 'masuk'])->name('masuk.simpan');
    Route::get('/daftar-akun', [AutentikasiController::class, 'formDaftar'])->name('daftar-akun');
    Route::post('/daftar-akun', [AutentikasiController::class, 'daftar'])->name('daftar-akun.simpan');
    Route::get('/lupa-password', [AutentikasiController::class, 'lupaPassword'])->name('lupa-password');
    Route::post('/lupa-password', [AutentikasiController::class, 'kirimTautanReset'])
        ->middleware('throttle:10,1')
        ->name('lupa-password.simpan');
    Route::get('/reset-password/{token}', [AutentikasiController::class, 'formResetKataSandi'])
        ->middleware('signed')
        ->name('password.reset');
    Route::post('/reset-password', [AutentikasiController::class, 'resetKataSandi'])
        ->middleware('throttle:10,1')
        ->name('password.reset.simpan');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AutentikasiController::class, 'keluar'])->name('keluar');

    Route::get('/kartu-akun', [KartuAkunController::class, 'tampil'])->name('kartu-akun.staf');

    Route::get('/ganti-password', [AutentikasiController::class, 'formGantiPassword'])->name('ganti-password');
    Route::post('/ganti-password', [AutentikasiController::class, 'gantiPassword'])->name('ganti-password.simpan');
});

/*
|--------------------------------------------------------------------------
| Walisantri
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'peran:walisantri'])->prefix('akun')->name('walisantri.')->group(function () {
    Route::get('/', [WalisantriController::class, 'beranda'])->name('beranda');

    Route::get('/orang-tua', [OrangTuaController::class, 'form'])->name('orang-tua');
    Route::put('/orang-tua', [OrangTuaController::class, 'simpan'])->name('orang-tua.simpan');

    Route::get('/daftar/baru', [PendaftaranController::class, 'formBaru'])->name('daftar.baru');
    Route::post('/daftar/baru', [PendaftaranController::class, 'mulai'])
        ->middleware('throttle:20,1')
        ->name('daftar.baru.simpan');
    Route::get('/daftar/baru/anak', [PendaftaranController::class, 'formAnakBaru'])->name('daftar.anak-baru');
    Route::put('/daftar/baru/anak', [PendaftaranController::class, 'simpanAnakBaru'])
        ->middleware('throttle:20,1')
        ->name('daftar.anak-baru.simpan');

    Route::get('/pendaftaran/{pendaftaran}', [PendaftaranController::class, 'detail'])
        ->name('pendaftaran.detail');
    Route::get('/pendaftaran/{pendaftaran}/sukses', [PendaftaranController::class, 'sukses'])
        ->name('pendaftaran.sukses');
    Route::get('/pendaftaran/{pendaftaran}/hasil', [PendaftaranController::class, 'hasil'])
        ->name('pendaftaran.hasil');

    Route::get('/pendaftaran/{pendaftaran}/langkah/{n}', [PendaftaranController::class, 'langkah'])
        ->whereNumber('n')
        ->name('pendaftaran.langkah');
    Route::put('/pendaftaran/{pendaftaran}/langkah/{n}', [PendaftaranController::class, 'simpanLangkah'])
        ->where('n', '2|3|4|5')
        ->middleware('throttle:20,1')
        ->name('pendaftaran.langkah.simpan');
    Route::put('/pendaftaran/{pendaftaran}/langkah/7', [PendaftaranController::class, 'simpanBiaya'])
        ->middleware('throttle:20,1')
        ->name('pendaftaran.biaya.simpan');

    Route::post('/pendaftaran/{pendaftaran}/kirim', [PendaftaranController::class, 'kirim'])
        ->middleware('throttle:10,1')
        ->name('pendaftaran.kirim');
    Route::post('/pendaftaran/{pendaftaran}/kirim-perbaikan', [PendaftaranController::class, 'kirimPerbaikan'])
        ->middleware('throttle:10,1')
        ->name('pendaftaran.kirim-perbaikan');

    Route::get('/pendaftaran/{pendaftaran}/berkas', [BerkasController::class, 'index'])
        ->name('pendaftaran.berkas.index');
    Route::post('/pendaftaran/{pendaftaran}/berkas', [BerkasController::class, 'simpan'])
        ->middleware('throttle:20,1')
        ->name('pendaftaran.berkas.simpan');
    Route::delete('/pendaftaran/{pendaftaran}/berkas/{berkas}', [BerkasController::class, 'hapus'])
        ->name('pendaftaran.berkas.hapus');
    Route::get('/berkas/{berkas}', [BerkasController::class, 'tampil'])
        ->middleware('throttle:60,1')
        ->name('berkas.tampil');

    Route::get('/pendaftaran/{pendaftaran}/pembayaran', [PembayaranController::class, 'index'])
        ->name('pendaftaran.pembayaran.index');
    Route::post('/pendaftaran/{pendaftaran}/pembayaran', [PembayaranController::class, 'simpan'])
        ->middleware('throttle:20,1')
        ->name('pendaftaran.pembayaran.simpan');
    Route::get('/pembayaran/{pembayaran}/bukti', [PembayaranController::class, 'bukti'])
        ->middleware('throttle:60,1')
        ->name('pembayaran.bukti');

    Route::get('/pendaftaran/{pendaftaran}/tahap-2', [Tahap2Controller::class, 'form'])
        ->name('pendaftaran.tahap2');
    Route::put('/pendaftaran/{pendaftaran}/tahap-2', [Tahap2Controller::class, 'simpan'])
        ->middleware('throttle:20,1')
        ->name('pendaftaran.tahap2.simpan');

    /*
    | Cetak: bukti pendaftaran (setelah terkirim) dan kuitansi pembayaran.
    */
    Route::get('/pendaftaran/{pendaftaran}/cetak/bukti', [WalisantriCetakController::class, 'bukti'])
        ->middleware('throttle:cetak')
        ->name('pendaftaran.cetak.bukti');
    Route::get('/pembayaran/{pembayaran}/cetak/kuitansi', [WalisantriCetakController::class, 'kuitansi'])
        ->middleware('throttle:cetak')
        ->name('pembayaran.cetak.kuitansi');
});

/*
|--------------------------------------------------------------------------
| Panitia (dan admin, yang mewarisi kemampuan panitia)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'peran:panitia,admin'])->prefix('panitia')->name('panitia.')->group(function () {
    Route::get('/', [PanitiaController::class, 'beranda'])->name('beranda');

    /*
    | Pendaftar
    */
    Route::get('/pendaftar', [PanitiaController::class, 'daftar'])->name('pendaftar.index');
    Route::get('/pendaftar/{pendaftaran}', [PanitiaController::class, 'detail'])->name('pendaftar.detail');
    Route::put('/pendaftar/{pendaftaran}/data', [PanitiaController::class, 'simpanData'])
        ->middleware('throttle:20,1')
        ->name('pendaftar.data');
    Route::put('/pendaftar/{pendaftaran}/catatan', [PanitiaController::class, 'simpanCatatan'])
        ->name('pendaftar.catatan');

    /*
    | Berkas
    */
    Route::post('/pendaftar/{pendaftaran}/berkas/{berkas}/periksa', [PanitiaBerkasController::class, 'periksa'])
        ->name('pendaftar.berkas.periksa');
    Route::post('/pendaftar/{pendaftaran}/berkas', [PanitiaBerkasController::class, 'simpan'])
        ->middleware('throttle:20,1')
        ->name('pendaftar.berkas.simpan');
    Route::get('/berkas/{berkas}', [PanitiaBerkasController::class, 'tampil'])
        ->middleware('throttle:60,1')
        ->name('berkas.tampil');

    /*
    | Keputusan
    */
    Route::post('/pendaftar/{pendaftaran}/keputusan', [KeputusanController::class, 'simpan'])
        ->name('pendaftar.keputusan');

    /*
    | Pembayaran
    */
    Route::get('/pembayaran', [PanitiaPembayaranController::class, 'index'])->name('pembayaran.index');
    Route::get('/pembayaran/{pembayaran}', [PanitiaPembayaranController::class, 'tampil'])
        ->middleware('throttle:60,1')
        ->name('pembayaran.tampil');
    Route::post('/pembayaran/{pembayaran}/setujui', [PanitiaPembayaranController::class, 'setujui'])
        ->name('pembayaran.setujui');
    Route::post('/pembayaran/{pembayaran}/tolak', [PanitiaPembayaranController::class, 'tolak'])
        ->name('pembayaran.tolak');
    Route::post('/pendaftar/{pendaftaran}/pembayaran-tunai', [PanitiaPembayaranController::class, 'tunai'])
        ->name('pendaftar.pembayaran-tunai');

    /*
    | Daftar ulang dan pengunduran diri
    */
    Route::put('/pendaftar/{pendaftaran}/daftar-ulang', [PanitiaDaftarUlangController::class, 'simpan'])
        ->name('pendaftar.daftar-ulang');
    Route::post('/pendaftar/{pendaftaran}/mengundurkan-diri', [PanitiaDaftarUlangController::class, 'mengundurkanDiri'])
        ->name('pendaftar.mengundurkan-diri');

    /*
    | Ekspor, unduh berkas (ZIP), dan cetak
    */
    Route::get('/ekspor', [PanitiaEksporController::class, 'index'])->name('ekspor.index');
    Route::post('/ekspor', [PanitiaEksporController::class, 'unduh'])
        ->middleware('throttle:ekspor')
        ->name('ekspor.unduh');

    Route::get('/pendaftar/{pendaftaran}/berkas.zip', [PanitiaZipController::class, 'unduh'])
        ->middleware('throttle:zip')
        ->name('pendaftar.zip');

    Route::get('/pendaftar/{pendaftaran}/cetak/formulir', [PanitiaCetakController::class, 'formulir'])
        ->middleware('throttle:cetak')
        ->name('pendaftar.cetak.formulir');
    Route::get('/pendaftar/{pendaftaran}/cetak/bukti', [PanitiaCetakController::class, 'bukti'])
        ->middleware('throttle:cetak')
        ->name('pendaftar.cetak.bukti');
    Route::get('/pembayaran/{pembayaran}/cetak/kuitansi', [PanitiaCetakController::class, 'kuitansi'])
        ->middleware('throttle:cetak')
        ->name('pembayaran.cetak.kuitansi');

    /*
    | Akun walisantri
    */
    Route::get('/kartu-akun', [PanitiaAkunController::class, 'kartu'])->name('akun.kartu');
    Route::post('/pendaftar/{pendaftaran}/atur-ulang-password', [PanitiaAkunController::class, 'aturUlangPassword'])
        ->name('pendaftar.atur-ulang-password');
    Route::post('/pendaftar/{pendaftaran}/pindah-anak', [PanitiaAkunController::class, 'pindahAnak'])
        ->name('pendaftar.pindah-anak');

    /*
    | Pendaftar offline
    */
    Route::get('/offline', [OfflineController::class, 'index'])->name('offline.index');
    Route::post('/offline/akun', [OfflineController::class, 'buatAkun'])
        ->middleware('throttle:20,1')
        ->name('offline.akun');
    Route::post('/offline/cari', [OfflineController::class, 'cari'])
        ->middleware('throttle:20,1')
        ->name('offline.cari');
    Route::get('/offline/orang-tua', [OfflineController::class, 'formOrangTua'])->name('offline.orang-tua');
    Route::put('/offline/orang-tua', [OfflineController::class, 'simpanOrangTua'])
        ->middleware('throttle:20,1')
        ->name('offline.orang-tua.simpan');
    Route::get('/offline/sekolah', [OfflineController::class, 'formSekolah'])->name('offline.sekolah');
    Route::post('/offline/sekolah', [OfflineController::class, 'simpanSekolah'])
        ->middleware('throttle:20,1')
        ->name('offline.sekolah.simpan');
    Route::get('/offline/anak-baru', [OfflineController::class, 'formAnakBaru'])->name('offline.anak-baru');
    Route::put('/offline/anak-baru', [OfflineController::class, 'simpanAnakBaru'])
        ->middleware('throttle:20,1')
        ->name('offline.anak-baru.simpan');
    Route::get('/offline/{pendaftaran}/langkah/{n}', [OfflineController::class, 'langkah'])
        ->whereNumber('n')
        ->name('offline.langkah');
    Route::put('/offline/{pendaftaran}/langkah/{n}', [OfflineController::class, 'simpanLangkah'])
        ->where('n', '2|3|4|5')
        ->middleware('throttle:20,1')
        ->name('offline.langkah.simpan');
    Route::get('/offline/{pendaftaran}/berkas', [OfflineController::class, 'berkasIndex'])
        ->name('offline.berkas.index');
    Route::post('/offline/{pendaftaran}/berkas', [OfflineController::class, 'berkasSimpan'])
        ->middleware('throttle:20,1')
        ->name('offline.berkas.simpan');
    Route::delete('/offline/{pendaftaran}/berkas/{berkas}', [OfflineController::class, 'berkasHapus'])
        ->name('offline.berkas.hapus');
    Route::get('/offline/{pendaftaran}/biaya', [OfflineController::class, 'biaya'])->name('offline.biaya');
    Route::put('/offline/{pendaftaran}/biaya', [OfflineController::class, 'simpanBiaya'])
        ->middleware('throttle:20,1')
        ->name('offline.biaya.simpan');
    Route::get('/offline/{pendaftaran}/ringkasan', [OfflineController::class, 'ringkasan'])
        ->name('offline.ringkasan');
    Route::post('/offline/{pendaftaran}/kirim', [OfflineController::class, 'kirim'])
        ->middleware('throttle:10,1')
        ->name('offline.kirim');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'peran:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'beranda'])->name('beranda');

    Route::get('/gelombang', [AdminGelombangController::class, 'index'])->name('gelombang.index');
    Route::post('/gelombang', [AdminGelombangController::class, 'simpan'])
        ->middleware('throttle:30,1')
        ->name('gelombang.simpan');
    Route::put('/gelombang/{gelombang}', [AdminGelombangController::class, 'ubah'])
        ->name('gelombang.ubah');
    Route::delete('/gelombang/{gelombang}', [AdminGelombangController::class, 'hapus'])
        ->name('gelombang.hapus');

    Route::get('/pengaturan', [AdminPengaturanController::class, 'index'])->name('pengaturan');
    Route::put('/pengaturan', [AdminPengaturanController::class, 'simpan'])->name('pengaturan.simpan');
    Route::put('/pengaturan/foto-sampul', [AdminPengaturanController::class, 'simpanFoto'])
        ->middleware('throttle:20,1')
        ->name('pengaturan.foto.simpan');
    Route::delete('/pengaturan/foto-sampul', [AdminPengaturanController::class, 'hapusFoto'])
        ->name('pengaturan.foto.hapus');

    Route::get('/panitia', [AdminPanitiaController::class, 'index'])->name('panitia.index');
    Route::post('/panitia', [AdminPanitiaController::class, 'simpan'])
        ->middleware('throttle:20,1')
        ->name('panitia.simpan');
    Route::post('/panitia/{user}/nonaktifkan', [AdminPanitiaController::class, 'nonaktifkan'])
        ->name('panitia.nonaktifkan');
    Route::post('/panitia/{user}/aktifkan', [AdminPanitiaController::class, 'aktifkan'])
        ->name('panitia.aktifkan');
    Route::post('/panitia/{user}/atur-ulang', [AdminPanitiaController::class, 'aturUlang'])
        ->name('panitia.atur-ulang');

    Route::get('/berkas', [AdminJenisBerkasController::class, 'index'])->name('berkas.index');
    Route::post('/berkas', [AdminJenisBerkasController::class, 'simpan'])->name('berkas.simpan');
    Route::put('/berkas/{jenisBerkas}', [AdminJenisBerkasController::class, 'ubah'])->name('berkas.ubah');
    Route::delete('/berkas/{jenisBerkas}', [AdminJenisBerkasController::class, 'hapus'])->name('berkas.hapus');

    Route::get('/biaya', [AdminRincianBiayaController::class, 'index'])->name('biaya.index');
    Route::post('/biaya', [AdminRincianBiayaController::class, 'simpan'])->name('biaya.simpan');
    Route::put('/biaya/{rincianBiaya}', [AdminRincianBiayaController::class, 'ubah'])->name('biaya.ubah');
    Route::delete('/biaya/{rincianBiaya}', [AdminRincianBiayaController::class, 'hapus'])->name('biaya.hapus');

    Route::get('/rekening', [AdminRekeningController::class, 'index'])->name('rekening.index');
    Route::post('/rekening', [AdminRekeningController::class, 'simpan'])->name('rekening.simpan');
    Route::put('/rekening/{rekeningSekolah}', [AdminRekeningController::class, 'ubah'])->name('rekening.ubah');
    Route::delete('/rekening/{rekeningSekolah}', [AdminRekeningController::class, 'hapus'])->name('rekening.hapus');

    Route::get('/isian', [AdminIsianController::class, 'index'])->name('isian.index');
    Route::put('/isian', [AdminIsianController::class, 'simpan'])->name('isian.simpan');

    Route::get('/pengumuman', [AdminPengumumanController::class, 'index'])->name('pengumuman.index');
    Route::get('/pengumuman/{gelombang}', [AdminPengumumanController::class, 'konfirmasi'])->name('pengumuman.konfirmasi');
    Route::post('/pengumuman/{gelombang}', [AdminPengumumanController::class, 'umumkan'])
        ->name('pengumuman.simpan');

    Route::get('/rekap', [AdminRekapController::class, 'index'])->name('rekap.index');
    Route::get('/rekap.xlsx', [AdminRekapController::class, 'unduh'])
        ->middleware('throttle:ekspor')
        ->name('rekap.unduh');
    Route::get('/log', [AdminLogController::class, 'index'])->name('log.index');

    Route::get('/konten', [AdminKontenController::class, 'index'])->name('konten.index');
    Route::put('/konten/teks', [AdminKontenController::class, 'simpanTeks'])->name('konten.teks');
    Route::post('/konten', [AdminKontenController::class, 'tambah'])->name('konten.tambah');
    Route::put('/konten/{konten}', [AdminKontenController::class, 'ubah'])->name('konten.ubah');
    Route::delete('/konten/{konten}', [AdminKontenController::class, 'hapus'])->name('konten.hapus');
});

/*
|--------------------------------------------------------------------------
| Super admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'peran:super_admin'])->prefix('super')->name('super.')->group(function () {
    Route::get('/', [SuperAdminController::class, 'beranda'])->name('beranda');

    Route::get('/sekolah', [SuperAdminSekolahController::class, 'index'])->name('sekolah.index');
    Route::get('/sekolah/{sekolah}/edit', [SuperAdminSekolahController::class, 'edit'])->name('sekolah.edit');
    Route::put('/sekolah/{sekolah}', [SuperAdminSekolahController::class, 'simpan'])->name('sekolah.simpan');
    Route::put('/sekolah/{sekolah}/foto-sampul', [SuperAdminSekolahController::class, 'simpanFoto'])
        ->middleware('throttle:20,1')
        ->name('sekolah.foto.simpan');
    Route::delete('/sekolah/{sekolah}/foto-sampul', [SuperAdminSekolahController::class, 'hapusFoto'])
        ->name('sekolah.foto.hapus');

    Route::get('/admin', [SuperAdminAdminController::class, 'index'])->name('admin.index');
    Route::post('/admin', [SuperAdminAdminController::class, 'simpan'])
        ->middleware('throttle:20,1')
        ->name('admin.simpan');
    Route::post('/admin/{user}/nonaktifkan', [SuperAdminAdminController::class, 'nonaktifkan'])
        ->name('admin.nonaktifkan');
    Route::post('/admin/{user}/aktifkan', [SuperAdminAdminController::class, 'aktifkan'])
        ->name('admin.aktifkan');
    Route::post('/admin/{user}/atur-ulang', [SuperAdminAdminController::class, 'aturUlang'])
        ->name('admin.atur-ulang');

    Route::get('/yayasan', [SuperAdminYayasanController::class, 'index'])->name('yayasan.index');
    Route::post('/yayasan', [SuperAdminYayasanController::class, 'simpan'])
        ->middleware('throttle:20,1')
        ->name('yayasan.simpan');
    Route::post('/yayasan/{user}/nonaktifkan', [SuperAdminYayasanController::class, 'nonaktifkan'])
        ->name('yayasan.nonaktifkan');
    Route::post('/yayasan/{user}/aktifkan', [SuperAdminYayasanController::class, 'aktifkan'])
        ->name('yayasan.aktifkan');
    Route::post('/yayasan/{user}/atur-ulang', [SuperAdminYayasanController::class, 'aturUlang'])
        ->name('yayasan.atur-ulang');

    Route::get('/konten', [SuperAdminKontenController::class, 'index'])->name('konten.index');
    Route::put('/konten/teks', [SuperAdminKontenController::class, 'simpanTeks'])->name('konten.teks');
    Route::post('/konten', [SuperAdminKontenController::class, 'tambah'])->name('konten.tambah');
    Route::put('/konten/{konten}', [SuperAdminKontenController::class, 'ubah'])->name('konten.ubah');
    Route::delete('/konten/{konten}', [SuperAdminKontenController::class, 'hapus'])->name('konten.hapus');

    Route::get('/log', [SuperAdminLogController::class, 'index'])->name('log.index');
});

/*
|--------------------------------------------------------------------------
| Yayasan
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'peran:yayasan'])->prefix('yayasan')->name('yayasan.')->group(function () {
    Route::get('/', [YayasanController::class, 'beranda'])->name('beranda');
    Route::get('/rekap', [YayasanController::class, 'rekap'])->name('rekap');
    Route::get('/rekap.xlsx', [YayasanController::class, 'unduh'])
        ->middleware('throttle:ekspor')
        ->name('rekap.unduh');
    Route::get('/profil', [YayasanProfilController::class, 'index'])->name('profil');
});
