@props(['rekap', 'tampilkanSekolah' => true])

@php
    $r = $rekap['ringkasan'];
@endphp

<x-kartu>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-bold text-[var(--teks)]">
            {{ $tampilkanSekolah ? ($rekap['sekolah']->nama ?? 'Sekolah') : 'Ringkasan' }}
        </h2>
        <p class="text-xs text-[var(--teks-sekunder)]">Hanya angka agregat, tanpa data per pendaftar.</p>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <div class="rounded-xl bg-[var(--latar)] p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Pendaftar terkirim</p>
            <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $r['total'] }}</p>
        </div>

        @foreach (\App\Enums\StatusHasil::cases() as $status)
            <div class="rounded-xl bg-[var(--latar)] p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">{{ $status->label() }}</p>
                <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $r['status'][$status->value] ?? 0 }}</p>
            </div>
        @endforeach

        <div class="rounded-xl bg-[var(--latar)] p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Daftar ulang selesai</p>
            <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $r['daftar_ulang_selesai'] }}</p>
        </div>
        <div class="rounded-xl bg-[var(--latar)] p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Daftar ulang belum</p>
            <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $r['daftar_ulang_belum'] }}</p>
        </div>
        <div class="rounded-xl bg-[var(--latar)] p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Mengundurkan diri</p>
            <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $r['mengundurkan_diri'] }}</p>
        </div>
        <div class="rounded-xl bg-[var(--latar)] p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Lulusan yayasan</p>
            <p class="mt-1 text-2xl font-bold text-[var(--teks)]">{{ $r['lulusan_yayasan'] }}</p>
        </div>
    </div>

    @if (($r['program'] ?? []) !== [])
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($r['program'] as $program => $jumlah)
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800">
                    {{ $program }}: {{ $jumlah }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
        @foreach (\App\Enums\StatusPembayaran::cases() as $status)
            <div class="rounded-xl border border-[var(--garis)] p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Pembayaran {{ $status->label() }}</p>
                <p class="mt-1 text-xl font-bold text-[var(--teks)]">{{ $r['pembayaran'][$status->value] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    @if ($rekap['gelombang'] !== [])
        <div class="mt-4">
            <x-tabel minimal="760px">
                <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                    <tr>
                        <th class="px-4 py-3">Gelombang</th>
                        <th class="px-4 py-3">Terkirim</th>
                        <th class="px-4 py-3">Diterima</th>
                        <th class="px-4 py-3">Ditolak</th>
                        <th class="px-4 py-3">Cadangan</th>
                        <th class="px-4 py-3">Kuota</th>
                        <th class="px-4 py-3">Terisi</th>
                        <th class="px-4 py-3">Diumumkan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--garis)]">
                    @foreach ($rekap['gelombang'] as $g)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-[var(--teks)]">{{ $g['nama'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $g['total'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $g['status'][\App\Enums\StatusHasil::Diterima->value] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm">{{ $g['status'][\App\Enums\StatusHasil::Ditolak->value] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm">{{ $g['status'][\App\Enums\StatusHasil::Cadangan->value] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm">{{ $g['kuota'] ?? 'Tanpa batas' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $g['terisi'] }}</td>
                            <td class="px-4 py-3">
                                <x-lencana-status
                                    :label="$g['diumumkan'] ? 'Sudah' : 'Belum'"
                                    :warna="$g['diumumkan'] ? 'hijau' : 'netral'"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tabel>
        </div>
    @endif
</x-kartu>
