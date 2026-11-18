<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Panel') — {{ config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--latar)] antialiased">
    <a href="#isi-panel" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-[var(--primer)]">
        Lompat ke isi
    </a>

    <div x-data="{ menu: false }" class="flex min-h-screen">
        <x-sidebar />

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b border-[var(--garis)] bg-white/95 backdrop-blur print:hidden">
                <div class="h-1 bg-gradient-to-r from-[var(--primer)] via-[var(--primer-gelap)] to-[var(--aksen)]" aria-hidden="true"></div>

                <div class="flex h-16 items-center justify-between gap-3 px-4 lg:px-6">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <button
                            type="button"
                            @click="menu = true"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[var(--garis)] text-[var(--teks)] transition hover:bg-[var(--latar)] lg:hidden"
                            aria-label="Buka menu"
                        >
                            <x-ikon nama="menu" kelas="h-5 w-5" />
                        </button>

                        <div class="min-w-0">
                            <p class="truncate text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--teks-sekunder)]">
                                {{ auth()->user()->peran?->label() ?? 'Panel' }}
                                @if (auth()->user()?->sekolah)
                                    <span class="text-[var(--garis)]">·</span> {{ auth()->user()->sekolah->nama }}
                                @endif
                            </p>
                            <h1 class="truncate text-base font-bold leading-tight text-[var(--teks)]">@yield('judul', 'Panel')</h1>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <a href="{{ route('ganti-password') }}"
                           class="flex items-center gap-2 rounded-full p-1 text-sm font-semibold text-[var(--teks)] transition hover:bg-[var(--latar)] sm:pr-3"
                           title="Pengaturan akun">
                            <x-avatar :nama="auth()->user()->nama" kelas="h-9 w-9 text-xs" />
                            <span class="hidden max-w-32 truncate sm:block">{{ auth()->user()->nama }}</span>
                        </a>

                        <form method="POST" action="{{ route('keluar') }}">
                            @csrf
                            <button type="submit"
                                    class="flex h-10 w-10 items-center justify-center rounded-full text-[var(--teks-sekunder)] transition hover:bg-red-50 hover:text-[var(--bahaya)]"
                                    title="Keluar" aria-label="Keluar">
                                <x-ikon nama="keluar" kelas="h-5 w-5" />
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main id="isi-panel" class="flex-1 px-4 py-5 print:p-0 lg:px-6 lg:py-6">
                <div class="mx-auto w-full max-w-7xl">
                    <x-toast />
                    @yield('isi')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
