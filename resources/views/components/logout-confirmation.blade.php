<dialog id="logout-confirmation"
        aria-labelledby="logout-confirmation-title"
        onclick="if (event.target === this) this.close()"
        class="m-auto w-[calc(100%_-_2rem)] max-w-md overflow-hidden rounded-2xl border border-red-500/30 bg-[#0f172a] p-0 text-left text-slate-100 shadow-2xl backdrop:bg-black/70 backdrop:backdrop-blur-sm">
    <div class="border-b border-slate-800 px-6 py-5">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-red-500/25 bg-red-500/10 text-red-400">
            <i data-lucide="log-out" class="h-6 w-6"></i>
        </div>
        <h2 id="logout-confirmation-title" class="text-base font-black text-slate-100">{{ __('Logout') }}?</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-400">Are you sure you want to end your current session?</p>
    </div>

    <div class="flex justify-end gap-3 px-6 py-4">
        <form method="dialog">
            <button type="submit" class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-2.5 text-xs font-bold uppercase text-slate-300 transition hover:bg-slate-700">
                Cancel
            </button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-xs font-bold uppercase text-white shadow-lg shadow-red-950/20 transition hover:bg-red-500">
                <i data-lucide="log-out" class="h-4 w-4"></i>
                {{ __('Logout') }}
            </button>
        </form>
    </div>
</dialog>
