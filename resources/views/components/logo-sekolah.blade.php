@props(['kode' => null, 'nama' => null, 'kelas' => 'h-8 w-8'])

@php
    $gambar = $kode
        ? app(\App\Support\GambarPublik::class)->logo($kode, $nama ? 'Logo '.$nama : 'Logo sekolah')
        : null;
@endphp

@if ($gambar)
    <img
        src="{{ $gambar['url'] }}"
        alt="{{ $gambar['alt'] }}"
        loading="lazy"
        decoding="async"
        {{ $attributes->merge(['class' => 'shrink-0 rounded-lg object-contain '.$kelas]) }}
    >
@else
    <span
        aria-label="Logo menyusul"
        {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-lg bg-gray-100 px-1 text-center text-[8px] font-semibold leading-tight text-gray-500 '.$kelas]) }}
    >Logo menyusul</span>
@endif
