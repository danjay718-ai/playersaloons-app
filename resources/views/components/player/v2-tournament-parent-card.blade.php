@props(['template', 'tab' => 'upcoming', 'publicView' => false])

@php
    $occurrences = $template->discovery_occurrences ?? collect();
    $lead = $occurrences->first();
    $game = $template->game;
    $translation = $game?->translations?->where('locale', app()->getLocale())->first()
        ?? $game?->translations?->where('locale', 'en')->first();
    $gameName = $translation?->name ?? $game?->slug ?? __('Game');
    $banner = $lead?->banner_url ?? 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop';
    $fees = $occurrences->pluck('entry_fee')->map(fn ($fee) => (float) $fee);
    $minimumFee = $fees->min() ?? 0;
    $maximumFee = $fees->max() ?? 0;
    $hasFeeVariation = $minimumFee !== $maximumFee;
    $isHeadToHead = ($template->competition_type?->value ?? $template->competition_type) === 'head_to_head';
    $entryLabel = $isHeadToHead ? 'players' : 'teams';
    $viewQuery = $publicView ? '?view=guest' : '';
@endphp

<article x-data="{ open: false }" class="player-tournament-card group overflow-hidden">
    <div class="relative h-32 overflow-hidden sm:h-36">
        <img src="{{ $banner }}" alt="{{ $template->name }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-zinc-950/15 to-black/20"></div>
        <div class="absolute inset-x-3 top-3 flex items-center justify-between gap-2">
            <span class="player-badge border-cyan-800/50 bg-zinc-950/85 text-cyan-400">{{ $gameName }}</span>
            <span class="player-badge border-violet-800/50 bg-violet-950/85 text-violet-300">{{ $occurrences->count() }} {{ \Illuminate\Support\Str::plural('slot', $occurrences->count()) }}</span>
        </div>
    </div>

    <div class="flex min-h-[245px] flex-col gap-4 p-4 sm:p-5">
        <div>
            <h3 class="line-clamp-2 font-orbitron text-base font-black leading-tight tracking-wide text-white sm:text-lg">{{ $template->name }}</h3>
            <p class="mt-2 text-xs text-zinc-500">Choose a {{ $tab === 'upcoming' ? 'currently available' : $tab }} {{ $isHeadToHead ? '1v1' : 'tournament' }} slot to view its exact details.</p>
        </div>
        <div class="grid grid-cols-2 gap-3 border-y border-zinc-800/60 py-3 text-xs">
            <div><span class="block text-[9px] font-bold uppercase tracking-widest text-zinc-600">Entry fee</span><span class="font-orbitron font-black text-violet-300">{{ $minimumFee > 0 ? '$'.number_format($minimumFee, 2) : 'Free' }}{{ $hasFeeVariation ? '+' : '' }}</span></div>
            <div class="text-right"><span class="block text-[9px] font-bold uppercase tracking-widest text-zinc-600">Max {{ $entryLabel }}</span><span class="font-mono font-bold text-zinc-200">{{ $template->max_participants }}</span></div>
        </div>
        <button type="button" @click="open = true" class="player-card-action mt-auto w-full">
            <span>{{ $tab === 'upcoming' ? 'View Available Slots' : 'View Slots' }}</span><i data-lucide="calendar-days" class="h-3.5 w-3.5 text-cyan-400"></i>
        </button>
    </div>

    {{-- Teleport prevents the overlay from being clipped or positioned inside the card/grid. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity @keydown.escape.window="open = false" class="fixed inset-0 z-[200] flex items-end bg-black/75 p-0 backdrop-blur-sm sm:items-center sm:justify-center sm:p-5" role="dialog" aria-modal="true">
        <div @click.outside="open = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-5 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" class="max-h-[92dvh] w-full overflow-hidden rounded-t-2xl border border-zinc-700 bg-zinc-950 shadow-2xl sm:max-w-2xl sm:rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-zinc-800 p-4 sm:p-5"><div><p class="text-[10px] font-black uppercase tracking-[.22em] text-violet-400">{{ $gameName }}</p><h2 class="mt-1 font-orbitron text-base font-black uppercase text-white sm:text-lg">{{ $template->name }}</h2></div><button type="button" @click="open = false" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-800 hover:text-white" aria-label="Close"><i data-lucide="x" class="h-5 w-5"></i></button></div>
            <div class="max-h-[calc(92dvh-86px)] space-y-4 overflow-y-auto p-4 sm:p-5">
                @if($tab === 'upcoming')<div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs leading-relaxed text-amber-100">Prize values are finalized from the actual paid entries for the selected slot. Entry fees and resulting prizes may vary by slot.</div>@endif
                <div class="space-y-3">
                    @foreach($occurrences as $occurrence)
                        <div class="flex flex-col gap-3 rounded-xl border border-zinc-800 bg-zinc-900/50 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                            <div class="min-w-0"><p class="font-orbitron text-xs font-black uppercase text-white">{{ $occurrence->start_at?->timezone($occurrence->timezone)->format('D, M j · g:i A') }}</p><p class="mt-1 text-xs text-zinc-500">{{ str_replace('_', ' ', $occurrence->status->value ?? $occurrence->status) }} · {{ $occurrence->registrations_count }}/{{ $occurrence->max_participants }} {{ $isHeadToHead ? 'players' : 'teams' }}</p></div>
                            <div class="flex items-center justify-between gap-4 sm:justify-end"><span class="font-orbitron text-sm font-black text-violet-300">{{ (float) $occurrence->entry_fee > 0 ? '$'.number_format((float) $occurrence->entry_fee, 2) : 'Free' }}</span><a href="/tournaments/{{ $occurrence->uuid }}/view{{ $viewQuery }}" wire:navigate class="rounded-lg bg-violet-600 px-4 py-2 text-center text-[10px] font-black uppercase tracking-wider text-white hover:bg-violet-500">{{ $tab === 'upcoming' ? 'Select slot' : 'View' }}</a></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    </template>
</article>
