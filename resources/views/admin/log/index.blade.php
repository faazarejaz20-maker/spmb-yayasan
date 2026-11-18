@extends('layouts.panel')

@section('judul', 'Log Aktivitas')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Log aktivitas"
            :sekolah="$sekolah?->nama"
            keterangan="Log tidak dapat diubah atau dihapus. Kata sandi dan nomor rekening utuh tidak pernah dicatat."
        />

        <form method="GET" action="{{ route('admin.log.index') }}" class="grid gap-3 rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <x-tanggal name="dari" label="Dari tanggal" :nilai="$filter['dari'] ?? null" />
            <x-tanggal name="sampai" label="Sampai tanggal" :nilai="$filter['sampai'] ?? null" />

            <div>
                <label for="pelaku" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Pelaku</label>
                <select id="pelaku" name="pelaku" class="min-h-12 w-full rounded-xl border border-[var(--garis)] bg-white px-4 text-sm">
                    <option value="">Semua pelaku</option>
                    @foreach ($pelaku as $orang)
                        <option value="{{ $orang->id }}" @selected((int) ($filter['pelaku'] ?? 0) === (int) $orang->id)>{{ $orang->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="aksi" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Aksi</label>
                <select id="aksi" name="aksi" class="min-h-12 w-full rounded-xl border border-[var(--garis)] bg-white px-4 text-sm">
                    <option value="">Semua aksi</option>
                    @foreach ($daftarAksi as $aksi)
                        <option value="{{ $aksi }}" @selected(($filter['aksi'] ?? '') === $aksi)>{{ $aksi }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <x-tombol tipe="sekunder">Terapkan</x-tombol>
                <a href="{{ route('admin.log.index') }}" class="inline-flex min-h-12 items-center text-sm font-semibold text-[var(--primer)] hover:underline">Reset</a>
            </div>
        </form>

        @if ($log->isEmpty())
            <x-kosong judul="Belum ada log" keterangan="Log akan terisi saat ada tindakan pengguna." />
        @else
            <x-tabel minimal="900px">
                <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Pelaku</th>
                        <th class="px-4 py-3">Aksi</th>
                        <th class="px-4 py-3">Objek</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--garis)]">
                    @foreach ($log as $baris)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-[var(--teks-sekunder)]">
                                {{ $baris->dibuat_pada?->format('d-m-Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                {{ $baris->user?->nama ?? 'Sistem' }}
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-[var(--teks)]">{{ $baris->aksi }}</td>
                            <td class="px-4 py-3 text-sm text-[var(--teks-sekunder)]">
                                {{ $baris->objek ? $baris->objek.' #'.$baris->objek_id : '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $data = $baris->data ? json_decode($baris->data, true) : null;
                                @endphp

                                @if (is_array($data) && $data !== [])
                                    <details>
                                        <summary class="cursor-pointer text-[var(--primer)]">Lihat rincian</summary>
                                        <pre class="mt-2 max-w-md overflow-x-auto whitespace-pre-wrap break-words rounded-lg bg-[var(--latar)] p-2 text-xs text-[var(--teks)]">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    <span class="text-[var(--teks-sekunder)]">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tabel>

            <div>{{ $log->links() }}</div>
        @endif
    </div>
@endsection
