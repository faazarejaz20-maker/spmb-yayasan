@extends('layouts.panel')

@section('judul', 'Dasbor Admin')

@section('isi')
    <div class="space-y-5">
        <section class="pola-hero relative overflow-hidden rounded-3xl bg-[var(--primer)] p-6 text-white shadow-sm sm:p-7">
            <div class="absolute -right-12 -top-14 h-40 w-40 rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="absolute -bottom-16 right-12 h-32 w-32 rounded-full bg-[var(--aksen)]/20" aria-hidden="true"></div>

            <div class="relative flex flex-wrap items-end justify-between gap-5">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/75">
                        Dasbor admin<span class="hidden sm:inline"> · {{ $sekolah?->nama ?? 'Sekolah belum ditetapkan' }}</span>
                    </p>
                    <h1 class="mt-1 text-2xl font-bold leading-tight">Selamat datang, {{ auth()->user()->nama }}</h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                        Ringkasan sekolah Anda. Data pendaftar per orang tidak ditampilkan di dasbor ini.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.pengumuman.index') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-[var(--primer)] shadow-sm transition hover:bg-teal-50">
                        <x-ikon nama="corong" kelas="h-4 w-4" />
                        Umumkan hasil
                    </a>
                    <a href="{{ route('admin.gelombang.index') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/40 px-4 text-sm font-semibold text-white transition hover:bg-white/10">
                        <x-ikon nama="kalender" kelas="h-4 w-4" />
                        Gelombang & kuota
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <x-statistik label="Pendaftar terkirim" :nilai="$jumlah['total']" ikon="pengguna-banyak" warna="primer" />
            <x-statistik label="Panitia aktif" :nilai="$jumlah['panitia']" ikon="pengguna" warna="biru" />

            @foreach ([
                ['status' => \App\Enums\StatusHasil::Baru, 'ikon' => 'jam', 'warna' => 'biru'],
                ['status' => \App\Enums\StatusHasil::Diverifikasi, 'ikon' => 'centang', 'warna' => 'hijau'],
                ['status' => \App\Enums\StatusHasil::PerluPerbaikan, 'ikon' => 'seru', 'warna' => 'oranye'],
                ['status' => \App\Enums\StatusHasil::Diterima, 'ikon' => 'centang', 'warna' => 'hijau'],
                ['status' => \App\Enums\StatusHasil::Ditolak, 'ikon' => 'seru', 'warna' => 'merah'],
                ['status' => \App\Enums\StatusHasil::Cadangan, 'ikon' => 'daftar', 'warna' => 'kuning'],
            ] as $kartu)
                <x-statistik
                    :label="$kartu['status']->label()"
                    :nilai="$jumlah['status'][$kartu['status']->value] ?? 0"
                    :ikon="$kartu['ikon']"
                    :warna="$kartu['warna']"
                />
            @endforeach
        </div>

        <section>
            <h2 class="px-1 text-sm font-bold text-[var(--teks)]">Aksi pengaturan</h2>

            <div class="mt-2.5 grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    ['label' => 'Gelombang', 'rute' => 'admin.gelombang.index', 'ikon' => 'kalender'],
                    ['label' => 'Umumkan hasil', 'rute' => 'admin.pengumuman.index', 'ikon' => 'corong'],
                    ['label' => 'Akun panitia', 'rute' => 'admin.panitia.index', 'ikon' => 'pengguna'],
                    ['label' => 'Jenis berkas', 'rute' => 'admin.berkas.index', 'ikon' => 'dokumen'],
                    ['label' => 'Rincian biaya', 'rute' => 'admin.biaya.index', 'ikon' => 'uang'],
                    ['label' => 'Pengaturan', 'rute' => 'admin.pengaturan', 'ikon' => 'gerigi'],
                ] as $aksi)
                    <a href="{{ route($aksi['rute']) }}"
                       class="group flex flex-col items-start gap-2 rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--primer)]/40 hover:shadow">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700 ring-1 ring-inset ring-teal-100 transition group-hover:bg-[var(--primer)] group-hover:text-white">
                            <x-ikon :nama="$aksi['ikon']" kelas="h-4 w-4" />
                        </span>
                        <span class="text-xs font-semibold text-[var(--teks)]">{{ $aksi['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-sm font-bold text-[var(--teks)]">
                    <x-ikon nama="kalender" kelas="h-4 w-4 text-[var(--primer)]" />
                    Gelombang sekolah
                </h2>
                <a href="{{ route('admin.rekap.index') }}" class="text-xs font-semibold text-[var(--primer)] hover:underline">Lihat rekap sekolah</a>
            </div>

            <div class="mt-4">
                @if ($gelombang->isEmpty())
                    <x-kosong judul="Belum ada gelombang" keterangan="Tambahkan gelombang di menu Gelombang & kuota." />
                @else
                    <x-tabel minimal="760px">
                        <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                            <tr>
                                <th class="px-4 py-3">Gelombang</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Kuota</th>
                                <th class="px-4 py-3">Terisi</th>
                                <th class="px-4 py-3">Pengumuman</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--garis)]">
                            @foreach ($gelombang as $baris)
                                @php($g = $baris['gelombang'])
                                <tr class="transition hover:bg-[var(--latar)]/60">
                                    <td class="px-4 py-3 font-semibold text-[var(--teks)]">{{ $g->nama }}</td>
                                    <td class="px-4 py-3"><x-lencana-status :label="$g->status?->label() ?? '—'" :warna="$g->status?->value === 'dibuka' ? 'hijau' : 'netral'" /></td>
                                    <td class="px-4 py-3 text-sm">{{ $g->kuota ?? 'Tanpa batas' }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $baris['terisi'] }}@if ($g->kuota !== null) / {{ $g->kuota }}@endif</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($baris['diumumkan'])
                                            <x-lencana-status label="Sudah diumumkan" warna="hijau" />
                                        @else
                                            <span class="text-[var(--teks-sekunder)]">Belum diumumkan</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tabel>
                @endif
            </div>
        </div>
    </div>
@endsection
