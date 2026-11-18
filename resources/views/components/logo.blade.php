@props(['tinggi' => 40, 'kelas' => ''])

@php
    $berkas = public_path('images/logo/yayasan.png');
    $tinggi = (int) $tinggi;
    $kelasKotak = match ($tinggi) {
        32 => 'h-8 w-8',
        36 => 'h-9 w-9',
        48 => 'h-12 w-12',
        56 => 'h-14 w-14',
        64 => 'h-16 w-16',
        default => 'h-10 w-10',
    };
@endphp

@if (file_exists($berkas))
    @php
        $ukuran = @getimagesize($berkas);
        $lebar = $ukuran !== false && $ukuran[1] > 0
            ? (int) round($ukuran[0] * ($tinggi / $ukuran[1]))
            : $tinggi;
    @endphp
    <img
        src="{{ asset('images/logo/yayasan.png') }}"
        alt="Logo Yayasan Al-Ishlah Insan Mulia"
        height="{{ $tinggi }}"
        width="{{ $lebar }}"
        loading="lazy"
        {{ $attributes->merge(['class' => 'object-contain '.$kelasKotak.' '.$kelas]) }}
    >
@else
    <span
        aria-label="Logo menyusul"
        {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg bg-gray-200 text-center text-[10px] leading-tight text-gray-500 '.$kelasKotak.' '.$kelas]) }}
    >Logo menyusul</span>
@endif
