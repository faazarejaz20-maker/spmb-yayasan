@extends('layouts.cetak')

@section('judul', 'Bukti Pendaftaran')

@php
    $namaSekolah = ($sekolah ?? null)?->nama ?? 'SPMB Al-Ishlah Insan Mulia';
@endphp

@section('isi')
    <div class="space-y-5 text-[13px] leading-snug text-gray-900">
        <div class="rounded border-2 border-gray-800 p-4 text-center">
            <p class="text-xs uppercase tracking-wide text-gray-600">Bukti Pendaftaran SPMB</p>
            <p class="mt-1 text-xl font-bold">{{ $nomorPeserta }}</p>
            <p class="text-sm text-gray-600">{{ $namaSekolah }}</p>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-2">
            <div>
                <dt class="text-xs text-gray-500">Nama anak</dt>
                <dd class="font-semibold">{{ $anak?->nama_lengkap ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">NIK (disamarkan)</dt>
                <dd class="font-mono">{{ $nikTersamarkan }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Gelombang</dt>
                <dd class="font-semibold">{{ $gelombang?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Tanggal kirim</dt>
                <dd class="font-semibold">{{ $tanggalKirim }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs text-gray-500">Status pendaftaran</dt>
                <dd class="mt-1 inline-flex rounded border border-gray-400 px-2 py-1 font-semibold">{{ $status->label }}</dd>
                @if ($status->langkahBerikutnya)
                    <p class="mt-1 text-xs text-gray-600">{{ $status->langkahBerikutnya }}</p>
                @endif
            </div>
        </dl>

        <section>
            <h2 class="mb-2 border-b border-gray-300 pb-1 text-sm font-bold uppercase tracking-wide text-gray-800">
                Berkas yang diunggah
            </h2>

            @if (empty($berkas))
                <p class="text-gray-600">Belum ada berkas diunggah.</p>
            @else
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($berkas as $b)
                        <li>{{ $b['nama'] }} &mdash; <span class="text-gray-600">{{ $b['status'] }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="rounded border border-amber-400 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-bold">Simpan bukti ini; bawa saat daftar ulang.</p>
            <p class="mt-1">
                Bukti ini hanya memuat status yang sudah diumumkan. Hasil seleksi dapat dilihat melalui akun
                walisantri setelah sekolah mengumumkannya.
            </p>
        </div>
    </div>
@endsection
