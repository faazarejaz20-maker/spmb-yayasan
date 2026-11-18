@props([
    'tipe' => 'primer',
    'jenis' => 'submit',
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
        'kecil' => 'px-3 py-1.5 text-sm',
        'besar' => 'px-6 py-3 text-base',
        default => 'px-4 py-2.5 text-sm',
    };
@endphp

<button type="{{ $jenis }}" {{ $attributes->merge(['class' => 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primer)] focus-visible:ring-offset-2 disabled:opacity-50 '.$warna.' '.$ukuran]) }}>
    {{ $slot }}
</button>
