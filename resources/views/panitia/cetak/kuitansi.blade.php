@extends('layouts.cetak')

@section('judul', 'Kuitansi Pembayaran')

@php
    $namaSekolah = ($sekolah ?? null)?->nama ?? 'SPMB Al-Ishlah Insan Mulia';
@endphp

@section('isi')
    <div class="space-y-5 text-[13px] leading-snug text-gray-900">
        <div class="flex items-start justify-between border-b border-gray-300 pb-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-600">Nomor kuitansi</p>
                <p class="font-mono text-base font-bold">{{ $nomorKuitansi }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-gray-600">Tanggal</p>
                <p class="font-semibold">{{ $tanggal }}</p>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-2">
            <div>
                <dt class="text-xs text-gray-500">Nama anak</dt>
                <dd class="font-semibold">{{ $namaAnak }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Nomor peserta</dt>
                <dd class="font-mono font-semibold">{{ $nomorPeserta }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs text-gray-500">Komponen pembayaran</dt>
                <dd class="font-semibold">{{ $komponen }}</dd>
            </div>
        </dl>

        <div class="rounded border-2 border-gray-800 p-4 text-center">
            <p class="text-xs uppercase tracking-wide text-gray-600">Jumlah dibayar</p>
            <p class="mt-1 text-2xl font-bold">Rp {{ number_format((int) $nominal, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-600">Metode: {{ $metode }}</p>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-2">
            <div>
                <dt class="text-xs text-gray-500">Diterima oleh</dt>
                <dd class="font-semibold">{{ $penerima }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Status pembayaran</dt>
                <dd class="font-semibold">Disetujui</dd>
            </div>
        </dl>

        <p class="rounded border border-gray-400 bg-gray-50 p-3 text-xs font-semibold text-gray-800">
            Bukan bukti lunas SPP. Kuitansi ini hanya untuk pembayaran yang tercatat pada sistem SPMB.
        </p>

        <div class="mt-10 flex justify-end">
            <div class="w-56 text-center text-xs">
                <p class="text-gray-600">{{ $namaSekolah }}</p>
                <div class="mt-16 border-t border-gray-400 pt-1">Penerima</div>
            </div>
        </div>
    </div>
@endsection
