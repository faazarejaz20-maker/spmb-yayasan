@props([
    'judul' => 'Yakin?',
    'teks' => '',
    'aksi' => '#',
    'metode' => 'POST',
    'labelTombol' => 'Ya, lanjutkan',
    'warna' => 'bahaya',
])

@php
    $kelasTombol = match ($warna) {
        'primer' => 'bg-[var(--primer)] hover:bg-[var(--primer-gelap)]',
        default => 'bg-[var(--bahaya)] hover:bg-red-800',
    };
@endphp

<div x-data="{ buka: false }">
    <button type="button" @click="buka = true" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-lg text-sm font-semibold '.($warna === 'primer' ? 'text-[var(--primer)]' : 'text-[var(--bahaya)]')]) }}>
        {{ $slot }}
    </button>

    <div x-cloak x-show="buka" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="buka = false" @keydown.escape.window="buka = false">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
            <h3 class="text-base font-bold text-[var(--teks)]">{{ $judul }}</h3>
            @if ($teks)
                <p class="mt-2 text-sm text-[var(--teks-sekunder)]">{{ $teks }}</p>
            @endif

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="buka = false" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-4 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primer)] focus-visible:ring-offset-2">Batal</button>
                <form method="POST" action="{{ $aksi }}">
                    @csrf
                    @method($metode)
                    {{ $isian ?? '' }}
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primer)] focus-visible:ring-offset-2 {{ $kelasTombol }}">{{ $labelTombol }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
