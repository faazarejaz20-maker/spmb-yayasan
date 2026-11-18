@props(['item' => [], 'aktif' => null])

<nav class="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="Bagian halaman">
    @foreach ($item as $kunci => $tab)
        @php
            $url = is_array($tab) ? ($tab['url'] ?? '#') : $tab;
            $teks = is_array($tab) ? ($tab['label'] ?? $kunci) : $kunci;
            $lencana = is_array($tab) ? ($tab['lencana'] ?? null) : null;
        @endphp

        <a
            href="{{ $url }}"
            class="inline-flex min-h-11 shrink-0 items-center rounded-full px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primer)] focus-visible:ring-offset-2 {{ $aktif === $kunci ? 'bg-[var(--primer)] text-white shadow-sm' : 'border border-[var(--garis)] bg-white text-[var(--teks)] hover:bg-gray-50' }}"
            @if ($aktif === $kunci) aria-current="page" @endif
        >
            {{ $teks }}
            @if ($lencana)
                <span class="ml-1 rounded-full bg-black/10 px-1.5 text-xs">{{ $lencana }}</span>
            @endif
        </a>
    @endforeach
</nav>
