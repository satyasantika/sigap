@if (config('services.google.aktif'))
    <div class="mt-4">
        @if (session('galat_google'))
            <p role="alert" class="mb-3 text-sm text-danger-600 dark:text-danger-400">{{ session('galat_google') }}</p>
        @endif

        <a href="{{ route('auth.google.redirect', ['asal' => url()->current()]) }}"
           class="fi-btn fi-btn-size-md fi-color-gray flex w-full items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold ring-1 ring-gray-950/10 hover:bg-gray-50 dark:ring-white/20 dark:hover:bg-white/5">
            Masuk dengan Google
        </a>
    </div>
@endif
