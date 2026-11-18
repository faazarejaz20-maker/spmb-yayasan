<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'Cetak')</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 antialiased">
    @php
        // Kop sekolah: `$sekolah` dikirim controller cetak. Halaman lain
        // (mis. kartu akun) memakai nama yayasan.
        $namaSekolah = ($sekolah ?? null)?->nama ?? 'SPMB Al-Ishlah Insan Mulia';
        $alamatSekolah = ($sekolah ?? null)?->alamat ?? 'Wonosobo, Jawa Tengah';
    @endphp

    <div class="mx-auto max-w-[210mm] bg-white p-6 shadow-sm print:max-w-none print:p-0 print:shadow-none">
        <div class="tanpa-cetak mb-4 flex flex-wrap items-center justify-between gap-2">
            <button
                type="button"
                data-cetak
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--primer)] px-4 text-sm font-semibold text-white hover:bg-[var(--primer-gelap)]"
            >
                Cetak
            </button>

            <button
                type="button"
                data-tutup-cetak
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--garis)] bg-white px-4 text-sm font-semibold text-[var(--teks)] hover:bg-gray-50"
            >
                Tutup
            </button>
        </div>

        <header class="mb-6 flex items-start justify-between gap-4 border-b-2 border-gray-800 pb-4">
            <div class="flex items-center gap-3">
                <x-logo :tinggi="56" />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Yayasan Al-Ishlah Insan Mulia</p>
                    <p class="text-lg font-bold text-gray-900">{{ $namaSekolah ?? 'SPMB Al-Ishlah Insan Mulia' }}</p>
                    <p class="text-xs text-gray-600">{{ $alamatSekolah ?? 'Wonosobo, Jawa Tengah' }}</p>
                </div>
            </div>

            <div class="text-right text-xs text-gray-600">
                <p class="text-base font-bold text-gray-900">@yield('judul', 'Dokumen')</p>
                <p>Dicetak {{ now()->format('d-m-Y H:i') }}</p>
            </div>
        </header>

        @yield('isi')

        <footer class="mt-8 border-t border-gray-300 pt-3 text-center text-xs text-gray-500">
            Sistem SPMB Yayasan Al-Ishlah Insan Mulia &middot; Dokumen dicetak dari sistem, bukan pengganti dokumen resmi yang ditandatangani.
        </footer>
    </div>
</body>
</html>
