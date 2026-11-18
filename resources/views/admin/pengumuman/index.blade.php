@extends('layouts.panel')

@section('judul', 'Umumkan Hasil')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Umumkan hasil"
            :sekolah="$sekolah?->nama"
            keterangan="Hasil seleksi tidak terlihat oleh walisantri sebelum diumumkan untuk sekolah dan gelombang ini. Pengumuman hanya dapat dilakukan sekali per gelombang."
        />

        @if ($gelombang->isEmpty())
            <x-kosong judul="Belum ada gelombang" keterangan="Tambahkan gelombang sebelum mengumumkan hasil." />
        @else
            <x-tabel minimal="820px">
                <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                    <tr>
                        <th class="px-4 py-3">Gelombang</th>
                        <th class="px-4 py-3">Terkirim</th>
                        <th class="px-4 py-3">Belum final</th>
                        <th class="px-4 py-3">Diterima</th>
                        <th class="px-4 py-3">Ditolak</th>
                        <th class="px-4 py-3">Cadangan</th>
                        <th class="px-4 py-3">Status pengumuman</th>
                        <th class="px-4 py-3 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--garis)]">
                    @foreach ($gelombang as $g)
                        @php
                            $r = $ringkasan[$g->id] ?? ['total' => 0, 'status' => [], 'belum_final' => 0];
                            $umum = $pengumuman->get($g->id);
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[var(--teks)]">{{ $g->nama }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">{{ $g->status?->label() }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $r['total'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $r['belum_final'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $r['status'][\App\Enums\StatusHasil::Diterima->value] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm">{{ $r['status'][\App\Enums\StatusHasil::Ditolak->value] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm">{{ $r['status'][\App\Enums\StatusHasil::Cadangan->value] ?? 0 }}</td>
                            <td class="px-4 py-3">
                                @if ($umum)
                                    <x-lencana-status label="Sudah diumumkan" warna="hijau" />
                                    <p class="mt-1 text-xs text-[var(--teks-sekunder)]">{{ $umum->diumumkan_pada?->format('d-m-Y H:i') }}</p>
                                @else
                                    <x-lencana-status label="Belum diumumkan" warna="netral" />
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($umum)
                                    <span class="text-sm text-[var(--teks-sekunder)]">Sudah diumumkan</span>
                                @else
                                    <x-tautan-tombol :href="route('admin.pengumuman.konfirmasi', $g)" ukuran="kecil">
                                        Umumkan
                                    </x-tautan-tombol>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tabel>
        @endif
    </div>
@endsection
