@php
    $isHeadToHeadListing = ($listingType ?? 'tournament') === 'head_to_head';
    $fixedFrequency = $fixedFrequency ?? false;
    $competitionLabel = $isHeadToHeadListing ? __('Head-to-Head') : __('Tournament');
    $competitionPlural = $isHeadToHeadListing
        ? __('Head-to-Head Matches')
        : ($fixedFrequency ? __(':frequency Tournaments', ['frequency' => __(ucfirst($frequency))]) : __('Tournaments'));
    $publicView = $publicView ?? false;
    $viewQuery = $publicView ? '?view=guest' : '';
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

    $arenaTimezone = app(\App\Modules\Tournament\Services\TournamentTimezone::class)->value();
    $symbol = \App\Shared\Support\Currency::symbol();

    $spotlightSource = $featuredTournaments->isNotEmpty()
        ? $featuredTournaments->take(5)
        : collect($tournaments->items())->take(5);

    $spotlightPayload = $spotlightSource->values()->map(function ($item) use ($viewQuery, $arenaTimezone, $symbol) {
        $gameTranslation = $item->game?->translations?->where('locale', app()->getLocale())->first()
            ?? $item->game?->translations?->where('locale', 'en')->first();
        $gameName = $gameTranslation?->name ?? $item->game?->slug ?? __('Game');
        $joined = (int) ($item->getAttribute('registrations_count')
            ?? ($item->relationLoaded('registrations') ? $item->registrations->count() : 0));
        $max = max(1, (int) $item->max_participants);
        $isH2h = ($item->competition_type?->value ?? $item->competition_type) === 'head_to_head';
        $entryFee = (float) $item->entry_fee;

        if ($item->finalized_first_prize !== null) {
            $prizeAmount = (float) $item->finalized_first_prize + (float) ($item->finalized_second_prize ?? 0);
        } elseif ((int) $item->workflow_version === 2) {
            $v2Calc = app(\App\Modules\Tournament\Services\V2PrizePolicy::class)->calculate($item, $max);
            $prizeAmount = ((int) ($v2Calc['first_minor'] ?? 0) + (int) ($v2Calc['second_minor'] ?? 0)) / 100;
        } else {
            $prizeAmount = (float) ($item->advertised_prize_pool ?? $item->prize_pool ?? $item->prize_1st ?? 0);
            if ($prizeAmount <= 0 && $entryFee > 0) {
                $prizeAmount = round($entryFee * $max * 0.9, 2);
            }
        }

        return [
            'uuid' => $item->uuid,
            'title' => $item->display_name,
            'game' => $gameName,
            'type' => $isH2h ? __('1v1 Head-to-Head') : __('Tournament'),
            'frequency' => $item->frequency ? __(ucfirst((string) $item->frequency)) : null,
            'banner' => $item->banner_url ?? $item->game?->bannerUrl() ?? $item->game?->cardImageUrl() ?? 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=900&auto=format&fit=crop',
            'entry' => $entryFee > 0 ? $symbol.number_format($entryFee, 2) : __('Free'),
            'prize' => $symbol.number_format($prizeAmount, 2),
            'joined' => $joined,
            'max' => $max,
            'seatsLeft' => max(0, $max - $joined),
            'pipCount' => min(24, $max),
            'pipFilled' => (int) round(($joined / $max) * min(24, $max)),
            'startAtLabel' => $item->start_at ? $item->start_at->copy()->setTimezone($arenaTimezone)->format('M d · H:i').' '.$arenaTimezone : __('Schedule pending'),
            'href' => '/tournaments/'.$item->uuid.'/view'.$viewQuery,
            'ctaLabel' => $isH2h ? __('Take 1v1 Seat') : __('Take a Seat'),
        ];
    })->all();
@endphp

<div class="player-tournament-discovery space-y-10" wire:poll.10s.visible x-data="tournamentListRealtime(() => $wire.$refresh())">
    <!-- 1. Interactive Top Preview Stage (Main Event Spotlight + Starting Next Queue) -->
    @if(!empty($spotlightPayload))
        <section
            x-data="{
                items: @js($spotlightPayload),
                activeIndex: 0,
                get current() { return this.items[this.activeIndex] || this.items[0]; }
            }"
            class="grid gap-5 lg:grid-cols-12"
            aria-label="{{ __('Competition Spotlight') }}"
        >
            <!-- Left: Active Competition Preview -->
            <article class="relative flex flex-col justify-between overflow-hidden rounded-3xl border border-cyan-500/25 bg-zinc-900/80 p-5 shadow-[0_24px_60px_-15px_rgba(0,0,0,0.7)] sm:p-7 lg:col-span-7">
                <img :src="current.banner" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-25 transition-all duration-500">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#05080f] via-[#05080f]/90 to-[#05080f]/65"></div>

                <div class="relative z-10 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-lime-400/40 bg-lime-400/15 px-3 py-1 font-orbitron text-[10px] font-black uppercase tracking-widest text-lime-300">
                                <span class="h-2 w-2 rounded-full bg-lime-400 shadow-[0_0_8px_#c6ff3d] animate-pulse"></span>
                                {{ __('Spotlight Preview') }}
                            </span>
                            <span class="rounded-full border border-cyan-400/35 bg-cyan-950/60 px-3 py-1 font-orbitron text-[10px] font-bold uppercase tracking-wider text-cyan-300" x-text="current.game"></span>
                            <span class="rounded-full border border-white/10 bg-zinc-950/70 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-zinc-300" x-text="current.type"></span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-amber-400/35 bg-zinc-950/85 px-3 py-1.5 font-mono text-xs font-bold text-amber-300">
                            <i data-lucide="clock" class="h-3.5 w-3.5 text-amber-400"></i>
                            <span x-text="current.startAtLabel"></span>
                        </span>
                    </div>

                    <div>
                        <h2 class="font-orbitron text-2xl font-black uppercase tracking-tight text-white sm:text-3xl" x-text="current.title"></h2>
                        <p class="mt-1 text-xs font-semibold text-zinc-400">
                            <span class="text-cyan-300" x-text="current.game"></span>
                            <template x-if="current.frequency">
                                <span> · <span x-text="current.frequency"></span></span>
                            </template>
                        </p>
                    </div>

                    <dl class="grid grid-cols-3 gap-3 rounded-2xl border border-white/10 bg-zinc-950/75 p-3.5 sm:p-4">
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">{{ __('Entry Fee') }}</dt>
                            <dd class="mt-1 font-orbitron text-base font-black text-white sm:text-lg" x-text="current.entry"></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">{{ __('Prize') }}</dt>
                            <dd class="mt-1 font-orbitron text-base font-black text-lime-300 sm:text-lg" x-text="current.prize"></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">{{ __('Full Capacity') }}</dt>
                            <dd class="mt-1 font-mono text-base font-black text-cyan-300 sm:text-lg">
                                <span x-text="current.joined"></span>
                                <span class="text-xs text-zinc-500">/</span>
                                <span x-text="current.max"></span>
                            </dd>
                        </div>
                    </dl>

                    <!-- Interactive Seat Map Pips -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                            <span>{{ __('Live Seat Map') }}</span>
                            <span class="text-lime-300" x-text="current.seatsLeft > 0 ? '{{ __('Next seat ready') }}' : '{{ __('Full capacity') }}'"></span>
                        </div>
                        <div class="grid grid-cols-12 gap-1.5 sm:grid-cols-24" aria-hidden="true">
                            <template x-for="pip in current.pipCount" :key="pip">
                                <span
                                    class="h-2.5 rounded-sm transition-all duration-300"
                                    :class="pip <= current.pipFilled
                                        ? 'bg-cyan-400 shadow-[0_0_8px_#22e6ff]'
                                        : (pip === current.pipFilled + 1 ? 'bg-lime-400 animate-pulse shadow-[0_0_10px_#c6ff3d]' : 'bg-white/10')"
                                ></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                    <a :href="current.href" wire:navigate class="gr-btn gr-btn-primary !min-h-[44px] !px-6 !py-2.5 !text-xs uppercase tracking-wider">
                        <span x-text="current.ctaLabel"></span>
                        <span aria-hidden="true">↗</span>
                    </a>
                    <a href="#competition-board" class="text-xs font-extrabold uppercase tracking-wider text-zinc-400 transition hover:text-cyan-300">
                        {{ __('Jump to full board') }} ↓
                    </a>
                </div>
            </article>

            <!-- Right: Starting Next Queue (Click to Preview) -->
            <aside class="flex flex-col justify-between rounded-3xl border border-cyan-500/20 bg-zinc-900/65 p-5 backdrop-blur-md sm:p-6 lg:col-span-5">
                <div>
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-cyan-400 shadow-[0_0_8px_#22e6ff]"></span>
                            <h3 class="font-orbitron text-xs font-black uppercase tracking-widest text-white">{{ __('Starting Next — Click to Preview') }}</h3>
                        </div>
                        <span class="font-mono text-[11px] font-bold text-lime-300">{{ $tournaments->total() }} {{ __('open') }}</span>
                    </div>

                    <div class="mt-3 flex flex-col gap-2">
                        <template x-for="(item, idx) in items" :key="item.uuid">
                            <button
                                type="button"
                                @click="activeIndex = idx"
                                class="group flex w-full items-center justify-between gap-3 rounded-2xl border px-3.5 py-2.5 text-left transition-all"
                                :class="activeIndex === idx
                                    ? 'border-lime-400/60 bg-lime-400/10 shadow-[0_0_20px_rgba(198,255,61,0.12)]'
                                    : 'border-white/5 bg-zinc-950/60 hover:border-cyan-400/35 hover:bg-zinc-950'"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate font-orbitron text-xs font-black text-white group-hover:text-lime-300" x-text="item.title"></span>
                                    </div>
                                    <p class="mt-0.5 truncate text-[11px] text-zinc-400">
                                        <span class="font-semibold text-cyan-300" x-text="item.game"></span>
                                        <span> · </span>
                                        <span x-text="item.joined + '/' + item.max + ' seats'"></span>
                                        <span> · </span>
                                        <span class="text-amber-300" x-text="item.startAtLabel"></span>
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="block font-orbitron text-xs font-black text-lime-300" x-text="item.entry"></span>
                                    <span class="text-[9px] font-bold uppercase tracking-wider" :class="activeIndex === idx ? 'text-lime-300' : 'text-zinc-500'" x-text="activeIndex === idx ? 'Previewing' : 'Preview'"></span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-3 text-xs text-zinc-400">
                    <span>{{ __('Select any row above to inspect seats') }}</span>
                    <a :href="current.href" wire:navigate class="font-orbitron text-[10px] font-black uppercase tracking-wider text-cyan-300 hover:text-lime-300">
                        {{ __('Open Details') }} →
                    </a>
                </div>
            </aside>
        </section>
    @endif

    <!-- 2. Popular Games Rail -->
    <section class="space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="gr-eyebrow !mb-1.5"><span class="gr-slash">//</span> Discover your next arena</p>
                <h1 class="font-orbitron text-xl font-black uppercase tracking-tight text-white sm:text-2xl">Popular Games</h1>
                <p class="mt-1 max-w-xl text-xs leading-relaxed text-zinc-400">
                    {{ $isHeadToHeadListing ? 'Click a game card to view its available head-to-head matches.' : 'Click a game card to view its available tournaments.' }}
                </p>
            </div>
            <label class="relative block w-full sm:max-w-xs"><span class="sr-only">Search game</span><i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500"></i><input wire:model.live.debounce.250ms="gameSearch" type="search" placeholder="Search game" class="player-filter-field w-full rounded-xl border border-cyan-500/20 bg-zinc-950/85 py-2.5 pl-10 pr-4 text-sm text-white placeholder-zinc-500 outline-none transition focus:border-lime-400/60 focus:ring-2 focus:ring-lime-400/15"></label>
        </div>
        <div class="relative">
            <button type="button" aria-label="Previous games" @click="$refs.gamesRail.scrollBy({ left: -420, behavior: 'smooth' })" class="player-rail-button absolute -left-1 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-cyan-500/30 bg-zinc-950/95 text-white shadow-2xl transition hover:border-lime-400 hover:bg-lime-400 hover:text-zinc-950 sm:-left-3 sm:h-10 sm:w-10">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <div x-ref="gamesRail" class="flex snap-x snap-mandatory gap-3.5 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @forelse($popularGames as $game)
                    <a href="{{ route('games.show', ['game' => $game, ...$gameContext]) }}" wire:navigate wire:key="game-{{ $game->slug }}" class="player-game-card group/game relative min-w-[160px] snap-start overflow-hidden rounded-2xl border border-cyan-500/20 bg-zinc-900 transition hover:border-lime-400/60 sm:min-w-[190px]">
                        <div class="aspect-[16/10] overflow-hidden">@if($game->cardImageUrl())<img src="{{ $game->cardImageUrl() }}" alt="{{ $game->localizedName() }}" class="h-full w-full object-cover transition duration-500 group-hover/game:scale-105 group-hover/game:blur-[2px] group-focus-visible/game:scale-105 group-focus-visible/game:blur-[2px]">@else<div class="h-full w-full bg-[radial-gradient(circle_at_top_right,rgba(34,230,255,.3),transparent_45%),linear-gradient(135deg,#10192b,#05080f)] transition duration-500 group-hover/game:scale-105 group-hover/game:blur-[2px] group-focus-visible/game:scale-105 group-focus-visible/game:blur-[2px]"></div>@endif</div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/15 to-transparent transition duration-300 group-hover/game:bg-black/45 group-focus-visible/game:bg-black/45"></div>
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center font-orbitron text-[11px] font-black uppercase tracking-[0.22em] text-lime-300 opacity-0 drop-shadow-[0_2px_8px_rgba(0,0,0,0.9)] transition duration-300 group-hover/game:opacity-100 group-focus-visible/game:opacity-100">
                            View Game
                        </span>
                        <div class="absolute inset-x-0 bottom-0 p-3">
                            <h2 class="truncate font-orbitron text-xs font-black uppercase text-white">{{ $game->localizedName() }}</h2>
                        </div>
                    </a>
                @empty
                    <div class="w-full rounded-2xl border border-dashed border-zinc-800 p-8 text-center text-sm text-zinc-500">No games match your search.</div>
                @endforelse
            </div>
            <button type="button" aria-label="Next games" @click="$refs.gamesRail.scrollBy({ left: 420, behavior: 'smooth' })" class="player-rail-button absolute -right-1 top-1/2 z-20 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-cyan-500/30 bg-zinc-950/95 text-white shadow-2xl transition hover:border-lime-400 hover:bg-lime-400 hover:text-zinc-950 sm:-right-3 sm:h-10 sm:w-10">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </button>
        </div>
    </section>

    <!-- 3. Featured List (when present) -->
    @if($featuredTournaments->isNotEmpty())
    <section class="space-y-4">
        <div class="flex items-end justify-between gap-4"><div><p class="gr-eyebrow !mb-1.5 !text-amber-300"><span class="gr-slash">//</span> Selected competitions</p><h2 class="font-orbitron text-xl font-black uppercase text-white">{{ __('Featured :value1', ['value1' => ($competitionPlural)]) }}</h2></div><span class="hidden text-xs text-zinc-400 sm:block">Highlights from active competitions</span></div>
        <div class="hidden rounded-xl border border-white/5 bg-zinc-950/50 px-5 py-2.5 text-[10px] font-extrabold uppercase tracking-widest text-zinc-500 md:grid md:grid-cols-[52px_minmax(0,1.8fr)_120px_130px_170px_44px] md:items-center md:gap-5" aria-hidden="true">
            <span></span>
            <span>{{ __('Competition') }}</span>
            <span>{{ __('Entry Fee') }}</span>
            <span>{{ __('Prize') }}</span>
            <span>{{ __('Full Capacity') }}</span>
            <span></span>
        </div>
        <div class="flex flex-col gap-2.5">@foreach($featuredTournaments as $tournament)<div wire:key="featured-{{ $tournament->uuid }}"><x-player.tournament-card :tournament="$tournament" action-label="Join" :public-view="$publicView" class="min-w-0" /></div>@endforeach</div>
        @if($hasMoreFeatured)<div class="text-center"><button wire:click="loadMoreFeatured" wire:loading.attr="disabled" class="rounded-xl border border-cyan-500/35 bg-cyan-500/10 px-6 py-2.5 font-orbitron text-[10px] font-black uppercase tracking-widest text-cyan-200 transition hover:border-lime-400/50 hover:bg-lime-400/15 hover:text-lime-300 disabled:opacity-50"><span wire:loading.remove wire:target="loadMoreFeatured">View More</span><span wire:loading wire:target="loadMoreFeatured">Loading...</span></button></div>@endif
    </section>
    @endif

    <!-- 4. Main Simplified Competition Board -->
    <section id="competition-board" class="space-y-4 scroll-mt-24">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-cyan-500/15 pb-3">
            <div class="flex gap-2 overflow-x-auto [scrollbar-width:none]">
                @foreach(['upcoming' => $isHeadToHeadListing ? 'Upcoming H2H' : 'Upcoming', 'ongoing' => $isHeadToHeadListing ? 'Ongoing H2H' : 'Ongoing', 'past' => 'Past '.$competitionPlural] as $key => $label)<button wire:click="$set('activeTab', '{{ $key }}')" class="whitespace-nowrap rounded-full px-5 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest transition {{ $activeTab === $key ? 'bg-lime-400 text-zinc-950 shadow-[0_0_20px_rgba(198,255,61,.35)]' : 'border border-white/10 bg-zinc-900/60 text-zinc-400 hover:border-cyan-400/40 hover:text-white' }}">{{ $label }}</button>@endforeach
            </div>
            <span class="hidden items-center gap-1.5 font-mono text-[11px] uppercase tracking-wider text-zinc-400 sm:inline-flex">
                <i data-lucide="list" class="h-3.5 w-3.5 text-cyan-400"></i>
                {{ __('Simplified Board') }}
            </span>
        </div>

        <div class="player-filter-panel rounded-2xl border border-cyan-500/20 bg-zinc-900/60 p-3.5 backdrop-blur-xl sm:p-4"><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 {{ $isHeadToHeadListing ? 'xl:grid-cols-5' : 'xl:grid-cols-6' }}">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search {{ strtolower($competitionPlural) }}" class="rounded-xl border border-white/10 bg-zinc-950/90 px-3.5 py-2.5 text-sm text-white outline-none transition focus:border-lime-400">
            @if($isHeadToHeadListing)
                <div class="flex items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 px-3 py-2.5 text-sm font-semibold text-cyan-200"><i data-lucide="swords" class="mr-2 h-4 w-4 text-lime-300"></i>1v1 only</div>
            @else
                <div class="flex min-w-0 gap-2">
                    <select wire:model.live="maxTeams" aria-label="Filter by maximum teams" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2.5 text-sm text-zinc-200 focus:border-lime-400">
                        <option value="">All max teams</option>
                        @foreach([4, 8, 16, 32, 64, 128] as $maximum)<option value="{{ $maximum }}">{{ __(':value1 teams', ['value1' => ($maximum)]) }}</option>@endforeach
                        <option value="custom">Custom</option>
                    </select>
                    @if($maxTeams === 'custom')
                        <input wire:model.live.debounce.300ms="customMaxTeams" type="number" min="2" max="128" step="2" aria-label="Custom maximum teams" placeholder="Max" class="w-24 rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2.5 text-sm text-white outline-none focus:border-lime-400">
                    @endif
                </div>
            @endif
            <select wire:model.live="gameId" class="rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2.5 text-sm text-zinc-200 focus:border-lime-400"><option value="">All games</option>@foreach($games as $game)<option value="{{ $game->id }}">{{ $game->localizedName() }}</option>@endforeach</select>
            @if($fixedFrequency)
                <div class="flex items-center rounded-xl border border-cyan-400/25 bg-cyan-400/10 px-3 py-2.5 text-sm font-semibold text-cyan-200"><i data-lucide="calendar-days" class="mr-2 h-4 w-4 text-lime-300"></i>{{ __(':value1 Tournaments', ['value1' => (ucfirst($frequency))]) }}</div>
            @else
                <select wire:model.live="frequency" class="rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2.5 text-sm text-zinc-200 focus:border-lime-400"><option value="">All frequencies</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="one-time">One-time</option></select>
            @endif
            @if($allowCompetitionSwitch ?? false)<select wire:model.live="competitionType" class="rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2.5 text-sm text-zinc-200 focus:border-lime-400"><option value="">Tournaments</option><option value="head_to_head">Head-to-Head</option></select>@else<div class="flex items-center rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2.5 text-sm font-semibold text-zinc-300">{{ $competitionLabel }}</div>@endif
            <select wire:model.live="platformId" class="rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2.5 text-sm text-zinc-200 focus:border-lime-400"><option value="">All platforms</option>@foreach($platforms as $platform)<option value="{{ $platform->id }}">{{ $platform->name }}</option>@endforeach</select>
        </div></div>

        @if($tournaments->count())
            <!-- Desktop Board Column Header -->
            <div class="hidden rounded-xl border border-white/5 bg-zinc-950/50 px-5 py-2.5 text-[10px] font-extrabold uppercase tracking-widest text-zinc-500 md:grid md:grid-cols-[52px_minmax(0,1.8fr)_120px_130px_170px_44px] md:items-center md:gap-5" aria-hidden="true">
                <span></span>
                <span>{{ __('Competition') }}</span>
                <span>{{ __('Entry Fee') }}</span>
                <span>{{ __('Prize') }}</span>
                <span>{{ __('Full Capacity') }}</span>
                <span></span>
            </div>

            <div class="flex flex-col gap-2.5">@foreach($tournaments as $tournament)<div wire:key="browse-{{ $tournament->uuid }}"><x-player.tournament-card :tournament="$tournament" :action-label="match ($activeTab) { 'upcoming' => 'Join', 'past' => 'View Results', default => 'View Tournament' }" :public-view="$publicView" /></div>@endforeach</div>
            <div class="flex flex-col gap-4 border-t border-zinc-900/60 pt-6 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-zinc-400">{{ __('Showing :value1–:value2 of :value3 results', ['value1' => ($tournaments->firstItem()), 'value2' => ($tournaments->lastItem()), 'value3' => ($tournaments->total())]) }}</p>{{ $tournaments->links('vendor.livewire.custom-pagination') }}</div>
        @else
            <div class="rounded-2xl border border-dashed border-zinc-800 p-12 text-center"><i data-lucide="trophy" class="mx-auto h-9 w-9 text-zinc-700"></i><h3 class="mt-4 font-orbitron text-sm font-black uppercase text-zinc-300">{{ __('No :value1 found', ['value1' => (strtolower($competitionPlural))]) }}</h3><p class="mt-2 text-sm text-zinc-500">Try changing the selected tab or filters.</p></div>
        @endif
    </section>
</div>
