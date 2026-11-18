@extends('layouts.panel')

@section('judul', 'Rekap Semua Sekolah')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Rekap semua sekolah"
            keterangan="Rincian agregat per gelombang. Tidak ada data per pendaftar di halaman ini."
        >
            <x-slot:aksi>
                <x-tautan-tombol :href="route('yayasan.rekap.unduh')">Unduh Rekap (Excel)</x-tautan-tombol>
            </x-slot:aksi>
        </x-kepala-halaman>

        @foreach ($rekap['sekolah'] as $s)
            <x-rekap-sekolah :rekap="$s" />
        @endforeach
    </div>
@endsection
