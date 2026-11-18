@extends('layouts.publik')

@section('judul', 'Buat Akun')
@section('robots', 'noindex, nofollow')

@section('isi')
    <div class="mx-auto max-w-md">
        <x-kartu judul="Buat Akun Walisantri">
            <x-toast />

            <form method="POST" action="{{ route('daftar-akun.simpan') }}" class="space-y-4">
                @csrf

                <x-isian name="nama" label="Nama lengkap" type="text" wajib autocomplete="name" autofocus />
                <x-isian name="no_hp" label="Nomor HP" type="text" wajib autocomplete="tel" placeholder="08xxxxxxxxxx" />
                <x-isian name="email" label="Email (opsional)" type="email" autocomplete="email" />
                <x-isian name="kata_sandi" label="Kata sandi" type="password" wajib autocomplete="new-password" />
                <x-isian name="kata_sandi_confirmation" label="Ulangi kata sandi" type="password" wajib autocomplete="new-password" />

                <label class="flex items-start gap-2 text-sm text-[var(--teks)]">
                    <input type="checkbox" name="setuju_data" value="1" class="mt-0.5 h-4 w-4 rounded border-[var(--garis)] text-[var(--primer)]">
                    <span>
                        Saya menyetujui penggunaan data sesuai
                        <a href="{{ route('kebijakan-privasi') }}" class="font-semibold text-[var(--primer)] hover:underline">Kebijakan Privasi</a>.
                    </span>
                </label>

                <x-tombol tipe="primer" class="w-full">Daftar</x-tombol>
            </form>

            <p class="mt-4 text-center text-sm text-[var(--teks-sekunder)]">
                Sudah punya akun?
                <a href="{{ route('masuk') }}" class="font-semibold text-[var(--primer)] hover:underline">Masuk</a>
            </p>
        </x-kartu>
    </div>
@endsection
