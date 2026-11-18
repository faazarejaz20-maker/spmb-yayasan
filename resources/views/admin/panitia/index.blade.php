@extends('layouts.panel')

@section('judul', 'Akun Panitia')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Akun panitia"
            :sekolah="$sekolah?->nama"
            keterangan="Panitia hanya melihat pendaftar sekolah ini. Kata sandi sementara tampil sekali setelah akun dibuat atau diatur ulang."
        />

        <x-kartu judul="Tambah akun panitia">
            <form method="POST" action="{{ route('admin.panitia.simpan') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <x-isian name="nama" label="Nama lengkap" wajib />
                <x-isian name="no_hp" label="Nomor HP" wajib bantuan="08... atau 62..." />
                <x-isian name="email" label="Email (opsional)" tipe="email" />

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-tombol>Buat akun panitia</x-tombol>
                </div>
            </form>

            @error('akun')
                <p class="mt-3 text-sm text-[var(--bahaya)]">{{ $message }}</p>
            @enderror
        </x-kartu>

        @if ($daftar->isEmpty())
            <x-kosong judul="Belum ada akun panitia" keterangan="Tambahkan akun panitia pertama untuk sekolah ini." />
        @else
            <x-tabel minimal="820px">
                <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kontak</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Terakhir masuk</th>
                        <th class="px-4 py-3 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--garis)]">
                    @foreach ($daftar as $panitia)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[var(--teks)]">{{ $panitia->nama }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">{{ $panitia->email ?: 'Tanpa email' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ \App\Support\NomorHp::tampil($panitia->no_hp) ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <x-lencana-status
                                    :label="$panitia->aktif ? 'Aktif' : 'Nonaktif'"
                                    :warna="$panitia->aktif ? 'hijau' : 'netral'"
                                />
                            </td>
                            <td class="px-4 py-3 text-sm text-[var(--teks-sekunder)]">
                                {{ $panitia->terakhir_masuk?->format('d-m-Y H:i') ?? 'Belum pernah' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.panitia.atur-ulang', $panitia) }}">
                                        @csrf
                                        <x-tombol tipe="sekunder" ukuran="kecil">Atur ulang kata sandi</x-tombol>
                                    </form>

                                    @if ($panitia->aktif)
                                        <x-modal-konfirmasi
                                            :aksi="route('admin.panitia.nonaktifkan', $panitia)"
                                            judul="Nonaktifkan akun?"
                                            teks="Akun tidak dapat masuk sampai diaktifkan kembali. Sesi yang sedang berjalan akan dihapus."
                                            labelTombol="Ya, nonaktifkan"
                                        >
                                            Nonaktifkan
                                        </x-modal-konfirmasi>
                                    @else
                                        <form method="POST" action="{{ route('admin.panitia.aktifkan', $panitia) }}">
                                            @csrf
                                            <x-tombol tipe="sekunder" ukuran="kecil">Aktifkan</x-tombol>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tabel>
        @endif
    </div>
@endsection
