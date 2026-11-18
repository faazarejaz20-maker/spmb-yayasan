@extends('layouts.panel')

@section('judul', 'Akun Admin Sekolah')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Akun admin sekolah"
            keterangan="Admin sekolah mengelola pengaturan sekolah dan turut menangani pendaftar sekolahnya. Setiap sekolah sebaiknya punya minimal satu admin aktif."
        />

        <x-kartu judul="Tambah akun admin sekolah">
            <form method="POST" action="{{ route('super.admin.simpan') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <x-isian name="nama" label="Nama lengkap" wajib />
                <x-isian name="no_hp" label="Nomor HP" wajib bantuan="08... atau 62..." />
                <x-isian name="email" label="Email (opsional)" tipe="email" />
                <x-pilihan name="sekolah_id" label="Sekolah" wajib :opsi="$sekolah->mapWithKeys(fn ($s) => [$s->id => $s->nama])->all()" />

                <div class="sm:col-span-2 lg:col-span-4">
                    <x-tombol>Buat akun admin</x-tombol>
                </div>
            </form>

            @error('akun')
                <p class="mt-3 text-sm text-[var(--bahaya)]">{{ $message }}</p>
            @enderror
        </x-kartu>

        @if ($daftar->isEmpty())
            <x-kosong judul="Belum ada akun admin" keterangan="Tambahkan akun admin untuk tiap sekolah." />
        @else
            <x-tabel minimal="900px">
                <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Sekolah</th>
                        <th class="px-4 py-3">Kontak</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Terakhir masuk</th>
                        <th class="px-4 py-3 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--garis)]">
                    @foreach ($daftar as $admin)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[var(--teks)]">{{ $admin->nama }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">{{ $admin->email ?: 'Tanpa email' }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $admin->sekolah?->nama ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ \App\Support\NomorHp::tampil($admin->no_hp) ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <x-lencana-status
                                    :label="$admin->aktif ? 'Aktif' : 'Nonaktif'"
                                    :warna="$admin->aktif ? 'hijau' : 'netral'"
                                />
                            </td>
                            <td class="px-4 py-3 text-sm text-[var(--teks-sekunder)]">
                                {{ $admin->terakhir_masuk?->format('d-m-Y H:i') ?? 'Belum pernah' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('super.admin.atur-ulang', $admin) }}">
                                        @csrf
                                        <x-tombol tipe="sekunder" ukuran="kecil">Atur ulang kata sandi</x-tombol>
                                    </form>

                                    @if ($admin->aktif)
                                        <x-modal-konfirmasi
                                            :aksi="route('super.admin.nonaktifkan', $admin)"
                                            judul="Nonaktifkan akun?"
                                            teks="Akun tidak dapat masuk sampai diaktifkan kembali. Sesi yang sedang berjalan akan dihapus."
                                            labelTombol="Ya, nonaktifkan"
                                        >
                                            Nonaktifkan
                                        </x-modal-konfirmasi>
                                    @else
                                        <form method="POST" action="{{ route('super.admin.aktifkan', $admin) }}">
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
