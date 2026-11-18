@extends('layouts.publik')

@section('judul', 'Kebijakan Privasi')

@section('isi')
    <div class="mx-auto max-w-3xl">
        <x-kartu>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Draf, menunggu tinjauan yayasan
            </div>

            <h1 class="mt-6 text-2xl font-bold text-[var(--teks)]">Kebijakan Privasi</h1>
            <p class="mt-2 text-sm text-[var(--teks-sekunder)]">Terakhir diperbarui: {{ now()->format('d F Y') }}</p>

            <div class="mt-6 space-y-6 text-sm leading-relaxed text-[var(--teks)]">
                <section>
                    <h2 class="font-bold">1. Data yang kami kumpulkan</h2>
                    <p class="mt-1">
                        Data orang tua/wali (nama, nomor HP, email) dan data anak (nama, tanggal lahir, alamat,
                        data kesehatan dasar, dan berkas pendaftaran). Data ini dipakai hanya untuk proses
                        penerimaan murid baru.
                    </p>
                </section>

                <section>
                    <h2 class="font-bold">2. Tujuan penggunaan</h2>
                    <p class="mt-1">
                        Data dipakai untuk memproses pendaftaran, verifikasi berkas, pengumuman hasil, dan
                        pendaftaran ulang di sekolah tujuan.
                    </p>
                </section>

                <section>
                    <h2 class="font-bold">3. Siapa yang dapat melihat</h2>
                    <p class="mt-1">
                        Hanya panitia dan admin sekolah tujuan yang dapat melihat data pendaftar. Pengelola
                        yayasan hanya melihat angka ringkasan, bukan data per orang.
                    </p>
                </section>

                <section>
                    <h2 class="font-bold">4. Lama penyimpanan</h2>
                    <p class="mt-1">
                        Data disimpan selama diperlukan untuk kepentingan sekolah, sesuai ketentuan yang berlaku.
                    </p>
                </section>

                <section>
                    <h2 class="font-bold">5. Hak Anda</h2>
                    <p class="mt-1">
                        Anda dapat meminta koreksi data dengan menghubungi panitia sekolah tujuan.
                    </p>
                </section>

                <section>
                    <h2 class="font-bold">6. Kontak</h2>
                    <p class="mt-1">
                        Untuk pertanyaan tentang privasi, hubungi panitia sekolah tujuan Anda.
                    </p>
                </section>
            </div>
        </x-kartu>
    </div>
@endsection
