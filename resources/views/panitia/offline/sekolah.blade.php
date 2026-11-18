@extends('layouts.panel')

@section('judul', 'Pilih Sekolah & Gelombang')

@section('isi')
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <x-kepala-halaman
                eyebrow="Akun walisantri"
                :judul="$akun->nama"
                :sekolah="\App\Support\NomorHp::tampil($akun->no_hp) ?: $akun->email"
            >
                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    <a href="{{ route('panitia.offline.orang-tua') }}" class="font-semibold text-[var(--primer)] hover:underline">
                        Data orang tua / wali
                    </a>
                    <a href="{{ route('panitia.offline.index') }}" class="font-semibold text-[var(--teks-sekunder)] hover:underline">
                        Ganti akun
                    </a>
                </div>
            </x-kepala-halaman>
        </div>

        @if ($gelombang->isEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Belum ada gelombang yang dibuka untuk {{ $sekolah->nama }}. Hubungi admin sekolah.
            </div>
        @endif

        <x-kartu judul="Pilih gelombang dan anak">
            <form method="POST" action="{{ route('panitia.offline.sekolah.simpan') }}" class="space-y-4">
                @csrf

                <div class="rounded-xl bg-[var(--latar)] p-3 text-sm">
                    <p class="text-xs text-[var(--teks-sekunder)]">Sekolah</p>
                    <p class="font-semibold text-[var(--teks)]">{{ $sekolah->nama }}</p>
                </div>

                @error('gelombang_id')
                    <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                @enderror

                @error('program')
                    <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                @enderror

                @error('anak_id')
                    <p class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
                @enderror

                <x-pilihan name="gelombang_id" label="Gelombang" wajib
                           :opsi="$gelombang->mapWithKeys(fn ($g) => [$g->id => $g->nama.($g->kuota !== null ? ' (kuota '.$g->kuota.')' : '')])->all()"
                           :nilai="old('gelombang_id')" />

                @if ($butuhProgram)
                    <x-pilihan name="program" label="Program" wajib
                               :opsi="collect(config('spmb.nomor_peserta.program_tki'))->mapWithKeys(fn ($p) => [$p => $p])->all()"
                               :nilai="old('program')" />
                @endif

                <div>
                    <label for="anak_id" class="mb-1 block text-sm font-semibold text-[var(--teks)]">Anak</label>
                    <select id="anak_id" name="anak_id"
                            class="min-h-12 w-full rounded-xl border border-[var(--garis)] bg-white px-4 text-sm">
                        <option value="">Anak baru (isi data anak)</option>
                        @foreach ($anak as $a)
                            <option value="{{ $a->id }}" @selected((string) old('anak_id') === (string) $a->id)>
                                {{ $a->nama_lengkap }}{{ $a->tanggal_lahir ? ' — '.$a->tanggal_lahir->format('d-m-Y') : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-[var(--teks-sekunder)]">
                        Pilih anak yang sudah ada untuk mendaftarkan ulang, atau biarkan kosong untuk mengisi anak baru.
                    </p>
                </div>

                <x-tombol>Lanjut isi formulir</x-tombol>
            </form>
        </x-kartu>
    </div>
@endsection
