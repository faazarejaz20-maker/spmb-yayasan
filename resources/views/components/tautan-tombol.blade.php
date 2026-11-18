@props([
    'href',
    'tipe' => 'primer',
    'ukuran' => 'sedang',
])

@php
    $warna = match ($tipe) {
        'sekunder' => 'bg-white text-[var(--teks)] border border-[var(--garis)] hover:bg-gray-50',
        'bahaya' => 'bg-[var(--bahaya)] text-white hover:bg-red-800',
        'hantu' => 'bg-transparent text-[var(--primer)] hover:bg-gray-50',
        default => 'bg-[var(--primer)] text-white hover:bg-[var(--primer-gelap)]',
    };

    $ukuran = match ($ukuran) {
        'kecil' => 'min-h-11 px-3 py-1.5 text-xs',
        'besar' => 'min-h-12 px-6 py-3 text-base',
        default => 'min-h-12 px-4 py-2.5 text-sm',
    };
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primer)] focus-visible:ring-offset-2 '.$warna.' '.$ukuran]) }}>
    {{ $slot }}
</a>
