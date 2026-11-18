@extends('layouts.panel')

@section('judul', 'Dasbor Super Admin')

@section('isi')
    <div class="space-y-5">
        <section class="pola-hero relative overflow-hidden rounded-3xl bg-[var(--primer)] p-6 text-white shadow-sm sm:p-7">
            <div class="absolute -right-12 -top-14 h-40 w-40 rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="absolute -bottom-16 right-12 h-32 w-32 rounded-full bg-[var(--aksen)]/20" aria-hidden="true"></div>

            <div class="relative flex flex-wrap items-end justify-between gap-5">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/75">Super admin · Yayasan</p>
                    <h1 class="mt-1 text-2xl font-bold leading-tight">Ringkasan yayasan</h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                        Masuk sebagai {{ auth()->user()->nama }}. Kelola sekolah dan akun staf yayasan.
                        Data pendaftar per orang tidak pernah ditampilkan di panel ini.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('super.sekolah.index') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-[var(--primer)] shadow-sm transition hover:bg-teal-50">
                        <x-ikon nama="gedung" kelas="h-4 w-4" />
                        Kelola sekolah
                    </a>
                    <a href="{{ route('super.admin.index') }}"
                       class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/40 px-4 text-sm font-semibold text-white transition hover:bg-white/10">
                        <x-ikon nama="pengguna" kelas="h-4 w-4" />
                        Akun admin
                    </a>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <x-statistik label="Sekolah" :nilai="$jumlahSekolah" ikon="gedung" warna="primer" />
            <x-statistik label="Admin sekolah" :nilai="$akun['admin'] ?? 0" ikon="pengguna" warna="biru" />
            <x-statistik label="Panitia" :nilai="$akun['panitia'] ?? 0" ikon="pengguna-banyak" warna="netral" />
            <x-statistik label="Yayasan" :nilai="$akun['yayasan'] ?? 0" ikon="perisai" warna="kuning" />
            <x-statistik label="Super admin" :nilai="$akun['super_admin'] ?? 0" ikon="kunci" warna="hijau" />
        </div>

        <section>
            <h2 class="px-1 text-sm font-bold text-[var(--teks)]">Aksi cepat</h2>

            <div class="mt-2.5 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                @foreach ([
                    ['label' => 'Sekolah', 'keterangan' => 'Identitas & mode penerimaan', 'rute' => 'super.sekolah.index', 'ikon' => 'gedung'],
                    ['label' => 'Akun admin sekolah', 'keterangan' => 'Tambah dan atur ulang sandi', 'rute' => 'super.admin.index', 'ikon' => 'pengguna'],
                    ['label' => 'Akun yayasan', 'keterangan' => 'Akun hanya lihat rekap', 'rute' => 'super.yayasan.index', 'ikon' => 'perisai'],
                    ['label' => 'Konten yayasan', 'keterangan' => 'Profil dan sorotan publik', 'rute' => 'super.konten.index', 'ikon' => 'foto'],
                ] as $aksi)
                    <a href="{{ route($aksi['rute']) }}"
                       class="group flex flex-col gap-2 rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--primer)]/40 hover:shadow">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700 ring-1 ring-inset ring-teal-100 transition group-hover:bg-[var(--primer)] group-hover:text-white">
                            <x-ikon :nama="$aksi['ikon']" kelas="h-4 w-4" />
                        </span>
                        <span class="text-xs font-semibold text-[var(--teks)]">{{ $aksi['label'] }}</span>
                        <span class="text-[11px] leading-snug text-[var(--teks-sekunder)]">{{ $aksi['keterangan'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="rounded-2xl border border-[var(--garis)] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 text-sm font-bold text-[var(--teks)]">
                    <x-ikon nama="gedung" kelas="h-4 w-4 text-[var(--primer)]" />
                    Sekolah dan jumlah agregat
                </h2>
                <span class="text-xs text-[var(--teks-sekunder)]">{{ $jumlahSekolah }} sekolah terdaftar</span>
            </div>

            <div class="mt-4">
                <x-tabel minimal="860px">
                    <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                        <tr>
                            <th class="px-4 py-3">Sekolah</th>
                            <th class="px-4 py-3">Jenjang</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Admin aktif</th>
                            <th class="px-4 py-3">Pendaftar terkirim</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--garis)]">
                        @foreach ($sekolah as $baris)
                            <tr class="transition hover:bg-[var(--latar)]/60">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <x-logo-sekolah :kode="$baris['sekolah']->kode" :nama="$baris['sekolah']->nama" kelas="h-8 w-8" />
                                        <div class="min-w-0">
                                            <p class="font-semibold text-[var(--teks)]">{{ $baris['sekolah']->nama }}</p>
                                            <p class="font-mono text-[11px] uppercase tracking-wide text-[var(--teks-sekunder)]">{{ $baris['sekolah']->kode }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $baris['sekolah']->jenjang }}</td>
                                <td class="px-4 py-3 text-sm">{{ $baris['sekolah']->mode_penerimaan?->label() ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($baris['admin'] === 0)
                                        <x-lencana-status label="Belum ada" warna="oranye" />
                                    @else
                                        <span class="font-semibold text-[var(--teks)]">{{ $baris['admin'] }}</span>
                                        <span class="text-[var(--teks-sekunder)]">akun</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-[var(--teks)]">{{ $baris['pendaftar'] }}</td>
                                <td class="px-4 py-3">
                                    <x-lencana-status
                                        :label="$baris['sekolah']->aktif ? 'Aktif' : 'Nonaktif'"
                                        :warna="$baris['sekolah']->aktif ? 'hijau' : 'netral'"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('super.sekolah.edit', $baris['sekolah']) }}"
                                       class="inline-flex items-center gap-1 font-semibold text-[var(--primer)] hover:underline">
                                        Ubah
                                        <x-ikon nama="panah-kanan" kelas="h-3.5 w-3.5" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tabel>
            </div>
        </div>
    </div>
@endsection
