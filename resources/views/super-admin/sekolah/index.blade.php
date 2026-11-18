@extends('layouts.panel')

@section('judul', 'Sekolah')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Sekolah"
            keterangan="Data dasar sekolah. Perbedaan antarsekolah (gelombang, berkas, biaya, rekening) diatur oleh admin masing-masing sekolah."
        />

        <x-tabel minimal="880px">
            <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Jenjang</th>
                    <th class="px-4 py-3">Mode</th>
                    <th class="px-4 py-3">Kontak</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--garis)]">
                @foreach ($sekolah as $s)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-[var(--teks)]">{{ $s->nama }}</p>
                            <p class="text-xs text-[var(--teks-sekunder)]">{{ $s->singkatan ?: '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $s->kode }}</td>
                        <td class="px-4 py-3 text-sm">{{ $s->jenjang }}</td>
                        <td class="px-4 py-3 text-sm">{{ $s->mode_penerimaan?->label() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ \App\Support\NomorHp::tampil($s->kontak) ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <x-lencana-status
                                :label="$s->aktif ? 'Aktif' : 'Nonaktif'"
                                :warna="$s->aktif ? 'hijau' : 'netral'"
                            />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-tautan-tombol :href="route('super.sekolah.edit', $s)" tipe="sekunder" ukuran="kecil">Ubah</x-tautan-tombol>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-tabel>
    </div>
@endsection
