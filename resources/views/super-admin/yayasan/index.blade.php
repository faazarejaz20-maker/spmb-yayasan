@extends('layouts.panel')

@section('judul', 'Akun Yayasan')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Akun yayasan"
            keterangan="Akun yayasan hanya dapat melihat rekap agregat. Akun ini tidak terikat pada satu sekolah dan tidak memiliki akses ke data pendaftar."
        />

        <x-kartu judul="Tambah akun yayasan">
            <form method="POST" action="{{ route('super.yayasan.simpan') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <x-isian name="nama" label="Nama lengkap" wajib />
                <x-isian name="no_hp" label="Nomor HP" wajib bantuan="08... atau 62..." />
                <x-isian name="email" label="Email (opsional)" tipe="email" />

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-tombol>Buat akun yayasan</x-tombol>
                </div>
            </form>

            @error('akun')
                <p class="mt-3 text-sm text-[var(--bahaya)]">{{ $message }}</p>
            @enderror
        </x-kartu>

        @if ($daftar->isEmpty())
            <x-kosong judul="Belum ada akun yayasan" keterangan="Tambahkan akun untuk pengurus yayasan." />
        @else
            <x-tabel minimal="860px">
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
                    @foreach ($daftar as $akun)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[var(--teks)]">{{ $akun->nama }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">{{ $akun->email ?: 'Tanpa email' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ \App\Support\NomorHp::tampil($akun->no_hp) ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <x-lencana-status
                                    :label="$akun->aktif ? 'Aktif' : 'Nonaktif'"
                                    :warna="$akun->aktif ? 'hijau' : 'netral'"
                                />
                            </td>
                            <td class="px-4 py-3 text-sm text-[var(--teks-sekunder)]">
                                {{ $akun->terakhir_masuk?->format('d-m-Y H:i') ?? 'Belum pernah' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('super.yayasan.atur-ulang', $akun) }}">
                                        @csrf
                                        <x-tombol tipe="sekunder" ukuran="kecil">Atur ulang kata sandi</x-tombol>
                                    </form>

                                    @if ($akun->aktif)
                                        <x-modal-konfirmasi
                                            :aksi="route('super.yayasan.nonaktifkan', $akun)"
                                            judul="Nonaktifkan akun?"
                                            teks="Akun tidak dapat masuk sampai diaktifkan kembali. Sesi yang sedang berjalan akan dihapus."
                                            labelTombol="Ya, nonaktifkan"
                                        >
                                            Nonaktifkan
                                        </x-modal-konfirmasi>
                                    @else
                                        <form method="POST" action="{{ route('super.yayasan.aktifkan', $akun) }}">
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
