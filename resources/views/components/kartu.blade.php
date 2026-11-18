@props(['judul' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-[var(--garis)] bg-[var(--kartu)] p-5 shadow-sm']) }}>
    @if ($judul)
        <h2 class="mb-4 border-b border-[var(--garis)] pb-3 text-base font-bold text-[var(--teks)]">{{ $judul }}</h2>
    @endif

    {{ $slot }}
</div>
