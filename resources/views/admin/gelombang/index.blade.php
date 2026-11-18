@extends('layouts.panel')

@section('judul', 'Gelombang & Kuota')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Gelombang & kuota"
            :sekolah="$sekolah?->nama.' · tahun ajaran '.($tahun?->nama ?? 'belum aktif')"
            keterangan="Kuota kosong berarti tanpa batas. Kuota dan tautan grup tidak tampil di halaman publik."
        />

        <x-kartu judul="Tambah gelombang">
            <form method="POST" action="{{ route('admin.gelombang.simpan') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <x-isian name="nama" label="Nama gelombang" wajib />
                <x-tanggal name="tanggal_buka" label="Tanggal buka" />
                <x-tanggal name="tanggal_tutup" label="Tanggal tutup" />
                <x-isian name="kuota" label="Kuota (kosongkan bila tanpa batas)" tipe="angka" />
                <x-pilihan name="status" label="Status" wajib :opsi="['draf' => 'Draf', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup']" />
                <x-isian name="tautan_grup_wa" label="Tautan grup WhatsApp" bantuan="Hanya https://chat.whatsapp.com/..." />

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-tombol>Simpan gelombang</x-tombol>
                </div>
            </form>
        </x-kartu>

        @if ($gelombang->isEmpty())
            <x-kosong judul="Belum ada gelombang" keterangan="Tambahkan gelombang pertama untuk sekolah ini." />
        @else
            <div class="space-y-4">
                @foreach ($gelombang as $g)
                    <x-kartu>
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-[var(--teks)]">{{ $g->nama }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    {{ $g->status?->label() }} &middot; dibuat {{ $g->created_at?->format('d-m-Y H:i') }}
                                </p>
                            </div>

                            <x-modal-konfirmasi
                                :aksi="route('admin.gelombang.hapus', $g)"
                                metode="DELETE"
                                judul="Hapus gelombang?"
                                teks="Gelombang yang sudah punya pendaftaran atau pengumuman tidak dapat dihapus. Tindakan ini tidak dapat dibatalkan."
                                labelTombol="Ya, hapus"
                            >
                                Hapus
                            </x-modal-konfirmasi>
                        </div>

                        <form method="POST" action="{{ route('admin.gelombang.ubah', $g) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <x-isian name="nama" label="Nama gelombang" wajib :nilai="$g->nama" />
                            <x-tanggal name="tanggal_buka" label="Tanggal buka" :nilai="$g->tanggal_buka" />
                            <x-tanggal name="tanggal_tutup" label="Tanggal tutup" :nilai="$g->tanggal_tutup" />
                            <x-isian name="kuota" label="Kuota" tipe="angka" :nilai="$g->kuota" />
                            <x-pilihan name="status" label="Status" wajib :nilai="$g->status?->value" :opsi="['draf' => 'Draf', 'dibuka' => 'Dibuka', 'ditutup' => 'Ditutup']" />
                            <x-isian name="tautan_grup_wa" label="Tautan grup WhatsApp" :nilai="$g->tautan_grup_wa" bantuan="Hanya https://chat.whatsapp.com/..." />

                            @if ($g->tautan_grup_wa)
                                <p class="text-xs text-[var(--teks-sekunder)] sm:col-span-2 lg:col-span-3">
                                    Tautan saat ini:
                                    <a href="{{ $g->tautan_grup_wa }}" class="break-all font-semibold text-[var(--primer)] hover:underline" rel="noopener noreferrer" target="_blank">{{ $g->tautan_grup_wa }}</a>
                                </p>
                            @endif

                            <div class="sm:col-span-2 lg:col-span-3">
                                <x-tombol>Simpan perubahan</x-tombol>
                            </div>
                        </form>
                    </x-kartu>
                @endforeach
            </div>
        @endif
    </div>
@endsection
