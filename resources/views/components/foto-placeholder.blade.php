@props(['tinggi' => 'sedang', 'alt' => 'Foto menyusul'])

@php
    $kelasTinggi = match ($tinggi) {
        'pendek', 'kecil' => 'h-32',
        'tinggi', 'besar' => 'h-56',
        default => 'h-40',
    };
@endphp

<div
    role="img"
    aria-label="{{ $alt }}"
    {{ $attributes->merge(['class' => 'flex w-full items-center justify-center rounded-xl bg-gray-200 text-gray-500 '.$kelasTinggi]) }}
>
    <span class="px-4 text-center text-sm">{{ $alt }}</span>
</div>
