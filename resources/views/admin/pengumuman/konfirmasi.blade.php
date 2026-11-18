@extends('layouts.panel')

@section('judul', 'Konfirmasi Umumkan Hasil')

@section('isi')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-kepala-halaman
            judul="Konfirmasi pengumuman hasil"
            :sekolah="$sekolah?->nama.' · '.$gelombang->nama"
            :kembali="route('admin.pengumuman.index')"
            labelKembali="Umumkan hasil"
        />

        <x-kartu judul="Ringkasan hasil">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <div class="rounded-xl bg-[var(--latar)] p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Terkirim</p>
                    <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $ringkasan['total'] }}</p>
                </div>
                @foreach (\App\Enums\StatusHasil::cases() as $status)
                    <div class="rounded-xl bg-[var(--latar)] p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">{{ $status->label() }}</p>
                        <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $ringkasan['status'][$status->value] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>

            @if ($ringkasan['belum_final'] > 0)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Masih ada {{ $ringkasan['belum_final'] }} pendaftar yang belum final
                    (Baru, Diverifikasi, atau Perlu perbaikan). Mereka akan tetap melihat hasil setelah diumumkan,
                    tetapi keputusan akhir dapat diubah oleh admin dengan catatan setelah pengumuman.
                </div>
            @endif
        </x-kartu>

        <x-kartu judul="Pesan siap bagikan">
            <p class="whitespace-pre-line rounded-xl bg-[var(--latar)] p-4 text-sm text-[var(--teks)]">{{ $teksPesan }}</p>
            <p class="mt-2 text-xs text-[var(--teks-sekunder)]">
                Pesan hanya berisi pemberitahuan umum. Hasil per anak tidak dikirim lewat WhatsApp.
            </p>
        </x-kartu>

        <form method="POST" action="{{ route('admin.pengumuman.simpan', $gelombang) }}" class="space-y-4">
            @csrf

            <label class="flex items-start gap-3 rounded-xl border border-[var(--garis)] bg-white p-4 text-sm text-[var(--teks)]">
                <input type="checkbox" name="konfirmasi" value="1" class="mt-1 h-4 w-4 rounded border-[var(--garis)]">
                <span>
                    Saya memahami bahwa setelah diumumkan, hasil Diterima, Ditolak, dan Cadangan langsung terlihat
                    oleh walisantri dan pengumuman tidak dapat dibatalkan.
                </span>
            </label>
            @error('konfirmasi')
                <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap gap-3">
                <x-tombol>Umumkan hasil sekarang</x-tombol>
                <x-tautan-tombol :href="route('admin.pengumuman.index')" tipe="sekunder">Batal</x-tautan-tombol>
            </div>
        </form>
    </div>
@endsection
