@php
    $footer = \App\Modules\CMS\Models\LandingSection::query()
        ->where('key', 'footer')
        ->where('is_active', true)
        ->with('activeItems')
        ->first();
@endphp

<footer class="relative z-10 border-t border-zinc-900/50 bg-zinc-950/80 px-4 py-10 text-center">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 md:flex-row">
        <a href="/" aria-label="Gamers Rival home"><x-public-brand :compact="true" /></a>
        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-700">
            &copy; {{ date('Y') }} {{ __($footer?->body ?? 'All rights reserved. Operated by GamersRival Systems.') }}
        </p>
        <div class="flex flex-wrap justify-center gap-4 text-[10px] font-black uppercase tracking-widest text-zinc-600 sm:gap-8">
            @forelse($footer?->activeItems ?? [] as $item)
                <a href="{{ $item->url ?: '#' }}" wire:navigate class="transition-colors hover:text-cyan-400">{{ __($item->label ?: $item->title) }}</a>
            @empty
                <a href="{{ route('policies.index') }}" wire:navigate class="transition-colors hover:text-cyan-400">{{ __('Policies') }}</a>
            @endforelse
            <a href="{{ route('contact') }}" wire:navigate class="transition-colors hover:text-cyan-400">{{ __('Contact') }}</a>
        </div>
    </div>
</footer>
