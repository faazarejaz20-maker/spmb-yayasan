@php
    $pengguna = auth()->user();
    $tautanAkun = match ($pengguna?->peran?->value) {
        'walisantri' => route('walisantri.beranda'),
        'panitia', 'admin' => route('panitia.beranda'),
        'super_admin' => route('super.beranda'),
        'yayasan' => route('yayasan.beranda'),
        default => route('masuk'),
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'SPMB Al-Ishlah Insan Mulia')</title>
    <meta name="description" content="@yield('deskripsi', 'Penerimaan murid baru Yayasan Al-Ishlah Insan Mulia Wonosobo. Satu pintu untuk TKIT Al Ishlah, KB-TKIT Insan Mulia, SDIT, dan SMPIT Insan Mulia.')">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <meta property="og:title" content="@yield('judul', 'SPMB Al-Ishlah Insan Mulia')">
    <meta property="og:description" content="@yield('deskripsi', 'Penerimaan murid baru Yayasan Al-Ishlah Insan Mulia Wonosobo.')">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="SPMB Al-Ishlah Insan Mulia">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    <a href="#isi-utama" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-[var(--primer)]">
        Lompat ke isi
    </a>

    <header class="sticky top-0 z-40 border-b border-[var(--garis)] bg-white/95 backdrop-blur">
        <div class="mx-auto flex min-h-14 max-w-5xl items-center justify-between gap-3 px-4 py-2">
            <a href="{{ route('beranda') }}" class="flex min-w-0 items-center gap-2">
                <x-logo :tinggi="32" />
                <span class="truncate text-sm font-bold text-[var(--primer)] sm:text-base">
                    <span class="sm:hidden">SPMB Al-Ishlah</span>
                    <span class="hidden sm:inline">SPMB Al-Ishlah Insan Mulia</span>
                </span>
            </a>

            <nav aria-label="Navigasi utama" class="flex shrink-0 items-center gap-1 sm:gap-2">
                <a href="{{ route('beranda') }}" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-[var(--teks-sekunder)] hover:bg-gray-50 hover:text-[var(--primer)] sm:inline-flex">
                    Beranda
                </a>
                @auth
                    <a href="{{ $tautanAkun }}" class="inline-flex min-h-11 items-center rounded-lg bg-[var(--primer)] px-3 py-2 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                        Akun saya
                    </a>
                @else
                    <a href="{{ route('masuk') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold text-[var(--primer)] hover:bg-gray-50">
                        Masuk
                    </a>
                    <a href="{{ route('daftar-akun') }}" class="inline-flex min-h-11 items-center rounded-lg bg-[var(--primer)] px-3 py-2 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]">
                        Daftar
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="isi-utama" class="mx-auto w-full max-w-5xl px-4 py-6 @yield('kelas-utama')">
        @yield('isi')
    </main>

    <footer class="mt-10 border-t border-[var(--garis)] bg-white py-8">
        <div class="mx-auto grid max-w-5xl gap-6 px-4 text-sm text-[var(--teks-sekunder)] sm:grid-cols-[1fr_auto]">
            <div class="space-y-2">
                <p class="font-bold text-[var(--teks)]">{{ config('spmb.yayasan.nama') }}</p>
                <p>{{ config('spmb.yayasan.alamat') }}</p>
                <p>Bantuan: hubungi panitia sekolah tujuan.</p>
                <p>
                    <a href="{{ route('kebijakan-privasi') }}" class="font-semibold text-[var(--primer)] hover:underline">Kebijakan Privasi</a>
                </p>
            </div>
            <div class="flex items-start gap-3">
                @if (is_file(public_path('images/logo/jsit.png')))
                    <img src="{{ asset('images/logo/jsit.png') }}" alt="Logo JSIT" width="48" height="48" loading="lazy" class="h-12 w-12 object-contain">
                @endif
            </div>
        </div>
    </footer>
</body>
</html>
