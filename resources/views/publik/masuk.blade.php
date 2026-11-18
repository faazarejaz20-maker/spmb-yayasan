@extends('layouts.publik')

@section('judul', 'Masuk')
@section('robots', 'noindex, nofollow')

@section('isi')
    <div class="mx-auto max-w-md">
        <x-kartu judul="Masuk">
            <x-toast />

            <form method="POST" action="{{ route('masuk.simpan') }}" class="space-y-4">
                @csrf

                <x-isian
                    name="identitas"
                    label="Nomor HP atau email"
                    type="text"
                    wajib
                    autocomplete="username"
                    autofocus
                />

                <x-isian
                    name="kata_sandi"
                    label="Kata sandi"
                    type="password"
                    wajib
                    autocomplete="current-password"
                />

                <x-tombol tipe="primer" class="w-full">Masuk</x-tombol>
            </form>

            <div class="mt-4 flex items-center justify-between text-sm">
                <a href="{{ route('lupa-password') }}" class="font-semibold text-[var(--primer)] hover:underline">
                    Lupa kata sandi?
                </a>
                <a href="{{ route('daftar-akun') }}" class="font-semibold text-[var(--primer)] hover:underline">
                    Buat Akun Baru
                </a>
            </div>
        </x-kartu>
    </div>
@endsection
