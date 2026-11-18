@extends('layouts.publik')

@section('judul', '500 — Terjadi Kesalahan')
@section('robots', 'noindex, nofollow')

@section('isi')
    <div class="mx-auto max-w-md py-12 text-center">
        <p class="text-6xl font-bold text-[var(--primer)]">500</p>
        <h1 class="mt-4 text-xl font-bold text-[var(--teks)]">Terjadi Kesalahan</h1>
        <p class="mt-2 text-sm text-[var(--teks-sekunder)]">Mohon maaf, terjadi kesalahan pada sistem. Silakan coba lagi beberapa saat kemudian.</p>
        <div class="mt-6">
            <a href="{{ route('beranda') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[var(--primer)] px-6 py-3 font-semibold text-white hover:bg-[var(--primer-gelap)]">
                Kembali ke Beranda
            </a>
        </div>
    </div>
@endsection
