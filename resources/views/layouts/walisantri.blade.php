<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Akun Saya') — {{ config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--latar)] antialiased">
    <a href="#isi-utama" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-[var(--primer)]">
        Lompat ke isi
    </a>

    @php
        $namaPanggilan = \Illuminate\Support\Str::before((string) auth()->user()->nama, ' ');
        $menu = [
            ['label' => 'Beranda', 'rute' => 'walisantri.beranda', 'aktif' => request()->routeIs('walisantri.beranda')],
            ['label' => 'Daftar anak', 'rute' => 'walisantri.daftar.baru', 'aktif' => request()->routeIs('walisantri.daftar.*')],
            ['label' => 'Orang tua', 'rute' => 'walisantri.orang-tua', 'aktif' => request()->routeIs('walisantri.orang-tua*')],
        ];
    @endphp

    <header class="sticky top-0 z-40 border-b border-[var(--garis)] bg-white/95 backdrop-blur">
        <div class="h-1 bg-gradient-to-r from-[var(--primer)] via-[var(--primer-gelap)] to-[var(--aksen)]" aria-hidden="true"></div>

        <div class="mx-auto flex h-16 max-w-2xl items-center justify-between gap-2 px-4">
            <a href="{{ route('walisantri.beranda') }}" class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--latar)] ring-1 ring-inset ring-[var(--garis)]">
                    <x-logo :tinggi="30" />
                </span>
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-[10px] font-semibold uppercase tracking-[0.14em] text-[var(--teks-sekunder)]">SPMB 2027/2028</span>
                    <span class="block truncate text-sm font-bold text-[var(--teks)]">Akun Walisantri</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi akun">
                @foreach ($menu as $m)
                    <a href="{{ route($m['rute']) }}"
                       @if ($m['aktif']) aria-current="page" @endif
                       class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ $m['aktif'] ? 'bg-[var(--primer)]/10 text-[var(--primer)]' : 'text-[var(--teks-sekunder)] hover:bg-[var(--latar)] hover:text-[var(--teks)]' }}">
                        {{ $m['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-1">
                <a href="{{ route('ganti-password') }}" class="flex items-center gap-2 rounded-full p-1 pr-1 text-sm font-semibold text-[var(--teks)] hover:bg-[var(--latar)] sm:pr-3" title="Pengaturan akun">
                    <x-avatar :nama="auth()->user()->nama" kelas="h-9 w-9 text-xs" />
                    <span class="hidden max-w-24 truncate sm:block">{{ $namaPanggilan }}</span>
                </a>

                <form method="POST" action="{{ route('keluar') }}">
                    @csrf
                    <button type="submit"
                            class="flex h-10 w-10 items-center justify-center rounded-full text-[var(--teks-sekunder)] transition hover:bg-red-50 hover:text-[var(--bahaya)]"
                            title="Keluar" aria-label="Keluar">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main id="isi-utama" class="mx-auto max-w-2xl px-4 pb-28 pt-5 lg:pb-12">
        <x-toast />

        @yield('isi')
    </main>

    <x-navigasi-bawah />
</body>
</html>
