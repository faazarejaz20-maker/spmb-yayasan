<?php

namespace Tests\Feature\Keamanan;

use Tests\TestCase;

/**
 * Tahap 6 butir 11: berkas pendaftar tidak dapat dibuka lewat URL publik.
 *
 * Berkas disimpan di disk privat `local` (storage/app/private). Tidak ada
 * tautan `public/storage` ke folder itu, dan tidak ada rute yang meneruskan
 * jalur bebas. Unduhan hanya lewat controller dengan login, peran, dan policy.
 */
class BerkasPrivatTest extends TestCase
{
    public function test_jalur_storage_dan_tebakan_menghasilkan_404(): void
    {
        $tebakan = [
            '/storage/berkas/uji.pdf',
            '/storage/app/private/berkas/uji.pdf',
            '/storage/app/private/pembayaran/bukti.pdf',
            '/private/berkas/uji.pdf',
            '/berkas/uji.pdf',
            '/pembayaran/bukti.pdf',
        ];

        foreach ($tebakan as $jalur) {
            $this->get($jalur)->assertNotFound();
        }
    }

    public function test_folder_berkas_privat_berada_di_luar_public(): void
    {
        $privat = realpath(storage_path('app/private'));
        $publik = realpath(public_path());

        $this->assertNotFalse($privat, 'Folder berkas privat tidak ditemukan.');
        $this->assertNotFalse($publik, 'Folder public tidak ditemukan.');

        $this->assertFalse(
            $this->diDalam((string) $privat, (string) $publik),
            'Folder berkas privat tidak boleh berada di dalam public/.'
        );
    }

    public function test_tautan_storage_tidak_menunjuk_berkas_privat(): void
    {
        $tautan = public_path('storage');

        if (! file_exists($tautan) && ! is_link($tautan)) {
            $this->assertTrue(true, 'Belum ada tautan public/storage.');

            return;
        }

        $tujuan = realpath($tautan);
        $privat = realpath(storage_path('app/private'));

        $this->assertNotFalse($tujuan, 'Tautan public/storage rusak.');
        $this->assertNotFalse($privat, 'Folder berkas privat tidak ditemukan.');

        $this->assertFalse(
            $this->diDalam((string) $tujuan, (string) $privat),
            'public/storage tidak boleh menunjuk ke folder berkas privat.'
        );
    }

    /**
     * Bandingkan lintas pemisah jalur (Windows memakai `\`, Linux `/`).
     */
    private function diDalam(string $jalur, string $akar): bool
    {
        $normal = fn (string $teks): string => strtolower(str_replace('\\', '/', $teks));

        return $jalur === $akar
            || str_starts_with($normal($jalur).'/', rtrim($normal($akar), '/').'/');
    }
}
