@extends('layouts.panel')

@section('judul', 'Pendaftar Offline')

@section('isi')
    <div class="space-y-4">
        <x-kepala-halaman
            judul="Pendaftaran offline"
            keterangan="Untuk walisantri yang datang langsung ke sekolah. Buat atau cari akun walisantri, lalu isi formulir atas nama mereka."
        />

        @if ($akun)
            <div class="rounded-2xl border border-[var(--primer)] bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--teks-sekunder)]">Akun terpilih</p>
                        <p class="mt-1 font-semibold text-[var(--teks)]">{{ $akun->nama }}</p>
                        <p class="text-sm text-[var(--teks-sekunder)]">{{ \App\Support\NomorHp::tampil($akun->no_hp) ?: $akun->email }}</p>
                    </div>

                    <x-tautan-tombol :href="route('panitia.offline.sekolah')">Lanjut pilih sekolah</x-tautan-tombol>
                </div>

                <div class="mt-3 flex flex-wrap gap-3 border-t border-[var(--garis)] pt-3 text-sm">
                    <a href="{{ route('panitia.offline.orang-tua') }}" class="font-semibold text-[var(--primer)] hover:underline">
                        Isi data orang tua / wali
                    </a>
                </div>
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <x-kartu judul="Cari akun yang sudah ada">
                <p class="text-xs text-[var(--teks-sekunder)]">
                    Masukkan nomor HP persis seperti saat mendaftar.
                </p>

                <form method="POST" action="{{ route('panitia.offline.cari') }}" class="mt-3 space-y-3">
                    @csrf

                    @error('no_hp')
                        <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                    @enderror

                    <x-isian name="no_hp" label="Nomor HP walisantri" inputmode="tel" maxlength="20" :nilai="old('no_hp')" wajib />

                    <x-tombol tipe="sekunder">Cari akun</x-tombol>
                </form>
            </x-kartu>

            <x-kartu judul="Buat akun walisantri baru">
                <p class="text-xs text-[var(--teks-sekunder)]">
                    Kata sandi sementara dibuat otomatis dan ditampilkan sekali setelah akun dibuat.
                </p>

                <form method="POST" action="{{ route('panitia.offline.akun') }}" class="mt-3 space-y-3">
                    @csrf

                    @error('akun')
                        <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                    @enderror

                    <x-isian name="nama" label="Nama walisantri" :nilai="old('nama')" wajib />
                    <x-isian name="no_hp" label="Nomor HP" inputmode="tel" maxlength="20" :nilai="old('no_hp')" wajib
                             bantuan="Contoh 08xxxxxxxxxx. Nomor ini dipakai untuk masuk." />
                    <x-isian name="email" label="Email (opsional)" tipe="email" :nilai="old('email')" />

                    <x-tombol>Buat akun</x-tombol>
                </form>
            </x-kartu>
        </div>

        <x-kartu judul="Alur singkat">
            <ol class="list-inside list-decimal space-y-1 text-sm text-[var(--teks-sekunder)]">
                <li>Buat atau cari akun walisantri.</li>
                <li>Isi data orang tua / wali bila belum ada.</li>
                <li>Pilih gelombang dan anak yang didaftarkan.</li>
                <li>Lengkapi formulir, berkas, dan biaya, lalu kirim atas nama walisantri.</li>
            </ol>
        </x-kartu>
    </div>
@endsection
