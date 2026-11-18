@extends('layouts.publik')

@section('judul', 'Atur Ulang Kata Sandi')
@section('robots', 'noindex, nofollow')

@section('isi')
    <div class="mx-auto max-w-md">
        <x-kartu judul="Atur Ulang Kata Sandi">
            <x-toast />

            <p class="text-sm text-[var(--teks-sekunder)]">
                Buat kata sandi baru untuk akun SPMB Anda. Minimal 8 karakter dan mengandung huruf.
            </p>

            <form method="POST" action="{{ route('password.reset.simpan') }}" class="mt-4 space-y-4" novalidate>
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                @error('email')
                    <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                @enderror

                <div class="rounded-xl bg-[var(--latar)] p-3 text-sm">
                    <p class="text-xs text-[var(--teks-sekunder)]">Akun</p>
                    <p class="break-all font-semibold text-[var(--teks)]">{{ $email }}</p>
                </div>

                <x-isian
                    name="password"
                    label="Kata sandi baru"
                    type="password"
                    wajib
                    autocomplete="new-password"
                    bantuan="Minimal 8 karakter dan mengandung huruf."
                />

                <x-isian
                    name="password_confirmation"
                    label="Ulangi kata sandi baru"
                    type="password"
                    wajib
                    autocomplete="new-password"
                />

                <x-tombol class="w-full">Simpan kata sandi baru</x-tombol>
            </form>

            <div class="mt-4 text-sm">
                <a href="{{ route('masuk') }}" class="font-semibold text-[var(--primer)] hover:underline">
                    Kembali ke halaman masuk
                </a>
            </div>
        </x-kartu>
    </div>
@endsection
