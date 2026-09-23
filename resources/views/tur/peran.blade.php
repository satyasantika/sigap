<x-tur.susun :judul="'Tur ' . $peran['judul']">
    <a class="text-sm text-emerald-700 hover:underline dark:text-emerald-400" href="{{ url('/tur') }}">&larr; Semua tur</a>

    <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $peran['judul'] }}</h1>
    <p class="mt-2 max-w-3xl text-zinc-600 dark:text-zinc-400">{!! $peran['pembuka'] !!}</p>

    @if ($langkah)
        {{-- Bilah kemajuan: satu ruas per langkah, bukan persentase. Orang
             ingin tahu "tinggal berapa lagi", bukan "62%". --}}
        <div class="mt-8 flex items-center gap-3">
            <div class="flex flex-1 gap-1">
                @for ($i = 1; $i <= $jumlah; $i++)
                    <a href="{{ route('tur.peran', $kode) }}?langkah={{ $i }}"
                       aria-label="Langkah {{ $i }}"
                       @class([
                           'h-1.5 flex-1 rounded-full transition',
                           'bg-emerald-600' => $i <= $ke,
                           'bg-zinc-200 dark:bg-zinc-800' => $i > $ke,
                       ])></a>
                @endfor
            </div>
            <span class="text-sm tabular-nums text-zinc-500 dark:text-zinc-500">{{ $ke }} / {{ $jumlah }}</span>
        </div>

        <section class="mt-6 grid gap-8 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <p class="text-xs font-semibold tracking-wider text-zinc-500 uppercase">Langkah {{ $ke }}</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight">{{ $langkah['judul'] }}</h2>
                <div class="mt-3 space-y-3 text-zinc-600 dark:text-zinc-400">{!! $langkah['isi'] !!}</div>

                <div class="mt-8 flex gap-3">
                    @if ($ke > 1)
                        <a href="{{ route('tur.peran', $kode) }}?langkah={{ $ke - 1 }}"
                           class="rounded-xl border border-zinc-300 px-5 py-2.5 font-semibold hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                            &larr; Sebelumnya
                        </a>
                    @endif

                    @if ($ke < $jumlah)
                        <a href="{{ route('tur.peran', $kode) }}?langkah={{ $ke + 1 }}"
                           class="rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold text-white hover:bg-emerald-500">
                            Berikutnya &rarr;
                        </a>
                    @else
                        <a href="{{ url('/manual/' . $kode . '.html') }}"
                           class="rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold text-white hover:bg-emerald-500">
                            Baca manual lengkapnya &rarr;
                        </a>
                    @endif
                </div>
            </div>

            <figure class="lg:col-span-3">
                <img src="{{ url('/manual/gambar/' . $langkah['gambar']) }}"
                     alt="{{ $langkah['judul'] }}" loading="lazy"
                     class="w-full rounded-2xl border border-zinc-200 shadow-sm dark:border-zinc-800">
                <figcaption class="mt-3 text-sm text-zinc-500 dark:text-zinc-500">
                    Tangkapan layar sungguhan dari {{ \App\Support\Jati::nama() }}, dengan data contoh.
                </figcaption>
            </figure>
        </section>
    @else
        <p class="mt-8 text-zinc-600 dark:text-zinc-400">Tur untuk peran ini belum punya langkah.</p>
    @endif

    <section class="mt-16 border-t border-zinc-200 pt-8 dark:border-zinc-800">
        <h2 class="text-sm font-semibold tracking-wider text-zinc-500 uppercase">Tur peran lain</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($peranLain as $kodeLain => $lain)
                <a href="{{ route('tur.peran', $kodeLain) }}"
                   class="rounded-lg border border-zinc-200 px-4 py-2 text-sm hover:border-emerald-500 hover:text-emerald-700 dark:border-zinc-800 dark:hover:text-emerald-400">
                    {{ $lain['judul'] }}
                </a>
            @endforeach
        </div>
    </section>
</x-tur.susun>
