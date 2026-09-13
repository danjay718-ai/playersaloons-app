@php
    $isHeadToHeadListing = ($listingType ?? 'tournament') === 'head_to_head';
    $fixedFrequency = $fixedFrequency ?? false;
    $competitionLabel = $isHeadToHeadListing ? 'Head-to-Head' : 'Tournament';
    $competitionPlural = $isHeadToHeadListing
        ? 'Head-to-Head Matches'
        : ($fixedFrequency ? ucfirst($frequency).' Tournaments' : 'Tournaments');
    $publicView = $publicView ?? false;
    $gameContext = [];
    if ($isHeadToHeadListing) {
        $gameContext['competitionType'] = 'head_to_head';
    }
    if ($publicView) {
        $gameContext['view'] = 'guest';
    }
    if ($fixedFrequency && $frequency !== '') {
        $gameContext['frequency'] = $frequency;
        $gameContext['tab'] = 'browse';
    }
@endphp

<div class="space-y-12" x-data>
    <section class="space-y-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-[10px] font-black uppercase tracking-[0.3em] text-violet-400">Discover your next arena</p><h1 class="mt-2 font-orbitron text-2xl font-black uppercase tracking-tight text-white sm:text-3xl">Popular Games</h1></div>
            <label class="relative block w-full sm:max-w-xs"><span class="sr-only">Search game</span><i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500"></i><input wire:model.live.debounce.250ms="gameSearch" type="search" placeholder="Search game" class="w-full rounded-xl border border-zinc-800 bg-zinc-950/80 py-3 pl-10 pr-4 text-sm text-white placeholder-zinc-600 outline-none transition focus:border-violet-500/60 focus:ring-2 focus:ring-violet-500/10"></label>
        </div>
        <div class="relative">
            <button type="button" aria-label="Previous games" @click="$refs.gamesRail.scrollBy({ left: -420, behavior: 'smooth' })" class="absolute -left-1 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-zinc-950/95 text-white shadow-2xl transition hover:border-violet-400/50 hover:bg-violet-600 sm:-left-3 sm:h-11 sm:w-11">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <div x-ref="gamesRail" class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @forelse($popularGames as $game)
                    <a href="{{ route('games.show', ['game' => $game, ...$gameContext]) }}" wire:navigate wire:key="game-{{ $game->slug }}" class="group/game relative min-w-[180px] snap-start overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 sm:min-w-[220px]">
                        <div class="aspect-[4/3] overflow-hidden">@if($game->cardImageUrl())<img src="{{ $game->cardImageUrl() }}" alt="{{ $game->localizedName() }}" class="h-full w-full object-cover transition duration-500 group-hover/game:scale-105">@else<div class="h-full w-full bg-[radial-gradient(circle_at_top_right,rgba(168,85,247,.35),transparent_45%),linear-gradient(135deg,#18181b,#09090b)]"></div>@endif</div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/10 to-transparent"></div><div class="absolute inset-x-0 bottom-0 p-4"><h2 class="font-orbitron text-sm font-black uppercase text-white">{{ $game->localizedName() }}</h2></div>
                    </a>
                @empty
                    <div class="w-full rounded-2xl border border-dashed border-zinc-800 p-10 text-center text-sm text-zinc-500">No games match your search.</div>
                @endforelse
            </div>
            <button type="button" aria-label="Next games" @click="$refs.gamesRail.scrollBy({ left: 420, behavior: 'smooth' })" class="absolute -right-1 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-zinc-950/95 text-white shadow-2xl transition hover:border-violet-400/50 hover:bg-violet-600 sm:-right-3 sm:h-11 sm:w-11">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </button>
        </div>
    </section>

    <section class="space-y-6">
        <div class="flex items-end justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-[0.3em] text-amber-400">Selected competitions</p><h2 class="mt-2 font-orbitron text-2xl font-black uppercase text-white">Featured {{ $competitionPlural }}</h2></div><span class="hidden text-xs text-zinc-500 sm:block">Highlights from active competitions</span></div>
        @if(($featuredGroups && $featuredGroups->count()) || $featuredTournaments->isNotEmpty())
            @if($featuredGroups)
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($featuredGroups as $template)<div wire:key="featured-template-{{ $template->uuid }}"><x-player.v2-tournament-parent-card :template="$template" :public-view="$publicView" /></div>@endforeach</div>
            @else
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($featuredTournaments as $tournament)<div wire:key="featured-{{ $tournament->uuid }}"><x-player.tournament-card :tournament="$tournament" action-label="View Tournament" class="min-w-0" /></div>@endforeach</div>
            @endif
            @if($hasMoreFeatured)<div class="text-center"><button wire:click="loadMoreFeatured" wire:loading.attr="disabled" class="rounded-xl border border-violet-500/30 bg-violet-500/10 px-6 py-3 font-orbitron text-[10px] font-black uppercase tracking-widest text-violet-200 transition hover:bg-violet-500/20 disabled:opacity-50"><span wire:loading.remove wire:target="loadMoreFeatured">View More</span><span wire:loading wire:target="loadMoreFeatured">Loading...</span></button></div>@endif
        @else
            <div class="rounded-2xl border border-dashed border-zinc-800 p-10 text-center text-zinc-500">No featured {{ strtolower($competitionPlural) }} are active right now.</div>
        @endif
    </section>

    <section class="space-y-6">
        <div class="flex gap-2 overflow-x-auto border-b border-zinc-800 pb-3 [scrollbar-width:none]">
            @foreach(['upcoming' => $isHeadToHeadListing ? 'Upcoming H2H' : 'Upcoming', 'ongoing' => $isHeadToHeadListing ? 'Ongoing H2H' : 'Ongoing', 'past' => 'Past '.$competitionPlural] as $key => $label)<button wire:click="$set('activeTab', '{{ $key }}')" class="whitespace-nowrap rounded-lg px-5 py-2.5 font-orbitron text-[10px] font-black uppercase tracking-widest transition {{ $activeTab === $key ? 'bg-violet-600 text-white shadow-[0_0_20px_rgba(124,58,237,.3)]' : 'text-zinc-500 hover:bg-zinc-900 hover:text-white' }}">{{ $label }}</button>@endforeach
        </div>
        <div class="rounded-2xl border border-zinc-800/80 bg-zinc-900/35 p-4 backdrop-blur-xl sm:p-5"><div class="grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $isHeadToHeadListing ? 'xl:grid-cols-5' : 'xl:grid-cols-6' }}">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search {{ strtolower($competitionPlural) }}" class="rounded-xl border border-zinc-800 bg-zinc-950 px-4 py-3 text-sm text-white outline-none focus:border-violet-500">
            @if($isHeadToHeadListing)
                <div class="flex items-center rounded-xl border border-fuchsia-500/20 bg-fuchsia-500/5 px-3 py-3 text-sm font-semibold text-fuchsia-200"><i data-lucide="swords" class="mr-2 h-4 w-4"></i>1v1 only</div>
            @else
                <select wire:model.live="teamFormat" class="rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-300"><option value="">All formats</option><option value="solo">Solo</option><option value="team">Team</option></select>
            @endif
            <select wire:model.live="gameId" class="rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-300"><option value="">All games</option>@foreach($games as $game)<option value="{{ $game->id }}">{{ $game->localizedName() }}</option>@endforeach</select>
            @if($fixedFrequency)
                <div class="flex items-center rounded-xl border border-violet-500/25 bg-violet-500/10 px-3 py-3 text-sm font-semibold text-violet-200"><i data-lucide="calendar-days" class="mr-2 h-4 w-4"></i>{{ ucfirst($frequency) }} Tournaments</div>
            @else
                <select wire:model.live="frequency" class="rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-300"><option value="">All frequencies</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="one-time">One-time</option></select>
            @endif
            @if($allowCompetitionSwitch ?? false)<select wire:model.live="competitionType" class="rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-300"><option value="">Tournaments</option><option value="head_to_head">Head-to-Head</option></select>@else<div class="flex items-center rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm font-semibold text-zinc-400">{{ $competitionLabel }}</div>@endif
            <select wire:model.live="platformId" class="rounded-xl border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-300"><option value="">All platforms</option>@foreach($platforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select>
        </div></div>
        @if($tournamentGroups ? $tournamentGroups->count() : $tournaments->count())
            @if($tournamentGroups)
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($tournamentGroups as $template)<div wire:key="browse-template-{{ $template->uuid }}"><x-player.v2-tournament-parent-card :template="$template" :tab="$activeTab" :public-view="$publicView" /></div>@endforeach</div>
                <div class="border-t border-zinc-900/60 pt-6">{{ $tournamentGroups->links('vendor.livewire.custom-pagination') }}</div>
            @else
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">@foreach($tournaments as $tournament)<div wire:key="browse-{{ $tournament->uuid }}"><x-player.tournament-card :tournament="$tournament" :action-label="$activeTab === 'past' ? 'View Results' : 'View Tournament'" /></div>@endforeach</div>
                <div class="border-t border-zinc-900/60 pt-6">{{ $tournaments->links('vendor.livewire.custom-pagination') }}</div>
            @endif
        @else
            <div class="rounded-2xl border border-dashed border-zinc-800 p-12 text-center"><i data-lucide="trophy" class="mx-auto h-9 w-9 text-zinc-700"></i><h3 class="mt-4 font-orbitron text-sm font-black uppercase text-zinc-300">No {{ strtolower($competitionPlural) }} found</h3><p class="mt-2 text-sm text-zinc-600">Try changing the selected tab or filters.</p></div>
        @endif
    </section>
</div>
