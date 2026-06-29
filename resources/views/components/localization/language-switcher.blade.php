@props([
    'variant' => 'public',
    'align' => 'right',
])

@php
    $languages = config('localization.supported', []);
    $currentLocale = app()->getLocale();
    $currentLanguage = $languages[$currentLocale] ?? reset($languages);

    $buttonClasses = match ($variant) {
        'admin' => 'inline-flex items-center gap-2 rounded-lg border border-slate-700/70 bg-slate-900/70 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-indigo-500/50 hover:text-white',
        'player' => 'inline-flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-zinc-400 transition hover:border-purple-500/40 hover:text-purple-300',
        default => 'inline-flex items-center gap-2 rounded-lg border border-white/10 bg-zinc-950/70 px-3 py-2 text-xs font-semibold text-zinc-300 transition hover:border-cyan-400/40 hover:text-white',
    };

    $panelClasses = match ($variant) {
        'admin' => 'bg-[#0f172a] border-slate-700 text-slate-300',
        'player' => 'bg-[#0e0a24] border-purple-500/20 text-zinc-300',
        default => 'bg-[#070514] border-white/10 text-zinc-300',
    };

    $itemClasses = match ($variant) {
        'admin' => 'hover:bg-indigo-600/15 hover:text-white',
        'player' => 'hover:bg-purple-950/30 hover:text-white',
        default => 'hover:bg-cyan-500/10 hover:text-white',
    };
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click="open = !open" class="{{ $buttonClasses }}" aria-label="{{ __('Language') }}" aria-haspopup="true" :aria-expanded="open">
        <i data-lucide="globe-2" class="h-4 w-4"></i>
        <span>{{ strtoupper($currentLocale) }}</span>
        <span class="hidden sm:inline normal-case tracking-normal">{{ $currentLanguage['native'] ?? strtoupper($currentLocale) }}</span>
        <i data-lucide="chevron-down" class="h-3 w-3"></i>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="scale-95 opacity-0"
        x-transition:enter-end="scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="scale-100 opacity-100"
        x-transition:leave-end="scale-95 opacity-0"
        class="absolute {{ $align === 'left' ? 'left-0' : 'right-0' }} z-50 mt-2 max-h-80 w-52 overflow-y-auto rounded-xl border py-1 shadow-2xl {{ $panelClasses }}"
        x-cloak
    >
        @foreach($languages as $locale => $language)
            <form method="POST" action="{{ route('language.update') }}" class="m-0">
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <button type="submit" class="flex w-full items-center justify-between px-4 py-2 text-left text-xs transition {{ $itemClasses }} {{ $currentLocale === $locale ? 'font-bold text-white' : '' }}">
                    <span>{{ $language['native'] }}</span>
                    <span class="text-[10px] uppercase opacity-60">{{ $locale }}</span>
                </button>
            </form>
        @endforeach
    </div>
</div>
