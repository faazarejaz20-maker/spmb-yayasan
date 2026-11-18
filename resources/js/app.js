import './bootstrap';
import Alpine from '@alpinejs/csp';

window.Alpine = Alpine;

Alpine.start();

/*
| Tombol salin dan cetak pada kartu akun walisantri. Ditulis tanpa ekspresi
| inline supaya tetap berjalan di bawah CSP `script-src 'self'`.
*/
const tombolSalin = document.querySelector('[data-salin-kartu]');

if (tombolSalin) {
    tombolSalin.addEventListener('click', async () => {
        const teks = tombolSalin.dataset.salinKartu ?? '';
        const label = tombolSalin.querySelector('[data-label-salin]');
        let berhasil = false;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(teks);
                berhasil = true;
            }
        } catch {
            berhasil = false;
        }

        if (!berhasil) {
            const area = document.createElement('textarea');
            area.value = teks;
            area.setAttribute('readonly', '');
            area.className = 'fixed opacity-0';
            document.body.appendChild(area);
            area.select();

            try {
                berhasil = document.execCommand('copy');
            } catch {
                berhasil = false;
            }

            document.body.removeChild(area);
        }

        if (berhasil && label) {
            label.textContent = 'Tersalin';
            window.setTimeout(() => {
                label.textContent = 'Salin kata sandi';
            }, 2000);
        }
    });
}

document.querySelectorAll('[data-cetak-kartu]').forEach((tombol) => {
    tombol.addEventListener('click', () => window.print());
});

/*
 * Halaman cetak (layouts/cetak): tombol Cetak dan Tutup. Ditulis tanpa
 * ekspresi inline supaya tetap berjalan di bawah CSP `script-src 'self'`.
 */
document.querySelectorAll('[data-cetak]').forEach((tombol) => {
    tombol.addEventListener('click', () => window.print());
});

document.querySelectorAll('[data-tutup-cetak]').forEach((tombol) => {
    tombol.addEventListener('click', () => window.close());
});

/*
 * Unggah berkas satu ketukan: memilih berkas di pemilih sistem (galeri atau
 * pengelola berkas di HP) langsung mengirim formulirnya. Ditulis tanpa
 * ekspresi inline supaya tetap berjalan di bawah CSP `script-src 'self'`.
 */
document.querySelectorAll('[data-unggah-otomatis]').forEach((input) => {
    input.addEventListener('change', () => {
        if (! input.files || input.files.length === 0) {
            return;
        }

        const formulir = input.closest('form');

        if (! formulir) {
            return;
        }

        const tombol = formulir.querySelector('[data-tombol-unggah]');

        if (tombol) {
            tombol.textContent = 'Mengunggah…';
        }

        if (typeof formulir.requestSubmit === 'function') {
            formulir.requestSubmit();
        } else {
            formulir.submit();
        }
    });
});

/*
 * Pemeriksaan ukuran berkas sebelum formulir dikirim. Input dengan
 * data-maks-berkas (dalam byte) menolak berkas yang terlalu besar dan
 * menampilkan pesan di elemen yang ditunjuk data-galat-berkas. Tanpa
 * ekspresi inline agar CSP `script-src 'self'` terpenuhi.
 */
document.querySelectorAll('input[type="file"][data-maks-berkas]').forEach((input) => {
    const maks = Number(input.dataset.maksBerkas);
    const elemenGalat = input.dataset.galatBerkas
        ? document.getElementById(input.dataset.galatBerkas)
        : null;
    const angka = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

    input.addEventListener('change', () => {
        const berkas = input.files && input.files.length > 0 ? input.files[0] : null;

        let pesan = '';

        if (berkas && Number.isFinite(maks) && berkas.size > maks) {
            const batas = angka.format(maks / (1024 * 1024));
            const ukuran = angka.format(berkas.size / (1024 * 1024));
            pesan = `Ukuran gambar maksimal ${batas} MB. Berkas yang dipilih ${ukuran} MB.`;
        }

        input.setCustomValidity(pesan);

        if (elemenGalat) {
            elemenGalat.textContent = pesan;
            elemenGalat.hidden = pesan === '';
        }
    });
});

/*
 * Pilih anak atau "anak baru" pada formulir pendaftaran. Pilihan yang satu
 * mengosongkan pilihan yang lain. Ditulis tanpa ekspresi inline agar CSP
 * `script-src 'self'` terpenuhi.
 */
document.querySelectorAll('[data-pilih-anak]').forEach((pilihan) => {
    pilihan.addEventListener('change', () => {
        if (! pilihan.checked) {
            return;
        }

        const formulir = pilihan.closest('form');

        if (! formulir) {
            return;
        }

        const lawan = pilihan.name === 'anak_baru' ? 'anak_id' : 'anak_baru';

        formulir.querySelectorAll('[name="' + lawan + '"]').forEach((lain) => {
            if (lain.type === 'radio' || lain.type === 'checkbox') {
                lain.checked = false;
            }
        });
    });
});

