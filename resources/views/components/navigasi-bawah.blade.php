@php
    $item = [
        [
            'label' => 'Beranda',
            'rute' => 'walisantri.beranda',
            'aktif' => request()->routeIs('walisantri.beranda'),
            'ikon' => 'rumah',
        ],
        [
            'label' => 'Daftar',
            'rute' => 'walisantri.daftar.baru',
            'aktif' => request()->routeIs('walisantri.daftar.*'),
            'ikon' => 'tambah',
        ],
        [
            'label' => 'Orang tua',
            'rute' => 'walisantri.orang-tua',
            'aktif' => request()->routeIs('walisantri.orang-tua*'),
            'ikon' => 'keluarga',
        ],
        [
            'label' => 'Akun',
            'rute' => 'ganti-password',
            'aktif' => request()->routeIs('ganti-password*'),
            'ikon' => 'akun',
        ],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--garis)] bg-white/95 shadow-[0_-4px_16px_rgba(15,23,42,0.06)] backdrop-blur lg:hidden" aria-label="Navigasi akun walisantri">
    <div class="mx-auto grid max-w-2xl grid-cols-4 gap-1 px-2 pb-1.5 pt-1.5">
        @foreach ($item as $i)
            <a
                href="{{ route($i['rute']) }}"
                @if ($i['aktif']) aria-current="page" @endif
                class="flex flex-col items-center gap-0.5 rounded-xl px-1 py-1.5 text-[11px] font-semibold {{ $i['aktif'] ? 'text-[var(--primer)]' : 'text-[var(--teks-sekunder)]' }}"
            >
                <span class="flex h-8 w-12 items-center justify-center rounded-full {{ $i['aktif'] ? 'bg-[var(--primer)]/10' : '' }}">
                    @switch($i['ikon'])
                        @case('rumah')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            @break
                        @case('tambah')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            @break
                        @case('keluarga')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            @break
                        @default
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                    @endswitch
                </span>
                {{ $i['label'] }}
            </a>
        @endforeach
    </div>
</nav>
