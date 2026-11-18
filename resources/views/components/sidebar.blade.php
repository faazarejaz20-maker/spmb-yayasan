@php
    $user = auth()->user();
    $peran = $user?->peran?->value;

    $grup = [];

    if (in_array($peran, ['panitia', 'admin'], true)) {
        $grup['Operasional'] = [
            ['label' => 'Dasbor panitia', 'rute' => 'panitia.beranda', 'aktif' => 'panitia.beranda', 'ikon' => 'rumah'],
            ['label' => 'Pendaftar', 'rute' => 'panitia.pendaftar.index', 'aktif' => 'panitia.pendaftar.*', 'ikon' => 'pengguna-banyak'],
            ['label' => 'Pembayaran', 'rute' => 'panitia.pembayaran.index', 'aktif' => 'panitia.pembayaran.*', 'ikon' => 'kartu'],
            ['label' => 'Pendaftar offline', 'rute' => 'panitia.offline.index', 'aktif' => 'panitia.offline.*', 'ikon' => 'tambah-pengguna'],
        ];
    }

    if ($peran === 'admin') {
        $grup['Pengaturan sekolah'] = [
            ['label' => 'Dasbor admin', 'rute' => 'admin.beranda', 'aktif' => 'admin.beranda', 'ikon' => 'grafik'],
            ['label' => 'Gelombang & kuota', 'rute' => 'admin.gelombang.index', 'aktif' => 'admin.gelombang.*', 'ikon' => 'kalender'],
            ['label' => 'Pengaturan sekolah', 'rute' => 'admin.pengaturan', 'aktif' => 'admin.pengaturan*', 'ikon' => 'gerigi'],
            ['label' => 'Akun panitia', 'rute' => 'admin.panitia.index', 'aktif' => 'admin.panitia.*', 'ikon' => 'pengguna'],
            ['label' => 'Jenis berkas', 'rute' => 'admin.berkas.index', 'aktif' => 'admin.berkas.*', 'ikon' => 'dokumen'],
            ['label' => 'Rincian biaya', 'rute' => 'admin.biaya.index', 'aktif' => 'admin.biaya.*', 'ikon' => 'uang'],
            ['label' => 'Rekening sekolah', 'rute' => 'admin.rekening.index', 'aktif' => 'admin.rekening.*', 'ikon' => 'kartu'],
            ['label' => 'Isian formulir', 'rute' => 'admin.isian.index', 'aktif' => 'admin.isian.*', 'ikon' => 'papan-clip'],
        ];

        $grup['Pengumuman & laporan'] = [
            ['label' => 'Umumkan hasil', 'rute' => 'admin.pengumuman.index', 'aktif' => 'admin.pengumuman.*', 'ikon' => 'corong'],
            ['label' => 'Rekap sekolah', 'rute' => 'admin.rekap.index', 'aktif' => 'admin.rekap.*', 'ikon' => 'grafik'],
            ['label' => 'Kelola konten', 'rute' => 'admin.konten.index', 'aktif' => 'admin.konten.*', 'ikon' => 'foto'],
            ['label' => 'Log aktivitas', 'rute' => 'admin.log.index', 'aktif' => 'admin.log.*', 'ikon' => 'jam'],
        ];
    }

    if ($peran === 'super_admin') {
        $grup['Yayasan'] = [
            ['label' => 'Dasbor', 'rute' => 'super.beranda', 'aktif' => 'super.beranda', 'ikon' => 'rumah'],
            ['label' => 'Sekolah', 'rute' => 'super.sekolah.index', 'aktif' => 'super.sekolah.*', 'ikon' => 'gedung'],
            ['label' => 'Akun admin sekolah', 'rute' => 'super.admin.index', 'aktif' => 'super.admin.*', 'ikon' => 'pengguna'],
            ['label' => 'Akun yayasan', 'rute' => 'super.yayasan.index', 'aktif' => 'super.yayasan.*', 'ikon' => 'perisai'],
            ['label' => 'Konten yayasan', 'rute' => 'super.konten.index', 'aktif' => 'super.konten.*', 'ikon' => 'foto'],
        ];

        $grup['Keamanan'] = [
            ['label' => 'Log global', 'rute' => 'super.log.index', 'aktif' => 'super.log.*', 'ikon' => 'jam'],
        ];
    }

    if ($peran === 'yayasan') {
        $grup['Rekap'] = [
            ['label' => 'Rekap semua sekolah', 'rute' => 'yayasan.rekap', 'aktif' => 'yayasan.rekap*', 'ikon' => 'grafik'],
            ['label' => 'Profil akun', 'rute' => 'yayasan.profil', 'aktif' => 'yayasan.profil*', 'ikon' => 'pengguna'],
        ];
    }

    $peranLabel = $user?->peran?->label() ?? 'Panel';
@endphp

{{-- Sidebar desktop --}}
<aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-[var(--garis)] bg-white print:hidden lg:flex">
    <div class="flex h-[68px] items-center gap-2.5 border-b border-[var(--garis)] px-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--latar)] ring-1 ring-inset ring-[var(--garis)]">
            <x-logo :tinggi="28" />
        </span>
        <span class="min-w-0 leading-tight">
            <span class="block truncate text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--teks-sekunder)]">SPMB 2027/2028</span>
            <span class="block truncate text-sm font-bold text-[var(--teks)]">Al-Ishlah Insan Mulia</span>
        </span>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Menu panel">
        @foreach ($grup as $judulGrup => $daftar)
            <p class="px-3 pb-1.5 pt-4 text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--teks-sekunder)] first:pt-0">{{ $judulGrup }}</p>

            <div class="space-y-0.5">
                @foreach ($daftar as $item)
                    @php($aktif = request()->routeIs($item['aktif']))
                    <a
                        href="{{ route($item['rute']) }}"
                        @if ($aktif) aria-current="page" @endif
                        class="group relative flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-semibold transition {{ $aktif ? 'bg-teal-50 text-[var(--primer)]' : 'text-[var(--teks)] hover:bg-[var(--latar)]' }}"
                    >
                        @if ($aktif)
                            <span class="absolute inset-y-1.5 left-0 w-1 rounded-full bg-[var(--primer)]" aria-hidden="true"></span>
                        @endif

                        <x-ikon :nama="$item['ikon']" kelas="h-[18px] w-[18px] shrink-0 {{ $aktif ? 'text-[var(--primer)]' : 'text-gray-400 group-hover:text-gray-600' }}" />
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="border-t border-[var(--garis)] p-3">
        <div class="flex items-center gap-2.5 rounded-xl bg-[var(--latar)] p-2.5">
            <x-avatar :nama="$user?->nama ?? ''" kelas="h-9 w-9 text-xs" />

            <div class="min-w-0">
                <p class="truncate text-xs font-bold text-[var(--teks)]">{{ $user?->nama }}</p>
                <p class="truncate text-[11px] text-[var(--teks-sekunder)]">{{ $peranLabel }}</p>
            </div>
        </div>

        <a href="{{ route('ganti-password') }}"
           class="mt-1.5 flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold text-[var(--teks-sekunder)] transition hover:bg-[var(--latar)] hover:text-[var(--teks)]">
            <x-ikon nama="kunci" kelas="h-4 w-4" />
            Ganti kata sandi
        </a>
    </div>
</aside>

{{-- Laci menu untuk layar kecil; status `menu` berasal dari layout panel. --}}
<div x-cloak x-show="menu" class="fixed inset-0 z-40 print:hidden lg:hidden" @keydown.escape.window="menu = false">
    <div class="absolute inset-0 bg-black/40" @click="menu = false"></div>

    <aside
        x-show="menu"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        class="relative flex h-full w-72 max-w-[82vw] flex-col bg-white shadow-xl"
        role="dialog"
        aria-label="Menu panel"
    >
        <div class="flex h-[68px] items-center justify-between gap-2 border-b border-[var(--garis)] px-4">
            <span class="flex min-w-0 items-center gap-2">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[var(--latar)] ring-1 ring-inset ring-[var(--garis)]">
                    <x-logo :tinggi="26" />
                </span>
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-[10px] font-bold uppercase tracking-[0.14em] text-[var(--teks-sekunder)]">SPMB 2027/2028</span>
                    <span class="block truncate text-sm font-bold text-[var(--teks)]">Al-Ishlah Insan Mulia</span>
                </span>
            </span>

            <button type="button" @click="menu = false"
                    class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold uppercase tracking-wide text-[var(--teks-sekunder)] hover:bg-[var(--latar)]">
                Tutup
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Menu panel">
            @foreach ($grup as $judulGrup => $daftar)
                <p class="px-3 pb-1.5 pt-4 text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--teks-sekunder)] first:pt-0">{{ $judulGrup }}</p>

                <div class="space-y-0.5">
                    @foreach ($daftar as $item)
                        @php($aktif = request()->routeIs($item['aktif']))
                        <a
                            href="{{ route($item['rute']) }}"
                            @if ($aktif) aria-current="page" @endif
                            class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-semibold transition {{ $aktif ? 'bg-teal-50 text-[var(--primer)]' : 'text-[var(--teks)] hover:bg-[var(--latar)]' }}"
                        >
                            <x-ikon :nama="$item['ikon']" kelas="h-[18px] w-[18px] shrink-0 {{ $aktif ? 'text-[var(--primer)]' : 'text-gray-400' }}" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="border-t border-[var(--garis)] p-3">
            <div class="flex items-center gap-2.5 rounded-xl bg-[var(--latar)] p-2.5">
                <x-avatar :nama="$user?->nama ?? ''" kelas="h-9 w-9 text-xs" />

                <div class="min-w-0">
                    <p class="truncate text-xs font-bold text-[var(--teks)]">{{ $user?->nama }}</p>
                    <p class="truncate text-[11px] text-[var(--teks-sekunder)]">{{ $peranLabel }}</p>
                </div>
            </div>

            <a href="{{ route('ganti-password') }}"
               class="mt-1.5 flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold text-[var(--teks-sekunder)] hover:bg-[var(--latar)]">
                <x-ikon nama="kunci" kelas="h-4 w-4" />
                Ganti kata sandi
            </a>
        </div>
    </aside>
</div>
