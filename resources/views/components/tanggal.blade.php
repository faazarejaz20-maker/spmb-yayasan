@props(['name', 'label' => null, 'nilai' => null, 'wajib' => false, 'bantuan' => null])

@php
    $label = $label ?? ucwords(str_replace('_', ' ', $name));
    $lama = old($name, $nilai);

    if ($lama instanceof \DateTimeInterface) {
        $lama = $lama->format('Y-m-d');
    }
@endphp

<div class="{{ trim('w-full '.$attributes->get('class', '')) }}">
    <label for="{{ $name }}" class="mb-1 block text-sm font-semibold text-[var(--teks)]">
        {{ $label }}
        @if ($wajib)
            <span class="text-[var(--bahaya)]" aria-hidden="true">*</span>
        @endif
    </label>

    <input
        type="date"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $lama }}"
        {{ $attributes->except(['class', 'name', 'id', 'value', 'label', 'nilai', 'wajib', 'bantuan']) }}
        class="min-h-12 w-full rounded-xl border border-[var(--garis)] bg-white px-4 py-2.5 text-[var(--teks)] focus:border-[var(--primer)] focus:outline-none focus:ring-2 focus:ring-[var(--primer)]/30"
    >

    @if ($bantuan)
        <p class="mt-1 text-xs text-[var(--teks-sekunder)]">{{ $bantuan }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-sm text-[var(--bahaya)]">{{ $message }}</p>
    @enderror
</div>
