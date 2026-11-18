@props(['judul' => 'Tidak ada data', 'keterangan' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-2xl border border-dashed border-[var(--garis)] bg-white px-6 py-12 text-center']) }}>
    <svg class="mb-3 h-10 w-10 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5M4.5 6.75h15a1.5 1.5 0 0 1 1.5 1.5v9a1.5 1.5 0 0 1-1.5 1.5h-15a1.5 1.5 0 0 1-1.5-1.5v-9a1.5 1.5 0 0 1 1.5-1.5Z" />
    </svg>
    <p class="font-semibold text-[var(--teks)]">{{ $judul }}</p>
    @if ($keterangan)
        <p class="mt-1 max-w-md text-sm text-[var(--teks-sekunder)]">{{ $keterangan }}</p>
    @endif
</div>
