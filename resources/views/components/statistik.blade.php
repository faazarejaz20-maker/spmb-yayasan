@props([
    'label',
    'nilai',
    'ikon' => 'grafik',
    'warna' => 'primer',
    'keterangan' => null,
    'tautan' => null,
    'tautanLabel' => 'Lihat',
])

@php
    $gaya = match ($warna) {
        'biru' => 'bg-blue-50 text-blue-700 ring-blue-100',
        'oranye' => 'bg-orange-50 text-orange-700 ring-orange-100',
        'hijau' => 'bg-green-50 text-green-700 ring-green-100',
        'kuning' => 'bg-amber-50 text-amber-700 ring-amber-100',
        'merah' => 'bg-red-50 text-red-700 ring-red-100',
        'netral' => 'bg-gray-100 text-gray-600 ring-gray-200',
        default => 'bg-teal-50 text-teal-700 ring-teal-100',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase leading-tight tracking-wide text-[var(--teks-sekunder)]">{{ $label }}</p>
            <p class="mt-1.5 text-2xl font-bold leading-none text-[var(--teks)]">{{ $nilai }}</p>
        </div>

        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset {{ $gaya }}">
            <x-ikon :nama="$ikon" kelas="h-5 w-5" />
        </span>
    </div>

    @if ($keterangan)
        <p class="mt-2 text-xs leading-snug text-[var(--teks-sekunder)]">{{ $keterangan }}</p>
    @endif

    @if ($tautan)
        <a href="{{ $tautan }}" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-[var(--primer)] hover:underline">
            {{ $tautanLabel }}
            <x-ikon nama="panah-kanan" kelas="h-3.5 w-3.5" />
        </a>
    @endif
</div>
