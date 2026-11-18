@extends('layouts.panel')

@section('judul', 'Rekap Sekolah')

@section('isi')
    <div class="space-y-5">
        <x-kepala-halaman
            judul="Rekap sekolah"
            keterangan="Angka agregat pendaftaran dan pembayaran. Rekap ini tidak memuat nama, nomor peserta, atau rincian pembayaran per orang."
        >
            <x-slot:aksi>
                <x-tautan-tombol :href="route('admin.rekap.unduh')">Unduh Rekap (Excel)</x-tautan-tombol>
            </x-slot:aksi>
        </x-kepala-halaman>

        <x-rekap-sekolah :rekap="$rekap" />
    </div>
@endsection
