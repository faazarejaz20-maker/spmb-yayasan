@extends(auth()->user()->peran->value === 'walisantri' ? 'layouts.walisantri' : 'layouts.panel')

@section('judul', 'Ganti Kata Sandi')

@section('isi')
    <div class="mx-auto max-w-md">
        <x-kartu judul="Ganti Kata Sandi">
            <x-toast />

            <form method="POST" action="{{ route('ganti-password.simpan') }}" class="space-y-4">
                @csrf

                @unless (auth()->user()->wajib_ganti_password)
                    <x-isian name="kata_sandi_lama" label="Kata sandi lama" type="password" wajib autocomplete="current-password" />
                @else
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        Anda wajib mengganti kata sandi sementara sebelum melanjutkan.
                    </div>
                @endunless

                <x-isian name="kata_sandi_baru" label="Kata sandi baru" type="password" wajib autocomplete="new-password" />
                <x-isian name="kata_sandi_baru_confirmation" label="Ulangi kata sandi baru" type="password" wajib autocomplete="new-password" />

                <x-tombol tipe="primer" class="w-full">Simpan Kata Sandi</x-tombol>
            </form>
        </x-kartu>
    </div>
@endsection
