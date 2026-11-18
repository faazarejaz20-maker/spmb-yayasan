@if (session('sukses'))
    <div x-data="{ tampil: true }" x-show="tampil" x-transition
         role="status"
         class="mb-4 flex items-start justify-between rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
        <span>{{ session('sukses') }}</span>
        <button type="button" @click="tampil = false" class="ml-3 font-bold" aria-label="Tutup">&times;</button>
    </div>
@endif

@if (session('pesan'))
    <div x-data="{ tampil: true }" x-show="tampil" x-transition
         role="status"
         class="mb-4 flex items-start justify-between rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <span>{{ session('pesan') }}</span>
        <button type="button" @click="tampil = false" class="ml-3 font-bold" aria-label="Tutup">&times;</button>
    </div>
@endif

@if (session('peringatan_usia'))
    <div x-data="{ tampil: true }" x-show="tampil" x-transition
         role="status"
         class="mb-4 flex items-start justify-between rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <span>{{ session('peringatan_usia') }}</span>
        <button type="button" @click="tampil = false" class="ml-3 font-bold" aria-label="Tutup">&times;</button>
    </div>
@endif

@if ($errors->any())
    <div x-data="{ tampil: true }" x-show="tampil" x-transition
         role="alert"
         class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $galat)
                <li>{{ $galat }}</li>
            @endforeach
        </ul>
    </div>
@endif
