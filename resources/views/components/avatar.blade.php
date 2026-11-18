@props(['nama' => '', 'kelas' => 'h-10 w-10 text-sm'])

@php
    $awalan = \Illuminate\Support\Str::upper(
        \Illuminate\Support\Str::substr(trim((string) $nama), 0, 1)
    );
@endphp

<span
    aria-hidden="true"
    {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full bg-[var(--primer)]/10 font-bold text-[var(--primer)] ring-1 ring-inset ring-[var(--primer)]/15 '.$kelas]) }}
>{{ $awalan !== '' ? $awalan : '?' }}</span>
