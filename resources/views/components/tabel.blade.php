@props(['minimal' => '720px'])

@php
    // Kelas literal (bukan gaya inline) supaya CSP `style-src 'self'` terpenuhi
    // dan Tailwind tetap menemukan kelasnya saat build.
    $kelasTabel = match ((string) $minimal) {
        '640px' => 'min-w-[640px]',
        '760px' => 'min-w-[760px]',
        '820px' => 'min-w-[820px]',
        '860px' => 'min-w-[860px]',
        '880px' => 'min-w-[880px]',
        '900px' => 'min-w-[900px]',
        '960px' => 'min-w-[960px]',
        default => 'min-w-[720px]',
    };
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-[var(--garis)] bg-white']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm {{ $kelasTabel }}">
            {{ $slot }}
        </table>
    </div>

    <p class="border-t border-[var(--garis)] px-4 py-2 text-[11px] text-[var(--teks-sekunder)] sm:hidden print:hidden">
        Geser tabel ke samping untuk melihat kolom lainnya.
    </p>
</div>
