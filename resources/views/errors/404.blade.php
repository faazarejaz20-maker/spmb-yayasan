@extends('layouts.publik')

@section('judul', '404 — Tidak Ditemukan')
@section('robots', 'noindex, nofollow')

@section('isi')
    <div class="mx-auto max-w-md py-12 text-center">
        <p class="text-6xl font-bold text-[var(--primer)]">404</p>
        <h1 class="mt-4 text-xl font-bold text-[var(--teks)]">Halaman Tidak Ditemukan</h1>
        <p class="mt-2 text-sm text-[var(--teks-sekunder)]">Halaman yang Anda cari tidak tersedia atau sudah dipindahkan.</p>
        <div class="mt-6">
            <a href="{{ route('beranda') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-6 py-3 font-semibold text-white hover:bg-[var(--primer-gelap)]">
                Kembali ke Beranda
            </a>
        </div>
    </div>
@endsection
