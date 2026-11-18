<div class="space-y-4">
    <x-kartu judul="Riwayat status">
        @if ($pendaftaran->riwayatStatus->isEmpty())
            <p class="text-sm text-[var(--teks-sekunder)]">Belum ada perubahan status.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-[var(--garis)] text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                        <tr>
                            <th class="py-2 pr-3">Waktu</th>
                            <th class="py-2 pr-3">Jenis</th>
                            <th class="py-2 pr-3">Perubahan</th>
                            <th class="py-2 pr-3">Oleh</th>
                            <th class="py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--garis)]">
                        @foreach ($pendaftaran->riwayatStatus->sortByDesc('dibuat_pada') as $riwayat)
                            <tr class="align-top">
                                <td class="whitespace-nowrap py-2 pr-3 text-[var(--teks-sekunder)]">
                                    {{ $riwayat->dibuat_pada?->format('d-m-Y H:i') ?? '—' }}
                                </td>
                                <td class="py-2 pr-3 text-[var(--teks)]">{{ $riwayat->jenis?->label() ?? '—' }}</td>
                                <td class="py-2 pr-3 text-[var(--teks)]">
                                    {{ $riwayat->dari ?: '—' }} &rarr; <span class="font-semibold">{{ $riwayat->ke ?: '—' }}</span>
                                </td>
                                <td class="py-2 pr-3 text-[var(--teks)]">{{ $riwayat->olehUser?->nama ?? 'Sistem' }}</td>
                                <td class="py-2 text-[var(--teks-sekunder)]">{{ $riwayat->catatan ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-kartu>

    <x-kartu judul="Log aktivitas">
        <p class="text-xs text-[var(--teks-sekunder)]">
            Hanya mencatat nama kolom yang berubah, bukan isi data pribadi.
        </p>

        @if ($log->isEmpty())
            <p class="mt-2 text-sm text-[var(--teks-sekunder)]">Belum ada log untuk pendaftaran ini.</p>
        @else
            <ul class="mt-2 divide-y divide-[var(--garis)] text-sm">
                @foreach ($log as $baris)
                    <li class="py-2">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="font-semibold text-[var(--teks)]">{{ $baris->aksi }}</span>
                            <span class="text-xs text-[var(--teks-sekunder)]">{{ $baris->dibuat_pada?->format('d-m-Y H:i') ?? '—' }}</span>
                        </div>
                        <p class="text-xs text-[var(--teks-sekunder)]">
                            {{ $baris->user?->nama ?? 'Sistem' }}
                            @if ($baris->ip_address)
                                &middot; {{ $baris->ip_address }}
                            @endif
                        </p>

                        @if ($baris->data)
                            <pre class="mt-1 overflow-x-auto rounded-lg bg-[var(--latar)] p-2 text-xs text-[var(--teks-sekunder)]">{{ $baris->data }}</pre>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-kartu>
</div>
