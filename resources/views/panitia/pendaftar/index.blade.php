@extends('layouts.panel')

@section('judul', 'Pendaftar')

@section('isi')
    @php
        $programTki = (array) config('spmb.nomor_peserta.program_tki');
        $tautanUrut = function (string $kolom) use ($urut, $arah) {
            return request()->fullUrlWithQuery([
                'urut' => $kolom,
                'arah' => $urut === $kolom && $arah === 'asc' ? 'desc' : 'asc',
            ]);
        };
        $penandaUrut = function (string $kolom) use ($urut, $arah) {
            if ($urut !== $kolom) {
                return '';
            }

            return $arah === 'asc' ? ' ↑' : ' ↓';
        };
    @endphp

    <div class="space-y-4">
        <x-kepala-halaman
            judul="Daftar pendaftar"
            :sekolah="$sekolah?->nama.' · '.$daftar->total().' pendaftar terkirim'"
        >
            <x-slot:aksi>
                <x-tautan-tombol :href="route('panitia.ekspor.index', request()->query())" tipe="sekunder">
                    Unduh Excel
                </x-tautan-tombol>
                <x-tautan-tombol :href="route('panitia.pembayaran.index')" tipe="sekunder">
                    Antrean pembayaran
                </x-tautan-tombol>
                <x-tautan-tombol :href="route('panitia.offline.index')">
                    Pendaftar offline
                </x-tautan-tombol>
            </x-slot:aksi>
        </x-kepala-halaman>

        <form method="GET" action="{{ route('panitia.pendaftar.index') }}"
              class="grid gap-3 rounded-2xl border border-[var(--garis)] bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="cari" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Cari nama atau nomor peserta</label>
                <input type="search" id="cari" name="cari" value="{{ $cari }}" maxlength="150"
                       class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm text-[var(--teks)] focus:border-[var(--primer)] focus:outline-none focus:ring-2 focus:ring-[var(--primer)]/30">
            </div>

            <div>
                <label for="gelombang" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Gelombang</label>
                <select id="gelombang" name="gelombang"
                        class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                    <option value="">Semua gelombang</option>
                    @foreach ($gelombangPilihan as $g)
                        <option value="{{ $g->id }}" @selected((int) $gelombangId === (int) $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Status hasil</label>
                <select id="status" name="status"
                        class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                    <option value="">Semua status</option>
                    @foreach (\App\Enums\StatusHasil::cases() as $s)
                        <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="jenis" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Jenis pendaftar</label>
                <select id="jenis" name="jenis"
                        class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                    <option value="">Semua</option>
                    <option value="baru" @selected($jenis === 'baru')>Siswa baru</option>
                    <option value="lulusan" @selected($jenis === 'lulusan')>Lulusan yayasan</option>
                </select>
            </div>

            <div>
                <label for="pembayaran" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Pembayaran</label>
                <select id="pembayaran" name="pembayaran"
                        class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                    <option value="">Semua</option>
                    <option value="belum" @selected($pembayaran === 'belum')>Belum ada</option>
                    <option value="menunggu" @selected($pembayaran === 'menunggu')>Menunggu</option>
                    <option value="disetujui" @selected($pembayaran === 'disetujui')>Disetujui</option>
                    <option value="ditolak" @selected($pembayaran === 'ditolak')>Ditolak</option>
                </select>
            </div>

            <div>
                <label for="kelengkapan" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Kelengkapan berkas</label>
                <select id="kelengkapan" name="kelengkapan"
                        class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                    <option value="">Semua</option>
                    <option value="perlu_perbaikan" @selected($kelengkapan === 'perlu_perbaikan')>Ada yang perlu perbaikan</option>
                    <option value="lengkap" @selected($kelengkapan === 'lengkap')>Tidak ada perbaikan</option>
                </select>
            </div>

            @if ($sekolah?->kode === config('spmb.nomor_peserta.kode_program'))
                <div>
                    <label for="program" class="mb-1 block text-xs font-semibold text-[var(--teks)]">Program</label>
                    <select id="program" name="program"
                            class="min-h-11 w-full rounded-xl border border-[var(--garis)] bg-white px-3 text-sm">
                        <option value="">Semua program</option>
                        @foreach ($programTki as $p)
                            <option value="{{ $p }}" @selected(strtoupper($program) === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <input type="hidden" name="urut" value="{{ $urut }}">
            <input type="hidden" name="arah" value="{{ $arah }}">

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                <x-tombol>Terapkan filter</x-tombol>
                <x-tautan-tombol :href="route('panitia.pendaftar.index')" tipe="hantu">
                    Bersihkan
                </x-tautan-tombol>
            </div>
        </form>

        @if ($drafOffline->isNotEmpty())
            <x-kartu judul="Draf offline yang belum dikirim">
                <ul class="divide-y divide-[var(--garis)] text-sm">
                    @foreach ($drafOffline as $draf)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-[var(--teks)]">{{ $draf->anak?->nama_lengkap ?? 'Anak' }}</p>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    {{ $draf->gelombang?->nama }} &middot; diperbarui {{ $draf->updated_at?->format('d-m-Y H:i') }}
                                </p>
                            </div>
                            <a href="{{ route('panitia.offline.langkah', ['pendaftaran' => $draf->uuid, 'n' => 2]) }}"
                               class="text-sm font-semibold text-[var(--primer)] hover:underline">
                                Lanjutkan
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-kartu>
        @endif

        @if ($daftar->isEmpty())
            <x-kosong judul="Belum ada pendaftar" keterangan="Pendaftar yang sudah mengirim formulir akan tampil di sini." />
        @else
            <x-tabel minimal="960px">
                <thead class="border-b border-[var(--garis)] bg-gray-50 text-xs uppercase tracking-wide text-[var(--teks-sekunder)]">
                    <tr>
                        <th class="px-4 py-3">
                            <a href="{{ $tautanUrut('nomor_peserta') }}" class="hover:text-[var(--primer)]">Nomor peserta{{ $penandaUrut('nomor_peserta') }}</a>
                        </th>
                        <th class="px-4 py-3">
                            <a href="{{ $tautanUrut('nama') }}" class="hover:text-[var(--primer)]">Nama anak{{ $penandaUrut('nama') }}</a>
                        </th>
                        <th class="px-4 py-3">Gelombang</th>
                        <th class="px-4 py-3">
                            <a href="{{ $tautanUrut('status_hasil') }}" class="hover:text-[var(--primer)]">Status{{ $penandaUrut('status_hasil') }}</a>
                        </th>
                        <th class="px-4 py-3">Berkas</th>
                        <th class="px-4 py-3">Pembayaran</th>
                        <th class="px-4 py-3">
                            <a href="{{ $tautanUrut('dikirim_pada') }}" class="hover:text-[var(--primer)]">Dikirim{{ $penandaUrut('dikirim_pada') }}</a>
                        </th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--garis)]">
                    @foreach ($daftar as $p)
                        @php
                            $terunggah = $p->berkas->filter(fn ($b) => filled($b->path))->count();
                            $perluPerbaikan = $p->berkas->filter(fn ($b) => $b->status === \App\Enums\StatusBerkas::PerluPerbaikan)->count();
                            $bayar = $p->pembayaran->sortByDesc('id')->first();
                        @endphp

                        <tr class="align-top hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[var(--teks)]">
                                {{ $p->nomor_peserta ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('panitia.pendaftar.detail', $p->uuid) }}"
                                   class="font-semibold text-[var(--primer)] hover:underline">
                                    {{ $p->anak?->nama_lengkap ?? '—' }}
                                </a>
                                <p class="text-xs text-[var(--teks-sekunder)]">
                                    {{ $p->program ? 'Program '.$p->program : ($p->lulusan_yayasan ? 'Lulusan yayasan' : 'Siswa baru') }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-sm text-[var(--teks)]">{{ $p->gelombang?->nama ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-lencana-status :label="$p->status_hasil?->label() ?? '—'" />
                                @if ($p->status_murid)
                                    <p class="mt-1 text-xs text-[var(--teks-sekunder)]">{{ $p->status_murid->label() }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="{{ $perluPerbaikan > 0 ? 'font-semibold text-orange-700' : 'text-[var(--teks)]' }}">
                                    {{ $terunggah }} / {{ $totalJenis }}
                                </span>
                                @if ($perluPerbaikan > 0)
                                    <p class="text-xs text-orange-700">{{ $perluPerbaikan }} perlu perbaikan</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($bayar)
                                    <x-lencana-status :label="$bayar->status->label()" />
                                @else
                                    <span class="text-xs text-[var(--teks-sekunder)]">Belum ada</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-[var(--teks-sekunder)]">
                                {{ $p->dikirim_pada?->format('d-m-Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('panitia.pendaftar.detail', $p->uuid) }}"
                                   class="text-sm font-semibold text-[var(--primer)] hover:underline">
                                    Periksa
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tabel>

            <div class="mt-4">
                {{ $daftar->links() }}
            </div>
        @endif
    </div>
@endsection
