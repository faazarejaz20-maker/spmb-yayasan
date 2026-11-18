@extends('layouts.panel')

@section('judul', 'Profil Akun')

@section('isi')
    <div class="mx-auto max-w-xl space-y-5">
        <x-kepala-halaman
            judul="Profil akun"
            keterangan="Data akun yayasan Anda."
        />

        <x-kartu>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Nama</dt>
                    <dd class="font-semibold text-[var(--teks)]">{{ $pengguna->nama }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Peran</dt>
                    <dd class="text-[var(--teks)]">{{ $pengguna->peran?->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Nomor HP</dt>
                    <dd class="text-[var(--teks)]">{{ \App\Support\NomorHp::tampil($pengguna->no_hp) ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Email</dt>
                    <dd class="break-all text-[var(--teks)]">{{ $pengguna->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[var(--teks-sekunder)]">Terakhir masuk</dt>
                    <dd class="text-[var(--teks)]">{{ $pengguna->terakhir_masuk?->format('d-m-Y H:i') ?? '—' }}</dd>
                </div>
            </dl>

            <div class="mt-4 border-t border-[var(--garis)] pt-4">
                <x-tautan-tombol :href="route('ganti-password')" tipe="sekunder">Ganti kata sandi</x-tautan-tombol>
            </div>
        </x-kartu>
    </div>
@endsection
