@extends('layouts.panel')

@section('judul', 'Kelola Konten')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Kelola konten sekolah"
            :sekolah="$sekolah?->nama"
            keterangan="Teks disimpan sebagai teks biasa dan di-escape saat tampil. Tampilannya di halaman publik dibangun pada tahap berikutnya."
        />

        @error('konten')
            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror

        @include('panel.konten-editor', ['bagian' => $bagian, 'rute' => $rute])
    </div>
@endsection
