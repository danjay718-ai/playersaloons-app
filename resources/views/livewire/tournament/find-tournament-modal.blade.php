<div
    x-data
    @open-find-tournament.window="$wire.openModal($event.detail?.category ?? '', $event.detail?.gameId ?? '')"
    @keydown.escape.window="if ($wire.open) { $wire.closeModal() }"
>
    @if($open)
        <div
            class="fixed inset-0 z-[90] flex items-end justify-center p-0 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="find-tournament-modal-title"
        >
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="find-tournament-modal-panel relative z-10 flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl border border-cyan-500/25 bg-[#0a1220] text-zinc-100 shadow-[0_24px_80px_rgba(0,0,0,0.8)] sm:rounded-3xl">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p class="font-orbitron text-[10px] font-black uppercase tracking-[0.22em] text-cyan-300">{{ __('Quick Matchmaker') }}</p>
                        <h2 id="find-tournament-modal-title" class="mt-1 font-orbitron text-lg font-black uppercase tracking-tight text-white sm:text-xl">
                            {{ __('Find Tournament') }}
                        </h2>
                        <p class="mt-1 text-xs text-zinc-400">
                            {{ __('Filter active registration-open competitions by game, platform, entry fee, and start time.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeModal"
                        aria-label="{{ __('Close') }}"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-zinc-900/80 text-zinc-300 transition hover:border-cyan-400/50 hover:text-white"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Guided Filters --}}
                <div class="border-b border-white/10 bg-[#070d18]/90 px-5 py-4 sm:px-6">
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label class="sr-only" for="finder-search">{{ __('Search tournament or game') }}</label>
                            <input
                                id="finder-search"
                                type="search"
                                wire:model.live.debounce.250ms="search"
                                placeholder="{{ __('Search tournament name or game...') }}"
                                class="w-full rounded-xl border border-cyan-500/25 bg-zinc-950/90 px-3.5 py-2.5 text-sm text-white placeholder-zinc-500 outline-none transition focus:border-lime-400"
                            >
                        </div>

                        <select wire:model.live="category" aria-label="{{ __('Category') }}" class="w-full rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-zinc-200 outline-none focus:border-lime-400">
                            <option value="">{{ __('All categories') }}</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="gameId" aria-label="{{ __('Game') }}" class="w-full rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-zinc-200 outline-none focus:border-lime-400">
                            <option value="">{{ __('All games') }}</option>
                            @foreach($games as $game)
                                <option value="{{ $game['id'] }}">{{ $game['name'] }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="platformId" aria-label="{{ __('Platform') }}" class="w-full rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-zinc-200 outline-none focus:border-lime-400">
                            <option value="">{{ __('All platforms') }}</option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform['id'] }}">{{ $platform['name'] }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="budget" aria-label="{{ __('Entry Fee') }}" class="w-full rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-zinc-200 outline-none focus:border-lime-400">
                            <option value="">{{ __('Any entry fee') }}</option>
                            <option value="free">{{ __('Free only') }}</option>
                            <option value="up_to_5">{{ __('Up to :amount', ['amount' => $currencySymbol.'5']) }}</option>
                            <option value="up_to_20">{{ __('Up to :amount', ['amount' => $currencySymbol.'20']) }}</option>
                            <option value="high">{{ __('High stakes (:amount+)', ['amount' => $currencySymbol.'20']) }}</option>
                        </select>

                        <select wire:model.live="timeWindow" aria-label="{{ __('Time Window') }}" class="w-full rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-zinc-200 outline-none focus:border-lime-400">
                            <option value="">{{ __('Anytime') }}</option>
                            <option value="soon">{{ __('Starting soon (< 2h)') }}</option>
                            <option value="today">{{ __('Today') }}</option>
                            <option value="week">{{ __('This week') }}</option>
                        </select>

                        <select wire:model.live="competitionType" aria-label="{{ __('Mode') }}" class="w-full rounded-xl border border-white/10 bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-zinc-200 outline-none focus:border-lime-400">
                            <option value="">{{ __('All modes') }}</option>
                            <option value="tournament">{{ __('Tournaments') }}</option>
                            <option value="head_to_head">{{ __('1v1 Head-to-Head') }}</option>
                        </select>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px]">
                        <div class="flex items-center gap-2 text-zinc-400">
                            <span wire:loading.remove>
                                @if($hasActiveFilters)
                                    {{ __(':count open match(es) ready to join', ['count' => number_format($totalMatching)]) }}
                                @else
                                    {{ __('Select at least one filter above to search open tournaments.') }}
                                @endif
                            </span>
                            <span wire:loading class="font-semibold text-cyan-300">{{ __('Searching open tournaments...') }}</span>
                        </div>
                        @if($hasActiveFilters)
                            <button
                                type="button"
                                wire:click="resetFilters"
                                class="font-bold text-cyan-300 transition hover:text-lime-300"
                            >
                                {{ __('Reset filters') }}
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Results List --}}
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4 sm:px-6" wire:loading.class="opacity-60">
                    @if(! $hasActiveFilters)
                        <div class="rounded-2xl border border-dashed border-cyan-500/25 bg-zinc-900/40 px-6 py-10 text-center">
                            <p class="font-orbitron text-sm font-black uppercase text-white">{{ __('Start with a filter') }}</p>
                            <p class="mx-auto mt-1.5 max-w-md text-xs leading-relaxed text-zinc-400">
                                {{ __('Choose a category, game, platform, entry fee, or start window — or type a keyword above — to see matching open tournaments.') }}
                            </p>
                        </div>
                    @else
                        @if(! $exact && $results->isNotEmpty())
                            <div role="status" class="rounded-xl border border-amber-400/35 bg-amber-400/10 px-4 py-2.5 text-xs font-semibold text-amber-200">
                                {{ __('No exact matches for your filters — here are the closest open tournaments.') }}
                            </div>
                        @endif

                        @forelse($results as $tournament)
                        @php
                            $joined = (int) ($tournament->getAttribute('registrations_count') ?? 0);
                            $max = max(1, (int) $tournament->max_participants);
                            $fillPercent = min(100, (int) round(($joined / $max) * 100));
                            $isH2h = ($tournament->competition_type?->value ?? $tournament->competition_type) === 'head_to_head';
                            $isPlayStrategy = (bool) $tournament->game?->isPlayStrategyManaged();
                            $entryLabel = (float) $tournament->entry_fee > 0
                                ? $currencySymbol.number_format((float) $tournament->entry_fee, 2)
                                : __('Free');
                            $detailUrl = '/tournaments/'.$tournament->uuid.'/view'.($isGuest ? '?view=guest' : '');
                        @endphp
                        <article wire:key="finder-result-{{ $tournament->uuid }}" class="find-tournament-result flex flex-col justify-between gap-3 rounded-2xl border border-white/10 bg-zinc-900/70 p-4 transition hover:border-cyan-400/45 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-full border border-cyan-400/30 bg-cyan-500/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-cyan-300">
                                        {{ $tournament->game?->localizedName() ?? __('Game') }}
                                    </span>
                                    <span class="rounded-full border border-white/10 bg-zinc-950/70 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-300">
                                        {{ $isH2h ? __('1v1 Head-to-Head') : __('Tournament') }}
                                    </span>
                                    @if($isPlayStrategy)
                                        <span class="rounded-full border border-emerald-400/35 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-300">
                                            PlayStrategy
                                        </span>
                                    @endif
                                </div>

                                <h3 class="truncate font-orbitron text-sm font-black text-white">
                                    {{ $tournament->display_name }}
                                </h3>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-zinc-400">
                                    <span class="font-mono font-semibold text-amber-300">
                                        {{ $tournament->start_at?->copy()->setTimezone($arenaTimezone)->format('M d · H:i') }} {{ $arenaTimezone }}
                                    </span>
                                    <span>·</span>
                                    <span class="font-bold text-white">{{ __('Entry') }}: {{ $entryLabel }}</span>
                                    <span>·</span>
                                    <span class="font-mono text-cyan-300">{{ $joined }}/{{ $max }} {{ __('joined') }}</span>
                                </div>

                                <div class="h-1.5 w-full max-w-[200px] overflow-hidden rounded-full bg-zinc-800">
                                    <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-lime-400" style="width: {{ max(8, $fillPercent) }}%"></div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end">
                                @if($isGuest)
                                    <a
                                        href="{{ $detailUrl }}"
                                        class="find-tournament-cta inline-flex min-h-[40px] items-center justify-center rounded-xl bg-[#c6ff3d] px-4 py-2 font-orbitron text-[10px] font-black uppercase tracking-wider text-[#05080f] transition hover:brightness-105"
                                    >
                                        {{ __('View & Join') }} →
                                    </a>
                                @elseif($isPlayStrategy && ! $hasPlayStrategyLinked)
                                    <a
                                        href="/profile?tab=settings"
                                        wire:navigate
                                        class="inline-flex min-h-[38px] items-center justify-center rounded-xl border border-amber-400/40 bg-amber-400/15 px-3.5 py-1.5 text-[10px] font-black uppercase tracking-wider text-amber-200 transition hover:bg-amber-400/25"
                                    >
                                        {{ __('Connect PlayStrategy') }} →
                                    </a>
                                    <a
                                        href="{{ $detailUrl }}"
                                        wire:navigate
                                        class="text-[11px] font-bold text-cyan-300 hover:text-white"
                                    >
                                        {{ __('View Tournament') }}
                                    </a>
                                @else
                                    <a
                                        href="{{ $detailUrl }}"
                                        wire:navigate
                                        class="find-tournament-cta inline-flex min-h-[40px] items-center justify-center rounded-xl bg-[#c6ff3d] px-4 py-2 font-orbitron text-[10px] font-black uppercase tracking-wider text-[#05080f] transition hover:brightness-105"
                                    >
                                        {{ __('Join Now') }} →
                                    </a>
                                @endif
                            </div>
                        </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-white/15 px-6 py-10 text-center">
                                <p class="font-orbitron text-sm font-black uppercase text-white">{{ __('No open tournaments right now') }}</p>
                                <p class="mt-1.5 text-xs text-zinc-400">{{ __('Check back soon or browse the full schedule for upcoming events.') }}</p>
                            </div>
                        @endforelse
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 bg-[#070d18]/90 px-5 py-3.5 text-xs sm:px-6">
                    <a
                        href="{{ $isGuest ? '/tournaments' : route('tournaments.browse', ['frequency' => 'daily']) }}"
                        @if(! $isGuest) wire:navigate @endif
                        class="font-orbitron text-[10px] font-black uppercase tracking-wider text-cyan-300 transition hover:text-lime-300"
                    >
                        {{ __('Browse all tournaments') }} →
                    </a>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="rounded-xl border border-white/15 bg-zinc-900 px-4 py-2 text-xs font-bold text-zinc-200 transition hover:border-cyan-400/40"
                    >
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
