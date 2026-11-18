@extends('layouts.publik')

@section('judul', 'Lupa Kata Sandi')
@section('robots', 'noindex, nofollow')

@section('isi')
    <div class="mx-auto max-w-md space-y-4">
        <x-kartu judul="Lupa Kata Sandi">
            <x-toast />

            <p class="text-sm text-[var(--teks)]">
                Hubungi panitia sekolah tujuan Anda untuk mengatur ulang kata sandi.
                Panitia akan membuat kata sandi sementara yang wajib Anda ganti saat masuk berikutnya.
            </p>

            <div class="mt-4 space-y-2 text-sm">
                @foreach (\App\Models\Sekolah::where('aktif', true)->get() as $s)
                    @if ($s->kontak)
                        <a
                            href="https://wa.me/{{ $s->kontak }}"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center justify-between rounded-xl border border-[var(--garis)] bg-white p-3 hover:bg-gray-50"
                        >
                            <span class="font-semibold text-[var(--teks)]">{{ $s->nama }}</span>
                            <span class="font-semibold text-[var(--primer)]">WhatsApp</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </x-kartu>

        <x-kartu judul="Atur ulang lewat email">
            <p class="text-sm text-[var(--teks-sekunder)]">
                Bila akun Anda memiliki email dan sekolah sudah mengaktifkan pengiriman email, kami dapat mengirim tautan atur ulang.
                Tautan berlaku 60 menit dan hanya dapat dipakai sekali.
            </p>

            <form method="POST" action="{{ route('lupa-password.simpan') }}" class="mt-4 space-y-3" novalidate>
                @csrf

                <x-isian
                    name="email"
                    label="Email akun"
                    type="email"
                    wajib
                    autocomplete="email"
                />

                <x-tombol tipe="sekunder" class="w-full">Kirim tautan atur ulang</x-tombol>
            </form>

            <p class="mt-3 text-xs text-[var(--teks-sekunder)]">
                Demi keamanan, tampilan jawaban selalu sama, baik email terdaftar maupun tidak.
            </p>
        </x-kartu>

        <div class="text-center text-sm">
            <a href="{{ route('masuk') }}" class="font-semibold text-[var(--primer)] hover:underline">Kembali ke halaman masuk</a>
        </div>
    </div>
@endsection
