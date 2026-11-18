@props([
    'judul',
    'keterangan' => null,
    'eyebrow' => null,
    'anak' => null,
    'sekolah' => null,
    'kembali' => null,
    'labelKembali' => 'Kembali',
])

<div {{ $attributes->merge(['class' => 'flex min-w-0 flex-wrap items-start justify-between gap-3']) }}>
    <div class="min-w-0">
        @if ($kembali)
            <a href="{{ $kembali }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[var(--primer)] hover:underline">
                <span aria-hidden="true">&larr;</span> {{ $labelKembali }}
            </a>
        @endif

        @if ($eyebrow)
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[var(--primer)]">{{ $eyebrow }}</p>
        @endif

        <h1 class="mt-0.5 text-xl font-bold leading-tight text-[var(--teks)]">{{ $judul }}</h1>

        @if ($anak || $sekolah)
            <p class="mt-1 text-sm text-[var(--teks-sekunder)]">
                {{ $anak }}@if ($anak && $sekolah) · @endif{{ $sekolah }}
            </p>
        @endif

        @if ($keterangan)
            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-[var(--teks-sekunder)]">{{ $keterangan }}</p>
        @endif

        {{ $slot }}
    </div>

    @if (isset($aksi) && ! $aksi->isEmpty())
        <div class="flex flex-wrap items-center gap-2 print:hidden">{{ $aksi }}</div>
    @endif
</div>
