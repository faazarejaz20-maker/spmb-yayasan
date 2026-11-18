@props(['langkah' => 1, 'total' => 8])

@php
    $label = [
        1 => 'Pilih sekolah',
        2 => 'Data anak',
        3 => 'Alamat',
        4 => 'Kesehatan',
        5 => 'Asal sekolah',
        6 => 'Berkas',
        7 => 'Biaya',
        8 => 'Kirim',
    ];
@endphp

<div class="mb-6" aria-label="Kemajuan formulir">
    <div class="flex items-center justify-between text-sm font-semibold text-[var(--teks)] sm:hidden">
        <span>Langkah {{ $langkah }} dari {{ $total }}</span>
        <span class="text-[var(--teks-sekunder)]">{{ $label[$langkah] ?? '' }}</span>
    </div>

    <progress class="kemajuan-formulir mt-2 w-full sm:hidden" max="{{ max(1, (int) $total) }}" value="{{ max(0, min((int) $total, (int) $langkah)) }}">
        Langkah {{ $langkah }} dari {{ $total }}
    </progress>

    <ol class="hidden items-start justify-between sm:flex">
        @foreach ($label as $nomor => $teks)
            <li class="flex flex-1 flex-col items-center text-center">
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold
                        {{ $nomor < $langkah ? 'bg-[var(--primer)] text-white' : ($nomor === $langkah ? 'border-2 border-[var(--primer)] text-[var(--primer)]' : 'border border-[var(--garis)] text-[var(--teks-sekunder)]') }}"
                    aria-current="{{ $nomor === $langkah ? 'step' : 'false' }}"
                >
                    {{ $nomor }}
                </span>
                <span class="mt-1 text-[10px] leading-tight {{ $nomor === $langkah ? 'font-bold text-[var(--teks)]' : 'text-[var(--teks-sekunder)]' }}">
                    {{ $teks }}
                </span>
            </li>
        @endforeach
    </ol>
</div>
