<?php

namespace Tests\Unit;

use App\Support\NomorHp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NomorHpTest extends TestCase
{
    public function test_normalisasi_dari_awalan_08(): void
    {
        $this->assertSame('6285695422700', NomorHp::normalisasi('0856-9542-2700'));
        $this->assertSame('6285695422700', NomorHp::normalisasi('08 5695 4227 00'));
    }

    public function test_normalisasi_dari_62_dan_plus(): void
    {
        $this->assertSame('6285695422700', NomorHp::normalisasi('+62 856-9542-2700'));
        $this->assertSame('6285695422700', NomorHp::normalisasi('6285695422700'));
    }

    public function test_normalisasi_menolak_terlalu_pendek(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NomorHp::normalisasi('12345');
    }

    public function test_normalisasi_menolak_terlalu_panjang(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NomorHp::normalisasi('12345678901234567890');
    }

    public function test_normalisasi_kosong_menjadi_null(): void
    {
        $this->assertNull(NomorHp::normalisasi(null));
        $this->assertNull(NomorHp::normalisasi(''));
    }

    public function test_tampil_format_lokal(): void
    {
        $this->assertSame('0856-9542-2700', NomorHp::tampil('6285695422700'));
    }

    public function test_tampil_kosong(): void
    {
        $this->assertSame('', NomorHp::tampil(null));
    }

    public function test_samarkan(): void
    {
        $this->assertSame('*********2700', NomorHp::samarkan('6285695422700'));
    }
}
