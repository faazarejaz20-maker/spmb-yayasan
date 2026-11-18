@extends('layouts.panel')

@section('judul', 'Konten Yayasan')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Kelola konten yayasan"
            keterangan="Konten halaman yayasan. Teks disimpan sebagai teks biasa dan di-escape saat tampil. Konten sekolah diatur oleh admin masing-masing sekolah."
        />

        @error('konten')
            <p class="text-sm text-[var(--bahaya)]">{{ $message }}</p>
        @enderror

        @include('panel.konten-editor', ['bagian' => $bagian, 'rute' => $rute])
    </div>
@endsection
