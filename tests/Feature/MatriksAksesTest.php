<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MatriksAksesTest extends TestCase
{
    use RefreshDatabase;

    private ?Sekolah $sekolahA = null;

    private ?Sekolah $sekolahB = null;

    private array $user = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolahA = Sekolah::factory()->create(['kode' => 'SDA']);
        $this->sekolahB = Sekolah::factory()->create(['kode' => 'SDB']);

        $this->user = [
            'tamu' => null,
            'walisantri' => User::factory()->walisantri()->create(),
            'panitia_a' => User::factory()->panitia($this->sekolahA)->create(),
            'admin_a' => User::factory()->admin($this->sekolahA)->create(),
            'panitia_b' => User::factory()->panitia($this->sekolahB)->create(),
            'super_admin' => User::factory()->superAdmin()->create(),
            'yayasan' => User::factory()->yayasan()->create(),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function dataMatriks(): array
    {
        return [
            'tamu ke /akun' => ['tamu', '/akun', 302],
            'walisantri ke /akun' => ['walisantri', '/akun', 200],
            'panitia_a ke /akun' => ['panitia_a', '/akun', 403],
            'super_admin ke /akun' => ['super_admin', '/akun', 403],

            'tamu ke /panitia' => ['tamu', '/panitia', 302],
            'panitia_a ke /panitia' => ['panitia_a', '/panitia', 200],
            'admin_a ke /panitia' => ['admin_a', '/panitia', 200],
            'panitia_b ke /panitia' => ['panitia_b', '/panitia', 200],
            'walisantri ke /panitia' => ['walisantri', '/panitia', 403],
            'yayasan ke /panitia' => ['yayasan', '/panitia', 403],

            'tamu ke /admin' => ['tamu', '/admin', 302],
            'admin_a ke /admin' => ['admin_a', '/admin', 200],
            'panitia_a ke /admin' => ['panitia_a', '/admin', 403],
            'walisantri ke /admin' => ['walisantri', '/admin', 403],

            'tamu ke /super' => ['tamu', '/super', 302],
            'super_admin ke /super' => ['super_admin', '/super', 200],
            'admin_a ke /super' => ['admin_a', '/super', 403],
            'yayasan ke /super' => ['yayasan', '/super', 403],

            'tamu ke /yayasan' => ['tamu', '/yayasan', 302],
            'yayasan ke /yayasan' => ['yayasan', '/yayasan', 200],
            'super_admin ke /yayasan' => ['super_admin', '/yayasan', 403],
            'walisantri ke /yayasan' => ['walisantri', '/yayasan', 403],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataMatriks')]
    public function test_matrik_akses(string $peran, string $url, int $status): void
    {
        if ($peran === 'tamu') {
            $this->get($url)->assertStatus(302);

            return;
        }

        $this->actingAs($this->user[$peran])->get($url)->assertStatus($status);
    }
}
