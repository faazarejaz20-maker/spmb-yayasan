<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;

/**
 * Butir 12 Tahap 5: perintah `spmb:bersihkan-tmp` menghapus file lama dan
 * menyisakan yang baru.
 */
class BersihkanTmpCommandTest extends UnduhanTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_menghapus_file_lama_dan_menyisakan_yang_baru(): void
    {
        $folder = (string) config('ekspor.tmp_folder');
        $disk = Storage::disk('local');

        $disk->makeDirectory($folder);

        $lama = $folder.'/lama.zip';
        $baru = $folder.'/baru.zip';

        $disk->put($lama, 'x');
        $disk->put($baru, 'y');

        // Mundurkan waktu file lama ke dua jam lalu.
        touch($disk->path($lama), now()->subHours(2)->getTimestamp());

        $this->artisan('spmb:bersihkan-tmp')
            ->expectsOutputToContain('1 file sementara dihapus.')
            ->assertSuccessful();

        $this->assertFalse($disk->exists($lama));
        $this->assertTrue($disk->exists($baru));
    }

    public function test_folder_belum_ada_tidak_galat(): void
    {
        $this->artisan('spmb:bersihkan-tmp')
            ->expectsOutputToContain('Folder sementara belum ada')
            ->assertSuccessful();
    }

    public function test_file_baru_semua_tidak_dihapus(): void
    {
        $folder = (string) config('ekspor.tmp_folder');
        $disk = Storage::disk('local');

        $disk->makeDirectory($folder);
        $disk->put($folder.'/a.zip', 'x');
        $disk->put($folder.'/b.xlsx', 'y');

        $this->artisan('spmb:bersihkan-tmp')
            ->expectsOutputToContain('0 file sementara dihapus.')
            ->assertSuccessful();

        $this->assertTrue($disk->exists($folder.'/a.zip'));
        $this->assertTrue($disk->exists($folder.'/b.xlsx'));
    }
}
